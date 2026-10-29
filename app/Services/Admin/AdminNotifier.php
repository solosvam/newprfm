<?php

namespace App\Services\Admin;

use App\Models\Order\Order;
use Illuminate\Support\Facades\DB;

/**
 * Operatorlara bildiriş: admin paneldə zəng işarəsi (AdminNotificationsController).
 * Eyni hadisə ikinci dəfə yazılmır.
 */
final class AdminNotifier
{
    public const TYPES = [
        'one_click' => 'Yeni asan sifariş',
        'credit' => 'Hissə-hissə müraciəti',
        'site_order' => 'Sayt sifarişi',
    ];

    /** Saytdan gələn sifariş: növü ödəniş üsulundan və "bir kliklə"dən */
    public function order(Order $order): void
    {
        $order->loadMissing(['customer', 'paymentMethod']);
        $type = $order->one_click ? 'one_click' : ($order->paymentMethod?->code === 'installment' ? 'credit' : 'site_order');
        $who = $order->customer ? trim($order->customer->name.' '.$order->customer->surname) : 'Qonaq';
        $phone = $order->customer?->mobile ?? $order->guest_mobile;
        $body = match ($type) {
            'one_click' => "Bir kliklə · {$order->order_no} · {$who}".($phone ? " · {$phone}" : ''),
            'credit' => "Yeni kredit müraciəti · {$order->order_no} · {$who} · ".number_format((float) $order->total, 2).' ₼',
            default => "Səbətdən yeni sifariş · {$order->order_no} · {$who} · ".number_format((float) $order->total, 2).' ₼'
                .($order->paymentMethod ? ' · '.($order->paymentMethod->name_az ?? $order->paymentMethod->code) : ''),
        };
        $url = $order->customer_id
            ? route('admin.crm.order', [$order->customer_id, $order->id], false)
            : ($order->one_click ? route('admin.easy-orders.show', $order, false) : null);

        $this->notify($type, 'order', $order->id, self::TYPES[$type], $body, $url);
    }

    public function notify(string $type, string $subjectType, int $subjectId, string $title, ?string $body, ?string $url): void
    {
        // Bildiriş yazılmasa da müştərinin sifarişi pozulmamalıdır — xəta yalnız jurnala düşür
        rescue(fn () => DB::table('admin_notifications')->insertOrIgnore([
            'type' => $type, 'subject_type' => $subjectType, 'subject_id' => $subjectId,
            'title' => mb_substr($title, 0, 150), 'body' => $body !== null ? mb_substr($body, 0, 255) : null,
            'url' => $url, 'created_at' => now(),
        ]));
    }
}
