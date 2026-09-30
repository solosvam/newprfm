<?php

namespace App\Services;

use App\Models\Finance\FinanceAccount;
use App\Models\Finance\MoneyMovement;
use App\Models\Payment\Payment;
use App\Models\Payment\PaymentOperation;
use App\Models\Procurement\OrderItemAllocation;
use App\Models\Procurement\Warehouse;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Kassa və hesablaşma. Hər hərəkət from → to; qalıq = daxil olan − çıxan.
 *  - Kuryer: + → kuryer şirkətə təhvil verməlidir, − → şirkət kuryerə borcludur.
 *  - Sahibkar: − → şirkət sahibkara borcludur.
 *  - Anbar: borc = götürülmüş (picked) hissələrin alış dəyəri − anbara ödənilən.
 * Qeydlər silinmir: səhv əks əməliyyatla düzəldilir (reverse).
 */
class FinanceService
{
    public const COURIER_ROLE = 'Kuryer';

    public function system(string $code): FinanceAccount
    {
        return FinanceAccount::where('code', $code)->firstOrFail();
    }

    public function courierAccount(User $user): FinanceAccount
    {
        return FinanceAccount::firstOrCreate(['type' => 'courier', 'user_id' => $user->id], ['name' => trim($user->full_name) ?: 'Kuryer #'.$user->id]);
    }

    public function warehouseAccount(Warehouse $warehouse): FinanceAccount
    {
        return FinanceAccount::firstOrCreate(['type' => 'warehouse', 'warehouse_id' => $warehouse->id], ['name' => $warehouse->name_az]);
    }

    /** "Kuryer" rolundakı əməkdaşlar və anbarlar üçün hesablar yoxdursa yaradılır */
    public function syncAccounts(): void
    {
        try {
            User::role(self::COURIER_ROLE, 'admin')->get()->each(fn ($u) => $this->courierAccount($u));
        } catch (\Spatie\Permission\Exceptions\RoleDoesNotExist) {
            // Rol hələ yaradılmayıb
        }
        Warehouse::all()->each(fn ($w) => $this->warehouseAccount($w));
    }

    /** Hər hesabın qalığı (qəpiklə): account_id => cents */
    public function balances(): Collection
    {
        $in = MoneyMovement::selectRaw('to_account_id as id, SUM(amount) as total')->groupBy('to_account_id')->pluck('total', 'id');
        $out = MoneyMovement::selectRaw('from_account_id as id, SUM(amount) as total')->groupBy('from_account_id')->pluck('total', 'id');

        return FinanceAccount::pluck('id')->mapWithKeys(fn ($id) => [$id => (int) round(((float) ($in[$id] ?? 0) - (float) ($out[$id] ?? 0)) * 100)]);
    }

    /** Anbarlara borc: götürülmüş hissələrin dəyəri − anbar hesabına ödənilən (warehouse_id => cents) */
    public function warehouseDebts(): Collection
    {
        $owed = OrderItemAllocation::where('status', OrderItemAllocation::PICKED)
            ->selectRaw('warehouse_id, SUM(quantity * unit_cost) as total')->groupBy('warehouse_id')->pluck('total', 'warehouse_id');
        $balances = $this->balances();

        return FinanceAccount::where('type', 'warehouse')->get()->mapWithKeys(fn ($a) => [
            $a->warehouse_id => (int) round((float) ($owed[$a->warehouse_id] ?? 0) * 100) - ($balances[$a->id] ?? 0),
        ]);
    }

    /** Hər təminat hissəsinə ödənilən (əks əməliyyatlar çıxılmış): allocation_id => cents */
    public function allocationPaid(array $allocationIds): Collection
    {
        if (!$allocationIds) return collect();
        $rows = MoneyMovement::with('to:id,type', 'from:id,type')->whereIn('order_item_allocation_id', $allocationIds)->get();

        return collect($allocationIds)->mapWithKeys(fn ($id) => [$id => $rows->where('order_item_allocation_id', $id)->sum(function ($m) {
            $cents = (int) round((float) $m->amount * 100);
            return ($m->to?->type === 'warehouse' ? $cents : 0) - ($m->from?->type === 'warehouse' ? $cents : 0);
        })]);
    }

    /** Əl ilə və ya sistem tərəfindən hərəkət. Növ və hesab tipləri MoneyMovement::KINDS-ə uyğun olmalıdır. */
    public function record(FinanceAccount $from, FinanceAccount $to, float $amount, string $kind, array $attrs = [], ?int $actor = null): MoneyMovement
    {
        $rule = MoneyMovement::KINDS[$kind] ?? null;
        $this->ensure($rule !== null && $kind !== 'reversal', 'Əməliyyat növü yanlışdır.');
        $cents = (int) round($amount * 100);
        $this->ensure($cents > 0, 'Məbləğ sıfırdan böyük olmalıdır.');
        $this->ensure($from->id !== $to->id, 'Eyni hesab seçilib.');
        $this->ensure(in_array($from->type, $rule[1], true), '"'.$rule[0].'" üçün göndərən hesab uyğun deyil ('.$from->name.').');
        $this->ensure(in_array($to->type, $rule[2], true), '"'.$rule[0].'" üçün alan hesab uyğun deyil ('.$to->name.').');
        $this->ensure($from->active && $to->active, 'Hesab deaktivdir.');

        return DB::transaction(function () use ($from, $to, $cents, $kind, $attrs, $actor) {
            // Anbara ödəniş təminat hissəsinə bağlıdırsa: həmin anbar və dəyərdən çox ödənilə bilməz
            if (!empty($attrs['order_item_allocation_id'])) {
                $allocation = OrderItemAllocation::with('orderItem')->lockForUpdate()->findOrFail($attrs['order_item_allocation_id']);
                $this->ensure($kind === 'warehouse_payment' && (int) $allocation->warehouse_id === (int) $to->warehouse_id, 'Ödəniş bu anbarın təminatına aid deyil.');
                $this->ensure($allocation->status !== OrderItemAllocation::CANCELLED, 'Ləğv edilmiş seçimə ödəniş edilmir.');
                $left = (int) round($allocation->quantity * (float) $allocation->unit_cost * 100) - ($this->allocationPaid([$allocation->id])[$allocation->id] ?? 0);
                $this->ensure($cents <= $left, 'Bu hissə üzrə qalan borc '.number_format($left / 100, 2).' AZN-dir.');
                $attrs['order_id'] ??= $allocation->orderItem?->order_id;
            }

            return MoneyMovement::create($attrs + [
                'from_account_id' => $from->id,
                'to_account_id' => $to->id,
                'amount' => number_format($cents / 100, 2, '.', ''),
                'kind' => $kind,
                'created_by' => $actor,
                'occurred_at' => $attrs['occurred_at'] ?? now(),
            ]);
        });
    }

    /** Səhv qeydin əksi: eyni məbləğ əks istiqamətdə. Hər qeyd bir dəfə əks oluna bilər. */
    public function reverse(MoneyMovement $movement, int $actor, ?string $note = null): MoneyMovement
    {
        return DB::transaction(function () use ($movement, $actor, $note) {
            $locked = MoneyMovement::lockForUpdate()->findOrFail($movement->id);
            $this->ensure($locked->kind !== 'reversal', 'Əks əməliyyatın özü əks oluna bilməz.');
            $this->ensure(!MoneyMovement::where('reversal_of_id', $locked->id)->exists(), 'Bu qeyd artıq əks olunub.');

            return MoneyMovement::create([
                'from_account_id' => $locked->to_account_id,
                'to_account_id' => $locked->from_account_id,
                'amount' => $locked->amount,
                'kind' => 'reversal',
                'order_id' => $locked->order_id,
                'order_item_allocation_id' => $locked->order_item_allocation_id,
                'payment_id' => $locked->payment_id,
                'reversal_of_id' => $locked->id,
                'note' => $note ?: 'Əks əməliyyat: #'.$locked->id,
                'created_by' => $actor,
                'occurred_at' => now(),
            ]);
        });
    }

    /** Birbank ödənişi təsdiqlənəndə: Müştəri → Onlayn ödənişlər (bir dəfə) */
    public function recordOnlinePayment(Payment $payment): void
    {
        if (MoneyMovement::where('kind', 'customer_payment')->where('payment_id', $payment->id)->exists()) return;
        $this->record($this->system('customer'), $this->system('online'), (float) $payment->amount, 'customer_payment', [
            'order_id' => $payment->order_id, 'payment_id' => $payment->id, 'note' => 'Birbank ödənişi',
        ]);
    }

    /** Karta qaytarma uğurlu olanda: Onlayn ödənişlər → Müştəri (bir dəfə) */
    public function recordOnlineRefund(PaymentOperation $operation): void
    {
        if ($operation->status !== 'succeeded' || MoneyMovement::where('payment_operation_id', $operation->id)->exists()) return;
        $payment = $operation->payment;
        $this->record($this->system('online'), $this->system('customer'), (float) $operation->amount, 'customer_refund', [
            'order_id' => $payment?->order_id, 'payment_id' => $payment?->id, 'payment_operation_id' => $operation->id, 'note' => 'Karta qaytarma',
        ]);
    }

    private function ensure(bool $condition, string $message): void
    {
        if (!$condition) {
            throw ValidationException::withMessages(['finance' => $message]);
        }
    }
}
