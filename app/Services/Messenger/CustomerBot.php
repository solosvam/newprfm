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
 * Bağlı müştəriyə hələlik yalnız salam; /stop — ayırır. Söhbətin addımı (nömrə / kod gözlənilir) — Cache-də, 10 dəqiqə.
 */
class CustomerBot
{
    public const SHARE = '📱 Nömrəmi paylaş';
    public const OTHER_NUMBER = 'Başqa nömrə ilə';
    public const CANCEL = 'Ləğv et';

    private const STATE_TTL_MINUTES = 10;
    private const MAX_CODE_ATTEMPTS = 5;

    public function __construct(private MessengerAccounts $accounts, private SmsService $sms)
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
            $this->linked($message, $account->customer, $reply);

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

    private function linked(IncomingMessage $message, Customer $customer, callable $reply): void
    {
        if (preg_match('~^/stop(?:@\w+)?$~', $message->text)) {
            $this->accounts->unlink($customer, $message->channel);
            $reply($this->welcome('Hesabınız botdan ayrıldı. Yenidən bağlamaq üçün nömrənizi paylaşın.'));

            return;
        }

        $reply(OutgoingMessage::text('Salam, '.$customer->name.'! Hesabınız bağlıdır. Bot hazırlanır — tezliklə burada sifarişləriniz və ətir seçimi olacaq.'));
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
