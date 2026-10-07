<?php

namespace Tests\Feature\Bonus;

use App\Models\Customer\Customer;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** Bonus tarixçəsi: sonsuz scroll üçün AJAX cavabı ({html, next}) */
class BonusHistoryInfiniteScrollTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        Schema::create('customers', function (Blueprint $t) {
            $t->id(); $t->string('name')->nullable(); $t->string('surname')->nullable(); $t->string('mobile')->nullable(); $t->boolean('active')->default(true);
            $t->decimal('bonus_balance', 12, 2)->default(0); $t->rememberToken(); $t->timestamps();
        });
        Schema::create('customer_bonus_transactions', function (Blueprint $t) {
            $t->id(); $t->integer('customer_id'); $t->unsignedBigInteger('order_id')->nullable(); $t->string('type');
            $t->decimal('amount', 12, 2); $t->string('note')->nullable(); $t->timestamp('expires_at')->nullable();
            $t->timestamp('expired_at')->nullable(); $t->timestamp('reminded_at')->nullable(); $t->timestamps();
        });
        Schema::create('orders', fn (Blueprint $t) => [$t->id(), $t->integer('customer_id')->nullable(), $t->string('order_no')->nullable(), $t->timestamps()]);
    }

    public function test_next_pages_come_as_json_rows_until_the_end(): void
    {
        $customer = Customer::forceCreate(['name' => 'Aysel', 'mobile' => '994501234567']);
        foreach (range(1, 25) as $i) {
            $customer->bonusTransactions()->create(['type' => 'earn', 'amount' => $i, 'created_at' => now()->subMinutes($i)]);
        }

        $page2 = $this->actingAs($customer)->getJson(route('profile.bonus', ['page' => 2]), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()->json();
        $this->assertSame(5, substr_count($page2['html'], '<li '));
        $this->assertNull($page2['next']);
        $this->assertStringContainsString('+21.00', $page2['html']);   // ən köhnələr sonda

        $page1 = $this->actingAs($customer)->getJson(route('profile.bonus'), ['X-Requested-With' => 'XMLHttpRequest'])->json();
        $this->assertSame(20, substr_count($page1['html'], '<li '));
        $this->assertStringContainsString('page=2', $page1['next']);
    }
}
