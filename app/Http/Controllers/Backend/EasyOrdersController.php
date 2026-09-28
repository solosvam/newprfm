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
            return $customer;
        });

        return redirect()->route('admin.crm.show', $customer)
            ->with('success', 'Asan sifariş müştəriyə bağlandı, ünvan və ödəniş üsulu təsdiqləndi.');
    }
}
