<?php

namespace App\Services\Courier;

use App\Models\Order\Order;
use App\Models\User;
use App\Services\PushService;

/**
 * Kuryerə push bildirişi (OneSignal, "user-{id}"): kuryer tətbiqində icazə veribsə telefona gəlir.
 * Telegram bildirişi ilə yanaşı göndərilir (CourierTelegram); basanda sifariş kuryer tətbiqində açılır.
 */
class CourierPush
{
    public function __construct(private PushService $push) {}

    /** Sifariş kuryerə təyin olundu və ya ona ötürüldü */
    public function orderAssigned(Order $order, User $courier, ?User $from = null): bool
    {
        $order->loadMissing(['customer', 'address']);
        $title = $from ? 'Sifariş sizə ötürüldü: '.$order->order_no : 'Yeni sifariş: '.$order->order_no;
        $body = collect([
            trim($order->customer?->name.' '.$order->customer?->surname),
            $order->address?->shortLine(),
            $from ? trim($from->full_name).' ötürdü' : null,
        ])->filter()->implode(' · ');

        return $this->push->toUsers([$courier->id], $title, $body !== '' ? $body : 'Sifarişi açın', route('kuryer.order', $order));
    }
}
