<?php

namespace App\Services\GiftCard;

use App\Exceptions\GiftCardException;
use App\Models\Customer\Customer;
use App\Models\GiftCard\GiftCard;
use App\Models\GiftCard\GiftCardEvent;
use App\Models\Order\Order;
use App\Models\Payment\Payment;
use App\Models\Payment\PaymentOperation;
use App\Models\Setting;
use App\Services\FinanceService;
use App\Services\Payment\Birbank;
use App\Services\Payment\BirbankPaymentSync;
use App\Services\SmsService;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Virtual hədiyyə kartı (qərarlar: görüləcək işlər, 15 noyabr 2026):
 *  - məbləğ 100–1500 AZN, 50-50; yalnız onlayn (Birbank) alınır; kod ödənişdən sonra yaranır, 12 ay keçərlidir;
 *  - səbətdə ödəniş kimi tətbiq olunur (endirim deyil): promo kod normal işləyir (əvvəl promo, sonra kart),
 *    qalıq yalnız qapıda / onlayn / Birbank taksitlə; hissə-hissə və bonusla ödəniş bağlıdır;
 *  - kart sifarişdən böyükdürsə artıq qalan hissə yanır; bir sifarişə bir kart;
 *  - kart sifarişə bağlanır (used), sifariş ləğv olunsa əvvəlki məbləğlə yenidən aktiv olur;
 *  - kartla ödənilmiş sifarişdə məhsul ayrıca ləğv edilmir — yalnız bütün sifariş;
 *  - istifadə olunmamış kartın pulu admin paneldən karta qaytarıla bilər.
 */
class GiftCardService
{
    public const MIN = 100;
    public const MAX = 1500;
    public const STEP = 50;
    public const VALID_MONTHS = 12;
    public const REMIND_DAYS = 30;
    public const MESSAGE_MAX = 150;
    public const SENDER_MAX = 60;
    /** Kartın dizaynları (keçərli dəyərlər); "standard" — ilk satılan kartlar üçün saxlanır */
    public const DESIGNS = ['signature', 'love', 'bloom', 'standard'];
    /** Alış səhifəsində təklif olunanlar (4 konsept: Signature, Love, Bloom hazırdır; Celebration — sonra) */
    public const OFFERED_DESIGNS = ['signature', 'love', 'bloom'];
    public const DEFAULT_DESIGN = 'signature';

    /** Qalığı ödəmək olan üsullar (kart tətbiq olunanda) */
    public const REMAINDER_METHODS = ['cash', 'card_online', 'birbank_installment'];

    /** Kodun hərfləri: qarışdırılan simvollar yoxdur (0/O, 1/I/L) */
    private const ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
    private const CODE_LENGTH = 12;

    /**
     * Mətndə qadağan sözlər (Setting "gift_card_banned_words" ilə dəyişdirilir, sətir və ya vergüllə).
     * Sonu "*" olan söz — kök: o hərflərlə başlayan hər söz tutulur; qalanları — tam söz.
     */
    public const DEFAULT_BANNED_WORDS = [
        'sik*', 'sikdir*', 'göt*', 'qəhbə*', 'qehbe*', 'gijdılla*', 'gijdilla*', 'peysər*', 'peyser*', 'amcıq*', 'amciq*', 'dalbayob*', 'orospu*', 'çındır*',
        'хуй*', 'хуе*', 'пизд*', 'ебат*', 'ебан*', 'ёб*', 'бляд*', 'блят*', 'сука', 'суки', 'мудак*', 'пидор*', 'пидар*', 'гандон*',
        'fuck*', 'shit*', 'bitch*', 'cunt*', 'asshole*', 'dick', 'whore*',
    ];

    /** @return list<int> */
    public function amounts(): array
    {
        return range(self::MIN, self::MAX, self::STEP);
    }

    public function isValidAmount(mixed $amount): bool
    {
        return is_numeric($amount) && in_array((int) $amount, $this->amounts(), true) && (float) $amount == (int) $amount;
    }

    /** @return list<string> */
    public function bannedWords(): array
    {
        $custom = (string) Setting::valueOf('gift_card_banned_words', '');
        $words = trim($custom) !== '' ? preg_split('/[\r\n,]+/u', $custom) : self::DEFAULT_BANNED_WORDS;

        return array_values(array_filter(array_map(fn ($w) => $this->fold(trim((string) $w)), $words)));
    }

    /** Kiçik hərf; İ/I/ı fərqi və əlavə nöqtələr silinir (SİK, SIK, sık — eyni söz) */
    private function fold(string $text): string
    {
        $text = mb_strtolower(str_replace(['İ', 'I'], 'i', $text));

        return str_replace('ı', 'i', preg_replace('/\p{Mn}+/u', '', $text) ?? $text);
    }

    public function containsBannedWord(?string $text): bool
    {
        if ($text === null || trim($text) === '') {
            return false;
        }
        $tokens = preg_split('/[^\p{L}\p{N}]+/u', $this->fold($text), -1, PREG_SPLIT_NO_EMPTY);
        foreach ($this->bannedWords() as $word) {
            $prefix = str_ends_with($word, '*');
            $stem = rtrim($word, '*');
            if ($stem === '') {
                continue;
            }
            foreach ($tokens as $token) {
                if ($prefix ? str_starts_with($token, $stem) : $token === $stem) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Alış: kart "ödəniş gözlənilir" yaranır (kod yoxdur). Ödəniş — startPayment().
     *
     * @param  array{amount: mixed, design?: ?string, message?: ?string, sender_name?: ?string}  $data
     */
    public function purchase(Customer $customer, array $data): GiftCard
    {
        $message = $this->clean($data['message'] ?? null, self::MESSAGE_MAX);
        $sender = $this->clean($data['sender_name'] ?? null, self::SENDER_MAX);
        $design = $data['design'] ?? self::DEFAULT_DESIGN;

        $errors = [];
        if (!$this->isValidAmount($data['amount'] ?? null)) {
            $errors['amount'] = __('gift_card_amount_invalid', ['min' => self::MIN, 'max' => self::MAX, 'step' => self::STEP]);
        }
        if (!in_array($design, self::OFFERED_DESIGNS, true)) {
            $errors['design'] = __('gift_card_design_invalid');
        }
        if ($this->containsBannedWord($message)) {
            $errors['message'] = __('gift_card_message_banned');
        }
        if ($this->containsBannedWord($sender)) {
            $errors['sender_name'] = __('gift_card_message_banned');
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return DB::transaction(function () use ($customer, $data, $message, $sender, $design) {
            $card = GiftCard::create([
                'amount' => (int) $data['amount'],
                'design' => $design,
                'message' => $message,
                'sender_name' => $sender,
                'customer_id' => $customer->id,
                'status' => GiftCard::PENDING,
                'share_token' => Str::random(40),
            ]);
            $this->log($card, 'created');

            return $card;
        });
    }

    /**
     * Ödəniş səhifəsinin ünvanı. Əvvəlki cəhd bankda hələ açıqdırsa / ödənilibsə, yenisi yaranmır.
     * Kart artıq ödənilibsə null.
     */
    public function startPayment(GiftCard $card, string $locale = 'az'): ?string
    {
        $sync = app(BirbankPaymentSync::class);
        foreach ($card->payments()->where('provider', 'birbank')->where('status', Payment::PENDING)->get() as $pending) {
            try {
                $result = $pending->provider_order_id ? $sync->sync($pending, $locale) : $sync->failUnstarted($pending);
            } catch (\Throwable $e) {
                report($e);
                throw new GiftCardException(__('gift_card_payment_unknown'));
            }
            if ($result->status === Payment::PAID) {
                return null;
            }
            if ($result->status === Payment::PENDING) {
                $url = $result->response_text === 'Preparing' ? app(Birbank::class)->resumeUrl($result) : null;
                if ($url) {
                    return $url;
                }
                throw new GiftCardException(__('gift_card_payment_unknown'));
            }
        }
        $card->refresh();
        if ($card->status !== GiftCard::PENDING) {
            return null;
        }

        return app(Birbank::class)->createGiftCardOrder($card, $locale)['url'];
    }

    /** Bankın nəticəsi (callback və payments:check-birbank): ödənilibsə kod verilir, kassaya yazılır */
    public function applyPayment(Payment $payment): void
    {
        if (!$payment->virtual_gift_card_id || $payment->status !== Payment::PAID) {
            return;
        }
        $paid = DB::transaction(function () use ($payment) {
            $card = GiftCard::whereKey($payment->virtual_gift_card_id)->lockForUpdate()->first();
            if (!$card || $card->status !== GiftCard::PENDING) {
                return false;
            }
            $now = now();
            $card->update([
                'code' => $this->uniqueCode(),
                'status' => GiftCard::ACTIVE,
                'paid_at' => $now,
                'expires_at' => $now->copy()->addMonths(self::VALID_MONTHS)->endOfDay(),
            ]);
            $this->log($card, 'paid', note: number_format((float) $payment->amount, 2, '.', '').' AZN · ödəniş #'.$payment->id);

            return true;
        });
        if ($paid) {
            // Kassa: Müştəri → Onlayn ödənişlər (xəta ödənişi pozmasın)
            rescue(fn () => app(FinanceService::class)->recordOnlinePayment($payment));
        }
    }

    /** İstifadəçinin yazdığı kod: böyük hərf, boşluq və tire olmadan */
    public function normalize(string $code): string
    {
        return preg_replace('/[^A-Z0-9]/', '', mb_strtoupper(trim($code))) ?? '';
    }

    /** Tətbiq olunacaq kart (səbət / checkout). Keçərli deyilsə səbəbi ilə xəta. */
    public function find(string $code, bool $lock = false): GiftCard
    {
        $code = $this->normalize($code);
        $query = GiftCard::where('code', $code);
        $card = strlen($code) === self::CODE_LENGTH ? ($lock ? $query->lockForUpdate() : $query)->first() : null;

        if (!$card) {
            throw new GiftCardException(__('gift_card_invalid'));
        }
        if ($card->status === GiftCard::USED) {
            throw new GiftCardException(__('gift_card_already_used'));
        }
        if ($card->status === GiftCard::EXPIRED || ($card->status === GiftCard::ACTIVE && $card->isExpired())) {
            throw new GiftCardException(__('gift_card_expired'));
        }
        if ($card->status !== GiftCard::ACTIVE) {
            throw new GiftCardException(__('gift_card_invalid'));
        }

        return $card;
    }

    /**
     * Sifarişin ödənişinə kartı tətbiq edir (CheckoutController-in tranzaksiyası daxilində, sifariş yarandıqdan sonra).
     * Kart sifarişdən böyükdürsə artıq qalan hissə yanır. Qaytarır: kartla ödənilən məbləğ.
     */
    public function redeem(Order $order, GiftCard $card, float $payable): float
    {
        $card = GiftCard::whereKey($card->id)->lockForUpdate()->firstOrFail();
        if (!$card->isUsable()) {
            throw new GiftCardException(__('gift_card_already_used'));
        }
        $applied = round(min((float) $card->amount, max(0, $payable)), 2);
        $card->update(['status' => GiftCard::USED, 'used_order_id' => $order->id, 'used_at' => now()]);
        $this->log($card, 'used', $order->id, number_format($applied, 2, '.', '').' AZN'
            .($applied < (float) $card->amount ? ' (qalan '.number_format((float) $card->amount - $applied, 2, '.', '').' AZN yandı)' : ''));

        return $applied;
    }

    /** Sifariş ləğv olundu: kart əvvəlki məbləğlə yenidən aktiv olur (müddəti dəyişmir) */
    public function release(Order $order, ?int $actor = null): void
    {
        if (!$order->gift_card_id) {
            return;
        }
        $card = GiftCard::whereKey($order->gift_card_id)->lockForUpdate()->first();
        if (!$card || $card->status !== GiftCard::USED || (int) $card->used_order_id !== (int) $order->id) {
            return;
        }
        $card->update(['status' => GiftCard::ACTIVE, 'used_order_id' => null, 'used_at' => null]);
        $this->log($card, 'released', $order->id, null, $actor);
    }

    public function extend(GiftCard $card, CarbonInterface $until, int $actor): void
    {
        DB::transaction(function () use ($card, $until, $actor) {
            $card = GiftCard::whereKey($card->id)->lockForUpdate()->firstOrFail();
            $this->ensure(in_array($card->status, [GiftCard::ACTIVE, GiftCard::EXPIRED], true), 'Yalnız aktiv və ya müddəti bitmiş kartın müddəti uzadılır.');
            $this->ensure($until->isFuture(), 'Yeni tarix bu gündən sonra olmalıdır.');
            $old = $card->expires_at?->format('d.m.Y');
            $card->update(['expires_at' => $until->copy()->endOfDay(), 'status' => GiftCard::ACTIVE, 'reminded_at' => null]);
            $this->log($card, 'extended', note: ($old ?? '—').' → '.$until->format('d.m.Y'), actor: $actor);
        });
    }

    /** İstifadə olunmamış kartın pulu karta qaytarılır (Birbank), kart bağlanır */
    public function refund(GiftCard $card, int $actor): void
    {
        [$card, $payment] = DB::transaction(function () use ($card) {
            $card = GiftCard::whereKey($card->id)->lockForUpdate()->firstOrFail();
            $this->ensure(in_array($card->status, [GiftCard::ACTIVE, GiftCard::EXPIRED], true), 'Yalnız istifadə olunmamış kartın pulu qaytarılır.');
            $payment = $card->paidPayment();
            $this->ensure($payment !== null, 'Kartın təsdiqlənmiş Birbank ödənişi tapılmadı.');

            return [$card, $payment];
        });

        $key = 'gift-card-refund-'.$card->id;
        try {
            $operation = app(Birbank::class)->refund($payment, number_format((float) $payment->amount, 2, '.', ''), $key);
        } catch (\Throwable $e) {
            $pending = PaymentOperation::where('idempotency_key', $key)->exists();
            throw ValidationException::withMessages(['refund' => $pending
                ? 'Bank cavab vermədi — qaytarma bankda yoxlanılmalıdır, təkrar göndərilmir.'
                : 'Qaytarma alınmadı: '.$e->getMessage()]);
        }

        DB::transaction(function () use ($card, $operation, $actor) {
            $card = GiftCard::whereKey($card->id)->lockForUpdate()->firstOrFail();
            $card->update(['status' => GiftCard::REFUNDED, 'refunded_at' => now()]);
            $this->log($card, 'refunded', note: number_format((float) $operation->amount, 2, '.', '').' AZN', actor: $actor);
        });
        rescue(fn () => app(FinanceService::class)->recordOnlineRefund($operation));
    }

    /** Müddəti bitmiş aktiv kartlar "Müddəti bitib" olur */
    public function expireDue(): int
    {
        $count = 0;
        GiftCard::where('status', GiftCard::ACTIVE)->where('expires_at', '<', now())->orderBy('id')
            ->each(function (GiftCard $card) use (&$count) {
                $card->update(['status' => GiftCard::EXPIRED]);
                $this->log($card, 'expired');
                $count++;
            });

        return $count;
    }

    /** Müddətin bitməsinə 30 gün qalmış kartı alana bir dəfə SMS */
    public function remindExpiring(SmsService $sms): int
    {
        $sent = 0;
        GiftCard::with('customer')->where('status', GiftCard::ACTIVE)->whereNull('reminded_at')
            ->where('expires_at', '>', now())->where('expires_at', '<=', now()->addDays(self::REMIND_DAYS))
            ->orderBy('id')->each(function (GiftCard $card) use ($sms, &$sent) {
                $card->update(['reminded_at' => now()]);
                $customer = $card->customer;
                if (!$customer || !$customer->mobile || !$customer->active) {
                    return;
                }
                $values = ['amount' => number_format((float) $card->amount, 0, '.', ''), 'date' => $card->expires_at->format('d.m.Y'), 'link' => route('gift-card.show', $card->share_token)];
                $message = \App\Models\SmsTemplate::message('gift_card_expiring', $values)
                    ?? "Parfumshop: {$values['amount']} AZN hediyye kartinin muddeti {$values['date']} tarixinde bitir. {$values['link']}";
                try {
                    \App\Models\SmsLog::deliver($sms, $customer->mobile, $message, 'gift_card_expiring', $customer);
                    $this->log($card, 'reminded');
                    $sent++;
                } catch (\Throwable $e) {
                    report($e);
                }
            });

        return $sent;
    }

    public function log(GiftCard $card, string $event, ?int $orderId = null, ?string $note = null, ?int $actor = null): void
    {
        GiftCardEvent::create([
            'virtual_gift_card_id' => $card->id, 'event' => $event, 'order_id' => $orderId,
            'note' => $note !== null ? mb_substr($note, 0, 255) : null, 'user_id' => $actor, 'created_at' => now(),
        ]);
    }

    private function uniqueCode(): string
    {
        do {
            $code = '';
            for ($i = 0; $i < self::CODE_LENGTH; $i++) {
                $code .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
            }
        } while (GiftCard::where('code', $code)->exists());

        return $code;
    }

    private function clean(?string $value, int $max): ?string
    {
        $value = trim(preg_replace('/\s+/u', ' ', strip_tags((string) $value)) ?? '');

        return $value === '' ? null : mb_substr($value, 0, $max);
    }

    private function ensure(bool $condition, string $message): void
    {
        if (!$condition) {
            throw ValidationException::withMessages(['gift_card' => $message]);
        }
    }
}
