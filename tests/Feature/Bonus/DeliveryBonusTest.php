<?php

namespace Tests\Feature\Bonus;

use App\Models\Customer\Customer;
use App\Models\Order\Order;
use App\Services\BonusService;
use App\Services\OrderStatusService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\OrderStatusFixtures;
use Tests\TestCase;

/** Sifariş bonusu yalnız "Təhvil verildi"-də yazılır; bonus balansı və hissə-hissə ödənişli sifarişlər qazanmır */
class DeliveryBonusTest extends TestCase
{
    use OrderStatusFixtures;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        // Developer bazasına toxunmuruq: yaddaşda sqlite
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        Schema::create('settings', function (Blueprint $t) { $t->id(); $t->string('key'); $t->text('value')->nullable(); $t->timestamps(); });
        Schema::create('customers', function (Blueprint $t) { $t->id(); $t->string('name')->nullable(); $t->decimal('bonus_balance', 12, 2)->default(0); $t->timestamps(); });
        Schema::create('customer_bonus_transactions', function (Blueprint $t) {
            $t->id(); $t->integer('customer_id'); $t->unsignedBigInteger('order_id')->nullable(); $t->string('type');
            $t->decimal('amount', 12, 2); $t->string('note')->nullable(); $t->timestamp('expires_at')->nullable(); $t->timestamp('expired_at')->nullable(); $t->timestamps();
        });
        Schema::create('order_statuses', function (Blueprint $t) { $t->id(); $t->string('code'); });
        Schema::create('payment_methods', function (Blueprint $t) { $t->id(); $t->string('code'); });
        Schema::create('orders', function (Blueprint $t) {
            $t->id(); $t->integer('customer_id'); $t->string('order_no')->default('PS1'); $t->unsignedBigInteger('order_status_id');
            $t->unsignedBigInteger('payment_method_id')->nullable();
            foreach (['subtotal', 'discount', 'referral_discount', 'total', 'bonus_earned'] as $c) $t->decimal($c, 12, 2)->default(0);
            $t->timestamps();
        });
        $this->orderStatusFixtures();
        DB::table('payment_methods')->insert([['id' => 1, 'code' => 'cash'], ['id' => 2, 'code' => 'card_online'], ['id' => 3, 'code' => 'bonus_balance'], ['id' => 4, 'code' => 'installment']]);
        DB::table('settings')->insert(['key' => 'order_bonus_percent', 'value' => '5']);
        $this->customer = Customer::forceCreate(['name' => 'Test']);
    }

    private function order(int $method, array $attributes = []): Order
    {
        return Order::forceCreate($attributes + [
            'customer_id' => $this->customer->id, 'order_status_id' => 11, 'payment_method_id' => $method,
            'subtotal' => 200, 'discount' => 20, 'total' => 180,
        ]);
    }

    private function deliver(Order $order): void
    {
        app(OrderStatusService::class)->set($order, 'delivered', null);
    }

    public function test_cash_and_card_orders_earn_only_on_delivery(): void
    {
        $cash = $this->order(1);
        $card = $this->order(2);
        $this->assertEquals(9, app(BonusService::class)->pendingFor($cash), '(200 − 20) × 5%');
        $this->assertEquals(0, $this->customer->fresh()->bonus_balance, 'sifariş anında bonus yoxdur');

        $this->deliver($cash);
        $this->deliver($card);
        $this->assertEquals(18, $this->customer->fresh()->bonus_balance);
        $this->assertEquals(9, $cash->fresh()->bonus_earned);
        $this->assertEquals(0, app(BonusService::class)->pendingFor($cash->fresh()->load('status')), 'yazılıb — gözləyən yoxdur');
        $this->assertSame(2, DB::table('customer_bonus_transactions')->where('type', 'earn')->count());
    }

    public function test_bonus_balance_and_installment_orders_earn_nothing(): void
    {
        foreach ([3, 4] as $method) {
            $order = $this->order($method);
            $this->assertEquals(0, app(BonusService::class)->pendingFor($order));
            $this->deliver($order);
        }
        $this->assertEquals(0, $this->customer->fresh()->bonus_balance);
    }

    public function test_order_earned_under_old_rule_is_not_credited_again(): void
    {
        // köhnə qayda: qapıda ödənişdə bonus sifariş anında yazılıb
        $order = $this->order(1, ['bonus_earned' => 9]);
        $this->customer->update(['bonus_balance' => 9]);
        $this->deliver($order);
        $this->assertEquals(9, $this->customer->fresh()->bonus_balance);
    }

    public function test_cancelled_order_has_no_pending_bonus(): void
    {
        $order = $this->order(1);
        app(OrderStatusService::class)->set($order, 'cancelled', null);
        $this->assertEquals(0, app(BonusService::class)->pendingFor($order->fresh()->load('status')));
    }
}
