<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Customer\Customer;
use App\Models\Order\Order;
use App\Models\PaymentMethod;
use App\Models\Setting;
use App\Models\SmsTemplate;
use App\Services\SmsService;
use App\Services\BonusService;
use App\Services\ShopPricing;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class EasyOrdersController extends Controller
{
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
            'paymentMethods' => PaymentMethod::where('active', 1)->whereIn('code', ['cash', 'card_online'])->get(),
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
            'city' => ['required_if:address_choice,new', 'nullable', 'string', 'max:100'],
            'address' => ['required_if:address_choice,new', 'nullable', 'string', 'max:500'],
            'title' => ['required_if:address_choice,new', 'nullable', 'string', 'max:100'],
            'building' => ['nullable', 'string', 'max:50'],
            'entrance' => ['nullable', 'string', 'max:30'],
            'floor' => ['nullable', 'string', 'max:30'],
            'apartment' => ['nullable', 'string', 'max:30'],
            'note' => ['nullable', 'string', 'max:1000'],
            'district' => ['nullable', 'string', 'max:100'],
            'payment_method_id' => ['required', 'integer', 'exists:payment_methods,id'],
        ]);
        $method = PaymentMethod::whereKey($data['payment_method_id'])
            ->where('active', 1)->whereIn('code', ['cash', 'card_online'])->firstOrFail();

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
                $registrationBonus = (int) Setting::valueOf('registration_bonus_enabled', 1) === 1
                    ? max(0, round((float) Setting::valueOf('registration_bonus_amount', 10), 2)) : 0;
                $customer = Customer::create([
                    'name' => $data['name'],
                    'surname' => $data['surname'],
                    'mobile' => $data['mobile'],
                    'gender' => (int) $data['gender'],
                    'email' => null,
                    'password' => null,
                    'active' => 1,
                    'bonus_balance' => $registrationBonus,
                ]);
                if ($registrationBonus > 0) {
                    $customer->bonusTransactions()->create([
                        'type' => 'earn',
                        'amount' => $registrationBonus,
                        'note' => 'Qeydiyyat bonusu',
                    ]);
                }
            }
            if ($data['address_choice'] === 'new') {
                $address = $customer->addresses()->create([
                    'title' => $data['title'],
                    'city' => $data['city'],
                    'district' => $data['district'] ?? null,
                    'address' => $data['address'],
                    'building' => $data['building'] ?? null,
                    'entrance' => $data['entrance'] ?? null,
                    'floor' => $data['floor'] ?? null,
                    'apartment' => $data['apartment'] ?? null,
                    'note' => $data['note'] ?? null,
                    'is_default' => !$customer->addresses()->exists(),
                ]);
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
                'payment_status' => $method->code === 'cash' ? 'cod' : 'pending',
                'delivery_fee' => $delivery,
                'total' => round((float) $locked->subtotal - (float) $locked->discount + $delivery + (float) $locked->gift_wrap_fee, 2),
            ]);
            // Same order must not earn bonus twice if an operator retries.
            if ($method->code === 'cash' && !$locked->bonusTransactions()->exists()) {
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
                } catch (\\Throwable $e) {
                    Log::error('Asan sifariş qeydiyyat SMS-i göndərilmədi', [
                        'customer_id' => $customer->id, 'order_id' => $order->id,
                        'error' => $e->getMessage(),
                    ]);
                    return redirect()->route('admin.crm.show', $customer)
                        ->with('warning', 'Sifariş təsdiqləndi, lakin qeydiyyat SMS-i göndərilmədi. SMS xidmətini yoxlayın.');
                }
            }
        }

        return redirect()->route('admin.crm.show', $customer)
            ->with('success', 'Asan sifariş müştəriyə bağlandı, ünvan və ödəniş üsulu təsdiqləndi.');
    }
}
