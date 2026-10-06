<?php

namespace App\Services\Payment;

use App\Models\Order\Order;
use Illuminate\Support\Facades\Log;

/**
 * Ödənişə daxil olanlar: hər məhsul sətri, çatdırılma və qablaşdırma — faktiki ödənilən məbləğlə.
 * Geri ödəniş sonradan bu sətirlərə bağlanır (hansı məhsula görə nə qədər qaytarılır).
 *
 * orders.discount iki mənbədən dolur:
 *  - checkout: promo kod endirimi (unit_price sayt qiymətidir) → məhsullara proporsional paylanır;
 *  - CRM: operator endirimi, sayt: məhsul endirimi (ProductDiscount) — artıq unit_price-dadır (list_price > unit_price) → ikinci dəfə çıxılmır.
 * Hesab qəpiklə (tam ədəd) aparılır; sətirlərin cəmi həmişə ödəniş məbləğinə bərabərdir.
 */
class PaymentItemsBuilder
{
    public const ITEM = 'item';
    public const DELIVERY = 'delivery';
    public const GIFT_WRAP = 'gift_wrap';

    /** @return array<int, array{order_item_id: ?int, type: string, quantity: int, unit_price: string, amount: string}> */
    public function lines(Order $order, float $amount): array
    {
        $cents = fn ($value) => (int) round((float) $value * 100);

        $lines = [];
        foreach ($this->itemShares($order) as $row) {
            $lines[] = $this->line($row['item']->id, self::ITEM, $row['quantity'], $row['goods'] - $row['promo']);
        }
        if ($cents($order->delivery_fee) > 0) {
            $lines[] = $this->line(null, self::DELIVERY, 1, $cents($order->delivery_fee));
        }
        if ($cents($order->gift_wrap_fee) > 0) {
            $lines[] = $this->line(null, self::GIFT_WRAP, 1, $cents($order->gift_wrap_fee));
        }

        // Cəm ödəniş məbləğindən fərqlənirsə (köhnə/əl ilə dəyişilmiş sifariş), fərq sonuncu məhsula yazılır
        $diff = $cents($amount) - array_sum(array_column($lines, 'cents'));
        if ($diff !== 0) {
            if (abs($diff) > 5) {
                Log::warning('Payment items do not match payment amount', ['order_id' => $order->id, 'diff_cents' => $diff]);
            }
            $target = null;
            foreach ($lines as $k => $line) {
                if ($line['type'] === self::ITEM) $target = $k;
            }
            $target ??= array_key_last($lines);
            if ($target === null) {
                $lines[] = $this->line(null, self::ITEM, 1, $diff);
            } else {
                $lines[$target] = $this->line($lines[$target]['order_item_id'], $lines[$target]['type'], $lines[$target]['quantity'], $lines[$target]['cents'] + $diff);
            }
        }

        return array_map(function ($line) {
            unset($line['cents']);
            return $line;
        }, $lines);
    }

    /**
     * Aktiv məhsul sətirləri və hər birinə düşən promo endirimi (qəpiklə).
     * Promo = orders.discount − operator endirimi (CRM-də unit_price-a artıq daxildir); qalıq sonuncu sətrə.
     * Ləğv də (OrderItemCancellationService) eyni paylamadan istifadə edir — məbləğlər üst-üstə düşür.
     *
     * @return array<int, array{item: \App\Models\Order\OrderItem, quantity: int, goods: int, promo: int}>
     */
    public function itemShares(Order $order): array
    {
        if (!$order->relationLoaded('items')) {
            $order->load('items');
        }
        $cents = fn ($value) => (int) round((float) $value * 100);

        $items = $order->items->filter(fn ($item) => $item->activeQuantity() > 0)->values();
        $operatorDiscount = $items->sum(fn ($item) => $item->list_price !== null
            ? max(0, $cents($item->list_price) - $cents($item->unit_price)) * $item->activeQuantity()
            : 0);
        $goodsList = $items->map(fn ($item) => $cents($item->unit_price) * $item->activeQuantity());
        $goods = $goodsList->sum();
        $promo = min(max(0, $cents($order->discount) - $operatorDiscount), $goods);

        $rows = [];
        $shared = 0;
        foreach ($items as $i => $item) {
            $share = $i === $items->count() - 1
                ? $promo - $shared
                : ($goods ? intdiv($promo * $goodsList[$i], $goods) : 0);
            $shared += $share;
            $rows[] = ['item' => $item, 'quantity' => $item->activeQuantity(), 'goods' => $goodsList[$i], 'promo' => $share];
        }

        return $rows;
    }

    private function line(?int $orderItemId, string $type, int $quantity, int $cents): array
    {
        $cents = max(0, $cents);

        return [
            'order_item_id' => $orderItemId,
            'type' => $type,
            'quantity' => $quantity,
            // Vahid qiymət məlumat üçündür; hesab həmişə "amount" ilə aparılır
            'unit_price' => number_format($cents / max(1, $quantity) / 100, 2, '.', ''),
            'amount' => number_format($cents / 100, 2, '.', ''),
            'cents' => $cents,
        ];
    }
}
