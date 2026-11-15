<?php

namespace App\Services\Finance;

use App\Models\Finance\FinanceCategory;
use App\Models\Finance\MoneyMovement;
use App\Models\Order\Order;
use App\Models\Order\OrderStatus;
use App\Models\Procurement\OrderItemAllocation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Aylıq mənfəət: Satış − malın mayası (depolar) = ümumi mənfəət; − xərclər = xalis mənfəət.
 *  - Satış — həmin ay təhvil verilmiş sifarişlər (təhvil günü): yekun məbləğ − əlavə çatdırılma (kuryerin qazancıdır);
 *    hissə-hissə — Ferrum-un bizə köçürdüyü xalis məbləğ; bonusla ödənilən — 0 (Satılan mallar hesabatı ilə eyni).
 *  - Malın mayası — bu sifarişlər üçün depolardan götürülən malın alış qiyməti. Öz istehsalımız burada sayılmır:
 *    xammal və istehsal xərcləri ödəniləndə Xərclərə düşür (ikiqat çıxılmasın deyə).
 *  - Xərclər — Məxaric, kuryer xərcləri, tədarükçüyə ödəniş (xammal); ödəniş günü, kateqoriya üzrə. Depoya ödəniş
 *    (malın mayası artıq sayılıb), müştəriyə qaytarma, köçürmələr daxil deyil.
 */
class ProfitReport
{
    public const EXPENSE_KINDS = ['outgo', 'courier_expense', 'supplier_payment'];

    /**
     * @return array{months: list<Carbon>, sales: array, orders: array, cost: array, gross: array, expenses: list<array>, expense: array, net: array}
     *   Məbləğlər qəpiklə, [Y-m => cents]
     */
    public function build(Carbon $from, Carbon $to): array
    {
        $months = [];
        for ($m = $from->copy()->startOfMonth(); $m <= $to; $m->addMonth()) $months[] = $m->copy();
        $keys = array_map(fn ($m) => $m->format('Y-m'), $months);
        $zero = array_fill_keys($keys, 0);
        $start = $months[0]->copy()->startOfMonth();
        $end = $to->copy()->endOfMonth();

        $sales = $orders = $cost = $zero;
        $delivered = OrderStatus::where('code', 'delivered')->value('id');
        if ($delivered) {
            $deliveredAt = DB::table('order_status_logs')->where('status_id', $delivered)
                ->selectRaw('order_id, MAX(created_at) as delivered_at')->groupBy('order_id');
            $rows = Order::query()->joinSub($deliveredAt, 'd', 'd.order_id', '=', 'orders.id')
                ->where('orders.order_status_id', $delivered)->whereBetween('d.delivered_at', [$start, $end])
                ->select('orders.*', 'd.delivered_at')->with(['paymentMethod:id,code', 'creditApplication'])->get();

            $costs = DB::table('order_item_allocations')
                ->join('order_items', 'order_items.id', '=', 'order_item_allocations.order_item_id')
                ->join('warehouses', 'warehouses.id', '=', 'order_item_allocations.warehouse_id')
                ->whereIn('order_items.order_id', $rows->pluck('id')->all() ?: [0])
                ->whereNotIn('order_item_allocations.status', OrderItemAllocation::SUPPLY_INACTIVE)
                ->where('warehouses.is_own', false)
                ->selectRaw('order_items.order_id as id, SUM(order_item_allocations.quantity * order_item_allocations.unit_cost) as c')
                ->groupBy('order_items.order_id')->pluck('c', 'id');

            foreach ($rows as $order) {
                $ym = Carbon::parse($order->delivered_at)->format('Y-m');
                if (!isset($sales[$ym])) continue;
                $sales[$ym] += (int) round(self::revenue($order) * 100);
                $cost[$ym] += (int) round((float) ($costs[$order->id] ?? 0) * 100);
                $orders[$ym]++;
            }
        }
        $gross = collect($keys)->mapWithKeys(fn ($k) => [$k => $sales[$k] - $cost[$k]])->all();

        // Xərclər: əks əməliyyat əslinin kateqoriyasından çıxılır
        $byCategory = [];
        $movements = MoneyMovement::with('reversalOf:id,kind,category_id')->whereBetween('occurred_at', [$start, $end])
            ->where(fn ($q) => $q->whereIn('kind', self::EXPENSE_KINDS)
                ->orWhere(fn ($r) => $r->where('kind', 'reversal')->whereHas('reversalOf', fn ($o) => $o->whereIn('kind', self::EXPENSE_KINDS))))
            ->get();
        foreach ($movements as $m) {
            $base = $m->kind === 'reversal' ? $m->reversalOf : $m;
            $cat = (int) ($base->category_id ?? 0);
            $ym = $m->occurred_at->format('Y-m');
            $byCategory[$cat] ??= $zero;
            $byCategory[$cat][$ym] += ($m->kind === 'reversal' ? -1 : 1) * (int) round((float) $m->amount * 100);
        }
        $names = FinanceCategory::whereIn('id', array_keys($byCategory))->get()->keyBy('id');
        $expenses = collect($byCategory)->map(fn ($values, $cat) => [
            'category' => $names[$cat] ?? null, 'values' => $values, 'total' => array_sum($values),
        ])->filter(fn ($row) => $row['total'] !== 0)->sortByDesc('total')->values()->all();
        $expense = collect($keys)->mapWithKeys(fn ($k) => [$k => collect($expenses)->sum(fn ($row) => $row['values'][$k])])->all();
        $net = collect($keys)->mapWithKeys(fn ($k) => [$k => $gross[$k] - $expense[$k]])->all();

        return compact('months', 'sales', 'orders', 'cost', 'gross', 'expenses', 'expense', 'net');
    }

    /** Sifarişdən şirkətə qalan gəlir (AZN) */
    public static function revenue(Order $order): float
    {
        $courierFee = (float) $order->courier_fee;

        return match ($order->paymentMethod?->code) {
            'bonus_balance' => 0.0,
            'installment' => max(0.0, ($order->creditApplication
                ? ($order->creditApplication->netAmount() ?? (float) $order->creditApplication->total)
                : (float) $order->total) - $courierFee),
            default => max(0.0, (float) $order->total - $courierFee),
        };
    }
}
