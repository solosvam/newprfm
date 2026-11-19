<?php

namespace App\Services\Messenger;

use App\Models\Customer\Customer;
use App\Models\SmsLog;
use App\Services\Crm\CustomerRegistration;
use App\Services\SmsService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

/**
 * Müştəri botu — kanaldan asılı deyil (Telegram indi, WhatsApp Business sonra; kanal MessengerChannel-dir).
 *
 * Bağlı olmayan müştəri hesabını üç yolla bağlayır:
 *  1. "Nömrəmi paylaş" — kanalın təsdiqlədiyi öz nömrəsi: bazada varsa bağlanır, yoxdursa yeni müştəri yaranır (SMS getmir).
 *     WhatsApp-da nömrə hər mesajda məlumdur — ilk mesajda elə bu yolla bağlanır.
 *  2. "Başqa nömrə ilə" — saytdakı nömrəni yazır, ora SMS kodu gedir, kodu botda yazır.
 *  3. Saytda profil → Telegram → "Bağla" → /start <birdəfəlik açar> (MessengerAccounts::createLinkToken).
 * Bağlı müştəriyə hələlik yalnız salam; səsli mesaja səsli cavab — sınaq, yalnız seçilmiş nömrələrə; /stop — ayırır. Söhbətin addımı (nömrə / kod gözlənilir) — Cache-də, 10 dəqiqə.
 */
class CustomerBot
{
    public const SHARE = '📱 Nömrəmi paylaş';
    public const OTHER_NUMBER = 'Başqa nömrə ilə';
    public const CANCEL = 'Ləğv et';
    public const VOICE_MODES = ['direct' => '🎙 Birbaşa', 'chain' => '🔗 Zəncir'];
    /** OpenAI səsləri — hər ikisi (gpt-audio-mini və gpt-4o-mini-tts) dəstəkləyir */
    public const VOICES = ['alloy', 'ash', 'ballad', 'coral', 'echo', 'fable', 'nova', 'onyx', 'sage', 'shimmer', 'verse', 'marin', 'cedar'];
    public const VOICE_MENU = '🔊 Səs';

    private const STATE_TTL_MINUTES = 10;
    private const MAX_CODE_ATTEMPTS = 5;
    private const MAX_VOICE_SECONDS = 120;

    public function __construct(private MessengerAccounts $accounts, private SmsService $sms, private VoiceAssistant $voice)
    {
    }

    public function handle(IncomingMessage $message, MessengerChannel $channel): void
    {
        $reply = fn (OutgoingMessage $out) => $channel->send($message->senderId, $out);

        if (preg_match('~^/start(?:@\w+)?\s+(\S+)$~', $message->text, $m)) {
            $customer = $this->accounts->consumeLinkToken($m[1]);
            if ($customer) {
                $this->forgetState($message);
                $this->accounts->link($customer, $message, 'site', $customer->mobile);
                $reply(OutgoingMessage::text($this->linkedText($customer)));

                return;
            }
            if (!$this->accounts->find($message->channel, $message->senderId)) {
                $reply($this->welcome('Bu link artıq keçərli deyil. Saytda profilinizdə Telegram bölməsində yenidən "Bağla" basın və ya aşağıdakı düymə ilə nömrənizi paylaşın.'));

                return;
            }
        }

        $account = $this->accounts->find($message->channel, $message->senderId);
        if ($account) {
            $this->linked($message, $account->customer, $reply, $channel);

            return;
        }

        if ($message->verifiedPhone !== null) {
            $this->byVerifiedPhone($message, $reply);

            return;
        }
        if ($message->foreignContact) {
            $reply($this->welcome('Yalnız öz nömrənizi paylaşa bilərsiniz — aşağıdakı "'.self::SHARE.'" düyməsini basın.'));

            return;
        }

        $state = Cache::get($this->stateKey($message));
        if ($message->text === self::CANCEL) {
            $this->forgetState($message);
            $reply($this->welcome());

            return;
        }
        if ($message->text === self::OTHER_NUMBER) {
            $this->putState($message, ['step' => 'phone']);
            $reply(OutgoingMessage::withButtons('Saytda qeydiyyatdan keçdiyiniz nömrəni yazın (məsələn, 050 123 45 67).', [self::CANCEL]));

            return;
        }
        if (($state['step'] ?? null) === 'phone') {
            $this->askCodeFor($message, $reply);

            return;
        }
        if (($state['step'] ?? null) === 'code') {
            $this->checkCode($message, $state, $reply);

            return;
        }

        $reply($this->welcome());
    }

    private function linked(IncomingMessage $message, Customer $customer, callable $reply, MessengerChannel $channel): void
    {
        if (preg_match('~^/stop(?:@\w+)?$~', $message->text)) {
            $this->accounts->unlink($customer, $message->channel);
            $reply($this->welcome('Hesabınız botdan ayrıldı. Yenidən bağlamaq üçün nömrənizi paylaşın.'));

            return;
        }

        if ($this->voiceAllowed($customer)) {
            if ($message->voiceFileId !== null) {
                $mode = $this->voiceMode($customer);
                $this->later(fn () => $this->voiceReply($message, $customer, $channel, $mode));

                return;
            }
            if (str_starts_with($message->text, self::VOICE_MENU)) {
                $reply(OutgoingMessage::withButtons('Səsi seçin — seçəndə nümunə göndərəcəyəm:', array_map(fn ($v) => '🔊 '.ucfirst($v), self::VOICES), null, true));

                return;
            }
            $voice = array_search($message->text, array_map(fn ($v) => '🔊 '.ucfirst($v), self::VOICES), true);
            if ($voice !== false) {
                $voice = self::VOICES[$voice];
                Cache::forever($this->voiceKey($customer), $voice);
                $reply(OutgoingMessage::withButtons('Səs: '.ucfirst($voice).'.', $this->mainButtons($customer), null, true));
                $this->later(fn () => rescue(fn () => $channel->sendVoice($message->senderId,
                    $this->voice->speak('Salam, '.$customer->name.'! Sizə bu səslə cavab verəcəyəm.', $voice), '🔊 '.ucfirst($voice)), null, true));

                return;
            }
            $mode = array_search($message->text, self::VOICE_MODES, true);
            if ($mode !== false) {
                Cache::forever($this->voiceModeKey($customer), $mode);
            }
            $reply(OutgoingMessage::withButtons(
                ($mode !== false ? 'Səsli cavab üsulu: ' : 'Salam, '.$customer->name.'! Səsli mesaj göndərin. Səsli cavab üsulu: ')
                    .self::VOICE_MODES[$this->voiceMode($customer)].', səs: '.ucfirst($this->voiceName($customer)).".\n🎙 Birbaşa — model səsinizi dinləyib dərhal səslə cavab verir.\n🔗 Zəncir — səs mətnə, GPT cavabı, cavab səsə.",
                $this->mainButtons($customer), null, true,
            ));

            return;
        }

        $reply(OutgoingMessage::text('Salam, '.$customer->name.'! Hesabınız bağlıdır. Bot hazırlanır — tezliklə burada sifarişləriniz və ətir seçimi olacaq.'));
    }

    /** Səsli cavab — sınaq: yalnız services.telegram.customer_voice_mobiles nömrələrinə */
    private function voiceAllowed(Customer $customer): bool
    {
        $mobiles = array_filter(array_map(fn ($m) => CustomerRegistration::normalizeMobile($m), explode(',', (string) config('services.telegram.customer_voice_mobiles'))));

        return in_array($customer->mobile, $mobiles, true);
    }

    /** Müştərinin seçdiyi üsul (botdakı düymələr), seçməyibsə — services.telegram.customer_voice_mode */
    private function voiceMode(Customer $customer): string
    {
        $mode = Cache::get($this->voiceModeKey($customer)) ?: config('services.telegram.customer_voice_mode');

        return isset(self::VOICE_MODES[$mode]) ? $mode : 'direct';
    }

    /** Müştərinin seçdiyi səs, seçməyibsə — services.openai.tts_voice */
    private function voiceName(Customer $customer): string
    {
        $voice = Cache::get($this->voiceKey($customer)) ?: config('services.openai.tts_voice');

        return in_array($voice, self::VOICES, true) ? $voice : 'coral';
    }

    private function voiceKey(Customer $customer): string
    {
        return 'messenger:voice-name:'.$customer->id;
    }

    /** Sınayanın daimi düymələri: üsul + səs */
    private function mainButtons(Customer $customer): array
    {
        return [...array_values(self::VOICE_MODES), self::VOICE_MENU.': '.ucfirst($this->voiceName($customer))];
    }

    private function voiceModeKey(Customer $customer): string
    {
        return 'messenger:voice-mode:'.$customer->id;
    }

    /** Uzun iş (OpenAI 3 sorğu) — veb sorğuda cavab Telegram-a qayıdandan sonra; konsolda (telegram:customer-poll) dərhal */
    private function later(\Closure $work): void
    {
        if (app()->runningInConsole()) {
            $work();
        } else {
            dispatch($work)->afterResponse();
        }
    }

    /** Səs → mətn → AI cavabı → səs. Söhbətin son 10 növbəsi yadda saxlanır (2 saat). */
    public function voiceReply(IncomingMessage $message, Customer $customer, MessengerChannel $channel, string $mode = 'direct'): void
    {
        $to = $message->senderId;
        if ($message->voiceDuration > self::MAX_VOICE_SECONDS) {
            $channel->send($to, OutgoingMessage::text('Səsli mesaj 2 dəqiqədən qısa olsun.'));

            return;
        }
        $channel->recordingVoice($to);
        $started = microtime(true);
        $voice = $this->voiceName($customer);
        $buttons = $this->mainButtons($customer);
        $historyKey = 'messenger:voice-history:'.$message->channel.':'.$to;
        try {
            $audio = $channel->downloadVoice($message->voiceFileId);
            $history = Cache::get($historyKey, []);
            // müqayisə üçün: üsul, səs və cavabın neçə saniyəyə hazırlandığı (Telegram-dan yükləmədən səs hazır olana qədər)
            $label = fn () => self::VOICE_MODES[$mode].' · '.ucfirst($voice).' · '.number_format(microtime(true) - $started, 1).' san'."\n";
            if ($mode === 'direct') {
                // Birbaşa: audio model dinləyib səslə cavab verir; müştərinin sözləri tarixçə üçün cavabdan SONRA mətnə çevrilir
                $answer = $this->voice->talk($customer->name, $history, $audio, $voice);
                $channel->sendVoice($to, $answer['audio'], $label().$answer['text']);
                $this->sendLinks($channel, $to, $answer['products'], $buttons);
                $history[] = ['role' => 'user', 'text' => rescue(fn () => $this->voice->transcribe($audio), '(səsli mesaj)', false) ?: '(səsli mesaj)'];
                $history[] = ['role' => 'assistant', 'text' => $answer['text']];
                Cache::put($historyKey, array_slice($history, -10), now()->addHours(2));

                return;
            }
            $text = $this->voice->transcribe($audio);
            if ($text === '') {
                $channel->send($to, OutgoingMessage::text('Səsi başa düşə bilmədim, bir də deyin.'));

                return;
            }
            $answer = $this->voice->reply($customer->name, $history, $text);
            $channel->recordingVoice($to);
            $speech = $this->voice->speak($answer['text'], $voice);
            $channel->sendVoice($to, $speech, $label().$answer['text']);
            $this->sendLinks($channel, $to, $answer['products'], $buttons);
            $history[] = ['role' => 'user', 'text' => $text];
            $history[] = ['role' => 'assistant', 'text' => $answer['text']];
            Cache::put($historyKey, array_slice($history, -10), now()->addHours(2));
        } catch (Throwable $e) {
            report($e);
            rescue(fn () => $channel->send($to, OutgoingMessage::text('Bağışlayın, cavab hazırlana bilmədi. Bir az sonra yenidən yoxlayın.')), null, false);
        }
    }

    /** Səsli cavabda adı çəkilən (funksiyanın tapdığı) ətirlərin linkləri — ayrıca mesaj; üsul düymələri yerində qalır */
    private function sendLinks(MessengerChannel $channel, string $to, array $products, array $buttons): void
    {
        if ($products === []) {
            return;
        }
        $lines = array_map(fn ($p) => $p['name']."\n".$p['url'], array_slice($products, 0, 3));
        $channel->send($to, OutgoingMessage::withButtons(implode("\n\n", $lines), $buttons, null, true));
    }

    /** Kanalın təsdiqlədiyi öz nömrəsi: bazada varsa bağla, yoxdursa yeni müştəri */
    private function byVerifiedPhone(IncomingMessage $message, callable $reply): void
    {
        $mobile = CustomerRegistration::normalizeMobile($message->verifiedPhone);
        if (!preg_match('/^994[1-9]\d{8}$/', $mobile)) {
            $reply(OutgoingMessage::withButtons('Hazırda yalnız Azərbaycan nömrələri qəbul olunur. Saytdakı Azərbaycan nömrənizlə bağlamaq üçün "'.self::OTHER_NUMBER.'" basın.', [self::OTHER_NUMBER]));

            return;
        }
        $this->forgetState($message);
        $customer = Customer::where('mobile', $mobile)->orderBy('id')->first();
        if ($customer) {
            $this->accounts->link($customer, $message, 'contact', $mobile);
            $reply(OutgoingMessage::text($this->linkedText($customer)));

            return;
        }
        $customer = $this->accounts->register($message, $mobile);
        $this->accounts->link($customer, $message, 'register', $mobile);
        $reply(OutgoingMessage::text('Hesabınız açıldı və bota bağlandı, '.$customer->name.'! ✅ Saytda bu nömrə ilə daxil ola bilərsiniz (şifrəni "Şifrəni unutdum" ilə təyin edin).'));
    }

    /** "Başqa nömrə ilə": yazılan nömrəyə SMS kodu */
    private function askCodeFor(IncomingMessage $message, callable $reply): void
    {
        $mobile = CustomerRegistration::normalizeMobile($message->text);
        if (!preg_match('/^994[1-9]\d{8}$/', $mobile)) {
            $reply(OutgoingMessage::withButtons('Nömrəni düzgün yazın, məsələn: 050 123 45 67.', [self::CANCEL]));

            return;
        }
        $customer = Customer::where('mobile', $mobile)->orderBy('id')->first();
        if (!$customer) {
            $this->forgetState($message);
            $reply($this->welcome('Bu nömrə ilə hesab tapılmadı. Öz nömrənizi paylaşsanız, yeni hesab açılacaq.'));

            return;
        }

        $senderKey = 'messenger-otp:'.$message->channel.':'.$message->senderId;
        if (RateLimiter::tooManyAttempts($senderKey, 5) || !Cache::add('messenger:otp-sent:'.$customer->id, true, now()->addSeconds(60))) {
            $reply(OutgoingMessage::withButtons('Kod bu yaxında göndərilib. Bir az sonra yenidən cəhd edin.', [self::CANCEL]));

            return;
        }
        RateLimiter::hit($senderKey, 3600);

        $code = (string) random_int(100000, 999999);
        try {
            SmsLog::deliver($this->sms, $customer->mobile, 'ParfumShop.az tesdiq kodunuz: '.$code, 'otp', $customer, null, [$code]);
        } catch (Throwable $e) {
            report($e);
            Cache::forget('messenger:otp-sent:'.$customer->id);
            $reply(OutgoingMessage::withButtons('SMS göndərilmədi. Bir az sonra yenidən cəhd edin.', [self::CANCEL]));

            return;
        }
        $this->putState($message, ['step' => 'code', 'customer_id' => $customer->id, 'hash' => Hash::make($code), 'attempts' => 0]);
        $reply(OutgoingMessage::withButtons($this->mask($customer->mobile).' nömrəsinə SMS kodu göndərildi. Kodu bura yazın.', [self::CANCEL]));
    }

    private function checkCode(IncomingMessage $message, array $state, callable $reply): void
    {
        $code = preg_replace('/\D+/', '', $message->text);
        if (strlen($code) !== 6 || !Hash::check($code, $state['hash'])) {
            $state['attempts']++;
            if ($state['attempts'] >= self::MAX_CODE_ATTEMPTS) {
                $this->forgetState($message);
                $reply($this->welcome('Kod çox dəfə yanlış yazıldı. Yenidən başlayın.'));

                return;
            }
            $this->putState($message, $state);
            $reply(OutgoingMessage::withButtons('Kod yanlışdır. Yenidən yazın.', [self::CANCEL]));

            return;
        }

        $this->forgetState($message);
        $customer = Customer::find($state['customer_id']);
        if (!$customer) {
            $reply($this->welcome());

            return;
        }
        $this->accounts->link($customer, $message, 'sms', $customer->mobile);
        $reply(OutgoingMessage::text($this->linkedText($customer)));
    }

    private function welcome(?string $text = null): OutgoingMessage
    {
        return OutgoingMessage::withButtons(
            $text ?? "Salam! Mən ParfumShop.az-ın botuyam.\nDavam etmək üçün nömrənizi paylaşın — hesabınızı tapım və ya yenisini açım. Saytda başqa nömrə ilə qeydiyyatdan keçmisinizsə, \"".self::OTHER_NUMBER.'" basın.',
            [self::OTHER_NUMBER],
            self::SHARE,
        );
    }

    private function linkedText(Customer $customer): string
    {
        return 'Hesabınız bota bağlandı, '.$customer->name.'! ✅';
    }

    private function mask(string $mobile): string
    {
        return '+'.substr($mobile, 0, 3).' '.substr($mobile, 3, 2).' *** ** '.substr($mobile, -2);
    }

    private function stateKey(IncomingMessage $message): string
    {
        return 'messenger:state:'.$message->channel.':'.$message->senderId;
    }

    private function putState(IncomingMessage $message, array $state): void
    {
        Cache::put($this->stateKey($message), $state, now()->addMinutes(self::STATE_TTL_MINUTES));
    }

    private function forgetState(IncomingMessage $message): void
    {
        Cache::forget($this->stateKey($message));
    }
}
