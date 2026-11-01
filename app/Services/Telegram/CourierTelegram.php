<?php

namespace App\Services\Telegram;

use App\Models\Finance\MoneyMovement;
use App\Models\Order\Order;
use App\Models\SmsLog;
use App\Models\User;
use Illuminate\Support\Str;
use Throwable;

/**
 * Kuryerin şəxsi Telegram-ına bildiriş (users.telegram_id; bot ilə bir dəfə /start yazılmalıdır):
 *  - sifariş ona təhkim olundu (operator təyin etdi və ya başqa kuryer ötürdü);
 *  - baş kuryer onun balansındakı pulu özünə köçürdü.
 * Jurnal — sms_logs (channel = telegram). Xəta kuryerin / operatorun əməliyyatını pozmur.
 */
class CourierTelegram
{
    public function __construct(private TelegramApi $api)
    {
    }

    /** $from — sifarişi ötürən kuryer (operator təyin edibsə null). Yalnız bildiriş və "Sifarişi aç" düyməsi — sifarişin məlumatı linkdədir */
    public function orderAssigned(Order $order, User $courier, ?User $from, int $actor): ?SmsLog
    {
        $text = $from
            ? '🔁 <b>'.e(trim($from->full_name)).' sizə '.e($order->order_no).' sifarişini ötürdü</b>'
            : '📦 <b>Sizə sifariş təyin olundu: '.e($order->order_no).'</b>';
        $button = ['inline_keyboard' => [[['text' => 'Sifarişi aç', 'url' => route('admin.courier.order', $order)]]]];

        return $this->send($courier, $text, 'courier_order', $order->getMorphClass(), $order->id, $actor, $button);
    }

    public function moneyCollected(MoneyMovement $movement, User $courier, User $head, int $balanceAfterCents): ?SmsLog
    {
        $text = '💸 <b>'.e(trim($head->full_name)).' balansınızdan '.number_format((float) $movement->amount, 2).' AZN götürdü</b>'
            ."\n\nQalıq: ".($balanceAfterCents < 0 ? '−' : '').number_format(abs($balanceAfterCents) / 100, 2).' AZN'
            .($movement->note ? "\nQeyd: ".e($movement->note) : '');

        return $this->send($courier, $text, 'courier_collect', 'money_movement', $movement->id, $head->id);
    }

    private function send(User $to, string $text, string $context, string $subjectType, int $subjectId, int $actor, ?array $markup = null): ?SmsLog
    {
        $chat = trim((string) $to->telegram_id);
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
