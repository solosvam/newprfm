<?php

namespace Tests\Feature\Bonus;

use App\Models\Customer\Customer;
use App\Services\BonusService;
use App\Services\SmsService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** Bonus müddəti bitməzdən 3 gün əvvəl SMS: yalnız yanacaq qalıq, hər paket üçün bir dəfə */
class BonusExpiryReminderTest extends TestCase
{
    private array $sent = [];
    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        // Developer bazasına toxunmuruq: yaddaşda sqlite
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        Schema::create('customers', function (Blueprint $t) {
            $t->id(); $t->string('name')->nullable(); $t->string('mobile')->nullable(); $t->boolean('active')->default(true);
            $t->decimal('bonus_balance', 12, 2)->default(0); $t->timestamps();
        });
        Schema::create('customer_bonus_transactions', function (Blueprint $t) {
            $t->id(); $t->integer('customer_id'); $t->unsignedBigInteger('order_id')->nullable(); $t->string('type');
            $t->decimal('amount', 12, 2); $t->string('note')->nullable(); $t->timestamp('expires_at')->nullable();
            $t->timestamp('expired_at')->nullable(); $t->timestamp('reminded_at')->nullable(); $t->timestamps();
        });
        Schema::create('sms_templates', function (Blueprint $t) { $t->id(); $t->string('code'); $t->string('name'); $t->text('template'); $t->boolean('active'); });
        DB::table('sms_templates')->insert(['code' => 'bonus_expiring', 'name' => 'x', 'template' => '{name}: {amount} AZN {date}', 'active' => 1]);
        $this->customer = Customer::forceCreate(['name' => 'Aysel', 'mobile' => '994501234567']);
        $this->travelTo(now()->setDate(2026, 10, 7)->setTime(11, 0));
    }

    private function lot(float $amount, ?string $expires, string $type = 'earn'): void
    {
        $this->customer->bonusTransactions()->create(['type' => $type, 'amount' => $amount, 'expires_at' => $expires]);
    }

    private function remind(): int
    {
        $sms = new class($this->sent) extends SmsService {
            public function __construct(private array &$log) {}
            public function send(string $number, string $message): ?string { $this->log[] = $message; return 'ok'; }
        };

        return app(BonusService::class)->remindExpiring($sms);
    }

    public function test_reminds_once_with_amount_that_will_actually_expire(): void
    {
        // 20 (10.10-da bitir) + 30 (müddətsiz), 15 xərclənib → balans 35: xərc əvvəl 10.10-dakı paketdən → yanacaq 5
        $this->lot(20, '2026-10-10 11:00:00');
        $this->lot(30, null, 'register');
        $this->customer->update(['bonus_balance' => 35]);

        $this->assertSame(1, $this->remind());
        $this->assertSame(['Aysel: 5.00 AZN 10.10.2026'], $this->sent);
        $this->assertSame(0, $this->remind(), 'ikinci dəfə göndərilmir');
    }

    public function test_no_sms_when_nothing_will_expire_or_too_early(): void
    {
        $this->lot(20, '2026-10-09 11:00:00');
        $this->lot(10, '2026-10-20 11:00:00'); // 3 gündən sonra — hələ tez
        $this->customer->update(['bonus_balance' => 10]); // 20 xərclənib → 09.10-dakı paketdən qalıq yoxdur

        $this->assertSame(0, $this->remind());
        $this->assertSame([], $this->sent);
    }

    public function test_expire_due_removes_only_unspent_part(): void
    {
        // BonusService şərhindəki nümunə: A 20 (06.10), B 20 (15.10), 20 xərclənib, balans 20 → A-dan heç nə silinmir
        $this->lot(20, '2026-10-06 11:00:00');
        $this->lot(20, '2026-10-15 11:00:00');
        $this->customer->update(['bonus_balance' => 20]);
        app(BonusService::class)->expireDue();
        $this->assertEquals(20, $this->customer->fresh()->bonus_balance);

        // xərclənməyibsə — bitən paket tam silinir
        $other = Customer::forceCreate(['name' => 'B', 'bonus_balance' => 25]);
        $other->bonusTransactions()->create(['type' => 'earn', 'amount' => 15, 'expires_at' => '2026-10-06 11:00:00']);
        $other->bonusTransactions()->create(['type' => 'register', 'amount' => 10]);
        app(BonusService::class)->expireDue();
        $this->assertEquals(10, $other->fresh()->bonus_balance);
        $this->assertSame(-15.0, (float) $other->bonusTransactions()->where('type', 'expire')->value('amount'));
    }
}
