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
 *  - Satış — həmin ay təhvil verilmiş sifarişlər (təhvil günü): yekun məbləğ + hədiyyə kartı ilə ödənilən − əlavə çatdırılma (kuryerin qazancıdır);
 *    hədiyyə kartının alışı satış deyil (alıcıya borcdur) — satış kartla mal veriləndə yaranır;
 *    hissə-hissə — Ferrum-un bizə köçürdüyü xalis məbləğ; bonusla ödənilən — 0 (Satılan mallar hesabatı ilə eyni).
 *  - Hədiyyə kartı qalığı — istifadə olunmayan pul gəlirdir: müddəti bitən kartın məbləği (bitmə günü) və sifarişdən
 *    artıq qalıb yanan hissə (istifadə günü). Müddəti uzadılan / pulu qaytarılan kart sayılmır.
 *  - Malın mayası — bu sifarişlər üçün depolardan götürülən malın alış qiyməti. Öz istehsalımız burada sayılmır:
 *    xammal və istehsal xərcləri ödəniləndə Xərclərə düşür (ikiqat çıxılmasın deyə).
 *  - Xərclər — Məxaric, kuryer xərcləri, tədarükçüyə ödəniş (xammal); ödəniş günü, kateqoriya üzrə. Depoya ödəniş
 *    (malın mayası artıq sayılıb), müştəriyə qaytarma, köçürmələr daxil deyil.
 */
class ProfitReport
{
    public const EXPENSE_KINDS = ['outgo', 'courier_expense', 'supplier_payment'];

    /**
     * @return array{months: list<Carbon>, sales: array, orders: array, breakage: array, cost: array, gross: array, expenses: list<array>, expense: array, net: array}
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
        $breakage = $this->giftCardBreakage($start, $end, $zero);
        $gross = collect($keys)->mapWithKeys(fn ($k) => [$k => $sales[$k] + $breakage[$k] - $cost[$k]])->all();

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

        return compact('months', 'sales', 'orders', 'breakage', 'cost', 'gross', 'expenses', 'expense', 'net');
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
            default => max(0.0, (float) $order->total + (float) $order->gift_card_amount - $courierFee),
        };
    }

    /** İstifadə olunmayan hədiyyə kartı pulu: [Y-m => cents] */
    private function giftCardBreakage(Carbon $start, Carbon $end, array $zero): array
    {
        $result = $zero;
        if (!\Illuminate\Support\Facades\Schema::hasTable('virtual_gift_cards')) {
            return $result;
        }
        $add = function (Carbon $at, float $amount) use (&$result) {
            $ym = $at->format('Y-m');
            if (isset($result[$ym]) && $amount > 0) $result[$ym] += (int) round($amount * 100);
        };
        // Müddəti bitən: bitmə qeydinin günü (sonuncu "expired")
        $expired = \App\Models\GiftCard\GiftCard::where('status', \App\Models\GiftCard\GiftCard::EXPIRED)->get(['id', 'amount', 'expires_at']);
        $expiredAt = \App\Models\GiftCard\GiftCardEvent::whereIn('virtual_gift_card_id', $expired->pluck('id')->all() ?: [0])->where('event', 'expired')
            ->selectRaw('virtual_gift_card_id, MAX(created_at) as at')->groupBy('virtual_gift_card_id')->pluck('at', 'virtual_gift_card_id');
        foreach ($expired as $card) {
            $at = isset($expiredAt[$card->id]) ? Carbon::parse($expiredAt[$card->id]) : $card->expires_at;
            if ($at && $at->between($start, $end)) $add($at, (float) $card->amount);
        }
        // Sifarişdən artıq qalıb yanan hissə
        $used = \App\Models\GiftCard\GiftCard::with('usedOrder:id,gift_card_amount')->where('status', \App\Models\GiftCard\GiftCard::USED)
            ->whereBetween('used_at', [$start, $end])->get(['id', 'amount', 'used_at', 'used_order_id']);
        foreach ($used as $card) {
            $add($card->used_at, round((float) $card->amount - (float) $card->usedOrder?->gift_card_amount, 2));
        }

        return $result;
    }
}
