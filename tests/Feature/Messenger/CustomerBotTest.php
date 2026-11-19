<?php

namespace Tests\Feature\Messenger;

use App\Models\Customer\Customer;
use App\Models\Customer\CustomerMessengerAccount;
use App\Services\BonusService;
use App\Services\Messenger\CustomerBot;
use App\Services\Messenger\MessengerAccounts;
use App\Services\Messenger\OutgoingMessage;
use App\Services\Messenger\TelegramCustomerChannel;
use App\Services\Messenger\VoiceAssistant;
use App\Services\SmsService;
use App\Services\Telegram\TelegramApi;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Müştəri botu (Telegram): öz nömrəsini paylaşan bağlanır / qeydiyyatdan keçir; başqasının kontaktı rədd;
 * "Başqa nömrə ilə" — SMS kodu; saytdan birdəfəlik link; /stop; gizli başlıq yoxlanır.
 */
class CustomerBotTest extends TestCase
{
    public static array $replies = [];

    public static array $voices = [];

    private array $sms = [];

    public static array $bonuses = [];

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'cache.default' => 'array',
            'services.telegram.customer_bot_token' => '123:test', 'services.telegram.customer_bot_username' => 'parfumshopai_bot']);
        DB::purge('sqlite');
        Schema::create('customers', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->string('surname')->default(''); $t->integer('gender')->nullable(); $t->string('email')->nullable();
            $t->string('mobile'); $t->string('password')->nullable(); $t->boolean('active')->default(true); $t->string('source')->nullable();
            $t->string('registration_otp_hash')->nullable(); $t->timestamp('registration_otp_expires_at')->nullable(); $t->rememberToken(); $t->timestamps();
        });
        Schema::create('customer_messenger_accounts', function (Blueprint $t) {
            $t->id(); $t->integer('customer_id'); $t->string('channel'); $t->string('external_id'); $t->string('phone')->nullable();
            $t->string('display_name')->nullable(); $t->string('linked_via'); $t->timestamps();
            $t->unique(['channel', 'external_id']); $t->unique(['customer_id', 'channel']);
        });
        self::$replies = [];
        self::$voices = [];
        self::$bonuses = [];
        $this->app->instance(TelegramCustomerChannel::class, new class(TelegramApi::customer()) extends TelegramCustomerChannel {
            public function send(string $recipientId, OutgoingMessage $message): void { CustomerBotTest::$replies[] = [$recipientId, $message]; }
            public function downloadVoice(string $fileId): string { return 'ogg:'.$fileId; }
            public function sendVoice(string $recipientId, string $audio, ?string $caption = null): void { CustomerBotTest::$voices[] = [$recipientId, $audio, $caption]; }
            public function recordingVoice(string $recipientId): void {}
        });
        $this->app->instance(VoiceAssistant::class, new class extends VoiceAssistant {
            public function transcribe(string $audio): string { return 'Yay üçün ətir? ('.$audio.')'; }
            public function reply(string $customerName, array $history, string $text): string { return $customerName.', '.count($history).' növbə: cavab'; }
            public function speak(string $text): string { return 'opus:'.$text; }
            public function talk(string $customerName, array $history, string $ogg): array { return ['text' => $customerName.', '.count($history).' növbə: birbaşa', 'audio' => 'opus-direct:'.$ogg]; }
        });
        $this->app->instance(SmsService::class, new class($this->sms) extends SmsService {
            public function __construct(private array &$log) {}
            public function send(string $number, string $message): ?string { $this->log[] = [$number, $message]; return 'ok'; }
        });
        $this->mock(BonusService::class, fn ($m) => $m->shouldReceive('grantRegistration')->andReturnUsing(function ($c) { CustomerBotTest::$bonuses[] = $c->id; return 10.0; }));
    }

    private function update(array $message, int $from = 777): \Illuminate\Testing\TestResponse
    {
        $payload = ['update_id' => 1, 'message' => $message + ['message_id' => 1, 'chat' => ['id' => $from, 'type' => 'private'],
            'from' => ['id' => $from, 'is_bot' => false, 'first_name' => 'Aysel', 'last_name' => 'Məmmədova']]];

        return $this->postJson('/telegram/customer-webhook', $payload, ['X-Telegram-Bot-Api-Secret-Token' => TelegramApi::customer()->webhookSecret()]);
    }

    private function lastReply(): OutgoingMessage
    {
        return end(self::$replies)[1];
    }

    public function test_requires_secret_header(): void
    {
        $this->postJson('/telegram/customer-webhook', ['message' => ['text' => 'salam']], ['X-Telegram-Bot-Api-Secret-Token' => 'yanlis'])->assertForbidden();
        $this->assertSame([], self::$replies);
    }

    public function test_unknown_user_gets_share_button(): void
    {
        $this->update(['text' => '/start'])->assertOk();

        $buttons = $this->lastReply()->buttons;
        $this->assertSame([CustomerBot::SHARE, true], [$buttons[0]['label'], $buttons[0]['contact']]);
        $this->assertSame(CustomerBot::OTHER_NUMBER, $buttons[1]['label']);
        $this->assertSame(0, CustomerMessengerAccount::count());
    }

    public function test_own_contact_links_existing_customer_and_activates_without_sms(): void
    {
        $customer = Customer::forceCreate(['name' => 'Aysel', 'mobile' => '994501234567', 'active' => false]);

        $this->update(['contact' => ['phone_number' => '+994501234567', 'user_id' => 777, 'first_name' => 'Aysel']])->assertOk();

        $account = CustomerMessengerAccount::sole();
        $this->assertSame([$customer->id, 'telegram', '777', 'contact', '994501234567'],
            [(int) $account->customer_id, $account->channel, $account->external_id, $account->linked_via, $account->phone]);
        $this->assertTrue($customer->fresh()->active, 'nömrə Telegram-da təsdiqlənib — hesab aktivləşir');
        $this->assertSame([$customer->id], self::$bonuses);
        $this->assertSame([], $this->sms);
        $this->assertStringContainsString('bağlandı', $this->lastReply()->text);
    }

    public function test_own_contact_with_unknown_number_registers_customer(): void
    {
        $this->update(['contact' => ['phone_number' => '994557654321', 'user_id' => 777, 'first_name' => 'Aysel']])->assertOk();

        $customer = Customer::sole();
        $this->assertSame(['Aysel', 'Məmmədova', '994557654321', 'telegram', true],
            [$customer->name, $customer->surname, $customer->mobile, $customer->source, $customer->active]);
        $this->assertSame($customer->id, (int) CustomerMessengerAccount::sole()->customer_id);
        $this->assertSame('register', CustomerMessengerAccount::sole()->linked_via);
        $this->assertSame([$customer->id], self::$bonuses);
    }

    public function test_foreign_contact_is_rejected(): void
    {
        Customer::forceCreate(['name' => 'Başqası', 'mobile' => '994501234567']);

        $this->update(['contact' => ['phone_number' => '994501234567', 'user_id' => 999, 'first_name' => 'Başqası']])->assertOk();
        $this->update(['contact' => ['phone_number' => '994501234567', 'first_name' => 'Başqası']])->assertOk();

        $this->assertSame(0, CustomerMessengerAccount::count());
        $this->assertStringContainsString('öz nömrənizi', $this->lastReply()->text);
    }

    public function test_non_azerbaijani_own_number_is_not_registered(): void
    {
        $this->update(['contact' => ['phone_number' => '+79161234567', 'user_id' => 777, 'first_name' => 'Aysel']])->assertOk();

        $this->assertSame(0, Customer::count());
        $this->assertSame(CustomerBot::OTHER_NUMBER, $this->lastReply()->buttons[0]['label']);
    }

    public function test_other_number_links_after_sms_code(): void
    {
        $customer = Customer::forceCreate(['name' => 'Aysel', 'mobile' => '994501234567']);

        $this->update(['text' => CustomerBot::OTHER_NUMBER]);
        $this->update(['text' => '070 000 00 00']);
        $this->assertStringContainsString('tapılmadı', $this->lastReply()->text);
        $this->assertSame([], $this->sms);

        $this->update(['text' => CustomerBot::OTHER_NUMBER]);
        $this->update(['text' => '050 123 45 67']);
        $this->assertSame('994501234567', $this->sms[0][0]);
        $this->assertStringContainsString('+994 50 *** ** 67', $this->lastReply()->text);
        preg_match('/(\d{6})/', $this->sms[0][1], $m);

        $this->update(['text' => $m[1] === '000000' ? '111111' : '000000']);
        $this->assertStringContainsString('yanlışdır', $this->lastReply()->text);
        $this->assertSame(0, CustomerMessengerAccount::count());

        $this->update(['text' => $m[1]]);
        $account = CustomerMessengerAccount::sole();
        $this->assertSame([$customer->id, 'sms'], [(int) $account->customer_id, $account->linked_via]);
    }

    public function test_code_attempts_are_limited(): void
    {
        Customer::forceCreate(['name' => 'Aysel', 'mobile' => '994501234567']);
        $this->update(['text' => CustomerBot::OTHER_NUMBER]);
        $this->update(['text' => '0501234567']);
        preg_match('/(\d{6})/', $this->sms[0][1], $m);
        $wrong = $m[1] === '000000' ? '111111' : '000000';
        for ($i = 0; $i < 5; $i++) {
            $this->update(['text' => $wrong]);
        }

        $this->update(['text' => $m[1]]);
        $this->assertSame(0, CustomerMessengerAccount::count(), '5 yanlış cəhddən sonra kod etibarsızdır');
    }

    public function test_site_link_token_links_logged_in_customer_once(): void
    {
        $customer = Customer::forceCreate(['name' => 'Aysel', 'mobile' => '994501234567']);
        $this->actingAs($customer)->get('/profile/telegram/connect')->assertRedirectContains('https://t.me/parfumshopai_bot?start=');
        $token = app(MessengerAccounts::class)->createLinkToken($customer);

        $this->update(['text' => '/start '.$token]);
        $this->assertSame('site', CustomerMessengerAccount::sole()->linked_via);

        $this->update(['text' => '/start '.$token], 888);
        $this->assertSame(1, CustomerMessengerAccount::count(), 'link birdəfəlikdir');
        $this->assertStringContainsString('keçərli deyil', $this->lastReply()->text);
    }

    public function test_relinking_replaces_previous_account(): void
    {
        $first = Customer::forceCreate(['name' => 'Bir', 'mobile' => '994501234567']);
        $second = Customer::forceCreate(['name' => 'İki', 'mobile' => '994557654321']);
        $accounts = app(MessengerAccounts::class);

        $this->update(['contact' => ['phone_number' => '994501234567', 'user_id' => 777]]);
        $this->update(['text' => '/start '.$accounts->createLinkToken($second)]);

        $this->assertSame($second->id, (int) CustomerMessengerAccount::sole()->customer_id, 'eyni Telegram bir müştəriyə bağlıdır');

        $this->update(['text' => '/start '.$accounts->createLinkToken($second)], 888);
        $this->assertSame('888', CustomerMessengerAccount::sole()->external_id, 'müştərinin bir Telegram hesabı olur');
        $this->assertNull($first->fresh()->telegramAccount);
    }

    public function test_linked_customer_gets_greeting_and_can_stop(): void
    {
        $customer = Customer::forceCreate(['name' => 'Aysel', 'mobile' => '994501234567']);
        $this->update(['contact' => ['phone_number' => '994501234567', 'user_id' => 777]]);

        $this->update(['text' => 'salam']);
        $this->assertStringContainsString('Salam, Aysel', $this->lastReply()->text);

        $this->update(['text' => '/stop']);
        $this->assertSame(0, CustomerMessengerAccount::count());
        $this->assertNull($customer->fresh()->telegramAccount);
    }

    public function test_group_messages_are_ignored(): void
    {
        $this->postJson('/telegram/customer-webhook', ['message' => ['message_id' => 1, 'text' => 'salam', 'chat' => ['id' => -100, 'type' => 'group'], 'from' => ['id' => 777]]],
            ['X-Telegram-Bot-Api-Secret-Token' => TelegramApi::customer()->webhookSecret()])->assertOk();

        $this->assertSame([], self::$replies);
    }

    public function test_profile_disconnect(): void
    {
        $customer = Customer::forceCreate(['name' => 'Aysel', 'mobile' => '994501234567']);
        CustomerMessengerAccount::create(['customer_id' => $customer->id, 'channel' => 'telegram', 'external_id' => '777', 'linked_via' => 'contact']);

        $this->actingAs($customer)->from('/profile/personal')->post('/profile/telegram/disconnect')->assertRedirect('/profile/personal');
        $this->assertSame(0, CustomerMessengerAccount::count());
    }

    public function test_voice_message_gets_voice_reply_only_for_allowed_number(): void
    {
        config(['services.telegram.customer_voice_mobiles' => '+994 10 322 75 75', 'services.telegram.customer_voice_mode' => 'chain']);
        $allowed = Customer::forceCreate(['name' => 'Rufat', 'mobile' => '994103227575']);
        CustomerMessengerAccount::create(['customer_id' => $allowed->id, 'channel' => 'telegram', 'external_id' => '777', 'linked_via' => 'contact']);
        $other = Customer::forceCreate(['name' => 'Aysel', 'mobile' => '994501234567']);
        CustomerMessengerAccount::create(['customer_id' => $other->id, 'channel' => 'telegram', 'external_id' => '888', 'linked_via' => 'contact']);

        $this->update(['voice' => ['file_id' => 'F1', 'duration' => 4]])->assertOk();
        $this->update(['voice' => ['file_id' => 'F2', 'duration' => 4]])->assertOk();
        $this->assertSame([['777', 'opus:Rufat, 0 növbə: cavab', 'Rufat, 0 növbə: cavab'], ['777', 'opus:Rufat, 2 növbə: cavab', 'Rufat, 2 növbə: cavab']], self::$voices, 'söhbət yadda qalır');

        $this->update(['voice' => ['file_id' => 'F3', 'duration' => 4]], 888)->assertOk();
        $this->assertCount(2, self::$voices, 'başqa nömrəyə səsli cavab yoxdur');
        $this->assertStringContainsString('Salam, Aysel', $this->lastReply()->text);

        $this->update(['voice' => ['file_id' => 'F4', 'duration' => 300]])->assertOk();
        $this->assertCount(2, self::$voices);
        $this->assertStringContainsString('2 dəqiqədən', $this->lastReply()->text);
    }

    public function test_direct_voice_mode_answers_in_one_call_and_keeps_history(): void
    {
        config(['services.telegram.customer_voice_mobiles' => '994103227575', 'services.telegram.customer_voice_mode' => 'direct']);
        $customer = Customer::forceCreate(['name' => 'Rufat', 'mobile' => '994103227575']);
        CustomerMessengerAccount::create(['customer_id' => $customer->id, 'channel' => 'telegram', 'external_id' => '777', 'linked_via' => 'contact']);

        $this->update(['voice' => ['file_id' => 'F1', 'duration' => 4]])->assertOk();
        $this->update(['voice' => ['file_id' => 'F2', 'duration' => 4]])->assertOk();

        $this->assertSame([
            ['777', 'opus-direct:ogg:F1', 'Rufat, 0 növbə: birbaşa'],
            ['777', 'opus-direct:ogg:F2', 'Rufat, 2 növbə: birbaşa'],
        ], self::$voices);
    }
}
