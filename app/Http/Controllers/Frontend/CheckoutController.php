<?php
namespace App\Http\Controllers\Frontend;

use App\Exceptions\PromoCodeException;
use App\Http\Controllers\Controller;
use App\Mail\OrderCreatedMail;
use App\Models\Customer\CustomerAddress;
use App\Models\Credit\CreditApplication;
use App\Models\Credit\CreditPeriod;
use App\Models\Credit\CreditStatus;
use App\Models\Order\Order;
use App\Models\Order\OrderStatus;
use App\Models\Payment\Payment;
use App\Models\Payment\PaymentMethod;
use App\Models\Product\ProductVariant;
use App\Services\BonusService;
use App\Services\Payment\Birbank;
use App\Services\PromoCodeService;
use App\Services\ShopPricing;
use App\Support\LocalizedValidation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CheckoutController extends Controller
{
    public function index()
    {
        $addresses = auth() -> user()
            -> addresses()
            -> orderByDesc('is_default')
            -> latest()
            -> get();

        $paymentMethods = PaymentMethod ::where('active', 1)->where('code', '!=', 'm10')
            -> orderBy('sort_order')
            -> get();

        $creditPeriods = CreditPeriod::where('active', 1)->orderBy('sort_order')->get();
        $bonusBalance = (float) auth()->user()->bonus_balance;
        $creditProfileComplete = (bool) auth()->user()->creditProfile?->isComplete();
        $cities = \App\Models\City::forSelect()->get(['id', 'name']);

        return view('frontend.checkout', compact(
            'addresses',
            'paymentMethods', 'creditPeriods', 'bonusBalance', 'creditProfileComplete', 'cities'
        ));
    }

    public function store(Request $request)
    {
        $data = $request -> validate(
            [
                'cart' => ['required', 'array', 'min:1'],
                'cart.*.variant_id' => ['required', 'integer'],
                'cart.*.quantity' => ['required', 'integer', 'min:1', 'max:99'],

                'address_mode' => [
                    'required',
                    Rule ::in(['existing', 'new']),
                ],
                'address_id' => ['nullable', 'integer'],

                'title' => ['nullable', 'string', 'max:50'],
                'city_id' => ['nullable', 'integer'],
                'address' => ['nullable', 'string', 'max:500'],
                'building' => ['nullable', 'string', 'max:50'],
                'entrance' => ['nullable', 'string', 'max:50'],
                'floor' => ['nullable', 'string', 'max:30'],
                'apartment' => ['nullable', 'string', 'max:30'],
                'address_note' => ['nullable', 'string', 'max:1000'],

                'payment_method_id' => [
                    'required',
                    'integer',
                    'exists:payment_methods,id',
                ],

                'credit_period_id' => ['nullable', 'integer', 'exists:credit_periods,id'],
                'birbank_installment_months' => ['nullable', 'integer', Rule::in([2, 3, 6])],
                'accept_terms' => ['nullable', 'boolean'],
                'gift_wrap' => ['nullable', 'boolean'],
                'customer_note' => ['nullable', 'string', 'max:1500'],
            ],
            LocalizedValidation ::messages(),
            LocalizedValidation ::attributes()
        );

        $paymentMethod = PaymentMethod::whereKey($data['payment_method_id'])->where('active', 1)->firstOrFail();
        if (!in_array($paymentMethod->code, ['cash', 'card_online', 'birbank_installment', 'installment', 'bonus_balance'], true)) {
            return response()->json(['message' => 'Bu ödəniş üsulu deaktivdir.'], 422);
        }

        if ($paymentMethod->code === 'birbank_installment'
            && !in_array((int) ($data['birbank_installment_months'] ?? 0), [2, 3, 6], true)) {
            return response()->json(['message' => 'Birbank taksit müddətini seçin.'], 422);
        }

        $customer = $request->user();
        if ($paymentMethod->code === 'installment') {
            if (!$customer->creditProfile?->isComplete()) {
                return response()->json(['message' => __('credit_application_complete_profile'), 'redirect' => route('profile.credit')], 422);
            }
            if (empty($data['credit_period_id']) || empty($data['accept_terms'])) {
                return response()->json(['message' => 'Kredit müddətini və şərtləri təsdiqləyin.'], 422);
            }
        }

        return DB ::transaction(function() use ($data, $customer, $paymentMethod) {
            DB::table('customers')->where('id', $customer->id)->lockForUpdate()->first();

            // İlkin sifariş statusu
            $initialStatus = OrderStatus ::where('code', 'new')
                -> where('active', 1)
                -> firstOrFail();

            // Çatdırılma ünvanı
            if($data['address_mode'] === 'existing') {

                $address = $customer -> addresses()
                    -> findOrFail($data['address_id']);

            } else {

                validator(
                    $data,
                    [
                        'title' => ['required', 'string', 'max:50'],
                        'city_id' => CustomerAddress::formRules()['city_id'],
                        'address' => ['required'],
                    ],
                    [
                        'title.required' => __('validation_address_name_is_required'),
                        'city_id.required' => __('validation_city_is_required'),
                        'city_id.exists' => __('validation_city_is_required'),
                        'address.required' => __('validation_street_and_address_are_required'),
                    ]
                ) -> validate();

                $address = $customer -> addresses() -> create(
                    CustomerAddress::attributesFromForm($data) + [
                        'is_default' => $customer -> addresses() -> count() === 0,
                    ]
                );
            }

            // Səbətdəki məhsullar
            $cart = collect($data['cart']) -> keyBy('variant_id');

            // Deaktiv məhsulun variantı da "mövcud deyil" sayılır (səbətdə köhnə qalmış ola bilər)
            $variants = ProductVariant ::whereIn('id', $cart -> keys())
                -> where('active', 1)
                -> whereHas('product', fn ($q) => $q -> where('active', 1))
                -> get();

            abort_if(
                $variants -> count() !== $cart -> count(),
                422,
                __('validation_your_cart_contains_an_unavailable_product')
            );

            // Məhsullar və yekun məbləğ
            $subtotal = 0;
            $items = [];

            foreach($variants as $variant) {
                $quantity = (int)$cart[$variant -> id]['quantity'];

                $lineTotal = round(
                    (float)$variant -> price * $quantity,
                    2
                );

                $subtotal += $lineTotal;

                $items[] = [
                    'product_id' => $variant -> product_id,
                    'product_variant_id' => $variant -> id,
                    'unit_price' => $variant -> price,
                    'quantity' => $quantity,
                    'total' => $lineTotal,
                ];
            }

            $subtotal = round($subtotal, 2);
            $discount = 0;
            $promo = null;
            if ($code = session('promo_code')) {
                try {
                    ['promo' => $promo, 'discount' => $discount] = app(PromoCodeService::class)->resolve($code, $subtotal, true);
                } catch (PromoCodeException) {
                    session()->forget('promo_code');
                }
            }
            $goods = round($subtotal - $discount, 2);
            $delivery = app(ShopPricing::class)->deliveryFee($goods);
            $giftWrapFee = app(ShopPricing::class)->giftWrapFee((bool)($data['gift_wrap'] ?? false));
            $payable = round($goods + $delivery + $giftWrapFee, 2);
            $period = $paymentMethod->code === 'installment' ? CreditPeriod::whereKey($data['credit_period_id'])->where('active', 1)->firstOrFail() : null;
            $creditTotal = $period ? round($payable * (1 + (float) $period->interest_rate / 100), 2) : $payable;

            $orderNo = 'TMP'.Str::random(20);

            // Sifarişin yaradılması
            $order = Order ::create([
                'order_no' => $orderNo,
                'customer_id' => $customer -> id,
                'customer_address_id' => $address -> id,
                'payment_method_id' => $data['payment_method_id'],
                'birbank_installment_months' => $paymentMethod->code === 'birbank_installment'
                    ? (int) $data['birbank_installment_months'] : null,
                'payment_status' => in_array($paymentMethod->code, ['bonus_balance'], true) ? 'paid' : ($paymentMethod->code === 'cash' ? 'cod' : 'pending'),
                'source' => 'customer',
                'order_status_id' => $initialStatus -> id,
                'gift_wrap' => (bool)($data['gift_wrap'] ?? false),
                'customer_note' => $data['customer_note'] ?? null,
                'subtotal' => $subtotal,
                'discount' => $discount,
                'delivery_fee' => $delivery,
                'gift_wrap_fee' => $giftWrapFee,
                'promo_code_id' => $promo?->id,
                'total' => $creditTotal,
            ]);

            $order->update(['order_no' => 'PS'.now()->format('ymd').str_pad((string) $order->id, 6, '0', STR_PAD_LEFT)]);

            // Sifariş məhsulları
            $order -> items() -> createMany($items);

            if ($paymentMethod->code === 'bonus_balance') {
                $balance = (float) DB::table('customers')->where('id', $customer->id)->value('bonus_balance');
                if (round($balance, 2) < round($creditTotal, 2)) {
                    abort(422, 'Bonus balansınız kifayət etmir.');
                }
                DB::table('customers')->where('id', $customer->id)->decrement('bonus_balance', $creditTotal);
                $customer->bonusTransactions()->create([
                    'order_id' => $order->id, 'type' => 'spend',
                    'amount' => -$creditTotal, 'note' => 'Sifariş bonusla ödənildi',
                ]);
            }
            if ($paymentMethod->code === 'installment') {
                CreditApplication::create([
                    'customer_id' => $customer->id, 'order_id' => $order->id,
                    'credit_period_id' => $period->id, 'interest_rate' => $period->interest_rate,
                    'total' => $creditTotal, 'monthly' => round($creditTotal / $period->month, 2),
                    'credit_status_id' => CreditStatus::where('code', 'pending')->firstOrFail()->id,
                ]);
            }
            if ($paymentMethod->code === 'cash') {
                app(BonusService::class)->earnForOrder($customer, $order, (float) $order->total);
            }

            // Onlayn kartda istifadə limiti yalnız bank ödənişi təsdiqləyəndə tutulur.
            if ($promo && !in_array($paymentMethod->code, ['card_online', 'birbank_installment'], true)) {
                $promo->increment('used_count');
            }
            session()->forget('promo_code');

            // Status tarixçəsi
            DB ::table('order_status_logs') -> insert([
                'order_id' => $order -> id,
                'status_id' => $initialStatus -> id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if (in_array($paymentMethod->code, ['card_online', 'birbank_installment'], true)) {
                $months = $paymentMethod->code === 'birbank_installment'
                    ? (int) $data['birbank_installment_months'] : null;
                $bank = app(Birbank::class)->createOrder($order->load('paymentMethod'), app()->getLocale(), $months);
                $redirect = $bank['url'];
            } else {
                $redirect = route('checkout.success', $order);
            }

            if (!in_array($paymentMethod->code, ['card_online', 'birbank_installment'], true) && filter_var($customer->email, FILTER_VALIDATE_EMAIL)) {
                Mail::to($customer->email)->queue(new OrderCreatedMail($order, app()->getLocale()));
            }

            return response()->json([
                'ok' => true, 'order_no' => $order->order_no,
                'redirect' => $redirect,
                // The order and its items are already saved, including for card payments.
                // A failed card payment can be retried from the existing order.
                'clear_cart' => true,
            ]);
        });
    }

    public function success(Order $order)
    {
        abort_unless(
            $order -> customer_id === auth() -> id(),
            403
        );

        if (in_array($order->paymentMethod?->code, ['card_online', 'birbank_installment'], true)
            && !$order->payments()->where('status', Payment::PAID)->exists()) {
            return redirect()->route('order.details', $order)->with('error', 'Ödəniş hələ təsdiqlənməyib.');
        }
        return view('frontend.checkout-success', compact('order'));
    }
}
