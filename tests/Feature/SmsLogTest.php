<?php

namespace Tests\Feature;

use App\Models\SmsLog;
use App\Services\SmsService;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\ProcurementSchema;
use Tests\TestCase;

class SmsLogTest extends TestCase
{
    use ProcurementSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->procurementSchema();
        (require database_path('migrations/2026_10_10_180000_add_delivery_status_to_sms_logs.php'))->up();
        config(['services.parfumshop_sms.login' => 'login', 'services.parfumshop_sms.password' => 'secret']);
    }

    public function test_every_send_is_written_to_the_journal_with_secrets_hidden(): void
    {
        Http::fake(['apps.lsim.az/*' => Http::response(['obj' => 777, 'errorCode' => 0])]);

        $id = SmsLog::deliver(app(SmsService::class), '0501234567', 'Tesdiq kodunuz: 123456', 'otp', null, null, ['123456']);

        $this->assertSame('777', $id);
        $log = SmsLog::sole();
        $this->assertSame([SmsLog::SENT, '777', 'otp', 'Tesdiq kodunuz: ***'], [$log->status, $log->provider_id, $log->context, $log->message]);
    }

    public function test_a_failed_send_is_logged_and_still_throws(): void
    {
        Http::fake(['apps.lsim.az/*' => Http::response(['obj' => null, 'errorCode' => -104])]);

        try {
            SmsLog::deliver(app(SmsService::class), '0501234567', 'test', 'order_sent');
            $this->fail('Xəta atılmalı idi');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('balansı bitib', $e->getMessage());
        }

        $log = SmsLog::sole();
        $this->assertSame(SmsLog::FAILED, $log->status);
        $this->assertTrue($log->isProblem());
        $this->assertSame(1, SmsLog::problems()->count());
    }

    public function test_delivery_check_writes_the_provider_status_and_stops_on_final_ones(): void
    {
        $delivered = SmsLog::create(['context' => 'otp', 'msisdn' => '994501234567', 'status' => SmsLog::SENT, 'provider_id' => '1', 'created_at' => now()->subMinutes(10)]);
        $undelivered = SmsLog::create(['context' => 'otp', 'msisdn' => '994501234567', 'status' => SmsLog::SENT, 'provider_id' => '2', 'created_at' => now()->subMinutes(10)]);
        $old = SmsLog::create(['context' => 'otp', 'msisdn' => '994501234567', 'status' => SmsLog::SENT, 'provider_id' => '3', 'created_at' => now()->subDays(5)]);
        Http::fake(fn ($request) => Http::response(['obj' => ['1' => 101, '2' => 102][$request['trans_id']] ?? 100, 'errorCode' => 0]));

        $this->artisan('sms:check-delivery')->assertSuccessful();

        $this->assertSame(SmsLog::DELIVERED, $delivered->fresh()->delivery_status);
        $this->assertSame(102, $undelivered->fresh()->delivery_status);
        $this->assertTrue($undelivered->fresh()->isProblem());
        $this->assertNull($old->fresh()->delivery_status, 'köhnə SMS daha yoxlanmır');

        // Yekun statuslu SMS-lər ikinci dəfə soruşulmur
        Http::fake(fn () => $this->fail('Yekun status yenidən soruşulmamalıdır'));
        $this->artisan('sms:check-delivery')->assertSuccessful();
    }

    public function test_balance_is_read_from_the_provider(): void
    {
        Http::fake(['apps.lsim.az/quicksms/v1/balance*' => Http::response(['obj' => 1543, 'errorCode' => 0])]);

        $this->assertSame(1543.0, app(SmsService::class)->balance());
        Http::assertSent(fn ($request) => $request['key'] === md5(md5('secret').'login'));
    }
}
