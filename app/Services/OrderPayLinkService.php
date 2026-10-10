<?php

namespace App\Services;

use App\Support\ShortUrl;
use App\Models\Order\Order;
use App\Models\Setting;
use App\Models\SmsTemplate;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * SMS ödəniş linki: operator sifarişi yaradır, müştəri linkə keçib login olmadan ödəyir.
 * Token 10 simvol (a-z, A-Z, 0-9; Str::random — kriptoqrafik): ~8×10¹⁷ variant, təxmin edilmir.
 *
 * Linkin son istifadə vaxtı var (orders.pay_token_expires_at): müddət link yarananda başlayır, SMS göndəriləndə
 * və ya operator "Yenilə" basanda yenidən sayılır. Müddət: Ayarlar → Sifariş və çatdırılma (pay_link_hours).
 */
class OrderPayLinkService
{
    private const TOKEN_LENGTH = 10;
    public const DEFAULT_HOURS = 72;

    public function __construct(private SmsService $sms) {}

    /** Linkin müddəti (saat) — admin ayarı */
    public function hours(): int
    {
        return max(1, (int) Setting::valueOf('pay_link_hours', self::DEFAULT_HOURS));
    }

    public function ensureToken(Order $order): string
    {
        if ($order->pay_token) {
            // Müddət sütunundan əvvəl yaranmış link: müddət bu andan sayılır
            if (!$order->pay_token_expires_at) {
                $this->renew($order);
            }

            return $order->pay_token;
        }
        do {
            $token = Str::random(self::TOKEN_LENGTH);
        } while (Order::where('pay_token', $token)->exists());

        $order->forceFill(['pay_token' => $token, 'pay_token_expires_at' => now()->addHours($this->hours())])->save();

        return $token;
    }

    /** Müddəti bu andan yenidən sayır (link eyni qalır) */
    public function renew(Order $order): void
    {
        $order->forceFill(['pay_token_expires_at' => now()->addHours($this->hours())])->save();
    }

    public function isExpired(Order $order): bool
    {
        return !$order->pay_token_expires_at || $order->pay_token_expires_at->isPast();
    }

    public function url(Order $order): string
    {
        // Müştəriyə gedən link qısa domenlədir (paf.az) — App\Support\ShortUrl
        return ShortUrl::route('pay.link', $this->ensureToken($order));
    }

    /**
     * Linki müştərinin nömrəsinə SMS ilə göndərir.
     * Eyni sifarişə 60 saniyədə bir dəfə (operator düyməni təkrar bassa, iki SMS getməsin).
     */
    public function sendSms(Order $order): void
    {
        $order->loadMissing(['paymentMethod', 'customer']);
        if (!$order->canStartOnlinePayment()) {
            throw new RuntimeException('Bu sifariş üçün ödəniş linki aktiv deyil.');
        }
        $mobile = $order->customer?->mobile;
        if (!$mobile) {
            throw new RuntimeException('Müştərinin mobil nömrəsi yoxdur.');
        }

        $key = 'pay-link-sms:' . $order->id;
        if (!Cache::add($key, true, now()->addSeconds(60))) {
            throw new RuntimeException('SMS artıq göndərilib. 1 dəqiqə sonra yenidən cəhd edin.');
        }

        // SMS gedən link yenidən tam müddətə aktiv olur
        $this->ensureToken($order);
        $this->renew($order);

        $values = ['order_no' => $order->order_no, 'link' => $this->url($order)];
        $message = SmsTemplate::message('order_payment_link', $values)
            ?? "Parfumshop: {$values['order_no']} sifarisiniz ucun odenis linki: {$values['link']}";

        try {
            $this->sms->send($mobile, $message);
        } catch (\Throwable $e) {
            Cache::forget($key);
            throw $e;
        }
    }
}
