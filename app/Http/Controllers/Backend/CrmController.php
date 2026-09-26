<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Customer\Customer;
use App\Services\SmsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CrmController extends Controller
{
    public function index(Request $request): View
    {
        return view('backend.crm.index');
    }

    public function search(Request $request): JsonResponse
    {
        $number = preg_replace('/\D+/', '', (string) $request->query('number'));

        if (strlen($number) !== 9) {
            return response()->json(['message' => 'Telefon nömrəsi 9 rəqəm olmalıdır.'], 422);
        }

        $customer = Customer::where('mobile', '994' . $number)->first();

        if (!$customer) {
            return response()->json(['found' => false]);
        }

        return response()->json([
            'found' => true,
            'url' => route('admin.crm.show', $customer),
        ]);
    }

    public function show(Customer $customer): View
    {
        $customer->loadCount(['orders', 'bonusTransactions']);

        return view('backend.crm.show', compact('customer'));
    }

    public function tab(Customer $customer, string $tab): View
    {
        return match ($tab) {
            'orders' => view('backend.crm.tabs.orders', [
                'orders' => $customer->orders()
                    ->with(['status', 'paymentMethod', 'items.product'])
                    ->latest()
                    ->paginate(10),
            ]),
            'payments' => view('backend.crm.tabs.payments'),
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
            'active' => ['nullable', 'boolean'],
        ]);

        $customer->update([
            'name' => $data['name'],
            'surname' => $data['surname'],
            'mobile' => $data['mobile'],
            'email' => $data['email'] ?? null,
            'active' => $request->boolean('active'),
        ]);

        return redirect()
            ->route('admin.crm.show', $customer)
            ->with('success', 'Müştəri məlumatları yeniləndi.');
    }

    public function resetPassword(Customer $customer, SmsService $sms): RedirectResponse
    {
        if (!$customer->mobile) {
            return redirect()
                ->route('admin.crm.show', $customer)
                ->with('error', 'Müştərinin telefon nömrəsi olmadığı üçün şifrə yenilənmədi.');
        }

        try {
            $newPassword = Str::upper(Str::random(4)) . random_int(100000, 999999);
            $sms->send($customer->mobile, "ParfumShop: yeni şifrəniz {$newPassword}");
            $customer->forceFill(['password' => Hash::make($newPassword)])->save();
        } catch (\RuntimeException $exception) {
            return redirect()
                ->route('admin.crm.show', $customer)
                ->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('admin.crm.show', $customer)
            ->with('success', 'Yeni şifrə SMS ilə müştərinin nömrəsinə göndərildi.');
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

    public function order(Customer $customer, \App\Models\Order\Order $order): View
    {
        abort_unless($order->customer_id === $customer->id, 404);

        $order->load(['items.product', 'paymentMethod', 'status', 'address']);

        return view('backend.crm.order', compact('order'));
    }
}
