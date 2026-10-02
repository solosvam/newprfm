<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\Customer\Customer;
use App\Models\Customer\CustomerAddress;
use App\Models\Order\Order;
use App\Models\Payment\PaymentMethod;
use App\Models\Setting;
use App\Models\SmsTemplate;
use App\Services\BonusService;
use App\Services\ShopPricing;
use App\Services\SmsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EasyOrdersController extends Controller
{
    /** Asan sifarişdə seçilə bilən ödəniş üsulları */
    private const PAYMENT_CODES = ['cash', 'card_online', 'birbank_installment'];

    public function index()
    {
        return view('backend.easy-orders.index', [
            'orders' => Order::with(['items.product', 'items.variant.size', 'status'])
                ->where('one_click', true)->whereNull('customer_id')->latest()->paginate(20),
        ]);
    }

    public function show(Order $order)
    {
        abort_unless($order->one_click && $order->customer_id === null, 404);
        $order->load(['items.product', 'items.variant.size']);
        $existing = Customer::where('mobile', $order->guest_mobile)->first();
        return view('backend.easy-orders.show', [
            'order' => $order,
            'existing' => $existing,
            'addresses' => $existing?->addresses()->orderByDesc('is_default')->orderBy('id')->get() ?? collect(),
            'paymentMethods' => PaymentMethod::where('active', 1)->whereIn('code', self::PAYMENT_CODES)->orderBy('sort_order')->get(),
            'cities' => City::forSelect()->get(['id', 'name']),
        ]);
    }

    public function lookup(Request $request, Order $order)
    {
        abort_unless($order->one_click && $order->customer_id === null, 404);
        $data = $request->validate(['mobile' => ['required', 'regex:/^994[0-9]{9}$/']]);
        $customer = Customer::with('addresses')->where('mobile', $data['mobile'])->first();
        return response()->json([
            'found' => (bool) $customer,
            'name' => $customer?->name,
            'surname' => $customer?->surname,
            'customer_id' => $customer?->id,
            'addresses' => $customer?->addresses->sortByDesc('is_default')->values()->map(fn ($a) => [
                'id' => $a->id, 'label' => $a->label,
            ])->all() ?? [],
        ]);
    }

    public function confirm(Request $request, Order $order, ShopPricing $pricing)
    {
        abort_unless($order->one_click && $order->customer_id === null, 404);
        $data = $request->validate([
            'mobile' => ['required', 'regex:/^994[0-9]{9}$/'],
            'name' => ['required', 'string', 'max:30'],
            'surname' => ['required', 'string', 'max:30'],
            'gender' => ['nullable', 'integer', 'in:0,1'],
            'address_choice' => ['required', 'string'],
            // Ünvan adını operator vermir — "Ünvan #N"
            ...CustomerAddress::formRules('required_if:address_choice,new', withTitle: false),
            'payment_method_id' => ['required', 'integer', 'exists:payment_methods,id'],
            'birbank_installment_months' => ['nullable', 'integer', 'in:2,3,6'],
        ], [
            'city_id.required_if' => 'Şəhəri seçin.',
            'city_id.exists' => 'Şəhəri siyahıdan seçin.',
            'address.required_if' => 'Küçə və ünvanı daxil edin.',
        ]);
        $method = PaymentMethod::whereKey($data['payment_method_id'])
            ->where('active', 1)->whereIn('code', self::PAYMENT_CODES)->firstOrFail();
        if ($method->code === 'birbank_installment' && empty($data['birbank_installment_months'])) {
            return back()->withInput()->withErrors(['birbank_installment_months' => 'Birbank taksit müddətini seçin.']);
        }

        $created = false;
        $registrationBonus = 0.0;
        $customer = DB::transaction(function () use ($order, $data, $method, $pricing, &$created, &$registrationBonus) {
            $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->one_click && $locked->customer_id === null, 409);
            $customer = Customer::where('mobile', $data['mobile'])->lockForUpdate()->first();
            if (!$customer) {
                if (!array_key_exists('gender', $data) || $data['gender'] === null) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['gender' => 'Yeni müştəri üçün cinsiyyət seçin.']);
                }
                $created = true;
                $customer = Customer::create([
                    'name' => $data['name'],
                    'surname' => $data['surname'],
                    'mobile' => $data['mobile'],
                    'gender' => (int) $data['gender'],
                    'email' => null,
                    'password' => null,
                    'active' => 1,
                    'bonus_balance' => 0,
                    'source' => 'easy_order',
                ]);
                // Qeydiyyat bonusu — saytdakı qeydiyyatla eyni qayda (BonusService)
                $registrationBonus = app(BonusService::class)->grantRegistration($customer);
            }
            if ($data['address_choice'] === 'new') {
                $address = $customer->addresses()->create(
                    CustomerAddress::attributesFromForm($data, 'Ünvan #' . ($customer->addresses()->count() + 1)) + [
                        'is_default' => !$customer->addresses()->exists(),
                    ]
                );
            } else {
                abort_unless(ctype_digit($data['address_choice']), 422, 'Ünvan seçimi yanlışdır.');
                $address = $customer->addresses()->findOrFail((int) $data['address_choice']);
            }
            $delivery = $pricing->deliveryFee((float) $locked->subtotal - (float) $locked->discount);
            $locked->update([
                'customer_id' => $customer->id,
                'customer_address_id' => $address->id,
                'guest_mobile' => $data['mobile'],
                'payment_method_id' => $method->id,
                // Kart / Birbank: "ödəniş gözləyir" — müştəri "Sifarişlərim"-dən ödəyir
                'payment_status' => $method->code === 'cash' ? 'cod' : 'pending',
                'birbank_installment_months' => $method->code === 'birbank_installment'
                    ? (int) $data['birbank_installment_months'] : null,
                'delivery_fee' => $delivery,
                'total' => round((float) $locked->subtotal - (float) $locked->discount + $delivery + (float) $locked->gift_wrap_fee, 2),
            ]);
            // Same order must not earn bonus twice if an operator retries.
            if ($method->code === 'cash' && !$customer->bonusTransactions()->where('order_id', $locked->id)->where('type', 'earn')->exists()) {
                app(BonusService::class)->earnForOrder($customer, $locked, (float) $locked->total);
            }
            return $customer;
        });

        if ($created) {
            $templateCode = $registrationBonus > 0 ? 'easy_order_registration_bonus' : 'easy_order_registration';
            $message = SmsTemplate::message($templateCode, [
                'bonus' => number_format($registrationBonus, 2, '.', ''),
            ]);
            if ($message !== null) {
                try {
                    app(SmsService::class)->send($data['mobile'], $message);
                } catch (\Throwable $e) {
                    Log::error('Asan sifariş qeydiyyat SMS-i göndərilmədi', [
                        'customer_id' => $customer->id, 'order_id' => $order->id,
                        'error' => $e->getMessage(),
                    ]);
                    return redirect()->route('admin.crm.customer', $customer->id)
                        ->with('warning', 'Sifariş təsdiqləndi, lakin qeydiyyat SMS-i göndərilmədi. SMS xidmətini yoxlayın.');
                }
            }
        }

        return redirect()->route('admin.crm.customer', $customer->id)
            ->with('success', 'Asan sifariş müştəriyə bağlandı, ünvan və ödəniş üsulu təsdiqləndi.');
    }

    /**
     * Asan sifarişi ləğv edir: sifariş və ona aid bütün qeydlər sistemdən silinir.
     * Yalnız hələ müştəriyə bağlanmamış (təsdiqlənməmiş) bir klik sifarişləri silinə bilər.
     */
    public function destroy(Order $order)
    {
        $orderNo = DB::transaction(function () use ($order) {
            $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->one_click && $locked->customer_id === null, 409, 'Bu sifariş artıq təsdiqlənib, silinə bilməz.');

            // Asılı qeydlər (bəzilərində FK cascade var, bəzilərində yox — hamısını açıq silirik)
            DB::table('order_items')->where('order_id', $locked->id)->delete();
            DB::table('order_status_logs')->where('order_id', $locked->id)->delete();
            DB::table('payments')->where('order_id', $locked->id)->delete();
            DB::table('credit_applications')->where('order_id', $locked->id)->delete();
            DB::table('customer_bonus_transactions')->where('order_id', $locked->id)->delete();

            $orderNo = $locked->order_no;
            $locked->delete();

            return $orderNo;
        });

        Log::info('Asan sifariş ləğv edildi', ['order_no' => $orderNo, 'user_id' => auth()->id()]);

        return redirect()->route('admin.easy-orders.index')
            ->with('success', "Sifariş {$orderNo} ləğv edildi və silindi.");
    }
}
