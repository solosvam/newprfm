<?php

namespace Tests\Feature\Payment;

use App\Models\Order\Order;
use App\Services\Payment\BirbankPaymentSync;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ResumePendingPaymentTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:',
            'services.birbank' => ['test_mode' => true, 'test_url' => 'https://txpgtst.kapitalbank.az/api', 'test_username' => 'u', 'test_password' => 'p'],
        ]);
        DB::purge('sqlite');
        Schema::create('customers', fn (Blueprint $t) => [$t->id(), $t->string('email')->nullable(), $t->timestamps()]);
        Schema::create('order_statuses', fn (Blueprint $t) => [$t->id(), $t->string('code')]);
        Schema::create('payment_methods', fn (Blueprint $t) => [$t->id(), $t->string('code')]);
        Schema::create('orders', fn (Blueprint $t) => [
            $t->id(), $t->integer('customer_id'), $t->string('order_no')->default('PS1'), $t->string('payment_status')->default('pending'),
            $t->unsignedBigInteger('order_status_id')->default(1), $t->unsignedBigInteger('payment_method_id')->default(1),
            $t->unsignedBigInteger('promo_code_id')->nullable(), $t->decimal('total', 12, 2)->default(100), $t->timestamps(),
        ]);
        Schema::create('payments', fn (Blueprint $t) => [
            $t->id(), $t->integer('customer_id'), $t->unsignedBigInteger('order_id'), $t->string('provider'),
            $t->string('provider_order_id')->nullable(), $t->decimal('amount', 12, 2), $t->string('session_id')->nullable(),
            $t->string('hpp_url', 500)->nullable(), $t->string('card_pan')->nullable(), $t->string('response_text')->nullable(),
            $t->string('status'), $t->timestamps(),
        ]);
        DB::table('customers')->insert(['id' => 1]);
        DB::table('order_statuses')->insert(['id' => 1, 'code' => 'new']);
        DB::table('payment_methods')->insert(['id' => 1, 'code' => 'card_online']);
        DB::table('orders')->insert(['id' => 1, 'customer_id' => 1, 'created_at' => now(), 'updated_at' => now()]);
        Mail::fake();
    }

    private function pending(array $overrides = []): void
    {
        DB::table('payments')->insert($overrides + [
            'customer_id' => 1, 'order_id' => 1, 'provider' => 'birbank', 'amount' => 100, 'status' => 'pending',
            'provider_order_id' => 'B1', 'session_id' => 'secret', 'hpp_url' => 'https://txpgtst.kapitalbank.az/flex',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function bank(?string $status): void
    {
        Http::fake(fn () => $status === null
            ? Http::response([], 500)
            : Http::response(['order' => ['id' => 'B1', 'status' => $status, 'amount' => 100, 'currency' => 'AZN']]));
    }

    private function resume(): array
    {
        return app(BirbankPaymentSync::class)->resumePending(Order::findOrFail(1));
    }

    /** Bank taksiti təsvirdəki "TAKSIT=N" ilə tanıyır; adi kart ödənişində təsvir sifariş nömrəsidir */
    public function test_installment_months_are_sent_to_the_bank_in_the_description(): void
    {
        DB::table('payment_methods')->insert(['id' => 2, 'code' => 'birbank_installment']);
        // Ödəniş yarananda sətirləri də yazılır (Payment::booted) — bu test üçün lazım olan cədvəllər
        Schema::create('order_items', fn (Blueprint $t) => [$t->id(), $t->unsignedBigInteger('order_id'), $t->integer('quantity')->default(1),
            $t->integer('cancelled_quantity')->default(0), $t->decimal('unit_price', 12, 2)->default(0), $t->decimal('list_price', 12, 2)->nullable(), $t->timestamps()]);
        Schema::create('payment_items', fn (Blueprint $t) => [$t->id(), $t->unsignedBigInteger('payment_id'), $t->unsignedBigInteger('order_item_id')->nullable(),
            $t->string('type'), $t->integer('quantity')->default(1), $t->decimal('unit_price', 12, 2), $t->decimal('amount', 12, 2), $t->timestamps()]);
        Http::fake(fn () => Http::response(['order' => ['id' => 'B7', 'password' => 'pw', 'hppUrl' => 'https://txpgtst.kapitalbank.az/flex']]));
        $birbank = app(\App\Services\Payment\Birbank::class);

        $card = $birbank->createOrder(Order::findOrFail(1));
        DB::table('orders')->where('id', 1)->update(['payment_method_id' => 2]);
        $installment = $birbank->createOrder(Order::findOrFail(1), 'az', 6);

        $sent = collect(Http::recorded())->map(fn ($pair) => $pair[0]['order']['description'])->all();
        $this->assertSame(['PS1', 'TAKSIT=6'], $sent);
        $this->assertSame('https://txpgtst.kapitalbank.az/flex?id=B7&password=pw', $installment['url']);
        $this->assertSame('https://txpgtst.kapitalbank.az/flex', DB::table('payments')->where('id', $card['payment_id'])->value('hpp_url'));
    }

    public function test_no_pending_payment(): void
    {
        $this->assertSame(['state' => 'none'], $this->resume());
    }

    public function test_open_bank_order_is_resumed_on_the_same_bank_page(): void
    {
        $this->pending();
        $this->bank('Preparing');

        $result = $this->resume();

        $this->assertSame('resume', $result['state']);
        $this->assertSame('https://txpgtst.kapitalbank.az/flex?id=B1&password=secret', $result['url']);
        $this->assertSame(1, DB::table('payments')->count()); // yeni ödəniş yaranmır
        $this->assertSame('pending', DB::table('payments')->value('status'));
    }

    public function test_expired_attempt_releases_the_order_for_a_new_payment(): void
    {
        $this->pending();
        $this->bank('Expired');

        $this->assertSame(['state' => 'released'], $this->resume());
        $this->assertSame('cancelled', DB::table('payments')->value('status'));
        $this->assertSame('cancelled', DB::table('orders')->value('payment_status'));
        $this->assertTrue(Order::with('paymentMethod', 'status')->findOrFail(1)->canStartOnlinePayment());
    }

    public function test_paid_at_bank_marks_order_paid(): void
    {
        $this->pending();
        $this->bank('FullyPaid');

        $this->assertSame(['state' => 'paid'], $this->resume());
        $this->assertSame('paid', DB::table('orders')->value('payment_status'));
    }

    public function test_blocked_when_bank_is_down_processing_or_page_url_is_unknown_or_foreign(): void
    {
        $this->pending();
        $this->bank(null);
        $this->assertSame(['state' => 'blocked'], $this->resume());

        $this->bank('Authorized'); // əməliyyat icradadır — eyni səhifəyə qaytarmırıq
        $this->assertSame(['state' => 'blocked'], $this->resume());

        $this->bank('Preparing');
        DB::table('payments')->update(['hpp_url' => null]); // köhnə ödəniş: ünvan saxlanmayıb
        $this->assertSame(['state' => 'blocked'], $this->resume());

        DB::table('payments')->update(['hpp_url' => 'https://evil.example/flex']); // bank hostu deyil
        $this->assertSame(['state' => 'blocked'], $this->resume());
    }

    public function test_attempt_without_bank_order_is_released(): void
    {
        $this->pending(['provider_order_id' => null, 'session_id' => null, 'hpp_url' => null]);
        Http::fake();

        $this->assertSame(['state' => 'released'], $this->resume());
        $this->assertSame('failed', DB::table('payments')->value('status'));
        Http::assertNothingSent();
    }

    public function test_pay_link_is_available_while_an_attempt_is_pending(): void
    {
        $this->pending();
        $order = Order::with('paymentMethod', 'status')->findOrFail(1);

        $this->assertFalse($order->canStartOnlinePayment());
        $this->assertTrue($order->payLinkAvailable());

        DB::table('orders')->update(['payment_status' => 'paid']);
        $this->assertFalse(Order::with('paymentMethod', 'status')->findOrFail(1)->payLinkAvailable());
    }
}
