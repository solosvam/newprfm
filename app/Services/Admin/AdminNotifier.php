<?php

namespace App\Services\Admin;

use App\Models\Order\Order;
use App\Models\Procurement\OrderItemAllocation;
use App\Models\Procurement\WarehouseOffer;
use App\Models\User;
use App\Services\FinanceService;
use Illuminate\Support\Facades\DB;

/**
 * Operatorlara bildiriş: admin paneldə zəng işarəsi (AdminNotificationsController).
 * Sifarişlər (sayt, asan, kredit), depo (cavab, rezerv təsdiqi), kuryer (götürdü, yola çıxdı, təhvil verdi, xərc yazdı).
 * Eyni hadisə ikinci dəfə yazılmır. Bildiriş alınmasa əsas əməliyyat pozulmur.
 */
final class AdminNotifier
{
    public const TYPES = [
        'one_click' => 'Yeni asan sifariş',
        'credit' => 'Hissə-hissə müraciəti',
        'site_order' => 'Sayt sifarişi',
        'wh_answer' => 'Depo cavab verdi',
        'wh_reserved' => 'Depo rezervi təsdiqlədi',
        'courier_picked' => 'Kuryer götürdü',
        'courier_sent' => 'Kuryer yola çıxdı',
        'courier_delivered' => 'Kuryer təhvil verdi',
        'courier_expense' => 'Kuryer xərci',
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

    /** Depo sorğuya cavab verdi (link və ya Telegram) */
    public function warehouseAnswered(WarehouseOffer $offer): void
    {
        rescue(fn () => $this->offer($offer));
    }

    /** Təminat hissəsinin mərhələsi: depo özü rezervi təsdiqlədi (link / Telegram — actor 0), kuryer malı götürdü */
    public function allocationChanged(OrderItemAllocation $allocation, int $actor): void
    {
        rescue(function () use ($allocation, $actor) {
            if ($allocation->status === OrderItemAllocation::RESERVED && $actor === 0) {
                $this->allocation('wh_reserved', $allocation);
            } elseif ($allocation->status === OrderItemAllocation::PICKED && FinanceService::isCourier(User::find($actor))) {
                $this->allocation('courier_picked', $allocation, $actor);
            }
        });
    }

    /** Kuryer yola çıxdı / təhvil verdi */
    public function courier(string $type, Order $order, int $actor): void
    {
        rescue(fn () => $this->notify($type, 'order', $order->id, self::TYPES[$type],
            $this->line($order, [User::find($actor)?->full_name]), $this->orderUrl($order)));
    }

    private function offer(WarehouseOffer $offer): void
    {
        $offer->loadMissing(['requestItem.request.warehouse', 'requestItem.request.order', 'requestItem.orderItem.product']);
        $request = $offer->requestItem?->request;
        if (!$request?->order) {
            return;
        }
        $answer = $offer->available_quantity > 0
            ? 'var: '.$offer->available_quantity.' ədəd × '.number_format((float) $offer->unit_cost, 2).' ₼'
            : 'yoxdur';
        $this->notify('wh_answer', 'warehouse_offer', $offer->id, self::TYPES['wh_answer'],
            $this->line($request->order, [$request->warehouse?->name_az, $offer->requestItem->orderItem?->product?->name, $answer]),
            $this->orderUrl($request->order));
    }

    private function allocation(string $type, OrderItemAllocation $allocation, ?int $actor = null): void
    {
        $allocation->loadMissing(['warehouse', 'orderItem.order', 'orderItem.product']);
        $order = $allocation->orderItem?->order;
        if (!$order) {
            return;
        }
        $parts = [$allocation->warehouse?->name_az, $allocation->orderItem->product?->name.' × '.$allocation->quantity];
        if ($type === 'courier_picked') {
            array_unshift($parts, User::find($actor)?->full_name);
        }
        $this->notify($type, 'allocation', $allocation->id, self::TYPES[$type], $this->line($order, $parts), $this->orderUrl($order));
    }

    private function line(Order $order, array $parts): string
    {
        return implode(' · ', array_filter(array_map(fn ($p) => trim((string) $p), [$order->order_no, ...$parts]), 'filled'));
    }

    private function orderUrl(Order $order): string
    {
        return $order->customer_id
            ? route('admin.crm.order', [$order->customer_id, $order->id], false)
            : route('admin.procurement.show', $order, false);
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
