<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Customer\Customer;
use App\Models\Order\Order;
use App\Models\PaymentMethod;
use App\Services\ShopPricing;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

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
            'paymentMethods' => PaymentMethod::where('active', 1)->whereIn('code', ['cash', 'card_online'])->get(),
        ]);
    }

    public function confirm(Request $request, Order $order, ShopPricing $pricing)
    {
        abort_unless($order->one_click && $order->customer_id === null, 404);
        $data = $request->validate([
            'mobile' => ['required', 'regex:/^994[0-9]{9}$/'],
            'name' => ['required', 'string', 'max:30'],
            'surname' => ['required', 'string', 'max:30'],
            'city' => ['required', 'string', 'max:100'],
            'address' => ['required', 'string', 'max:500'],
            'district' => ['nullable', 'string', 'max:100'],
            'payment_method_id' => ['required', 'integer', 'exists:payment_methods,id'],
        ]);
        $method = PaymentMethod::whereKey($data['payment_method_id'])
            ->where('active', 1)->whereIn('code', ['cash', 'card_online'])->firstOrFail();

        $customer = DB::transaction(function () use ($order, $data, $method, $pricing) {
            $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->one_click && $locked->customer_id === null, 409);
            $customer = Customer::where('mobile', $data['mobile'])->lockForUpdate()->first();
            if (!$customer) {
                $customer = Customer::create([
                    'name' => $data['name'],
                    'surname' => $data['surname'],
                    'mobile' => $data['mobile'],
                    'password' => Str::random(48),
                    'active' => 1,
                    'bonus_balance' => 0,
                ]);
            }
            $address = $customer->addresses()->firstOrCreate(
                ['city' => $data['city'], 'address' => $data['address']],
                ['title' => 'Asan sifariş', 'district' => $data['district'] ?? null,
                 'is_default' => !$customer->addresses()->exists()]
            );
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
            return $customer;
        });

        return redirect()->route('admin.crm.show', $customer)
            ->with('success', 'Asan sifariş müştəriyə bağlandı, ünvan və ödəniş üsulu təsdiqləndi.');
    }
}
