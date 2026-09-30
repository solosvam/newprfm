<?php

namespace Tests\Feature\Order;

use App\Models\Order\Order;
use App\Models\Order\OrderItemCancellation;
use App\Models\Payment\Payment;
use App\Models\Payment\PaymentOperation;
use App\Models\Payment\PaymentRefundItem;
use App\Services\OrderItemCancellationService;
use App\Services\OrderRefundService;
use App\Services\Payment\Birbank;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\OrderStatusFixtures;
use Tests\TestCase;

class OrderRefundTest extends TestCase
{
    use OrderStatusFixtures;

    /** Saxta bank: 'ok' — uğurlu, 'timeout' — əməliyyat yaranır amma cavab yoxdur, 'reject' — bank çağırılmır */
    public static string $mode = 'ok';
    public static int $calls = 0;

    protected function setUp(): void
    {
        parent::setUp();
        self::$mode = 'ok';
        self::$calls = 0;
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
        foreach ([
            '2026_09_27_210000_create_payment_operations_and_saved_cards.php',
            '2026_09_29_150000_create_procurement_tables.php',
            '2026_09_29_150000_create_payment_items_table.php',
            '2026_09_29_160000_create_order_item_cancellations_table.php',
            '2026_09_29_170000_create_payment_refund_items_table.php',
            '2026_09_29_190000_add_supply_flow_to_order_item_allocations.php',
        ] as $file) {
            (require database_path('migrations/'.$file))->up();
        }
        $this->orderStatusFixtures();
        DB::table('payment_methods')->insert(['id' => 1, 'code' => 'card_online']);
        DB::table('customers')->insert(['id' => 1, 'name' => 'Test']);
        DB::table('products')->insert([['id' => 1, 'name' => 'Rose Oud'], ['id' => 2, 'name' => 'Gold Knight']]);

        $this->app->instance(Birbank::class, new class extends Birbank {
            public function refund(Payment $payment, string $amount, ?string $key = null): PaymentOperation
            {
                OrderRefundTest::$calls++;
                if (OrderRefundTest::$mode === 'reject') {
                    throw new \RuntimeException('Payment is not confirmed by the bank.');
                }
                $op = PaymentOperation::firstOrCreate(['idempotency_key' => $key], [
                    'payment_id' => $payment->id, 'type' => 'refund', 'amount' => $amount, 'status' => 'pending',
                ]);
                if (OrderRefundTest::$mode === 'timeout') {
                    throw new \RuntimeException('cURL timeout');
                }
                $op->update(['status' => 'succeeded']);
                return $op->refresh();
            }
        });
    }

    /** Kartla ödənilmiş 395 AZN sifariş, Rose Oud-dan 1 ədəd ləğv (146.25 qaytarılmalıdır) */
    private function cancelled(): OrderItemCancellation
    {
        $order = Order::create(['customer_id' => 1, 'order_status_id' => 1, 'payment_method_id' => 1, 'payment_status' => 'paid',
            'subtotal' => 400, 'discount' => 10, 'delivery_fee' => 5, 'total' => 395]);
        $order->items()->create(['product_id' => 1, 'unit_price' => 150, 'quantity' => 2, 'total' => 300]);
        $order->items()->create(['product_id' => 2, 'unit_price' => 100, 'quantity' => 1, 'total' => 100]);
        Payment::create(['customer_id' => 1, 'order_id' => $order->id, 'provider' => 'birbank', 'provider_order_id' => 'B1', 'amount' => 395, 'status' => Payment::PAID]);
        $order = $order->fresh('items');

        return app(OrderItemCancellationService::class)->cancel($order, $order->items[0], 1, 'not_in_stock', null, 7);
    }

    public function test_refund_goes_to_card_and_is_linked_to_the_product_line(): void
    {
        $c = $this->cancelled();
        $this->assertSame(OrderItemCancellation::REFUND_PENDING, $c->refund_status);

        $c = app(OrderRefundService::class)->refundCancellation($c);

        $this->assertSame(OrderItemCancellation::REFUND_DONE, $c->refund_status);
        $this->assertNotNull($c->refunded_at);
        $link = PaymentRefundItem::first();
        $this->assertEquals(146.25, $link->amount);
        $this->assertSame($c->order_item_id, $link->paymentItem->order_item_id);
        $this->assertEquals(146.25, $link->paymentItem->refundedAmount());
        $this->assertEquals(146.25, PaymentOperation::first()->amount);
    }

    public function test_second_click_does_not_refund_twice(): void
    {
        $c = app(OrderRefundService::class)->refundCancellation($this->cancelled());
        try {
            app(OrderRefundService::class)->refundCancellation($c);
            $this->fail('İkinci qaytarma qəbul edilməməli idi');
        } catch (ValidationException) {
        }
        $this->assertSame(1, self::$calls);
        $this->assertSame(1, PaymentOperation::count());
    }

    public function test_timeout_leaves_processing_and_blocks_retry(): void
    {
        self::$mode = 'timeout';
        $c = $this->cancelled();
        try {
            app(OrderRefundService::class)->refundCancellation($c);
            $this->fail('Xəta gözlənilirdi');
        } catch (ValidationException) {
        }
        $c->refresh();
        $this->assertSame(OrderItemCancellation::REFUND_PROCESSING, $c->refund_status);
        $this->assertSame(1, PaymentRefundItem::count()); // nəticə bəlli olmasa da, bağlantı qalır

        self::$mode = 'ok';
        $this->expectException(ValidationException::class);
        app(OrderRefundService::class)->refundCancellation($c);
    }

    public function test_when_bank_was_not_called_it_can_be_retried(): void
    {
        self::$mode = 'reject';
        $c = $this->cancelled();
        try {
            app(OrderRefundService::class)->refundCancellation($c);
        } catch (ValidationException) {
        }
        $this->assertSame(OrderItemCancellation::REFUND_PENDING, $c->fresh()->refund_status);

        self::$mode = 'ok';
        $this->assertSame(OrderItemCancellation::REFUND_DONE, app(OrderRefundService::class)->refundCancellation($c->fresh())->refund_status);
    }

    public function test_refunds_partial_renders(): void
    {
        $c = $this->cancelled();
        $order = Order::with('itemCancellations.orderItem.product')->find($c->order_id);
        $html = view('backend.crm.partials.order-refunds', ['order' => $order, 'customer' => (object) ['id' => 1]])->render();
        $this->assertStringContainsString('Qaytarılmalıdır', $html);
        $this->assertStringContainsString('146.25', $html);
    }
}
