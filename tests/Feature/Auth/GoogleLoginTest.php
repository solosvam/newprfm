<?php

namespace Tests\Feature\Auth;

use App\Models\Customer\Customer;
use App\Services\Auth\GoogleLogin;
use App\Services\BonusService;
use App\Services\Referral\ReferralService;
use App\Services\Referral\ReferralSettings;
use App\Services\SmsService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Tests\TestCase;

/**
 * Google ilə giriş: bağlı hesab → giriş; email uyğun → SMS ilə bağlama; yeni → nömrə + SMS → hesab;
 * nömrə ilə hesab varsa ona bağlanır; düymə yalnız açıq olanda və ya sınaq cookie-si ilə görünür.
 */
class GoogleLoginTest extends TestCase
{
    private array $sent = [];

    public static array $profile = [];

    public static array $bonuses = [];

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'cache.default' => 'array',
            'services.google' => ['client_id' => 'cid', 'client_secret' => 'secret', 'redirect' => null, 'login_public' => false, 'preview_key' => 'gizli-acar-123']]);
        DB::purge('sqlite');
        Mail::fake();
        Schema::create('customers', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->string('surname')->default(''); $t->integer('gender')->nullable(); $t->string('email')->nullable();
            $t->string('mobile'); $t->string('password')->nullable(); $t->string('google_id')->nullable()->unique(); $t->boolean('active')->default(true);
            $t->string('source')->nullable(); $t->string('registration_otp_hash')->nullable(); $t->timestamp('registration_otp_expires_at')->nullable();
            $t->rememberToken(); $t->timestamps();
        });
        $this->app->instance(SmsService::class, new class($this->sent) extends SmsService {
            public function __construct(private array &$log) {}
            public function send(string $number, string $message): ?string { $this->log[] = [$number, $message]; return 'ok'; }
        });
        $this->app->instance(GoogleLogin::class, new class extends GoogleLogin {
            public function redirect(): RedirectResponse { return new RedirectResponse('https://accounts.google.com/o/oauth2/auth'); }
            public function user(): array { return GoogleLoginTest::$profile; }
        });
        self::$bonuses = [];
        $this->mock(BonusService::class, fn ($m) => $m->shouldReceive('grantRegistration')->andReturnUsing(function ($c) { GoogleLoginTest::$bonuses[] = $c->id; return 10.0; }));
        $settings = Mockery::mock(ReferralSettings::class);
        $settings->shouldReceive('enabled')->andReturn(false);
        $this->mock(ReferralService::class, fn ($m) => $m->shouldReceive('settings')->andReturn($settings));

        self::$profile = ['id' => 'g-111', 'email' => 'aysel@gmail.com', 'email_verified' => true, 'name' => 'Aysel', 'surname' => 'Məmmədova'];
    }

    private function code(): string
    {
        preg_match('/(\d{6})/', (string) end($this->sent)[1], $m);

        return $m[1];
    }

    public function test_linked_google_account_logs_in(): void
    {
        $customer = Customer::forceCreate(['name' => 'Aysel', 'mobile' => '994501234567', 'google_id' => 'g-111']);

        $this->get('/auth/google/callback?code=x')->assertRedirect(route('home'));
        $this->assertSame($customer->id, Auth::id());
        $this->assertSame([], $this->sent);
    }

    public function test_email_match_links_after_sms_code(): void
    {
        $customer = Customer::forceCreate(['name' => 'Aysel', 'email' => 'Aysel@Gmail.com', 'mobile' => '994501234567']);

        $this->get('/auth/google/callback?code=x')->assertRedirect(route('auth.google.verify'));
        $this->assertGuest();
        $this->assertSame('994501234567', $this->sent[0][0], 'kod hesabdakı nömrəyə gedir');

        $this->post('/auth/google/verify', ['otp' => $this->code() === '000000' ? '111111' : '000000'])->assertSessionHasErrors('otp');
        $this->assertNull($customer->fresh()->google_id);

        $this->post('/auth/google/verify', ['otp' => $this->code()])->assertRedirect(route('home'));
        $this->assertSame('g-111', $customer->fresh()->google_id);
        $this->assertSame($customer->id, Auth::id());
        $this->assertSame([], self::$bonuses, 'aktiv hesaba yenidən bonus verilmir');
    }

    public function test_new_customer_gives_mobile_and_gender_then_account_is_created(): void
    {
        $this->get('/auth/google/callback?code=x')->assertRedirect(route('auth.google.complete'));
        $this->post('/auth/google/complete', ['mobile' => '+994 55 765 43 21', 'gender' => '0', 'name' => 'Aysel', 'surname' => 'Məmmədova'])
            ->assertRedirect(route('auth.google.verify'));
        $this->assertSame(0, Customer::count(), 'hesab kod təsdiqlənənə qədər yaranmır');
        $this->assertSame('994557654321', $this->sent[0][0]);

        $this->post('/auth/google/verify', ['otp' => $this->code()])->assertRedirect(route('home'));
        $customer = Customer::sole();
        $this->assertSame(['g-111', 'aysel@gmail.com', '994557654321', 0, 'google', true],
            [$customer->google_id, $customer->email, $customer->mobile, (int) $customer->gender, $customer->source, $customer->active]);
        $this->assertSame($customer->id, Auth::id());
        $this->assertSame([$customer->id], self::$bonuses, 'qeydiyyat bonusu');
    }

    public function test_existing_mobile_gets_linked_instead_of_duplicate(): void
    {
        self::$profile['email'] = 'basqa@gmail.com';
        $customer = Customer::forceCreate(['name' => 'Köhnə', 'email' => 'kohne@mail.ru', 'mobile' => '994557654321', 'active' => false]);

        $this->get('/auth/google/callback?code=x')->assertRedirect(route('auth.google.complete'));
        $this->post('/auth/google/complete', ['mobile' => '994557654321', 'gender' => '1', 'name' => 'Yeni', 'surname' => 'Ad'])
            ->assertRedirect(route('auth.google.verify'));
        $this->post('/auth/google/verify', ['otp' => $this->code()])->assertRedirect(route('home'));

        $this->assertSame(1, Customer::count());
        $fresh = $customer->fresh();
        $this->assertSame(['g-111', 'kohne@mail.ru', 'Köhnə', true], [$fresh->google_id, $fresh->email, $fresh->name, $fresh->active]);
        $this->assertSame([$customer->id], self::$bonuses, 'yarımçıq qeydiyyat təsdiqləndi — bonus');
    }

    public function test_account_linked_to_another_google_is_not_taken_over(): void
    {
        Customer::forceCreate(['name' => 'Aysel', 'email' => 'aysel@gmail.com', 'mobile' => '994501234567', 'google_id' => 'g-999']);

        $this->get('/auth/google/callback?code=x')->assertRedirect(route('front.login'))->assertSessionHas('error');
        $this->assertSame([], $this->sent);
        $this->assertGuest();
    }

    public function test_code_cannot_be_guessed(): void
    {
        Customer::forceCreate(['name' => 'Aysel', 'email' => 'aysel@gmail.com', 'mobile' => '994501234567']);
        $this->get('/auth/google/callback?code=x');
        $real = $this->code();
        $wrong = $real === '111111' ? '222222' : '111111';
        foreach (range(1, 5) as $i) {
            $this->post('/auth/google/verify', ['otp' => $wrong])->assertSessionHasErrors('otp');
        }
        $this->post('/auth/google/verify', ['otp' => $real])->assertSessionHasErrors('otp');
        $this->assertGuest();
    }

    public function test_profile_connect_and_disconnect(): void
    {
        $me = Customer::forceCreate(['name' => 'Aysel', 'mobile' => '994501234567']);
        $this->actingAs($me)->get('/auth/google/callback?code=x')->assertRedirect(route('profile.personal'))->assertSessionHas('success');
        $this->assertSame('g-111', $me->fresh()->google_id);

        $this->actingAs($me)->post('/profile/google/disconnect')->assertRedirect();
        $this->assertNull($me->fresh()->google_id);

        $other = Customer::forceCreate(['name' => 'Başqa', 'mobile' => '994551111111', 'google_id' => 'g-111']);
        $this->actingAs($me)->get('/auth/google/callback?code=x')->assertSessionHas('error');
        $this->assertNull($me->fresh()->google_id);
        $this->assertSame('g-111', $other->fresh()->google_id);
    }

    public function test_button_hidden_until_public_or_preview_cookie(): void
    {
        $this->getJson('/auth/google')->assertNotFound();
        $this->getJson('/google-preview/sehv-acar')->assertNotFound();

        $response = $this->get('/google-preview/gizli-acar-123')->assertRedirect(route('front.login'));
        $this->assertTrue(collect($response->headers->getCookies())->contains(fn ($c) => $c->getName() === GoogleLogin::PREVIEW_COOKIE));
        $this->withCookie(GoogleLogin::PREVIEW_COOKIE, 'gizli-acar-123')->get('/auth/google')->assertRedirect('https://accounts.google.com/o/oauth2/auth');

        config(['services.google.login_public' => true]);
        $this->get('/auth/google')->assertRedirect('https://accounts.google.com/o/oauth2/auth');

        config(['services.google.client_id' => null]);
        $this->getJson('/auth/google')->assertNotFound();
    }
}
