<?php

namespace App\Services\Payment;

use App\Mail\OrderCreatedMail;
use App\Models\Order\Order;
use App\Models\Payment\Payment;
use App\Models\PromoCode;
use App\Services\FinanceService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Birbank ödənişinin bankdakı nəticəsini bazaya yazır: ödəniş → sifariş → promo kod, kassa, email.
 * Həm müştəri bankdan qayıdanda (callback), həm də qayıtmayanda (payments:check-birbank) eyni yol işləyir.
 * Təkrar çağırış təhlükəsizdir: sifariş yalnız bir dəfə "paid" olur.
 */
class BirbankPaymentSync
{
    public function __construct(private Birbank $birbank)
    {
    }

    /** Bankdan nəticəni soruşur və sifarişə tətbiq edir */
    public function sync(Payment $payment, string $locale = 'az'): Payment
    {
        $payment = $this->birbank->verify($payment);
        $this->applyToOrder($payment, $locale);

        return $payment;
    }

    /**
     * Bankda sifariş yaranmamış ödəniş (provider_order_id yoxdur): müştəri ödəniş səhifəsinə heç yönləndirilməyib,
     * bankdan soruşmağa ID də yoxdur — uğursuz sayılır ki, sifarişi bloklamasın.
     */
    public function failUnstarted(Payment $payment): Payment
    {
        $payment = DB::transaction(function () use ($payment) {
            $locked = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === Payment::PENDING && !$locked->provider_order_id) {
                $locked->update([
                    'status' => Payment::FAILED,
                    'response_text' => trim(($locked->response_text ? $locked->response_text.' ' : '').'— bankda sifariş yaranmayıb'),
                ]);
            }

            return $locked->refresh();
        });

        $this->applyToOrder($payment);

        return $payment;
    }

    /**
     * Sifarişin gözləyən ödənişlərini bankdan yoxlayır və müştərinin nə edə biləcəyini qaytarır:
     *  - none     — gözləyən ödəniş yoxdur;
     *  - paid     — ödəniş bankda tamamlanıb (sifariş artıq "ödənilib");
     *  - released — əvvəlki cəhd bağlanıb (uğursuz / ləğv / vaxtı bitib), yeni ödənişə başlamaq olar;
     *  - resume   — ödəniş bankda hələ açıqdır: müştəri EYNİ bank səhifəsinə qaytarılır (url), yeni ödəniş yaranmır;
     *  - blocked  — nəticə bəlli deyil (bank cavab vermir və ya əməliyyat icradadır), gözləmək lazımdır.
     *
     * @return array{state: string, url?: string}
     */
    public function resumePending(Order $order, string $locale = 'az'): array
    {
        $pending = $order->payments()->where('provider', 'birbank')->where('status', Payment::PENDING)->orderBy('id')->get();

        if ($pending->isEmpty()) {
            return ['state' => 'none'];
        }

        $open = null;
        $unknown = false;

        foreach ($pending as $payment) {
            try {
                $result = $payment->provider_order_id ? $this->sync($payment, $locale) : $this->failUnstarted($payment);
            } catch (\Throwable $exception) {
                report($exception);
                $unknown = true;
                continue;
            }

            if ($result->status === Payment::PAID) {
                return ['state' => 'paid'];
            }
            if ($result->status === Payment::PENDING) {
                $open = $result;
            }
        }

        if ($open === null) {
            return ['state' => $unknown ? 'blocked' : 'released'];
        }

        // "Preparing" — bankda hələ heç bir əməliyyat aparılmayıb; yalnız bu halda eyni səhifəyə qayıtmaq təhlükəsizdir
        $url = $open->response_text === 'Preparing' ? $this->birbank->resumeUrl($open) : null;

        return $url ? ['state' => 'resume', 'url' => $url] : ['state' => 'blocked'];
    }

    public function applyToOrder(Payment $payment, string $locale = 'az'): void
    {
        DB::transaction(function () use ($payment, $locale) {
            $order = Order::whereKey($payment->order_id)->lockForUpdate()->firstOrFail();

            if ($payment->status === Payment::PAID && $order->payment_status !== 'paid') {
                $order->update(['payment_status' => 'paid']);
                if ($order->promo_code_id) {
                    PromoCode::whereKey($order->promo_code_id)->increment('used_count');
                }
                // Kassa: Müştəri → Onlayn ödənişlər (xəta ödənişi pozmasın)
                rescue(fn () => app(FinanceService::class)->recordOnlinePayment($payment));
                if (filter_var($order->customer?->email, FILTER_VALIDATE_EMAIL)) {
                    Mail::to($order->customer->email)->queue(
                        (new OrderCreatedMail($order, $locale))->afterCommit()
                    );
                }
            } elseif ($payment->status === Payment::FAILED && $order->payment_status !== 'paid') {
                $order->update(['payment_status' => 'failed']);
            } elseif ($payment->status === Payment::CANCELLED && $order->payment_status !== 'paid') {
                $order->update(['payment_status' => 'cancelled']);
            }
        });
    }
}
