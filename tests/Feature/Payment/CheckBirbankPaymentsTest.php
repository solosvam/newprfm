<?php

namespace Tests\Feature\Payment;

use App\Mail\OrderCreatedMail;
use App\Models\Payment\Payment;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CheckBirbankPaymentsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:',
            'services.birbank' => ['test_mode' => true, 'test_url' => 'https://bank.test/api', 'test_username' => 'u', 'test_password' => 'p'],
        ]);
        DB::purge('sqlite');
        Schema::create('customers', fn (Blueprint $t) => [$t->id(), $t->string('email')->nullable(), $t->timestamps()]);
        Schema::create('promo_codes', fn (Blueprint $t) => [$t->id(), $t->integer('used_count')->default(0), $t->timestamps()]);
        Schema::create('orders', fn (Blueprint $t) => [
            $t->id(), $t->integer('customer_id'), $t->string('order_no')->default('PS1'), $t->string('payment_status')->default('pending'),
            $t->unsignedBigInteger('promo_code_id')->nullable(), $t->decimal('total', 12, 2)->default(0), $t->timestamps(),
        ]);
        Schema::create('payments', fn (Blueprint $t) => [
            $t->id(), $t->integer('customer_id'), $t->unsignedBigInteger('order_id'), $t->string('provider'),
            $t->string('provider_order_id')->nullable(), $t->decimal('amount', 12, 2), $t->string('session_id')->nullable(),
            $t->string('card_pan')->nullable(), $t->string('response_text')->nullable(), $t->string('status'), $t->timestamps(),
        ]);
        DB::table('customers')->insert(['id' => 1, 'email' => 'musteri@example.com']);
        DB::table('promo_codes')->insert(['id' => 1, 'used_count' => 0]);
        Mail::fake();
    }

    /** Sifariş + gözləyən ödəniş; bank sifarişi verilən statusla cavab verir */
    private function pending(int $id, ?string $bankStatus, int $minutesAgo = 10, ?int $promo = null): void
    {
        DB::table('orders')->insert(['id' => $id, 'customer_id' => 1, 'total' => 100, 'promo_code_id' => $promo, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('payments')->insert([
            'id' => $id, 'customer_id' => 1, 'order_id' => $id, 'provider' => 'birbank', 'amount' => 100, 'status' => 'pending',
            'provider_order_id' => $bankStatus === null ? null : 'B'.$id,
            'created_at' => now()->subMinutes($minutesAgo), 'updated_at' => now()->subMinutes($minutesAgo),
        ]);
    }

    private function bank(array $statuses): void
    {
        Http::fake(function ($request) use ($statuses) {
            $id = basename(parse_url($request->url(), PHP_URL_PATH));

            return isset($statuses[$id])
                ? Http::response(['order' => ['id' => $id, 'status' => $statuses[$id], 'amount' => 100, 'currency' => 'AZN']])
                : Http::response([], 500);
        });
    }

    private function state(int $id): array
    {
        return [DB::table('payments')->where('id', $id)->value('status'), DB::table('orders')->where('id', $id)->value('payment_status')];
    }

    public function test_paid_payment_is_applied_without_customer_returning(): void
    {
        $this->pending(1, 'FullyPaid', promo: 1);
        $this->bank(['B1' => 'FullyPaid']);

        $this->artisan('payments:check-birbank')->expectsOutputToContain('ödənilib 1')->assertSuccessful();

        $this->assertSame(['paid', 'paid'], $this->state(1));
        $this->assertSame(1, (int) DB::table('promo_codes')->value('used_count'));
        Mail::assertQueued(OrderCreatedMail::class, 1);

        // Təkrar işləyəndə heç nə ikinci dəfə olmur
        $this->artisan('payments:check-birbank')->assertSuccessful();
        $this->assertSame(1, (int) DB::table('promo_codes')->value('used_count'));
        Mail::assertQueued(OrderCreatedMail::class, 1);
    }

    public function test_expired_declined_and_still_open_payments(): void
    {
        $this->pending(1, 'Expired');
        $this->pending(2, 'Declined');
        $this->pending(3, 'Rejected');
        $this->pending(4, 'Preparing');
        $this->bank(['B1' => 'Expired', 'B2' => 'Declined', 'B3' => 'Rejected', 'B4' => 'Preparing']);

        $this->artisan('payments:check-birbank')
            ->expectsOutputToContain('ödənilib 0, uğursuz 2, ləğv 1, hələ gözləyir 1, xəta 0')
            ->assertSuccessful();

        $this->assertSame(['cancelled', 'cancelled'], $this->state(1));
        $this->assertSame(['failed', 'failed'], $this->state(2));
        $this->assertSame(['failed', 'failed'], $this->state(3));
        $this->assertSame(['pending', 'pending'], $this->state(4)); // bankda hələ açıqdır — toxunulmur
        Mail::assertNothingQueued();
    }

    public function test_payment_without_bank_order_is_failed_and_bank_error_keeps_pending(): void
    {
        $this->pending(1, null);          // bankda sifariş yaranmayıb
        $this->pending(2, 'x');           // bank 500 qaytarır
        $this->pending(3, 'FullyPaid', minutesAgo: 0); // müştəri hələ ödəniş səhifəsindədir
        $this->bank(['B3' => 'FullyPaid']);

        $this->artisan('payments:check-birbank')
            ->expectsOutputToContain('Yoxlandı: 2 — ödənilib 0, uğursuz 1, ləğv 0, hələ gözləyir 0, xəta 1')
            ->assertSuccessful();

        $this->assertSame(['failed', 'failed'], $this->state(1));
        $this->assertSame(['pending', 'pending'], $this->state(2));
        $this->assertSame(['pending', 'pending'], $this->state(3));
    }

    public function test_paid_order_is_not_downgraded_by_an_older_failed_attempt(): void
    {
        $this->pending(1, 'Expired');
        DB::table('orders')->where('id', 1)->update(['payment_status' => 'paid']);
        $this->bank(['B1' => 'Expired']);

        $this->artisan('payments:check-birbank')->assertSuccessful();

        $this->assertSame(['cancelled', 'paid'], $this->state(1));
    }
}
