<?php

namespace App\Services;

use App\Models\Order\Order;
use App\Models\SmsTemplate;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * SMS ödəniş linki: operator sifarişi yaradır, müştəri linkə keçib login olmadan ödəyir.
 * Token 10 simvol (a-z, A-Z, 0-9; Str::random — kriptoqrafik): ~8×10¹⁷ variant, təxmin edilmir.
 */
class OrderPayLinkService
{
    private const TOKEN_LENGTH = 10;

    public function __construct(private SmsService $sms) {}

    public function ensureToken(Order $order): string
    {
        if ($order->pay_token) {
            return $order->pay_token;
        }
        do {
            $token = Str::random(self::TOKEN_LENGTH);
        } while (Order::where('pay_token', $token)->exists());

        $order->forceFill(['pay_token' => $token])->save();

        return $token;
    }

    public function url(Order $order): string
    {
        return route('pay.link', $this->ensureToken($order));
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
