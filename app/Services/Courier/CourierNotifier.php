<?php

namespace App\Services\Courier;

use App\Models\Courier\CourierNotification;
use App\Models\Finance\MoneyMovement;
use App\Models\Order\Order;
use App\Models\User;
use App\Services\PushService;

/**
 * Kuryerə bildiriş: kuryer tətbiqindəki "Bildirişlər"ə yazılır və push (OneSignal, "user-{id}") göndərilir.
 * Telegram bildirişi ayrıca qalır (CourierTelegram). Basanda — sifariş (və ya Hesab) açılır, bildiriş oxunmuş olur.
 * Xəta əməliyyatı pozmur (çağıran yerlər rescue ilə).
 */
class CourierNotifier
{
    public function __construct(private PushService $push) {}

    /** Sifariş kuryerə təyin olundu və ya ona ötürüldü */
    public function orderAssigned(Order $order, User $courier, ?User $from = null): CourierNotification
    {
        $order->loadMissing(['customer', 'address']);

        return $this->notify($courier, $from ? 'order_transferred' : 'order_assigned',
            $from ? 'Sifariş sizə ötürüldü: '.$order->order_no : 'Yeni sifariş: '.$order->order_no,
            collect([
                trim($order->customer?->name.' '.$order->customer?->surname),
                $order->address?->shortLine(),
                $from ? trim($from->full_name).' ötürdü' : null,
            ])->filter()->implode(' · '),
            $order);
    }

    /** Baş kuryer sifarişi bu kuryerdən başqasına ötürdü */
    public function orderTakenAway(Order $order, User $courier, User $to, User $head): CourierNotification
    {
        return $this->notify($courier, 'order_taken', 'Sifariş sizdən götürüldü: '.$order->order_no,
            trim($head->full_name).' sifarişi '.trim($to->full_name).' adlı kuryerə ötürdü', $order, route('kuryer.home', [], false));
    }

    /** Baş kuryer kuryerin balansından pul götürdü */
    public function moneyCollected(MoneyMovement $movement, User $courier, User $head, int $balanceAfterCents): CourierNotification
    {
        return $this->notify($courier, 'money_collected',
            trim($head->full_name).' balansınızdan '.number_format((float) $movement->amount, 2).' AZN götürdü',
            'Qalıq: '.($balanceAfterCents < 0 ? '−' : '').number_format(abs($balanceAfterCents) / 100, 2).' AZN'
                .($movement->note ? ' · '.$movement->note : ''),
            null, route('kuryer.account', [], false));
    }

    /** Komandadakı kuryer xərc yazdı — baş kuryerə (basanda kuryerin səhifəsindəki "Xərclər") */
    public function expenseAdded(MoneyMovement $expense, User $courier, User $head): CourierNotification
    {
        return $this->notify($head, 'expense_added',
            trim($courier->full_name).': '.number_format((float) $expense->amount, 2).' AZN xərc',
            (string) $expense->note, null, route('kuryer.team.show', $courier, false).'#expenses');
    }

    /** Xərc ləğv edildi — kuryerə (pul balansına qayıtdı) */
    public function expenseCancelled(MoneyMovement $expense, User $courier, User $by): CourierNotification
    {
        return $this->notify($courier, 'expense_cancelled',
            'Xərciniz ləğv edildi: '.number_format((float) $expense->amount, 2).' AZN',
            collect([$expense->note, trim($by->full_name).' ləğv etdi'])->filter()->implode(' · '),
            null, route('kuryer.account', [], false).'#expenses');
    }

    private function notify(User $courier, string $type, string $title, string $body, ?Order $order, ?string $url = null): CourierNotification
    {
        // Bazada nisbi link (domen / APP_URL-dən asılı deyil)
        $url ??= $order ? route('kuryer.order', $order, false) : route('kuryer.home', [], false);
        $notification = CourierNotification::create([
            'user_id' => $courier->id, 'order_id' => $order?->id, 'type' => $type,
            'title' => mb_substr($title, 0, 150), 'body' => $body !== '' ? mb_substr($body, 0, 255) : null,
            'url' => $url, 'created_at' => now(),
        ]);
        // Push-a basanda bildiriş oxunmuş olsun — bildirişin öz linki ilə
        // badge — oxunmamışların sayı: service worker tətbiqin ikonuna push-dan yazır (serverə sorğusuz)
        $this->push->toUsers([$courier->id], $notification->title, $notification->body ?? 'Açın', route('kuryer.notifications.open', $notification),
            ['badge' => CourierNotification::unreadCount($courier->id)]);

        return $notification;
    }
}
