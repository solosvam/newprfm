<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CrmController extends Controller
{
    public function index(Request $request): View
    {
        $query = Customer::query()->latest('id');
        $search = trim((string) $request->query('q'));

        if ($search !== '') {
            $query->where(function ($customers) use ($search) {
                $customers
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('surname', 'like', "%{$search}%")
                    ->orWhere('mobile', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        return view('backend.crm.index', [
            'customers' => $query->paginate(20)->withQueryString(),
            'search' => $search,
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
            'payments' => view('backend.crm.tabs.payments', [
                'orders' => $customer->orders()
                    ->with(['paymentMethod', 'status'])
                    ->latest()
                    ->paginate(10),
            ]),
            'bonuses' => view('backend.crm.tabs.bonuses', [
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

    public function resetPassword(Customer $customer): RedirectResponse
    {
        $customer->forceFill(['password' => null])->save();

        return redirect()
            ->route('admin.crm.show', $customer)
            ->with('success', 'Müştərinin şifrəsi sıfırlandı. Növbəti girişdə OTP ilə yeni şifrə təyin edəcək.');
    }
}
