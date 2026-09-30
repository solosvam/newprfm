<?php

namespace Tests\Unit;

use App\Models\Order\Order;
use App\Models\Order\OrderItem;
use App\Services\Payment\PaymentItemsBuilder;
use Tests\TestCase;

/** Bazasız: sifariş və məhsullar yaddaşda qurulur */
class PaymentItemsBuilderTest extends TestCase
{
    private function order(array $order, array $items): Order
    {
        $model = new Order($order + ['discount' => 0, 'delivery_fee' => 0, 'gift_wrap_fee' => 0]);
        $model->id = 1;
        $model->setRelation('items', collect(array_map(function ($row, $i) {
            $item = new OrderItem($row + ['list_price' => null]);
            $item->id = $i + 1;
            $item->total = round($row['unit_price'] * $row['quantity'], 2);
            return $item;
        }, $items, array_keys($items))));

        return $model;
    }

    private function sum(array $lines): float
    {
        return round(array_sum(array_map(fn ($l) => (float) $l['amount'], $lines)), 2);
    }

    public function test_without_discount_each_line_is_its_total(): void
    {
        $order = $this->order(['delivery_fee' => 5, 'gift_wrap_fee' => 3], [
            ['unit_price' => 100, 'quantity' => 2],
            ['unit_price' => 50, 'quantity' => 1],
        ]);
        $lines = (new PaymentItemsBuilder)->lines($order, 258);

        $this->assertSame(['200.00', '50.00', '5.00', '3.00'], array_column($lines, 'amount'));
        $this->assertSame(['item', 'item', 'delivery', 'gift_wrap'], array_column($lines, 'type'));
        $this->assertSame([1, 2, null, null], array_column($lines, 'order_item_id'));
    }

    public function test_promo_discount_is_shared_proportionally_and_sums_to_amount(): void
    {
        // 300 + 100, promo 10 → 7.50 və 2.50
        $order = $this->order(['discount' => 10], [
            ['unit_price' => 150, 'quantity' => 2],
            ['unit_price' => 100, 'quantity' => 1],
        ]);
        $lines = (new PaymentItemsBuilder)->lines($order, 390);

        $this->assertSame(['292.50', '97.50'], array_column($lines, 'amount'));
        $this->assertSame('146.25', $lines[0]['unit_price']);
        $this->assertSame(390.0, $this->sum($lines));
    }

    public function test_rounding_remainder_goes_to_last_item(): void
    {
        // 3 bərabər məhsul, promo 10 → 3.33 + 3.33 + 3.34
        $order = $this->order(['discount' => 10], [
            ['unit_price' => 10, 'quantity' => 1],
            ['unit_price' => 10, 'quantity' => 1],
            ['unit_price' => 10, 'quantity' => 1],
        ]);
        $lines = (new PaymentItemsBuilder)->lines($order, 20);

        $this->assertSame(['6.67', '6.67', '6.66'], array_column($lines, 'amount'));
        $this->assertSame(20.0, $this->sum($lines));
    }

    public function test_operator_discount_is_not_subtracted_twice(): void
    {
        // CRM: sayt 129 → 110 (unit_price artıq endirimli), orders.discount = 19
        $order = $this->order(['discount' => 19], [
            ['unit_price' => 110, 'list_price' => 129, 'quantity' => 1],
            ['unit_price' => 50, 'quantity' => 1],
        ]);
        $lines = (new PaymentItemsBuilder)->lines($order, 160);

        $this->assertSame(['110.00', '50.00'], array_column($lines, 'amount'));
    }

    public function test_mismatch_is_absorbed_by_last_item(): void
    {
        $order = $this->order(['delivery_fee' => 5], [['unit_price' => 100, 'quantity' => 1]]);
        $lines = (new PaymentItemsBuilder)->lines($order, 104);

        $this->assertSame(['99.00', '5.00'], array_column($lines, 'amount'));
    }
}
