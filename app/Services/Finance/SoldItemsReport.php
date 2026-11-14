<?php

namespace App\Services\Finance;

use App\Models\Order\OrderItem;
use App\Models\Order\OrderStatus;
use App\Models\Procurement\OrderItemAllocation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Satılan mallar (köhnə hesabat proqramındakı kimi): seçilən günlərdə təhvil verilmiş sifarişlərin malları.
 * Sətir — malın götürüldüyü depo hissəsi (bir məhsul iki depodan götürülübsə iki sətir; mayası, mənbəyi fərqlidir).
 *  - Tarix — sifarişin "Təhvil verildi" vaxtı;
 *  - Mənsəb — ödəniş üsulu; bonusla ödənilibsə satış qiyməti 0 (maya real — bonusla verilən malın xərci);
 *    hissə-hissə (Ferrum) — xalis: Ferrum-un bizə köçürdüyü məbləğ mallara qiymətləri nisbətində bölünür
 *    (müştərinin ödədiyi faiz əlavəsi daxil, Ferrum-un faizi çıxılıb — maliyyədəki xalis satışla eyni);
 *  - Miq. — götürülmüş say (ləğv / qapıda imtina — depoya qaytarılan çıxılır);
 *  - Maya — deponun alış qiyməti; depo hissəsi olmayan qalıq (mal depodan götürülməyib) — maya bilinmir.
 */
class SoldItemsReport
{
    /**
     * @param  array{payment?: ?string, warehouse?: ?int, q?: ?string}  $filters
     * @return Collection<int, array{delivered_at: Carbon, order: \App\Models\Order\Order, item: OrderItem, quantity: int,
     *         price: float, cost: ?float, warehouse: ?\App\Models\Procurement\Warehouse}>
     */
    public function rows(Carbon $from, Carbon $to, array $filters = []): Collection
    {
        $delivered = OrderStatus::where('code', 'delivered')->value('id');
        if (!$delivered) {
            return collect();
        }
        // Təhvil vaxtı: "Təhvil verildi" statusunun son qeydi
        $deliveredAt = DB::table('order_status_logs')->where('status_id', $delivered)
            ->selectRaw('order_id, MAX(created_at) as delivered_at')->groupBy('order_id');

        $items = OrderItem::query()
            ->joinSub($deliveredAt, 'd', 'd.order_id', '=', 'order_items.order_id')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.order_status_id', $delivered)
            ->whereBetween('d.delivered_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->when($filters['payment'] ?? null, fn ($q, $code) => $q->whereIn('orders.payment_method_id', DB::table('payment_methods')->where('code', $code)->select('id')))
            ->when($filters['q'] ?? null, function ($q, $term) {
                $like = '%'.addcslashes($term, '%_\\').'%';
                $q->whereHas('product', fn ($p) => $p->where('name', 'like', $like)->orWhereHas('brand', fn ($b) => $b->where('name', 'like', $like)));
            })
            ->select('order_items.*', 'd.delivered_at')
            ->with(['order.paymentMethod', 'order.creditApplication', 'product.brand', 'product.type', 'product.genders', 'variant.size', 'allocations.warehouse'])
            ->orderBy('d.delivered_at')->orderBy('order_items.id')
            ->get();

        $rows = collect();
        foreach ($items as $item) {
            $active = $item->activeQuantity();
            if ($active <= 0) {
                continue;
            }
            $price = match ($item->order->paymentMethod?->code) {
                'bonus_balance' => 0.0,
                'installment' => round((float) $item->unit_price * self::creditFactor($item->order), 2),
                default => (float) $item->unit_price,
            };
            $at = Carbon::parse($item->delivered_at);
            $parts = $item->allocations->whereNotIn('status', OrderItemAllocation::SUPPLY_INACTIVE)->sortBy('id');
            $left = $active;
            foreach ($parts as $part) {
                if ($left <= 0) break;
                $qty = min($left, (int) $part->quantity);
                $rows->push(['delivered_at' => $at, 'order' => $item->order, 'item' => $item, 'quantity' => $qty,
                    'price' => $price, 'cost' => (float) $part->unit_cost, 'warehouse' => $part->warehouse]);
                $left -= $qty;
            }
            if ($left > 0) {
                $rows->push(['delivered_at' => $at, 'order' => $item->order, 'item' => $item, 'quantity' => $left,
                    'price' => $price, 'cost' => null, 'warehouse' => null]);
            }
        }

        if ($warehouse = $filters['warehouse'] ?? null) {
            $rows = $rows->filter(fn ($r) => $r['warehouse']?->id === (int) $warehouse)->values();
        }

        return $rows;
    }

    /**
     * Hissə-hissə: malın qiymətinin neçə faizi bizə çatır — Ferrum-un xalis köçürməsi / müştərinin faizsiz borcu
     * (mallar − endirim + çatdırılma + qablaşdırma). Müraciət yoxdursa — 1.
     */
    public static function creditFactor(\App\Models\Order\Order $order): float
    {
        $credit = $order->creditApplication;
        $payable = (float) $order->subtotal - (float) $order->discount + (float) $order->delivery_fee + (float) $order->gift_wrap_fee;
        if (!$credit || $payable <= 0) {
            return 1.0;
        }

        // Əlavə çatdırılma kreditə daxildir, amma malın deyil — kuryerin qazancıdır (maliyyədə də satışdan çıxılır)
        return max(0.0, ($credit->netAmount() ?? (float) $credit->total) - (float) $order->courier_fee) / $payable;
    }

    /** @return array{count: int, sales: float, cost: float, profit: float, unknown: int} */
    public function totals(Collection $rows): array
    {
        $sales = round($rows->sum(fn ($r) => $r['price'] * $r['quantity']), 2);
        $cost = round($rows->sum(fn ($r) => (float) $r['cost'] * $r['quantity']), 2);

        return [
            'count' => (int) $rows->sum('quantity'),
            'sales' => $sales,
            'cost' => $cost,
            'profit' => round($sales - $cost, 2),
            'unknown' => (int) $rows->whereNull('cost')->sum('quantity'),
        ];
    }

    /** Mal: brend, model, ətir növü (EDP / EDT...), cinsi, həcmi */
    public static function title(OrderItem $item): string
    {
        $product = $item->product;
        $type = $product?->type?->name_az;
        $kind = match (true) {
            $type === null || $type === '' => null,
            (bool) preg_match('/toilette/i', $type) => 'EDT',
            (bool) preg_match('/cologne/i', $type) => 'EDC',
            (bool) preg_match('/eau\s*de\s*parfum/i', $type) => 'EDP',
            (bool) preg_match('/extrait/i', $type) => 'Extrait',
            default => $type,
        };

        return collect([
            $product?->brand?->name,
            $product?->name ?? 'Silinmiş məhsul',
            $kind,
            $product?->genders?->pluck('name_az')->implode(' / ') ?: null,
            $item->variant?->size?->name_az,
        ])->filter()->implode(' · ');
    }
}
