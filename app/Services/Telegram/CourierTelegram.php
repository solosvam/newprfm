<?php

namespace App\Services\Telegram;

use App\Models\Finance\MoneyMovement;
use App\Models\Order\Order;
use App\Models\SmsLog;
use App\Models\User;
use Illuminate\Support\Str;
use Throwable;

/**
 * Kuryerlərə Telegram bildirişləri:
 *  - sifariş təyin olundu / ötürüldü — yalnız kuryerlərin ümumi qrupuna, kuryerin adı ilə (şəxsi mesaj getmir);
 *  - şəxsi Telegram-a (users.telegram_id; bot ilə bir dəfə /start yazılmalıdır): sifariş ondan götürüldü, baş kuryer balansındakı pulu köçürdü.
 * Jurnal — sms_logs (channel = telegram). Xəta kuryerin / operatorun əməliyyatını pozmur.
 */
class CourierTelegram
{
    public function __construct(private TelegramApi $api)
    {
    }

    /**
     * Kuryerlərin ümumi Telegram qrupuna (TELEGRAM_COURIER_GROUP_ID): "Filankəs Filankəsov, sizə sifariş təyin olundu".
     * Bot qrupa əlavə olunmalıdır. Qrup yazılmayıbsa heç nə göndərilmir.
     */
    public function groupAssigned(Order $order, User $courier, ?User $from, int $actor): ?SmsLog
    {
        $chat = trim((string) config('services.telegram.courier_group_id'));
        if ($chat === '') {
            return null;
        }
        $text = $from
            ? '🔁 <b>'.e(trim($courier->full_name)).'</b>, sizə sifariş ötürüldü: '.e($order->order_no).' ('.e(trim($from->full_name)).' ötürdü)'
            : '📦 <b>'.e(trim($courier->full_name)).'</b>, sizə sifariş təyin olundu: '.e($order->order_no);

        // Yalnız mətn — "Sifarişi aç" düyməsi yoxdur (kuryer sifarişi tətbiqdən açır)
        return $this->sendTo($chat, $text, 'courier_group', $order->getMorphClass(), $order->id, $actor);
    }

    /** Baş kuryer kuryerin sifarişini başqasına ötürdü — köhnə kuryerə (sifariş onun siyahısından çıxdı) */
    public function orderTakenAway(Order $order, User $courier, User $to, User $head): ?SmsLog
    {
        $text = '↪️ <b>'.e(trim($head->full_name)).' '.e($order->order_no).' sifarişini sizdən '.e(trim($to->full_name)).' adlı kuryerə ötürdü</b>';

        return $this->send($courier, $text, 'courier_order', $order->getMorphClass(), $order->id, $head->id);
    }

    public function moneyCollected(MoneyMovement $movement, User $courier, User $head, int $balanceAfterCents): ?SmsLog
    {
        $text = '💸 <b>'.e(trim($head->full_name)).' balansınızdan '.number_format((float) $movement->amount, 2).' AZN götürdü</b>'
            ."\n\nQalıq: ".($balanceAfterCents < 0 ? '−' : '').number_format(abs($balanceAfterCents) / 100, 2).' AZN'
            .($movement->note ? "\nQeyd: ".e($movement->note) : '');

        return $this->send($courier, $text, 'courier_collect', 'money_movement', $movement->id, $head->id);
    }

    public function moneyGiven(MoneyMovement $movement, User $courier, User $head, int $balanceAfterCents): ?SmsLog
    {
        $text = '💵 <b>'.e(trim($head->full_name)).' sizə '.number_format((float) $movement->amount, 2).' AZN verdi</b>'
            ."\n\nBalans: ".($balanceAfterCents < 0 ? '−' : '').number_format(abs($balanceAfterCents) / 100, 2).' AZN'
            .($movement->note ? "\nQeyd: ".e($movement->note) : '');

        return $this->send($courier, $text, 'courier_give', 'money_movement', $movement->id, $head->id);
    }

    private function send(User $to, string $text, string $context, string $subjectType, int $subjectId, int $actor, ?array $markup = null): ?SmsLog
    {
        return $this->sendTo(trim((string) $to->telegram_id), $text, $context, $subjectType, $subjectId, $actor, $markup);
    }

    private function sendTo(string $chat, string $text, string $context, string $subjectType, int $subjectId, int $actor, ?array $markup = null): ?SmsLog
    {
        if ($chat === '' || !$this->api->configured()) {
            return null;
        }
        $log = ['context' => $context, 'channel' => 'telegram', 'subject_type' => $subjectType, 'subject_id' => $subjectId,
            'msisdn' => $chat, 'message' => Str::limit(strip_tags($text), 1000), 'created_by' => $actor ?: null, 'created_at' => now()];
        try {
            $sent = $this->api->call('sendMessage', ['chat_id' => $chat, 'text' => $text, 'parse_mode' => 'HTML', 'disable_web_page_preview' => true]
                + ($markup ? ['reply_markup' => $markup] : []));
            $log += ['status' => SmsLog::SENT, 'provider_id' => (string) ($sent['message_id'] ?? '')];
        } catch (Throwable $e) {
            if (!app()->runningUnitTests()) {
                report($e);
            }
            $log += ['status' => SmsLog::FAILED, 'error' => Str::limit($e->getMessage(), 250)];
        }

        return SmsLog::create($log);
    }
}
