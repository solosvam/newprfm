<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Finance\FinanceAccount;
use App\Models\Finance\MoneyMovement;
use App\Services\FinanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Kassa və hesablar: qalıqlar, pul hərəkətləri, əl ilə əməliyyat, əks əməliyyat */
class FinanceController extends Controller
{
    public function index(Request $request, FinanceService $finance): View
    {
        $finance->syncAccounts();
        $accounts = FinanceAccount::with('user', 'warehouse')->where('active', true)->orderBy('type')->orderBy('name')->get();
        $balances = $finance->balances();
        $debts = $finance->warehouseDebts();

        $accountId = $request->integer('account') ?: null;
        $movements = MoneyMovement::with(['from', 'to', 'order', 'user', 'reversedBy'])
            ->when($accountId, fn ($q) => $q->where(fn ($w) => $w->where('from_account_id', $accountId)->orWhere('to_account_id', $accountId)))
            ->latest('occurred_at')->latest('id')->limit(1000)->get();

        return view('backend.finance.index', compact('accounts', 'balances', 'debts', 'movements', 'accountId'));
    }

    /**
     * Hesabın səhifəsi. Anbar: açıq hissələr + "Ödə"; kuryer: haqq-hesab + "Pulu təhvil al".
     */
    public function account(FinanceAccount $account, FinanceService $finance): View
    {
        $balances = $finance->balances();
        $balance = $balances[$account->id] ?? 0;
        $movements = MoneyMovement::with(['from', 'to', 'order', 'user', 'reversedBy'])
            ->where(fn ($w) => $w->where('from_account_id', $account->id)->orWhere('to_account_id', $account->id))
            ->latest('occurred_at')->latest('id')->limit(1000)->get();

        $parts = collect();
        $payers = collect();
        $debt = null;
        if ($account->type === 'warehouse') {
            $debt = $finance->warehouseDebts()[$account->warehouse_id] ?? 0;
            $all = \App\Models\Procurement\OrderItemAllocation::with(['orderItem.order', 'orderItem.product', 'orderItem.variant.size'])
                ->where('warehouse_id', $account->warehouse_id)->where('status', '!=', 'cancelled')->orderBy('id')->get();
            $paid = $finance->allocationPaid($all->pluck('id')->all());
            // Ödənilməmiş hissələr (götürülənlər borcdur, qalanları — əvvəlcədən ödəmək olar)
            $parts = $all->map(fn ($a) => ['a' => $a, 'cost' => (int) round($a->quantity * (float) $a->unit_cost * 100), 'paid' => $paid[$a->id] ?? 0])
                ->filter(fn ($r) => $r['cost'] > $r['paid'])->values();
            $payers = FinanceAccount::whereIn('type', ['courier', 'cash', 'bank', 'owner'])->where('active', true)->orderBy('name')->get()
                ->sortBy(fn ($a) => array_search($a->type, ['cash', 'bank', 'courier', 'owner'], true))->values();
        }
        $cash = $finance->system('cash');

        return view('backend.finance.account', compact('account', 'balance', 'movements', 'parts', 'payers', 'debt', 'cash'));
    }

    public function store(Request $request, FinanceService $finance): RedirectResponse
    {
        $data = $request->validate([
            'kind' => ['required', Rule::in(array_merge(MoneyMovement::MANUAL, MoneyMovement::FROM_ACCOUNT_PAGE))],
            'from_account_id' => ['required', 'integer', 'exists:finance_accounts,id'],
            'to_account_id' => ['nullable', 'integer', 'exists:finance_accounts,id'],
            'order_item_allocation_id' => ['nullable', 'integer'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:9999999999.99'],
            // Brauzer yerli vaxtı göndərir (server UTC ola bilər) — bir günlük ehtiyat saxlanılır
            'occurred_at' => ['nullable', 'date', 'before:+1 day'],
            'note' => ['nullable', 'required_if:kind,expense', 'string', 'max:2000'],
        ], [
            'note.required_if' => 'Xərcin adını yazın.',
            'kind.required' => 'Əməliyyat növünü seçin.',
            'from_account_id.required' => 'Pulun çıxdığı hesabı seçin.',
            'amount.required' => 'Məbləği daxil edin.',
            'amount.numeric' => 'Məbləğ rəqəm olmalıdır.',
            'amount.min' => 'Məbləğ 0.01 AZN-dən az ola bilməz.',
            'occurred_at.date' => 'Tarix düzgün deyil.',
            'occurred_at.before' => 'Gələcək tarix yazmaq olmaz.',
            'kind.in' => 'Əməliyyat növü yanlışdır.',
            'from_account_id.exists' => 'Hesab tapılmadı.',
            'to_account_id.exists' => 'Hesab tapılmadı.',
            'note.max' => 'Qeyd çox uzundur.',
        ]);

        $from = FinanceAccount::findOrFail($data['from_account_id']);
        // Təminat hissəsinə ödəniş: alan hesab həmin anbardır (formadan gəlmir)
        if (!empty($data['order_item_allocation_id'])) {
            $allocation = \App\Models\Procurement\OrderItemAllocation::with('warehouse')->findOrFail($data['order_item_allocation_id']);
            $to = $finance->warehouseAccount($allocation->warehouse);
        } else {
            abort_if(empty($data['to_account_id']), 422, 'Pulun getdiyi hesabı seçin.');
            $to = FinanceAccount::findOrFail($data['to_account_id']);
        }

        $movement = $finance->record($from, $to, (float) $data['amount'], $data['kind'], array_filter([
            'order_item_allocation_id' => $data['order_item_allocation_id'] ?? null,
            'occurred_at' => $data['occurred_at'] ?? null,
            'note' => $data['note'] ?? null,
        ]), (int) auth('admin')->id());

        return back()->with('success', $movement->kindLabel().': '.number_format((float) $movement->amount, 2).' AZN qeydə alındı.');
    }

    public function reverse(Request $request, MoneyMovement $movement, FinanceService $finance): RedirectResponse
    {
        $data = $request->validate(['note' => ['required', 'string', 'max:2000']], ['note.required' => 'Səbəbi yazın.']);
        $finance->reverse($movement, (int) auth('admin')->id(), 'Əks əməliyyat #'.$movement->id.': '.$data['note']);

        return back()->with('success', 'Əks əməliyyat qeydə alındı.');
    }
}
