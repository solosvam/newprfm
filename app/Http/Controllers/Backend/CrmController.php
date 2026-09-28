<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Customer\Customer;
use App\Models\Order\Order;
use App\Models\Payment\PaymentMethod;
use App\Services\ShopPricing;
use App\Services\SmsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

        return view('backend.crm.customer', compact('customer', 'counts'));
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
            ->route('admin.crm.show', $customer)
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

        return redirect()->route('admin.crm.show', $customer)->with('success', 'Kredit profili yeniləndi.');
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
        return redirect()->route('admin.crm.show', $customer)
            ->with('success', 'Sifarişin ünvanı və ödəniş üsulu təsdiqləndi.');
    }

    public function order(Customer $customer, Order $order): View
    {
        abort_unless($order->customer_id === $customer->id, 404);

        $order->load(['items.product', 'paymentMethod', 'status', 'address']);

        return view('backend.crm.order', [
            'order' => $order,
            'customer' => $customer,
            'addresses' => $customer->addresses()->get(),
            'oneClickPaymentMethods' => PaymentMethod::where('active', 1)->whereIn('code', ['cash', 'card_online'])->get(),
        ]);
    }
}
