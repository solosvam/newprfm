<?php

namespace Tests\Feature\Auth;

use App\Models\Customer\Customer;
use App\Services\SmsService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** "Şifrəni unutdum": SMS kod → yeni şifrə; kodu təxmin etmək olmur; müştəri həmişə xatırlanır */
class ForgotPasswordTest extends TestCase
{
    private array $sent = [];

    protected function setUp(): void
    {
        parent::setUp();
        // Developer bazasına toxunmuruq: yaddaşda sqlite
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'cache.default' => 'array']);
        DB::purge('sqlite');
        Schema::create('customers', function (Blueprint $t) {
            $t->id(); $t->string('name')->nullable(); $t->string('mobile'); $t->string('password')->nullable();
            $t->boolean('active')->default(true); $t->rememberToken(); $t->timestamps();
        });
        $this->app->instance(SmsService::class, new class($this->sent) extends SmsService {
            public function __construct(private array &$log) {}
            public function send(string $number, string $message): ?string { $this->log[] = $message; return 'ok'; }
        });
        Customer::forceCreate(['name' => 'Aysel', 'mobile' => '994501234567', 'password' => Hash::make('kohne-sifre')]);
    }

    private function code(): string
    {
        preg_match('/(\d{6})/', (string) end($this->sent), $m);

        return $m[1];
    }

    public function test_forgot_password_resets_and_logs_in_remembered(): void
    {
        $this->postJson('/login/forgot', ['mobile' => '994501234567'])->assertOk()->assertJsonPath('status', 'otp');
        $this->postJson('/login/otp', ['mobile' => '994501234567', 'otp' => $this->code()])->assertOk()->assertJsonPath('status', 'verified');
        $response = $this->postJson('/login/set-password', ['mobile' => '994501234567', 'password' => 'yeni-sifre', 'password_confirmation' => 'yeni-sifre'])
            ->assertOk()->assertJsonPath('status', 'success');

        $customer = Customer::first();
        $this->assertTrue(Hash::check('yeni-sifre', $customer->password));
        $this->assertSame($customer->id, Auth::id());
        $this->assertNotNull($customer->remember_token, '"Məni xatırla" həmişə aktivdir');
        $this->assertTrue(collect($response->headers->getCookies())->contains(fn ($c) => str_starts_with($c->getName(), 'remember_')));
    }

    public function test_unknown_number_gets_no_sms(): void
    {
        $this->postJson('/login/forgot', ['mobile' => '994559999999'])->assertStatus(422)->assertJsonValidationErrors('mobile');
        $this->assertSame([], $this->sent);
    }

    public function test_code_cannot_be_guessed(): void
    {
        $this->postJson('/login/forgot', ['mobile' => '994501234567'])->assertOk();
        $real = $this->code();
        $wrong = $real === '111111' ? '222222' : '111111';

        foreach (range(1, 4) as $i) {
            $this->postJson('/login/otp', ['mobile' => '994501234567', 'otp' => $wrong])->assertStatus(422);
        }
        $this->postJson('/login/otp', ['mobile' => '994501234567', 'otp' => $wrong])->assertStatus(422)
            ->assertJsonPath('errors.otp.0', __('auth_too_many_otp_attempts'));
        // 5 səhvdən sonra düzgün kod da keçmir — yeni kod lazımdır
        $this->postJson('/login/otp', ['mobile' => '994501234567', 'otp' => $real])->assertStatus(422);
        $this->assertGuest();
    }
}
