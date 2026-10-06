<?php

namespace Tests\Unit;

use App\Models\Order\Order;
use App\Models\Order\OrderItem;
use PHPUnit\Framework\TestCase;

/** Sifarişin hesabı: Məhsullar − Endirim + Çatdırılma + Qablaşdırma − Ləğv olunan = Toplam */
class OrderTotalsBreakdownTest extends TestCase
{
    private function order(array $attributes, array $items, float $cancelled): Order
    {
        $order = (new Order())->forceFill($attributes + ['delivery_fee' => 0, 'gift_wrap_fee' => 0, 'discount' => 0, 'referral_discount' => 0]);
        $order->setAttribute('cancelled_amount', $cancelled); // withSum('itemCancellations as cancelled_amount')
        $order->setRelation('items', collect(array_map(fn ($i) => (new OrderItem())->forceFill($i), $items)));

        return $order;
    }

    private function adds(Order $order, array $b): bool
    {
        return round($b['goods'] - $b['discount'] - $b['referral'] + $order->delivery_fee + $order->gift_wrap_fee - $b['cancelled'], 2) === round($b['total'], 2);
    }

    public function test_without_cancellations_keeps_promo_and_referral_apart(): void
    {
        $order = $this->order(['subtotal' => 100, 'discount' => 15, 'referral_discount' => 10, 'delivery_fee' => 5, 'total' => 90], [], 0);
        $b = $order->totalsBreakdown();
        $this->assertSame([100.0, 5.0, 10.0, 0.0, 90.0], [$b['goods'], $b['discount'], $b['referral'], $b['cancelled'], $b['total']]);
        $this->assertTrue($this->adds($order, $b));
    }

    public function test_partial_cancellation_shows_original_goods_and_cancelled_row(): void
    {
        // 199 + 25 sifariş olunub, 25-lik məhsul ləğv edilib
        $order = $this->order(['subtotal' => 199, 'total' => 199], [
            ['unit_price' => 199, 'quantity' => 1, 'cancelled_quantity' => 0],
            ['unit_price' => 25, 'quantity' => 1, 'cancelled_quantity' => 1],
        ], 25);
        $b = $order->totalsBreakdown();
        $this->assertSame([224.0, 0.0, 25.0, 199.0], [$b['goods'], $b['discount'], $b['cancelled'], $b['total']]);
        $this->assertTrue($this->adds($order, $b));
    }

    public function test_full_cancellation_with_promo_and_refunded_delivery_adds_up_to_zero(): void
    {
        // 2 × 50 = 100, promo 10, çatdırılma 5 → 95 ödənilib; hamısı ləğv (məhsullar 90 + çatdırılma 5)
        $order = $this->order(['subtotal' => 0, 'discount' => 0, 'delivery_fee' => 5, 'total' => 0], [
            ['unit_price' => 50, 'quantity' => 2, 'cancelled_quantity' => 2],
        ], 95);
        $b = $order->totalsBreakdown();
        $this->assertSame([100.0, 10.0, 95.0, 0.0], [$b['goods'], $b['discount'], $b['cancelled'], $b['total']]);
        $this->assertSame(95.0, $order->originalTotal());
        $this->assertTrue($this->adds($order, $b));
    }
}
