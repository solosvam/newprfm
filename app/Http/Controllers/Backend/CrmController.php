<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Mail\OrderCreatedMail;
use App\Models\Credit\CreditApplication;
use App\Models\Credit\CreditPeriod;
use App\Models\Credit\CreditStatus;
use App\Models\Customer\Customer;
use App\Models\Customer\CustomerAddress;
use App\Models\Order\Order;
use App\Models\Order\OrderStatus;
use App\Models\Payment\PaymentMethod;
use App\Models\Product\ProductVariant;
use App\Services\BonusService;
use App\Services\OrderPayLinkService;
use App\Services\ShopPricing;
use App\Services\SmsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;


class CrmController extends Controller
{
    public function index(Request $request): View
    {
        return view('backend.crm.index');
    }

    public function customer($id)
    {
        $customer = Customer::findOrFail($id);

        $counts = [
            'orders' => $customer->orders()
                ->where('payment_method_id', '!=', 4)
                ->count(),

            'installment' => $customer->orders()
                ->where('payment_method_id', 4)
                ->count(),

            'payments' => $customer->payments()->count(),
        ];

        // "Yeni sifariş" modalı üçün
        $pricing = app(ShopPricing::class);
        $orderForm = [
            'addresses' => $customer->addresses()->orderByDesc('is_default')->latest()->get(),
            'paymentMethods' => PaymentMethod::where('active', 1)->whereIn('code', self::ORDER_PAYMENT_CODES)->orderBy('sort_order')->get(),
            'creditPeriods' => CreditPeriod::where('active', 1)->orderBy('sort_order')->get(),
            'creditReady' => (bool) $customer->creditProfile?->isComplete(),
            'bonusBalance' => (float) $customer->bonus_balance,
            'delivery' => $pricing->delivery(),
            'giftWrap' => $pricing->giftWrap(),
            'bonusRate' => $pricing->bonusRate(),
            'cities' => \App\Models\City::forSelect()->get(['id', 'name']),
        ];

        return view('backend.crm.customer', compact('customer', 'counts', 'orderForm'));
    }

    public function tab(Customer $customer, string $tab): View
    {
        return match ($tab) {
            'orders' => view('backend.crm.tabs.orders', [
                'orders' => $customer->orders()
                    ->where('payment_method_id', '!=', 4)
                    ->with(['status', 'paymentMethod'])
                    ->latest()
                    ->paginate(10),
            ]),
            'installment' => view('backend.crm.tabs.installment', [
                'orders' => $customer->orders()
                    ->where('payment_method_id', 4)
                    ->with(['status', 'creditApplication.status', 'creditApplication.period'])
                    ->latest()
                    ->paginate(10),
            ]),
            'payments' => view('backend.crm.tabs.payments', [
                'payments' => $customer->payments()
                    ->with(['order', 'refunds'])
                    ->latest()
                    ->paginate(10),
            ]),
            'balance' => view('backend.crm.tabs.balance', [
                'customer' => $customer,
                'transactions' => $customer->bonusTransactions()
                    ->with('order')
                    ->latest()
                    ->paginate(10),
            ]),
            'settings' => view('backend.crm.tabs.settings', [
                'customer' => $customer->load('addresses'),
            ]),
            'credit-profile' => view('backend.crm.tabs.credit-profile', [
                'customer' => $customer->load('creditProfile'),
                'profile' => $customer->creditProfile,
            ]),
            default => abort(404),
        };
    }

    public function update(Customer $customer, Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:30'],
            'surname' => ['required', 'string', 'max:30'],
            'mobile' => ['required', 'digits:12', 'unique:customers,mobile,' . $customer->id],
            'email' => ['nullable', 'email', 'max:50', 'unique:customers,email,' . $customer->id],
            'gender' => ['required', 'in:0,1'],
            'active' => ['nullable', 'boolean'],
        ]);

        $customer->update([
            'name' => $data['name'],
            'surname' => $data['surname'],
            'mobile' => $data['mobile'],
            'email' => $data['email'] ?? null,
            'gender' => (int) $data['gender'],
            'active' => $request->boolean('active'),
        ]);

        return redirect()
            ->route('admin.crm.customer', $customer->id)
            ->with('success', 'Müştəri məlumatları yeniləndi.');
    }

    public function updateCreditProfile(Customer $customer, Request $request): RedirectResponse
    {
        $profile = $customer->creditProfile;
        $request->merge(['fin' => strtoupper(trim((string) $request->input('fin')))]);

        $data = $request->validate([
            'father_name' => ['required', 'string', 'max:100'],
            'fin' => ['required', 'regex:/^[A-Z0-9]{7}$/', \Illuminate\Validation\Rule::unique('customer_credit_profiles', 'fin')->ignore($profile?->id)],
            'relative_1_name' => ['required', 'string', 'max:100'],
            'relative_1_phone' => ['required', 'regex:/^\\+?[0-9 ]{9,16}$/'],
            'relative_2_name' => ['required', 'string', 'max:100'],
            'relative_2_phone' => ['required', 'regex:/^\\+?[0-9 ]{9,16}$/'],
            'workplace_name' => ['required', 'string', 'max:255'],
            'salary' => ['required', 'numeric', 'gt:0', 'max:99999999.99'],
            'position' => ['required', 'string', 'max:150'],
            'id_card_front' => [$profile?->id_card_front ? 'nullable' : 'required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'id_card_back' => [$profile?->id_card_back ? 'nullable' : 'required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        foreach (['id_card_front', 'id_card_back'] as $field) {
            unset($data[$field]);
            if ($request->hasFile($field)) {
                $file = $request->file($field);
                $filename = \Illuminate\Support\Str::uuid() . '.' . $file->getClientOriginalExtension();
                $file->move(public_path('frontend/uploads/customers'), $filename);
                $data[$field] = $filename;
                if ($profile?->{$field}) {
                    @unlink(public_path('frontend/uploads/customers/' . basename($profile->{$field})));
                }
            }
        }

        $customer->creditProfile()->updateOrCreate([], $data);

        return redirect()->route('admin.crm.customer', $customer->id)->with('success', 'Kredit profili yeniləndi.');
    }

    public function resetPassword($id)
    {
        $customer = Customer::findOrFail($id);

        $newPassword = rand(100000, 999999);

        $customer->update([
            'password' => bcrypt($newPassword),
        ]);

        $sms = new SmsService();

        $sms->send(
            $customer->mobile,
            "Hörmətli {$customer->fullname}, yeni şifrəniz: {$newPassword}"
        );

        return response()->json(['success' => true]);
    }

    public function sms(Customer $customer, SmsService $sms): View
    {
        if (!$customer->mobile) {
            return view('backend.crm.sms', [
                'messages' => [],
                'error' => 'Müştərinin telefon nömrəsi qeyd edilməyib.',
            ]);
        }

        try {
            $messages = $sms->history($customer->mobile);
            $error = null;
        } catch (\RuntimeException $exception) {
            $messages = [];
            $error = $exception->getMessage();
        }

        return view('backend.crm.sms', compact('messages', 'error'));
    }

    public function confirmOneClick(Request $request, Customer $customer, Order $order, ShopPricing $pricing): RedirectResponse
    {
        abort_unless($order->one_click && $order->customer_id === $customer->id, 404);
        $data = $request->validate([
            'customer_address_id' => ['required', 'integer'],
            'payment_method_id' => ['required', 'integer', 'exists:payment_methods,id'],
        ]);
        $address = $customer->addresses()->findOrFail($data['customer_address_id']);
        $method = PaymentMethod::whereKey($data['payment_method_id'])->where('active', 1)->firstOrFail();
        abort_unless(in_array($method->code, ['cash', 'card_online'], true), 422);
        DB::transaction(function () use ($order, $address, $method, $pricing) {
            $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            abort_if($locked->payment_status === 'paid', 422, 'Ödənilmiş sifariş dəyişdirilə bilməz.');
            $delivery = $pricing->deliveryFee((float)$locked->subtotal - (float)$locked->discount);
            $locked->update([
                'customer_address_id' => $address->id,
                'payment_method_id' => $method->id,
                'payment_status' => $method->code === 'cash' ? 'cod' : 'pending',
                'delivery_fee' => $delivery,
                'total' => round((float)$locked->subtotal - (float)$locked->discount + $delivery + (float)$locked->gift_wrap_fee, 2),
            ]);
        });
        return redirect()->route('admin.crm.customer', $customer->id)
            ->with('success', 'Sifarişin ünvanı və ödəniş üsulu təsdiqləndi.');
    }

    public function order(Customer $customer, Order $order): View
    {
        abort_unless($order->customer_id === $customer->id, 404);

        $order->load([
            'items.product', 'items.variant.size', 'items.allocations.warehouse', 'items.allocations.logs',
            'paymentMethod', 'status', 'address', 'statusLogs.status',
            'payments' => fn ($query) => $query->latest('id'),
            'payments.operations',
        ]);

        // Ödəniş linki yalnız ödənişə başlamaq mümkün olanda (onlayn üsul, ödənilməyib, ləğv deyil)
        $payLinkUrl = $order->canStartOnlinePayment() ? app(OrderPayLinkService::class)->url($order) : null;

        return view('backend.crm.order', [
            'requests' => \App\Models\Procurement\WarehouseRequest::where('order_id', $order->id)
                ->with(['warehouse', 'items.offers', 'items.orderItem.product', 'items.orderItem.variant.size'])->latest('id')->get(),
            'warehouses' => \App\Models\Procurement\Warehouse::where('active', true)->orderBy('name_az')->get(),
            'order' => $order,
            'payLinkUrl' => $payLinkUrl,
            'customer' => $customer,
            'addresses' => $customer->addresses()->get(),
            'oneClickPaymentMethods' => PaymentMethod::where('active', 1)->whereIn('code', ['cash', 'card_online'])->get(),
        ]);
    }

    /** CRM-dən sifariş yaratmaq üçün icazəli ödəniş üsulları */
    private const ORDER_PAYMENT_CODES = ['cash', 'card_online', 'birbank_installment', 'installment', 'bonus_balance'];

    /**
     * CRM → "Yeni sifariş": operator müştəri adından sifariş yaradır.
     * Frontdakı checkout-dan fərqləri:
     *  - source = operator, created_by = admin istifadəçi;
     *  - onlayn kart / Birbank taksit seçiləndə bank səhifəsinə yönləndirmə yoxdur —
     *    sifariş "gözləyir" statusunda yaranır, müştəri profilindən ödəyir;
     *  - yeni ünvanın adını operator vermir: "Ünvan #N";
     *  - promo kod tətbiq olunmur.
     */
    public function storeOrder(Request $request, Customer $customer): JsonResponse
    {
        $data = $request->validate([
            'cart' => ['required', 'array', 'min:1', 'max:50'],
            'cart.*.variant_id' => ['required', 'integer', 'distinct'],
            'cart.*.quantity' => ['required', 'integer', 'min:1', 'max:99'],
            // Operatorun satdığı vahid qiymət (boşdursa sayt qiyməti)
            'cart.*.price' => ['nullable', 'numeric', 'min:0', 'max:999999'],

            'address_mode' => ['required', Rule::in(['existing', 'new'])],
            'address_id' => ['nullable', 'required_if:address_mode,existing', 'integer'],
            // Ünvan adını operator vermir — "Ünvan #N"
            ...CustomerAddress::formRules('required_if:address_mode,new', withTitle: false),

            'payment_method_id' => ['required', 'integer'],
            'birbank_installment_months' => ['nullable', 'integer', Rule::in([2, 3, 6])],
            'credit_period_id' => ['nullable', 'integer'],
            'gift_wrap' => ['nullable', 'boolean'],
        ], [
            'cart.required' => 'Səbət boşdur.',
            'address_id.required_if' => 'Ünvanı seçin.',
            'city_id.required_if' => 'Şəhəri seçin.',
            'city_id.exists' => 'Şəhəri siyahıdan seçin.',
            'address.required_if' => 'Küçə və ünvanı daxil edin.',
            'payment_method_id.required' => 'Ödəniş üsulunu seçin.',
        ]);

        $paymentMethod = PaymentMethod::whereKey($data['payment_method_id'])
            ->where('active', 1)
            ->whereIn('code', self::ORDER_PAYMENT_CODES)
            ->first();
        if (!$paymentMethod) {
            return response()->json(['success' => false, 'message' => 'Bu ödəniş üsulu deaktivdir.'], 422);
        }
        $code = $paymentMethod->code;

        if ($code === 'birbank_installment' && empty($data['birbank_installment_months'])) {
            return response()->json(['success' => false, 'message' => 'Birbank taksit müddətini seçin.'], 422);
        }
        $period = null;
        if ($code === 'installment') {
            if (!$customer->creditProfile?->isComplete()) {
                return response()->json(['success' => false, 'message' => 'Müştərinin kredit profili tamamlanmayıb.'], 422);
            }
            $period = CreditPeriod::whereKey($data['credit_period_id'] ?? 0)->where('active', 1)->first();
            if (!$period) {
                return response()->json(['success' => false, 'message' => 'Kredit müddətini seçin.'], 422);
            }
        }

        $order = DB::transaction(function () use ($data, $customer, $paymentMethod, $code, $period) {
            DB::table('customers')->where('id', $customer->id)->lockForUpdate()->first();

            $initialStatus = OrderStatus::where('code', 'new')->where('active', 1)->firstOrFail();

            // Ünvan
            if ($data['address_mode'] === 'existing') {
                $address = $customer->addresses()->findOrFail($data['address_id']);
            } else {
                $address = $customer->addresses()->create(
                    CustomerAddress::attributesFromForm($data, 'Ünvan #' . ($customer->addresses()->count() + 1)) + [
                        'is_default' => !$customer->addresses()->exists(),
                    ]
                );
            }

            // Məhsullar — sayt qiyməti bazadan; operator yalnız endirim edə bilər (qiymət ≤ sayt qiyməti)
            $cart = collect($data['cart'])->keyBy('variant_id');
            $variants = ProductVariant::whereIn('id', $cart->keys())
                ->where('active', 1)
                ->whereHas('product', fn ($p) => $p->where('active', 1))
                ->get();
            abort_if($variants->count() !== $cart->count(), 422, 'Səbətdə satışda olmayan məhsul var.');

            $subtotal = 0; // sayt qiymətləri ilə
            $discount = 0; // operatorun endirimi
            $items = [];
            foreach ($variants as $variant) {
                $quantity = (int) $cart[$variant->id]['quantity'];
                $listPrice = round((float) $variant->price, 2);
                $price = $cart[$variant->id]['price'] ?? null;
                $unitPrice = $price === null ? $listPrice : round((float) $price, 2);
                abort_if($unitPrice > $listPrice, 422, 'Qiymət saytdakı qiymətdən yüksək ola bilməz.');

                $subtotal += $listPrice * $quantity;
                $discount += ($listPrice - $unitPrice) * $quantity;
                $items[] = [
                    'product_id' => $variant->product_id,
                    'product_variant_id' => $variant->id,
                    'unit_price' => $unitPrice,
                    'list_price' => $unitPrice < $listPrice ? $listPrice : null,
                    'quantity' => $quantity,
                    'total' => round($unitPrice * $quantity, 2),
                ];
            }
            $subtotal = round($subtotal, 2);
            $discount = round($discount, 2);
            $goods = round($subtotal - $discount, 2);

            $pricing = app(ShopPricing::class);
            $giftWrap = (bool) ($data['gift_wrap'] ?? false);
            $delivery = $pricing->deliveryFee($goods); // checkout-dakı kimi: endirimdən sonrakı məbləğə görə
            $giftWrapFee = $pricing->giftWrapFee($giftWrap);
            $payable = round($goods + $delivery + $giftWrapFee, 2);
            $total = $period ? round($payable * (1 + (float) $period->interest_rate / 100), 2) : $payable;

            $order = Order::create([
                'order_no' => 'TMP' . Str::random(20),
                'customer_id' => $customer->id,
                'customer_address_id' => $address->id,
                'payment_method_id' => $paymentMethod->id,
                'birbank_installment_months' => $code === 'birbank_installment' ? (int) $data['birbank_installment_months'] : null,
                'payment_status' => $code === 'bonus_balance' ? 'paid' : ($code === 'cash' ? 'cod' : 'pending'),
                'source' => 'operator',
                'created_by' => auth()->id(),
                'order_status_id' => $initialStatus->id,
                'gift_wrap' => $giftWrap,
                'subtotal' => $subtotal,
                'discount' => $discount,
                'delivery_fee' => $delivery,
                'gift_wrap_fee' => $giftWrapFee,
                'total' => $total,
            ]);
            $order->update(['order_no' => 'PS' . now()->format('ymd') . str_pad((string) $order->id, 6, '0', STR_PAD_LEFT)]);
            $order->items()->createMany($items);

            if ($code === 'bonus_balance') {
                $balance = (float) DB::table('customers')->where('id', $customer->id)->value('bonus_balance');
                abort_if(round($balance, 2) < round($total, 2), 422, 'Müştərinin bonus balansı kifayət etmir.');
                DB::table('customers')->where('id', $customer->id)->decrement('bonus_balance', $total);
                $customer->bonusTransactions()->create([
                    'order_id' => $order->id, 'type' => 'spend',
                    'amount' => -$total, 'note' => 'Sifariş bonusla ödənildi',
                ]);
            }
            if ($code === 'installment') {
                CreditApplication::create([
                    'customer_id' => $customer->id, 'order_id' => $order->id,
                    'credit_period_id' => $period->id, 'interest_rate' => $period->interest_rate,
                    'total' => $total, 'monthly' => round($total / $period->month, 2),
                    'credit_status_id' => CreditStatus::where('code', 'pending')->firstOrFail()->id,
                ]);
            }
            // Kart / Birbank: bonus ödəniş təsdiqlənəndə (BirbankPaymentController) yazılır
            if ($code === 'cash') {
                app(BonusService::class)->earnForOrder($customer, $order, (float) $order->total);
            }

            DB::table('order_status_logs')->insert([
                'order_id' => $order->id,
                'status_id' => $initialStatus->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $order;
        });

        if (!in_array($code, ['card_online', 'birbank_installment'], true) && filter_var($customer->email, FILTER_VALIDATE_EMAIL)) {
            Mail::to($customer->email)->queue(new OrderCreatedMail($order, 'az'));
        }

        // Kart / Birbank: müştəriyə SMS ilə ödəniş linki (login olmadan ödəyir)
        $message = "Sifariş {$order->order_no} yaradıldı.";
        if (in_array($code, Order::ONLINE_PAYMENT_CODES, true)) {
            try {
                app(OrderPayLinkService::class)->sendSms($order);
                $message .= ' Ödəniş linki müştəriyə SMS ilə göndərildi.';
            } catch (\Throwable $e) {
                report($e);
                $message .= ' Ödəniş linkini SMS ilə göndərmək alınmadı — sifarişin detalından yenidən göndərin.';
            }
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'order_no' => $order->order_no,
        ]);
    }

    /** Sifariş detalı → "SMS ilə göndər": ödəniş linkini müştəriyə yenidən göndərir */
    public function sendPayLink(Customer $customer, Order $order, OrderPayLinkService $payLink): JsonResponse
    {
        abort_unless($order->customer_id === $customer->id, 404);

        try {
            $payLink->sendSms($order);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            report($e);
            return response()->json(['success' => false, 'message' => 'SMS göndərilmədi. Bir az sonra yenidən cəhd edin.'], 500);
        }

        return response()->json(['success' => true, 'message' => 'Ödəniş linki müştəriyə SMS ilə göndərildi.']);
    }
}
