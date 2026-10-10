<?php

namespace App\Services;

use App\Models\Order\Order;
use App\Models\Order\OrderItem;
use App\Models\Procurement\OrderItemAllocation;
use App\Services\Payment\PaymentItemsBuilder;
use Illuminate\Support\Collection;

/**
 * CRM sifariş səhifəsinin "Proses" tabı üçün: hər məhsul hansı mərhələdədir və operator indi nə etməlidir.
 *
 * Məhsul qrupları (səhifədə bu sıra ilə göstərilir):
 *  - choose   — anbar cavabı gəlib, seçim gözləyir (işin özü — ən yuxarıda);
 *  - waiting  — hələ istifadə edilə bilən cavab yoxdur (sorğu göndərilməyib və ya cavab gözlənilir);
 *  - selected — lazım olan miqdar anbarlara seçilib (kart yığılır; problemli olanlar qrupun əvvəlində).
 */
class OrderProcessSummary
{
    public const GROUPS = [
        'choose' => 'Cavab gəlib, seçim gözləyir',
        'waiting' => 'Cavab gözləyir',
        'selected' => 'Anbar seçilib',
    ];

    public function __construct(private WarehouseNotifier $notifier, private PaymentItemsBuilder $shares)
    {
    }

    /**
     * @param  Collection<int, \App\Models\Procurement\WarehouseRequest>  $requests  (items.offers yüklənmiş)
     * @return array{
     *   items: array<int, array{group: string, problem: bool, sms_failed: bool, has_request: bool, missing: int,
     *     sale_unit: ?float, promo_unit: float, margin: ?float, margin_basis: ?string}>,
     *   order: list<int>, counts: array<string, int>, no_margin: int,
     *   tone: string, headline: string, detail: ?string, last: ?array{at: \Illuminate\Support\Carbon, by: ?string, text: string}
     * }
     */
    public function for(Order $order, Collection $requests, Collection $staff, ?string $courierBlock = null, ?string $startBlock = null): array
    {
        $order->loadMissing(['items.allocations.warehouse', 'items.allocations.logs', 'items.product', 'status', 'statusLogs.status']);
        $allocationIds = $order->items->flatMap->allocations->pluck('id');
        $sms = $this->notifier->lastLogs($requests->pluck('id'), $allocationIds);

        // order_item_id => sorğu sətirləri
        $requestItems = $requests->flatMap->items->groupBy('order_item_id');

        // Xalis satış: vahid qiymət − məhsula düşən promo payı (ödəniş sətirləri və ləğv ilə eyni paylama)
        $shares = collect($this->shares->itemShares($order))->keyBy(fn ($row) => $row['item']->id);

        $items = [];
        foreach ($order->items as $item) {
            if ($item->activeQuantity() <= 0) {
                continue;
            }
            $active = $item->allocations->whereNotIn('status', OrderItemAllocation::SUPPLY_INACTIVE);
            $missing = max(0, $item->activeQuantity() - (int) $active->sum('quantity'));
            $rows = $requestItems->get($item->id, collect());
            $costs = $rows->map(function ($requestItem) {
                $offer = $requestItem->offers->sortByDesc('id')->first();

                return $offer && $offer->available_quantity > 0 && $offer->unit_cost !== null ? (float) $offer->unit_cost : null;
            })->filter(fn ($cost) => $cost !== null);
            $usable = $costs->isNotEmpty();

            // Qazanc: anbar seçilibsə seçilən hissələrin cəmi, seçilməyibsə ən ucuz təklifin 1 ədədi üzrə.
            // Çatdırılma xərci və bank komissiyası hesaba girmir.
            $share = $shares->get($item->id);
            $saleUnit = $share && $share['goods'] > 0 ? ($share['goods'] - $share['promo']) / $share['quantity'] / 100 : null;
            $margin = $basis = null;
            if ($saleUnit !== null && $active->isNotEmpty()) {
                $margin = round($active->sum(fn (OrderItemAllocation $a) => ($saleUnit - (float) $a->unit_cost) * $a->quantity), 2);
                $basis = 'selected';
            } elseif ($saleUnit !== null && $usable) {
                $margin = round($saleUnit - $costs->min(), 2);
                $basis = 'offer';
            }
            $smsFailed = $active->contains(fn (OrderItemAllocation $a) => $a->status === OrderItemAllocation::SELECTED
                && ($log = $sms['allocation'][$a->id] ?? null) && !$log->isSent());

            $items[$item->id] = [
                'group' => $missing === 0 ? 'selected' : ($usable ? 'choose' : 'waiting'),
                'problem' => $active->contains('status', OrderItemAllocation::PROBLEM),
                'sms_failed' => $smsFailed,
                'has_request' => $rows->isNotEmpty(),
                'missing' => $missing,
                'sale_unit' => $saleUnit,
                'promo_unit' => $share ? $share['promo'] / $share['quantity'] / 100 : 0.0,
                'margin' => $margin,
                'margin_basis' => $basis,
            ];
        }

        // Sıra: qrup → (seçilənlərdə) problemli/SMS-i getməyən əvvəl → sifarişdəki sıra
        $position = array_flip(array_keys($items));
        $order_ = array_keys($items);
        usort($order_, function (int $a, int $b) use ($items, $position) {
            $rank = fn (int $id) => [
                array_search($items[$id]['group'], array_keys(self::GROUPS), true),
                $items[$id]['problem'] || $items[$id]['sms_failed'] ? 0 : 1,
                $position[$id],
            ];

            return $rank($a) <=> $rank($b);
        });

        $counts = array_count_values(array_column($items, 'group')) + ['choose' => 0, 'waiting' => 0, 'selected' => 0];

        return [
            'items' => $items,
            'order' => $order_,
            'counts' => $counts,
            'no_margin' => count(array_filter($items, fn ($i) => $i['margin'] !== null && $i['margin'] <= 0)),
            'last' => $this->lastAction($order, $requests, $staff),
        ] + $this->headline($order, $items, $counts, $courierBlock, $startBlock);
    }

    /** @return array{tone: string, headline: string, detail: ?string} */
    private function headline(Order $order, array $items, array $counts, ?string $courierBlock, ?string $startBlock): array
    {
        $status = $order->status?->code;
        $names = fn (string $group) => $order->items->whereIn('id', array_keys(array_filter($items, fn ($i) => $i['group'] === $group)))
            ->map(fn (OrderItem $item) => $item->product?->name ?? 'Silinmiş məhsul')->implode(', ');
        $problems = count(array_filter($items, fn ($i) => $i['problem']));
        $smsFailed = count(array_filter($items, fn ($i) => $i['sms_failed']));
        $side = collect([
            $problems ? $problems.' seçimdə problem var' : null,
            $smsFailed ? $smsFailed.' seçimdə anbara SMS getməyib' : null,
        ])->filter()->implode(' · ') ?: null;

        if ($order->isCancelled()) {
            return ['tone' => 'muted', 'headline' => 'Sifariş ləğv edilib', 'detail' => null];
        }
        if ($status === 'delivered') {
            return ['tone' => 'success', 'headline' => 'Sifariş təhvil verilib', 'detail' => null];
        }
        if ($status === 'new') {
            return $startBlock
                ? ['tone' => 'warning', 'headline' => 'Sifariş hələ icraya götürülə bilməz', 'detail' => $startBlock]
                : ['tone' => 'warning', 'headline' => 'Sifarişi icraya götürün', 'detail' => 'Anbarlara sorğu yalnız icraya götürüləndən sonra göndərilir — yuxarıdakı "İcraya götür" düyməsi.'];
        }
        if (in_array($status, OrderStatusService::PROCUREMENT, true)) {
            if ($problems) {
                return ['tone' => 'danger', 'headline' => $problems.' seçimdə anbar problemi var — həll edin', 'detail' => $this->pending($counts, $smsFailed)];
            }
            if ($counts['choose']) {
                return ['tone' => 'warning', 'headline' => $counts['choose'].' məhsul üçün anbar seçilməlidir: '.$names('choose'),
                    'detail' => collect(['Cavablar gəlib.', $counts['waiting'] ? $counts['waiting'].' məhsul hələ cavab gözləyir.' : null, $smsFailed ? $smsFailed.' seçimdə anbara SMS getməyib.' : null])->filter()->implode(' ')];
            }
            if ($counts['waiting']) {
                $noRequest = count(array_filter($items, fn ($i) => $i['group'] === 'waiting' && !$i['has_request']));

                return $noRequest
                    ? ['tone' => 'warning', 'headline' => $noRequest.' məhsul üçün anbarlara sorğu göndərilməyib: '.$names('waiting'), 'detail' => '"Yeni sorğu" ilə anbarlardan qiymət soruşun.']
                    : ['tone' => 'info', 'headline' => $counts['waiting'].' məhsul üçün anbar cavabı gözlənilir: '.$names('waiting'), 'detail' => 'Cavab telefonla gəlibsə, "Cavab daxil et" ilə özünüz yazın.'.($side ? ' '.$side.'.' : '')];
            }
            if ($smsFailed) {
                return ['tone' => 'danger', 'headline' => $smsFailed.' seçimdə anbara SMS getməyib', 'detail' => 'Anbar seçimdən xəbərsizdir — nömrəni düzəldib SMS-i yenidən göndərin və ya telefonla bildirin.'];
            }

            return $courierBlock
                ? ['tone' => 'info', 'headline' => 'Bütün məhsullar üçün anbar seçilib', 'detail' => $courierBlock]
                : ['tone' => 'success', 'headline' => 'Bütün məhsullar üçün anbar seçilib — kuryer təyin edə bilərsiniz', 'detail' => null];
        }

        // Kuryer mərhələləri
        $courier = $order->courier?->full_name;
        $headline = match ($status) {
            'courier_assigned' => 'Kuryer təyin olunub'.($courier ? ': '.$courier : '').' — malları anbarlardan götürür',
            'sent' => 'Kuryer yoldadır'.($courier ? ': '.$courier : ''),
            'at_address' => 'Kuryer ünvandadır'.($courier ? ': '.$courier : ''),
            default => $order->status?->name_az ?? '—',
        };

        return ['tone' => $problems ? 'danger' : 'info', 'headline' => $headline, 'detail' => $side];
    }

    private function pending(array $counts, int $smsFailed): ?string
    {
        return collect([
            $counts['choose'] ? $counts['choose'].' məhsul seçim gözləyir.' : null,
            $counts['waiting'] ? $counts['waiting'].' məhsul cavab gözləyir.' : null,
            $smsFailed ? $smsFailed.' seçimdə anbara SMS getməyib.' : null,
        ])->filter()->implode(' ') ?: null;
    }

    /** İşin ortasında girən operator üçün: son əməliyyatı kim, nə vaxt, nə edib */
    private function lastAction(Order $order, Collection $requests, Collection $staff): ?array
    {
        $who = fn ($id) => $id === 0 || $id === '0' ? 'Anbar (link)' : ($id ? ($staff[$id] ?? null) : null);
        $events = collect();

        foreach ($order->statusLogs as $log) {
            $events->push(['at' => $log->created_at, 'by' => $who($log->user_id), 'text' => $log->status?->name_az ?? 'Status dəyişdi']);
        }
        foreach ($requests as $request) {
            $events->push(['at' => $request->created_at, 'by' => $who($request->created_by), 'text' => $request->warehouse?->name_az.' — sorğu göndərildi']);
            foreach ($request->items as $requestItem) {
                foreach ($requestItem->offers as $offer) {
                    $events->push(['at' => $offer->created_at, 'by' => $who($offer->recorded_by), 'text' => $request->warehouse?->name_az.' cavab verdi']);
                }
            }
        }
        foreach ($order->items->flatMap->allocations as $allocation) {
            foreach ($allocation->logs as $log) {
                $events->push(['at' => $log->created_at, 'by' => $who($log->user_id),
                    'text' => $allocation->warehouse?->name_az.' — '.mb_strtolower(OrderItemAllocation::LABELS[$log->to_status] ?? (string) $log->to_status)]);
            }
        }

        return $events->filter(fn ($e) => $e['at'])->sortBy('at')->last();
    }
}
