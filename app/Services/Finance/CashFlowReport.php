<?php

namespace App\Services\Finance;

use App\Models\Finance\FinanceAccount;
use App\Models\Finance\FinanceCategory;
use App\Models\Finance\MoneyMovement;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Pul axını (Clopos kimi): aylar sütunda, kateqoriyalar sətirdə.
 *  - Gəlir / xərc növləri (Mədaxil, Məxaric, Satış, Anbara ödəniş...) kateqoriyasına yazılır. Bütün balanslarda — kimin
 *    ödəməsindən asılı olmayaraq (şəxs öz cibindən ödəyibsə də xərcdir); bir balans seçiləndə — yalnız o balansın hərəkətləri.
 *  - Köçürmə: hər iki tərəf baxılan balanslardadırsa — "Köçürmə" (məxaricdə də, mədaxildə də; gizlədilə bilər).
 *    Bir tərəf şəxsdirsə — "Şəxslərlə hesablaşma" (nəticəyə qarışmır).
 *  - Əks əməliyyat əslinin sətrindən çıxılır.
 * Baxılan balanslar: hamısı — şirkətin hesabları + kuryerlər (kuryerdəki nağd da şirkətin puludur); və ya seçilən bir hesab.
 */
class CashFlowReport
{
    private const INCOME_KINDS = ['income', 'customer_payment', 'warehouse_refund'];
    private const OUTGO_KINDS = ['outgo', 'customer_refund', 'warehouse_payment'];
    private const TRANSFER_KINDS = ['transfer', 'courier_advance', 'courier_handover'];

    /**
     * @return array{months: list<Carbon>, expense: list<array>, income: list<array>, totals: array, persons: array}
     *   Sətir: ['category' => ?FinanceCategory, 'child' => bool, 'values' => [ym => cents], 'total' => cents]
     */
    public function build(Carbon $from, Carbon $to, ?FinanceAccount $account = null, bool $hideTransfers = true, bool $hideEmpty = false): array
    {
        $months = [];
        for ($m = $from->copy()->startOfMonth(); $m <= $to; $m->addMonth()) $months[] = $m->copy();
        $keys = array_map(fn ($m) => $m->format('Y-m'), $months);
        $scope = $account ? [$account->id] : FinanceAccount::whereIn('type', array_merge(FinanceAccount::OWN, ['courier']))->pluck('id')->all();
        $in = fn ($id) => in_array((int) $id, $scope, true);

        $movements = MoneyMovement::with(['from:id,type', 'to:id,type', 'reversalOf.from:id,type', 'reversalOf.to:id,type'])
            ->whereBetween('occurred_at', [$months[0]->copy()->startOfMonth(), $to->copy()->endOfMonth()])
            ->when($account, fn ($q) => $q->where(fn ($w) => $w->where('from_account_id', $account->id)->orWhere('to_account_id', $account->id)))
            ->get();

        $sum = ['income' => [], 'expense' => []];           // [section][category_id][ym] => cents
        $persons = ['in' => array_fill_keys($keys, 0), 'out' => array_fill_keys($keys, 0)];
        foreach ($movements as $m) {
            // Əks əməliyyat: əslinin növü və istiqaməti, məbləğ mənfi
            $base = $m->kind === 'reversal' ? $m->reversalOf : $m;
            if (!$base) continue;
            $sign = $m->kind === 'reversal' ? -1 : 1;
            $cents = $sign * (int) round((float) $m->amount * 100);
            $ym = $m->occurred_at->format('Y-m');
            $cat = $m->category_id ?? 0;

            if (in_array($base->kind, self::TRANSFER_KINDS, true)) {
                $fromIn = $in($base->from_account_id);
                $toIn = $in($base->to_account_id);
                if ($fromIn && $toIn) {
                    if ($hideTransfers) continue;
                    $sum['expense'][$cat][$ym] = ($sum['expense'][$cat][$ym] ?? 0) + $cents;
                    $sum['income'][$cat][$ym] = ($sum['income'][$cat][$ym] ?? 0) + $cents;
                } elseif ($toIn && $base->from?->type === 'person') {
                    $persons['in'][$ym] += $cents;
                } elseif ($fromIn && $base->to?->type === 'person') {
                    $persons['out'][$ym] += $cents;
                } elseif ($toIn || $fromIn) {
                    // Seçilən hesabla başqa şirkət hesabı arasında köçürmə
                    if ($hideTransfers) continue;
                    $section = $toIn ? 'income' : 'expense';
                    $sum[$section][$cat][$ym] = ($sum[$section][$cat][$ym] ?? 0) + $cents;
                }
                continue;
            }
            $section = in_array($base->kind, self::INCOME_KINDS, true) ? 'income' : (in_array($base->kind, self::OUTGO_KINDS, true) ? 'expense' : null);
            if ($section) $sum[$section][$cat][$ym] = ($sum[$section][$cat][$ym] ?? 0) + $cents;
        }

        $tree = FinanceCategory::tree();
        $rows = fn (string $section) => $this->rows($tree, $sum[$section], $section, $keys, $hideEmpty, $hideTransfers);
        $expense = $rows('expense');
        $income = $rows('income');
        $total = fn ($section) => collect($keys)->mapWithKeys(fn ($k) => [$k => collect($sum[$section])->sum(fn ($byMonth) => $byMonth[$k] ?? 0)])->all();
        $totals = ['expense' => $total('expense'), 'income' => $total('income')];
        $totals['result'] = collect($keys)->mapWithKeys(fn ($k) => [$k => $totals['income'][$k] - $totals['expense'][$k]])->all();
        $persons['net'] = collect($keys)->mapWithKeys(fn ($k) => [$k => $persons['in'][$k] - $persons['out'][$k]])->all();

        return ['months' => $months, 'expense' => $expense, 'income' => $income, 'totals' => $totals, 'persons' => $persons];
    }

    /** Ağac sırası; üst kateqoriyanın dəyəri — özü + alt kateqoriyaları. Bölməyə uyğun olmayan və dəyəri olmayan sətir çıxmır. */
    private function rows(Collection $tree, array $sum, string $section, array $keys, bool $hideEmpty, bool $hideTransfers): array
    {
        $fits = fn ($c) => $c->type === 'both' || $c->type === $section;
        $values = fn (array $ids) => collect($keys)->mapWithKeys(fn ($k) => [$k => collect($ids)->sum(fn ($id) => $sum[$id][$k] ?? 0)])->all();
        $rows = [];
        foreach ($tree as $c) {
            if ($hideTransfers && $c->code === 'transfer') continue;
            $ids = $c->parent_id ? [$c->id] : array_merge([$c->id], $tree->where('parent_id', $c->id)->pluck('id')->all());
            $v = $values($ids);
            $total = array_sum($v);
            if (!$total && ($hideEmpty || !$fits($c))) continue;
            $rows[] = ['category' => $c, 'child' => (bool) $c->parent_id, 'values' => $v, 'total' => $total];
        }
        if (!empty($sum[0])) {
            $v = $values([0]);
            $rows[] = ['category' => null, 'child' => false, 'values' => $v, 'total' => array_sum($v)];
        }

        return $rows;
    }
}
