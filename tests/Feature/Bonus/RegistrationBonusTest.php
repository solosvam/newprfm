<?php

namespace Tests\Feature\Bonus;

use App\Models\Customer\Customer;
use App\Services\BonusService;
use App\Services\RegistrationOtpService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RegistrationBonusTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Developer bazasına toxunmuruq: yaddaşda sqlite
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        Schema::create('customers', function (Blueprint $t) {
            $t->id(); $t->string('name')->nullable(); $t->string('mobile')->nullable(); $t->boolean('active')->default(false);
            $t->decimal('bonus_balance', 12, 2)->default(0); $t->string('registration_otp_hash')->nullable();
            $t->timestamp('registration_otp_expires_at')->nullable(); $t->timestamps();
        });
        Schema::create('customer_bonus_transactions', function (Blueprint $t) {
            $t->id(); $t->integer('customer_id'); $t->unsignedBigInteger('order_id')->nullable(); $t->string('type');
            $t->decimal('amount', 12, 2); $t->string('note')->nullable(); $t->unsignedBigInteger('created_by')->nullable(); $t->timestamps();
        });
        Schema::create('settings', function (Blueprint $t) { $t->id(); $t->string('key'); $t->text('value')->nullable(); $t->timestamps(); });
        DB::table('settings')->insert([['key' => 'registration_bonus_enabled', 'value' => '1'], ['key' => 'registration_bonus_amount', 'value' => '10']]);
    }

    private function customer(): Customer
    {
        return Customer::forceCreate(['name' => 'Test', 'mobile' => '0501112233', 'active' => false,
            'registration_otp_hash' => Hash::make('123456'), 'registration_otp_expires_at' => now()->addDay()]);
    }

    public function test_site_registration_gets_bonus_once_on_otp_verification(): void
    {
        $customer = $this->customer();
        $this->assertTrue(app(RegistrationOtpService::class)->verify($customer, '123456'));

        $this->assertEquals(10, $customer->fresh()->bonus_balance);
        $this->assertSame('register', DB::table('customer_bonus_transactions')->value('type'));

        // Təkrar çağırış (məs. kod yenidən təsdiqlənsə) — ikinci bonus yoxdur
        $this->assertEquals(0, app(BonusService::class)->grantRegistration($customer->fresh()));
        $this->assertEquals(10, $customer->fresh()->bonus_balance);
    }

    public function test_no_bonus_when_setting_is_off(): void
    {
        DB::table('settings')->where('key', 'registration_bonus_enabled')->update(['value' => '0']);
        $customer = $this->customer();
        app(RegistrationOtpService::class)->verify($customer, '123456');

        $this->assertEquals(0, $customer->fresh()->bonus_balance);
        $this->assertSame(0, DB::table('customer_bonus_transactions')->count());
    }

    public function test_old_easy_order_bonus_is_retyped_and_not_granted_again(): void
    {
        $customer = $this->customer();
        DB::table('customer_bonus_transactions')->insert(['customer_id' => $customer->id, 'type' => 'earn', 'amount' => 10, 'note' => 'Qeydiyyat bonusu']);
        (require database_path('migrations/2026_09_29_230000_retype_registration_bonus_transactions.php'))->up();

        $this->assertSame('register', DB::table('customer_bonus_transactions')->value('type'));
        $this->assertEquals(0, app(BonusService::class)->grantRegistration($customer));
    }
}
