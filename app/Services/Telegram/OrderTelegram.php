<?php

namespace App\Services\Telegram;

use App\Models\Order\Order;
use App\Models\SmsLog;
use Illuminate\Support\Str;
use Throwable;

/**
 * Saytdan yeni sifariş → operatorların Telegram qrupuna (TELEGRAM_ORDERS_GROUP_ID): səbətdən sifariş, asan (bir kliklə)
 * sifariş, kredit müraciəti. Yalnız mətn — düymə / link yoxdur. Göndəriş jurnala yazılır (SmsLog, context orders_group).
 */
class OrderTelegram
{
    public function __construct(private TelegramApi $api) {}

    public function newOrder(Order $order): ?SmsLog
    {
        $chat = trim((string) config('services.telegram.orders_group_id'));
        if ($chat === '' || !$this->api->configured()) {
            return null;
        }
        // Eyni sifariş ikinci dəfə göndərilmir (AdminNotifier::order təkrar çağırılsa da)
        if (SmsLog::where('context', 'orders_group')->where('subject_type', $order->getMorphClass())->where('subject_id', $order->id)
            ->where('status', SmsLog::SENT)->exists()) {
            return null;
        }

        $log = ['context' => 'orders_group', 'channel' => 'telegram', 'subject_type' => $order->getMorphClass(), 'subject_id' => $order->id,
            'msisdn' => $chat, 'created_at' => now()];
        try {
            $text = $this->text($order);
            $log['message'] = Str::limit(strip_tags($text), 1000);
            $sent = $this->api->call('sendMessage', ['chat_id' => $chat, 'text' => $text, 'parse_mode' => 'HTML', 'disable_web_page_preview' => true]);
            $log += ['status' => SmsLog::SENT, 'provider_id' => (string) ($sent['message_id'] ?? '')];
        } catch (Throwable $e) {
            if (!app()->runningUnitTests()) {
                report($e);
            }
            $log += ['message' => $log['message'] ?? $order->order_no, 'status' => SmsLog::FAILED, 'error' => Str::limit($e->getMessage(), 250)];
        }

        return SmsLog::create($log);
    }

    public function text(Order $order): string
    {
        $order->loadMissing(['customer', 'paymentMethod', 'address', 'items.product.brand', 'items.variant.size']);
        $title = match (true) {
            (bool) $order->one_click => '⚡ <b>Asan sifariş</b>',
            $order->paymentMethod?->code === 'installment' => '📝 <b>Yeni kredit müraciəti</b>',
            default => '🛒 <b>Yeni sifariş</b>',
        };
        $name = $order->customer ? trim($order->customer->name.' '.$order->customer->surname) : 'Qonaq';
        $phone = $order->customer?->mobile ?? $order->guest_mobile;

        $lines = [$title.' · '.e($order->order_no), '👤 '.e($name).($phone ? ' · '.e($phone) : '')];
        $lines[] = '';
        foreach ($order->items as $item) {
            $product = collect([$item->product?->brand?->name, $item->product?->name ?? 'Məhsul', $item->variant?->size?->name_az])->filter()->implode(' ');
            $lines[] = '• '.e($product).' × '.$item->quantity.' — '.number_format((float) $item->unit_price * $item->quantity, 2).' AZN';
        }
        $lines[] = '';
        $payment = $order->paymentMethod?->name_az;
        $pending = in_array($order->paymentMethod?->code, Order::ONLINE_PAYMENT_CODES, true) && $order->payment_status !== 'paid';
        $lines[] = '💰 <b>'.number_format((float) $order->total, 2).' AZN</b>'.($payment ? ' · '.e($payment) : '').($pending ? ' (ödəniş gözlənilir)' : '');
        if ($order->address && !$order->address->on_call) {
            $lines[] = '📍 '.e($order->address->label);
        }
        if ($order->customer_note) {
            $lines[] = '💬 '.e(Str::limit($order->customer_note, 300));
        }

        return implode("\n", $lines);
    }
}
