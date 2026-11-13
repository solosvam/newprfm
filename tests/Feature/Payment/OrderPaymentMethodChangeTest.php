<?php

namespace Tests\Feature\Payment;

use App\Models\Order\Order;
use App\Services\OrderPaymentMethodChange;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OrderPaymentMethodChangeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'cache.default' => 'array']);
        DB::purge('sqlite');

        Schema::create('order_statuses', fn (Blueprint $t) => [$t->id(), $t->string('code')]);
        Schema::create('payment_methods', fn (Blueprint $t) => [$t->id(), $t->string('code'), $t->string('name_az')]);
        Schema::create('customers', fn (Blueprint $t) => [$t->id(), $t->string('name')->nullable(), $t->string('mobile')->nullable(), $t->timestamps()]);
        Schema::create('promo_codes', fn (Blueprint $t) => [$t->id(), $t->integer('used_count')->default(0), $t->timestamps()]);
        Schema::create('orders', fn (Blueprint $t) => [
            $t->id(), $t->integer('customer_id'), $t->string('order_no')->default('PS777'), $t->string('pay_token', 16)->nullable(),
            $t->timestamp('pay_token_expires_at')->nullable(), $t->unsignedBigInteger('order_status_id'), $t->unsignedBigInteger('payment_method_id'),
            $t->string('payment_status')->default('cod'), $t->decimal('total', 12, 2)->default(100), $t->integer('promo_code_id')->nullable(),
            $t->integer('birbank_installment_months')->nullable(), $t->timestamps(),
        ]);
        Schema::create('payments', fn (Blueprint $t) => [$t->id(), $t->integer('customer_id'), $t->unsignedBigInteger('order_id'), $t->string('provider'), $t->string('status'), $t->timestamps()]);
        Schema::create('money_movements', fn (Blueprint $t) => [$t->id(), $t->unsignedBigInteger('order_id')->nullable(), $t->string('kind'), $t->timestamps()]);
        Schema::create('order_status_logs', fn (Blueprint $t) => [$t->id(), $t->unsignedBigInteger('order_id'), $t->unsignedBigInteger('status_id')->nullable(), $t->text('note')->nullable(), $t->string('kind')->nullable(), $t->unsignedBigInteger('user_id')->nullable(), $t->timestamps()]);

        DB::table('order_statuses')->insert([['id' => 1, 'code' => 'courier_assigned'], ['id' => 2, 'code' => 'delivered'], ['id' => 3, 'code' => 'cancelled']]);
        DB::table('payment_methods')->insert([
            ['id' => 1, 'code' => 'cash', 'name_az' => 'Qapıda ödəniş'], ['id' => 3, 'code' => 'card_online', 'name_az' => 'Saytda onlayn ödəniş'],
            ['id' => 4, 'code' => 'installment', 'name_az' => 'Hissə-hissə ödəniş'],
        ]);
        DB::table('customers')->insert(['id' => 1, 'name' => 'Aysel', 'mobile' => '0501234567']);
        DB::table('promo_codes')->insert(['id' => 1, 'used_count' => 3]);
        DB::table('orders')->insert(['id' => 1, 'customer_id' => 1, 'order_status_id' => 1, 'payment_method_id' => 1, 'promo_code_id' => 1, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function change(string $code): Order
    {
        return app(OrderPaymentMethodChange::class)->change(Order::findOrFail(1), $code, 7);
    }

    public function test_cash_to_online_and_back(): void
    {
        $order = $this->change('card_online');
        $this->assertSame('card_online', $order->paymentMethod->code);
        $this->assertSame('pending', $order->payment_status);
        $this->assertTrue($order->payLinkAvailable());
        $this->assertSame(2, (int) DB::table('promo_codes')->value('used_count')); // bank təsdiqləyəndə yenidən artacaq
        $this->assertSame('Ödəniş üsulu: Qapıda ödəniş → Saytda onlayn ödəniş', DB::table('order_status_logs')->where('kind', 'payment_change')->value('note'));

        // Uğursuz bank cəhdi olub — geri qapıda ödənişə, sonra yenidən onlayna: müştəri yenə ödəyə bilir
        DB::table('payments')->insert(['customer_id' => 1, 'order_id' => 1, 'provider' => 'birbank', 'status' => 'failed']);
        DB::table('orders')->update(['pay_token' => 'abc', 'pay_token_expires_at' => now()->addDay()]);
        $order = $this->change('cash');
        $this->assertSame('cod', $order->payment_status);
        $this->assertNull($order->pay_token_expires_at); // köhnə link işləmir
        $this->assertSame(3, (int) DB::table('promo_codes')->value('used_count'));

        $order = $this->change('card_online');
        $this->assertSame('cancelled', $order->payment_status);
        $this->assertTrue($order->payLinkAvailable());
    }

    public function test_blocked_cases(): void
    {
        $service = app(OrderPaymentMethodChange::class);
        $order = fn () => Order::findOrFail(1);

        DB::table('payments')->insert(['customer_id' => 1, 'order_id' => 1, 'provider' => 'birbank', 'status' => 'pending']);
        DB::table('orders')->update(['payment_method_id' => 3, 'payment_status' => 'pending']);
        $this->assertStringContainsString('Bankda', $service->block($order()));
        DB::table('payments')->delete();
        $this->assertNull($service->block($order()));

        DB::table('orders')->update(['payment_status' => 'paid']);
        $this->assertSame('Sifariş artıq ödənilib.', $service->block($order()));

        DB::table('orders')->update(['payment_method_id' => 1, 'payment_status' => 'cod']);
        DB::table('money_movements')->insert(['order_id' => 1, 'kind' => 'customer_payment']);
        $this->assertSame('Müştərinin ödənişi artıq yazılıb.', $service->block($order()));
        DB::table('money_movements')->delete();

        DB::table('orders')->update(['order_status_id' => 2]);
        $this->assertSame('Sifariş artıq təhvil verilib.', $service->block($order()));
        DB::table('orders')->update(['order_status_id' => 3]);
        $this->assertSame('Sifariş ləğv edilib.', $service->block($order()));
        DB::table('orders')->update(['order_status_id' => 1, 'payment_method_id' => 4]);
        $this->assertNotNull($service->block($order()));

        $this->expectException(ValidationException::class);
        $this->change('cash');
    }
}
