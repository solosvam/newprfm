<?php

namespace Tests\Feature\Order;

use App\Models\Order\Order;
use App\Models\Order\OrderItemCancellation;
use App\Models\Procurement\OrderItemAllocation;
use App\Models\Procurement\Warehouse;
use App\Models\Procurement\WarehouseRequestItem;
use App\Services\OrderItemCancellationService;
use App\Services\Payment\PaymentItemsBuilder;
use App\Services\ProcurementService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\OrderStatusFixtures;
use Tests\TestCase;

class OrderItemCancellationTest extends TestCase
{
    use OrderStatusFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        // Developer bazasına toxunmuruq: yaddaşda sqlite
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        Schema::create('order_statuses', function (Blueprint $t) { $t->id(); $t->string('code'); $t->string('name_az')->nullable(); });
        Schema::create('payment_methods', function (Blueprint $t) { $t->id(); $t->string('code'); });
        Schema::create('customers', function (Blueprint $t) { $t->id(); $t->string('name')->nullable(); $t->decimal('bonus_balance', 12, 2)->default(0); $t->timestamps(); });
        Schema::create('customer_bonus_transactions', function (Blueprint $t) {
            $t->id(); $t->integer('customer_id'); $t->unsignedBigInteger('order_id')->nullable(); $t->string('type');
            $t->decimal('amount', 12, 2); $t->string('note')->nullable(); $t->unsignedBigInteger('created_by')->nullable(); $t->timestamps();
        });
        Schema::create('settings', function (Blueprint $t) { $t->id(); $t->string('key'); $t->text('value')->nullable(); $t->timestamps(); });
        Schema::create('orders', function (Blueprint $t) {
            $t->id(); $t->integer('customer_id'); $t->string('order_no')->default('PS1'); $t->unsignedBigInteger('order_status_id');
            $t->unsignedBigInteger('payment_method_id'); $t->string('payment_status')->default('pending');
            foreach (['subtotal', 'discount', 'delivery_fee', 'gift_wrap_fee', 'total', 'bonus_earned'] as $c) $t->decimal($c, 12, 2)->default(0);
            $t->timestamps();
        });
        Schema::create('order_items', function (Blueprint $t) {
            $t->id(); $t->foreignId('order_id')->constrained(); $t->integer('product_id')->nullable(); $t->integer('product_variant_id')->nullable();
            $t->decimal('unit_price', 12, 2); $t->decimal('list_price', 12, 2)->nullable(); $t->integer('quantity'); $t->decimal('total', 12, 2); $t->timestamps();
        });
        Schema::create('products', function (Blueprint $t) { $t->id(); $t->string('name'); });
        Schema::create('payments', function (Blueprint $t) {
            $t->id(); $t->integer('customer_id'); $t->unsignedBigInteger('order_id'); $t->string('provider');
            $t->string('provider_order_id')->nullable(); $t->decimal('amount', 12, 2); $t->string('status'); $t->timestamps();
        });
        (require database_path('migrations/2026_09_29_150000_create_procurement_tables.php'))->up();
        (require database_path('migrations/2026_09_29_160000_create_order_item_cancellations_table.php'))->up();
        (require database_path('migrations/2026_09_29_190000_add_supply_flow_to_order_item_allocations.php'))->up();

        DB::table('order_statuses')->insert(['id' => 2, 'code' => 'courier']);
        $this->orderStatusFixtures();
        DB::table('payment_methods')->insert([['id' => 1, 'code' => 'card_online'], ['id' => 2, 'code' => 'cash'], ['id' => 3, 'code' => 'bonus_balance'], ['id' => 4, 'code' => 'installment']]);
        DB::table('customers')->insert(['id' => 1, 'name' => 'Test', 'bonus_balance' => 100]);
        DB::table('settings')->insert(['key' => 'order_bonus_percent', 'value' => '5']);
        DB::table('products')->insert([['id' => 1, 'name' => 'Rose Oud'], ['id' => 2, 'name' => 'Gold Knight']]);
    }

    /** 150×2 + 100×1, promo 10, çatdırılma 5 → yekun 395 */
    private function order(array $attrs = []): Order
    {
        $order = Order::create($attrs + ['customer_id' => 1, 'order_status_id' => 1, 'payment_method_id' => 1, 'payment_status' => 'paid',
            'subtotal' => 400, 'discount' => 10, 'delivery_fee' => 5, 'total' => 395, 'bonus_earned' => 19.50]);
        $order->items()->create(['product_id' => 1, 'unit_price' => 150, 'quantity' => 2, 'total' => 300]);
        $order->items()->create(['product_id' => 2, 'unit_price' => 100, 'quantity' => 1, 'total' => 100]);

        return $order->fresh('items');
    }

    private function cancel(Order $order, int $itemIndex, int $quantity)
    {
        return app(OrderItemCancellationService::class)->cancel($order, $order->items[$itemIndex], $quantity, 'not_in_stock', null, 7);
    }

    public function test_partial_cancel_updates_totals_bonus_and_marks_refund(): void
    {
        $order = $this->order();
        $c = $this->cancel($order, 0, 1);

        // 150 − promo payının yarısı (7.50 / 2 = 3.75)
        $this->assertEquals(146.25, $c->amount);
        $this->assertSame(OrderItemCancellation::REFUND_PENDING, $c->refund_status);
        $order->refresh();
        $this->assertEquals(248.75, $order->total);
        $this->assertEquals(250, $order->subtotal);
        $this->assertEquals(6.25, $order->discount);
        // Bonus: 19.50 → 5% × (250 − 6.25) = 12.19 → 7.31 geri alınır
        $this->assertEquals(7.31, $c->bonus_adjustment);
        $this->assertEquals(12.19, $order->bonus_earned);
        $this->assertEquals(92.69, DB::table('customers')->value('bonus_balance'));
        $this->assertSame(1, DB::table('customer_bonus_transactions')->where('type', 'adjustment')->count());

        $item = $order->items()->first();
        $this->assertSame(1, (int) $item->cancelled_quantity);
        $this->assertSame(2, (int) $item->quantity); // tarixçə üçün qalır
        $this->assertEquals(150, $item->total);

        // Yeni ödəniş (məs. link) yenilənmiş tərkiblə eyni məbləği verir
        $lines = app(PaymentItemsBuilder::class)->lines($order->fresh('items'), (float) $order->total);
        $this->assertSame(['146.25', '97.50', '5.00'], array_column($lines, 'amount'));
    }

    public function test_cash_order_has_no_refund_and_unpaid_order_has_no_bonus_change(): void
    {
        $order = $this->order(['payment_method_id' => 2, 'payment_status' => 'cod', 'bonus_earned' => 0]);
        $c = $this->cancel($order, 1, 1);

        $this->assertNull($c->refund_status);
        $this->assertEquals(0, $c->bonus_adjustment);
        $this->assertEquals(100, DB::table('customers')->value('bonus_balance'));
        $this->assertEquals(297.50, $order->fresh()->total); // 395 − (100 − 2.50)
    }

    public function test_bonus_paid_order_returns_amount_to_bonus_balance(): void
    {
        $order = $this->order(['payment_method_id' => 3, 'bonus_earned' => 0]);
        $c = $this->cancel($order, 1, 1);

        $this->assertSame(OrderItemCancellation::REFUND_BONUS, $c->refund_status);
        $this->assertEquals(197.50, DB::table('customers')->value('bonus_balance'));
    }

    public function test_excess_warehouse_selections_are_closed_newest_first(): void
    {
        $order = $this->order(['payment_status' => 'pending', 'bonus_earned' => 0]);
        $procurement = app(ProcurementService::class);
        $procurement->createRequests($order, [Warehouse::create(['name_az' => 'A'])->id], [$order->items[0]->id], 7);
        $offer = $procurement->recordOffer($order, WarehouseRequestItem::first()->id, ['available_quantity' => 2, 'unit_cost' => '50', 'source' => 'phone'], 7);
        $first = $procurement->allocate($order, $offer->id, 1, 7);
        $second = $procurement->allocate($order, $offer->id, 1, 7);

        $this->cancel($order->fresh('items'), 0, 1);

        $this->assertSame('selected', $first->fresh()->status);
        $this->assertSame('cancelled', $second->fresh()->status);
        $this->assertSame(2, OrderItemAllocation::count()); // silinmir
    }

    public function test_cannot_cancel_more_than_remaining_or_every_unit(): void
    {
        $order = $this->order();
        $this->cancel($order, 1, 1);
        $this->expectException(ValidationException::class);
        $this->cancel($order->fresh('items'), 0, 2); // sifarişdə heç nə qalmazdı
    }

    public function test_blocked_after_courier_for_credit_and_with_pending_bank_payment(): void
    {
        $service = app(OrderItemCancellationService::class);
        $this->assertNotNull($service->blockReason($this->order(['order_status_id' => 2])));
        $this->assertNotNull($service->blockReason($this->order(['payment_method_id' => 4])));

        $order = $this->order(['payment_status' => 'pending']);
        DB::table('payments')->insert(['customer_id' => 1, 'order_id' => $order->id, 'provider' => 'birbank', 'amount' => 395, 'status' => 'pending']);
        $this->assertNotNull($service->blockReason($order->fresh()));
        $this->assertNull($service->blockReason($this->order()));
    }
}
