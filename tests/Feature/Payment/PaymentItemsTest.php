<?php

namespace Tests\Feature\Payment;

use App\Models\Order\Order;
use App\Models\Order\OrderItem;
use App\Models\Payment\Payment;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PaymentItemsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Developer bazasına toxunmuruq: yaddaşda sqlite, lazım olan sütunlarla
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        Schema::create('orders', function (Blueprint $t) {
            $t->id();
            $t->integer('customer_id')->nullable();
            $t->decimal('discount', 12, 2)->default(0);
            $t->decimal('delivery_fee', 12, 2)->default(0);
            $t->decimal('gift_wrap_fee', 12, 2)->default(0);
            $t->decimal('total', 12, 2)->default(0);
            $t->timestamps();
        });
        Schema::create('order_items', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('order_id');
            $t->decimal('unit_price', 12, 2);
            $t->decimal('list_price', 12, 2)->nullable();
            $t->integer('quantity');
            $t->decimal('total', 12, 2);
            $t->timestamps();
        });
        Schema::create('payments', function (Blueprint $t) {
            $t->id();
            $t->integer('customer_id');
            $t->unsignedBigInteger('order_id');
            $t->string('provider');
            $t->string('provider_order_id')->nullable();
            $t->decimal('amount', 12, 2);
            $t->string('status')->default('pending');
            $t->timestamps();
        });
        $migration = require database_path('migrations/2026_09_29_150000_create_payment_items_table.php');
        $migration->up();
    }

    public function test_creating_a_payment_stores_what_it_contains(): void
    {
        $order = Order::create(['customer_id' => 1, 'discount' => 10, 'delivery_fee' => 5, 'total' => 395]);
        OrderItem::create(['order_id' => $order->id, 'unit_price' => 150, 'quantity' => 2, 'total' => 300]);
        OrderItem::create(['order_id' => $order->id, 'unit_price' => 100, 'quantity' => 1, 'total' => 100]);

        $payment = Payment::create(['customer_id' => 1, 'order_id' => $order->id, 'provider' => 'birbank', 'amount' => 395, 'status' => 'pending']);

        $items = $payment->items()->orderBy('id')->get();
        $this->assertSame(['item', 'item', 'delivery'], $items->pluck('type')->all());
        $this->assertSame(['292.50', '97.50', '5.00'], $items->pluck('amount')->all());
        $this->assertSame(395.0, round((float) $items->sum('amount'), 2));
    }

    public function test_migration_backfills_existing_payments(): void
    {
        Schema::drop('payment_items');
        $order = Order::create(['customer_id' => 1, 'total' => 80]);
        OrderItem::create(['order_id' => $order->id, 'unit_price' => 40, 'quantity' => 2, 'total' => 80]);
        DB::table('payments')->insert(['customer_id' => 1, 'order_id' => $order->id, 'provider' => 'birbank', 'amount' => 80, 'status' => 'paid']);

        (require database_path('migrations/2026_09_29_150000_create_payment_items_table.php'))->up();

        $this->assertSame(1, DB::table('payment_items')->count());
        $this->assertEquals(80, DB::table('payment_items')->value('amount'));
    }
}
