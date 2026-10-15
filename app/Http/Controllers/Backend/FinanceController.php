<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Finance\FinanceAccount;
use App\Models\Finance\FinanceCategory;
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
        $movements = MoneyMovement::with(['from', 'to', 'order', 'user', 'reversedBy', 'category'])
            ->when($accountId, fn ($q) => $q->where(fn ($w) => $w->where('from_account_id', $accountId)->orWhere('to_account_id', $accountId)))
            ->latest('occurred_at')->latest('id')->limit(1000)->get();

        $categories = FinanceCategory::tree();

        return view('backend.finance.index', compact('accounts', 'balances', 'debts', 'movements', 'accountId', 'categories'));
    }

    /**
     * Hesabın səhifəsi. Anbar: açıq hissələr + "Ödə"; kuryer: haqq-hesab + "Pulu təhvil al".
     */
    public function account(FinanceAccount $account, FinanceService $finance): View
    {
        $balances = $finance->balances();
        $balance = $balances[$account->id] ?? 0;
        $movements = MoneyMovement::with(['from', 'to', 'order', 'user', 'reversedBy', 'category'])
            ->where(fn ($w) => $w->where('from_account_id', $account->id)->orWhere('to_account_id', $account->id))
            ->latest('occurred_at')->latest('id')->limit(1000)->get();

        $parts = collect();
        $payers = collect();
        $debt = null;
        $byDay = null;
        if ($account->type === 'warehouse') {
            $debt = $finance->warehouseDebts()[$account->warehouse_id] ?? 0;
            $byDay = $finance->warehouseDebtByDay($account->warehouse);
            $all = \App\Models\Procurement\OrderItemAllocation::with(['orderItem.order', 'orderItem.product', 'orderItem.variant.size'])
                ->where('warehouse_id', $account->warehouse_id)->where('status', '!=', 'cancelled')->orderBy('id')->get();
            $paid = $finance->allocationPaid($all->pluck('id')->all());
            // Ödənilməmiş hissələr (götürülənlər borcdur, qalanları — əvvəlcədən ödəmək olar)
            $parts = $all->map(fn ($a) => ['a' => $a, 'cost' => (int) round($a->quantity * (float) $a->unit_cost * 100), 'paid' => $paid[$a->id] ?? 0])
                ->filter(fn ($r) => $r['cost'] > $r['paid'])->values();
            $payers = FinanceAccount::whereIn('type', ['courier', 'cash', 'bank', 'person'])->where('active', true)->orderBy('name')->get()
                ->sortBy(fn ($a) => array_search($a->type, ['cash', 'bank', 'courier', 'owner'], true))->values();
        }
        $cash = $finance->system('cash');

        return view('backend.finance.account', compact('account', 'balance', 'movements', 'parts', 'payers', 'debt', 'cash', 'byDay'));
    }

    /**
     * Yeni əməliyyat. Mədaxil / Məxaric: bir hesab + kateqoriya (qarşı tərəf "Kənar"); Köçürmə: haradan → haraya
     * (kuryerə köçürmə — avans, kuryerdən kassaya — təhvil). Anbara ödəniş və anbarın qaytarması hesabın səhifəsindən.
     */
    public function store(Request $request, FinanceService $finance): RedirectResponse
    {
        $data = $request->validate([
            'kind' => ['required', Rule::in(array_merge(['outgo', 'income', 'transfer'], MoneyMovement::FROM_ACCOUNT_PAGE))],
            'account_id' => ['nullable', 'required_if:kind,income,outgo', 'integer', 'exists:finance_accounts,id'],
            'category_id' => ['nullable', 'required_if:kind,income,outgo', 'integer', 'exists:finance_categories,id'],
            'from_account_id' => ['nullable', 'required_unless:kind,income,outgo', 'integer', 'exists:finance_accounts,id'],
            'to_account_id' => ['nullable', 'integer', 'exists:finance_accounts,id'],
            'order_item_allocation_id' => ['nullable', 'integer'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:9999999999.99'],
            // Brauzer yerli vaxtı göndərir (server UTC ola bilər) — bir günlük ehtiyat saxlanılır
            'occurred_at' => ['nullable', 'date', 'before:+1 day'],
            'note' => ['nullable', 'string', 'max:2000'],
        ], [
            'kind.required' => 'Əməliyyat növünü seçin.',
            'kind.in' => 'Əməliyyat növü yanlışdır.',
            'account_id.required_if' => 'Balansı seçin.',
            'category_id.required_if' => 'Kateqoriyanı seçin.',
            'from_account_id.required_unless' => 'Pulun çıxdığı hesabı seçin.',
            'amount.required' => 'Məbləği daxil edin.',
            'amount.numeric' => 'Məbləğ rəqəm olmalıdır.',
            'amount.min' => 'Məbləğ 0.01 AZN-dən az ola bilməz.',
            'occurred_at.date' => 'Tarix düzgün deyil.',
            'occurred_at.before' => 'Gələcək tarix yazmaq olmaz.',
            '*.exists' => 'Seçim tapılmadı.',
            'note.max' => 'Açıqlama çox uzundur.',
        ]);

        $kind = $data['kind'];
        if (in_array($kind, ['income', 'outgo'], true)) {
            $account = FinanceAccount::findOrFail($data['account_id']);
            $external = $finance->system('external');
            [$from, $to] = $kind === 'income' ? [$external, $account] : [$account, $external];
        } else {
            $from = FinanceAccount::findOrFail($data['from_account_id']);
            // Anbar pulu qaytardı: göndərən həmin anbardır, from_account_id isə pulu alan hesabdır (kuryer / kassa / bank)
            if ($kind === 'warehouse_refund') {
                abort_if(empty($data['order_item_allocation_id']), 422, 'Təminat hissəsi seçilməyib.');
                $allocation = \App\Models\Procurement\OrderItemAllocation::with('warehouse')->findOrFail($data['order_item_allocation_id']);
                $to = $from;
                $from = $finance->warehouseAccount($allocation->warehouse);
            } elseif (!empty($data['order_item_allocation_id'])) {
                // Təminat hissəsinə ödəniş: alan hesab həmin anbardır (formadan gəlmir)
                $allocation = \App\Models\Procurement\OrderItemAllocation::with('warehouse')->findOrFail($data['order_item_allocation_id']);
                $to = $finance->warehouseAccount($allocation->warehouse);
            } else {
                abort_if(empty($data['to_account_id']), 422, 'Pulun getdiyi hesabı seçin.');
                $to = FinanceAccount::findOrFail($data['to_account_id']);
            }
            // Kuryerlə köçürmə: kuryerə — avans / qaytarma, kuryerdən — kassaya təhvil
            if ($kind === 'transfer' && $to->type === 'courier') $kind = 'courier_advance';
            if ($kind === 'transfer' && $from->type === 'courier') $kind = 'courier_handover';
        }

        $movement = $finance->record($from, $to, (float) $data['amount'], $kind, array_filter([
            'category_id' => in_array($kind, ['income', 'outgo'], true) ? $data['category_id'] : null,
            'order_item_allocation_id' => $data['order_item_allocation_id'] ?? null,
            'occurred_at' => $data['occurred_at'] ?? null,
            'note' => $data['note'] ?? null,
        ]), (int) auth('admin')->id());

        return back()->with('success', $movement->load('category')->label().': '.number_format((float) $movement->amount, 2).' AZN qeydə alındı.');
    }

    /** Yeni hesab: Nağd / Kart / Bank / Şəxs. Başlanğıc qalıq — "Düzəliş" kateqoriyalı Mədaxil */
    public function storeAccount(Request $request, FinanceService $finance): RedirectResponse
    {
        $data = $this->accountData($request);
        $opening = $data['type'] !== 'person' ? (float) ($data['opening'] ?? 0) : 0;

        $account = \Illuminate\Support\Facades\DB::transaction(function () use ($data, $opening, $finance) {
            $account = FinanceAccount::create(['type' => $data['type'], 'name' => trim($data['name']), 'description' => $data['description'] ?? null]);
            if ($opening > 0) {
                $finance->record($finance->system('external'), $account, $opening, 'income',
                    ['category_id' => FinanceCategory::byCode('adjustment')?->id, 'note' => 'Başlanğıc qalıq'], (int) auth('admin')->id());
            }

            return $account;
        });

        return back()->with('success', 'Hesab yaradıldı: '.$account->name.'.');
    }

    /** Ad və açıqlama; qalığı sıfır olan hesab deaktiv edilə bilər (sistem hesabları — yox) */
    public function updateAccount(Request $request, FinanceAccount $account, FinanceService $finance): RedirectResponse
    {
        abort_unless(in_array($account->type, array_merge(FinanceAccount::CREATABLE, ['online']), true), 404);
        $data = $this->accountData($request, $account);
        $account->fill(['name' => trim($data['name']), 'description' => $data['description'] ?? null]);
        if ($request->boolean('deactivate')) {
            if ($account->code || ($finance->balances()[$account->id] ?? 0) !== 0) {
                return back()->withErrors(['finance' => $account->code ? 'Sistem hesabı deaktiv edilmir.' : 'Qalığı sıfır olmayan hesab deaktiv edilmir — əvvəlcə pulu köçürün.']);
            }
            $account->active = false;
        }
        $account->save();

        return $account->active
            ? back()->with('success', 'Hesab yeniləndi.')
            : redirect()->route('admin.finance.index')->with('success', $account->name.' deaktiv edildi.');
    }

    private function accountData(Request $request, ?FinanceAccount $account = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('finance_accounts', 'name')->where('active', true)->ignore($account?->id)],
            'type' => [$account ? 'nullable' : 'required', Rule::in(FinanceAccount::CREATABLE)],
            'opening' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'description' => ['nullable', 'string', 'max:2000'],
        ], [
            'name.required' => 'Hesabın adını yazın.',
            'name.max' => 'Ad çox uzundur.',
            'name.unique' => 'Bu adda hesab artıq var.',
            'type.required' => 'Növü seçin.',
            'type.in' => 'Növ yanlışdır.',
            'opening.numeric' => 'Məbləğ rəqəm olmalıdır.',
            'opening.min' => 'Başlanğıc qalıq mənfi ola bilməz.',
        ]);
    }

    /** Pul axını: aylar × kateqoriyalar (ən çox 24 ay) */
    public function report(Request $request, \App\Services\Finance\CashFlowReport $report): View
    {
        $month = fn ($value, $default) => rescue(fn () => $value ? \Illuminate\Support\Carbon::createFromFormat('!Y-m', $value) : $default, $default, false);
        $to = $month($request->query('to'), now()->startOfMonth());
        $from = $month($request->query('from'), $to->copy()->subMonths(5));
        if ($from > $to) [$from, $to] = [$to, $from];
        if ($from->diffInMonths($to) > 23) $from = $to->copy()->subMonths(23);

        $accounts = FinanceAccount::whereIn('type', array_merge(FinanceAccount::OWN, ['courier']))->where('active', true)->orderBy('name')->get()
            ->sortBy(fn ($a) => array_search($a->type, array_merge(FinanceAccount::OWN, ['courier']), true))->values();
        $account = $accounts->firstWhere('id', $request->integer('account'));
        // Formadan gələndə qutu işarələnməyibsə — göstərilir; ilk açılışda köçürmələr gizlidir
        $submitted = $request->has('to');
        $hideTransfers = $submitted ? $request->boolean('hide_transfers') : true;
        $hideEmpty = $request->boolean('hide_empty');

        $data = $report->build($from, $to->copy()->endOfMonth(), $account, $hideTransfers, $hideEmpty);

        return view('backend.finance.report', $data + compact('accounts', 'account', 'from', 'to', 'hideTransfers', 'hideEmpty'));
    }

    public function categories(): View
    {
        $categories = FinanceCategory::withCount(['movements', 'children'])->get();
        $tree = FinanceCategory::tree()->map(fn ($c) => $categories->firstWhere('id', $c->id));

        return view('backend.finance.categories', ['categories' => $tree, 'parents' => $tree->whereNull('parent_id')]);
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        FinanceCategory::create($this->categoryData($request));

        return back()->with('success', 'Kateqoriya yaradıldı.');
    }

    public function updateCategory(Request $request, FinanceCategory $category): RedirectResponse
    {
        abort_if($category->system, 403, 'Sistem kateqoriyası dəyişdirilmir.');
        $category->update($this->categoryData($request, $category));

        return back()->with('success', 'Kateqoriya yeniləndi.');
    }

    public function destroyCategory(FinanceCategory $category): RedirectResponse
    {
        abort_if($category->system, 403, 'Sistem kateqoriyası silinmir.');
        if ($category->movements()->exists() || $category->children()->exists()) {
            return back()->withErrors(['finance' => '"'.$category->name.'" silinmir: '.($category->children()->exists() ? 'alt kateqoriyaları var.' : 'əməliyyatlarda istifadə olunub.')]);
        }
        $category->delete();

        return back()->with('success', 'Kateqoriya silindi.');
    }

    private function categoryData(Request $request, ?FinanceCategory $category = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'type' => ['required', Rule::in(array_keys(FinanceCategory::TYPES))],
            // Ağac iki səviyyəlidir: üst kateqoriya özü alt ola bilməz
            'parent_id' => ['nullable', 'integer', Rule::exists('finance_categories', 'id')->whereNull('parent_id')],
        ], [
            'name.required' => 'Kateqoriyanın adını yazın.',
            'name.max' => 'Ad çox uzundur.',
            'type.required' => 'Növü seçin.',
            'type.in' => 'Növ yanlışdır.',
            'parent_id.exists' => 'Üst kateqoriya yanlışdır.',
        ]);
        $parentId = $data['parent_id'] ?? null;
        $parent = $parentId ? FinanceCategory::find($parentId) : null;
        // Üst kateqoriya avtomatik ola bilməz; növü eyni və ya "Hər ikisi" olmalıdır
        if ($parent && (in_array($parent->code, FinanceCategory::AUTO, true) || !in_array($parent->type, ['both', $data['type']], true))) {
            throw \Illuminate\Validation\ValidationException::withMessages(['parent_id' => '"'.$parent->name.'" altına bu növdə kateqoriya yaradılmır.']);
        }
        if ($category && $parentId && ((int) $parentId === $category->id || $category->children()->exists())) {
            throw \Illuminate\Validation\ValidationException::withMessages(['parent_id' => 'Alt kateqoriyası olan kateqoriya başqasının altına keçirilmir.']);
        }

        return ['name' => trim($data['name']), 'type' => $data['type'], 'parent_id' => $parentId];
    }

    public function reverse(Request $request, MoneyMovement $movement, FinanceService $finance): RedirectResponse
    {
        $data = $request->validate(['note' => ['required', 'string', 'max:2000']], ['note.required' => 'Səbəbi yazın.']);
        $finance->reverse($movement, (int) auth('admin')->id(), 'Əks əməliyyat #'.$movement->id.': '.$data['note']);

        return back()->with('success', 'Əks əməliyyat qeydə alındı.');
    }
}
