<?php

namespace App\Services;

use App\Models\Order\Order;
use App\Models\Procurement\OrderItemAllocation;
use App\Models\Procurement\Warehouse;
use App\Models\Procurement\WarehouseRequest;
use App\Models\SmsLog;
use App\Models\SmsTemplate;
use App\Services\Telegram\TelegramWarehouseBot;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Throwable;

/**
 * Anbar bildirişləri. Anbarın sorğu tipinə görə: SMS (lsim; hər SMS-də portal linki /w/{token}, 7 gün)
 * və ya Telegram (anbarın qrupuna düyməli mesaj — TelegramWarehouseBot). Jurnal hər ikisində sms_logs-dadır.
 *  - sorğu yaradılanda   → "N məhsul üçün sorğu var";
 *  - təklif seçiləndə    → "məhsul sizdən seçildi, rezerv edin" + status "Anbara bildirildi";
 *  - seçim ləğv olunanda → "seçim ləğv edildi" (yalnız anbar artıq xəbərdar idisə).
 * SMS DB tranzaksiyasından SONRA göndərilir; alınmasa əməliyyat qalır, jurnalda "failed" olur
 * və operator "SMS-i təkrar göndər" edə bilir. Telefonu olmayan anbar — null (SMS yoxdur).
 */
class WarehouseNotifier
{
    public const SUBJECT_REQUEST = 'warehouse_request';
    public const SUBJECT_ALLOCATION = 'allocation';

    public function __construct(private SmsService $sms, private WarehousePortalService $portal) {}

    public function notifyRequest(WarehouseRequest $request, int $actor): ?SmsLog
    {
        $warehouse = $request->warehouse;
        if (!$this->reachable($warehouse)) {
            return null;
        }
        if ($this->viaTelegram($warehouse)) {
            return app(TelegramWarehouseBot::class)->sendRequest($request, $actor);
        }
        $values = ['count' => $request->items()->count(), 'link' => $this->portal->issue($warehouse, $actor), 'warehouse' => $warehouse->name_az];
        $message = SmsTemplate::message('warehouse_request', $values)
            ?? 'Parfumshop: '.$values['count'].' mehsul ucun sorgu var. Movcudlugu ve qiymeti yazin: '.$values['link'];

        return $this->deliver($warehouse, $message, 'warehouse_request', self::SUBJECT_REQUEST, $request->id, $actor);
    }

    /** Seçim barədə SMS; uğurludursa "Seçilib" → "Anbara bildirildi". */
    public function notifySelected(Order $order, OrderItemAllocation $allocation, int $actor): ?SmsLog
    {
        $allocation->loadMissing(['warehouse', 'orderItem.product', 'orderItem.variant.size']);
        $warehouse = $allocation->warehouse;
        if (!$this->reachable($warehouse)) {
            return null;
        }
        if ($this->viaTelegram($warehouse)) {
            $log = app(TelegramWarehouseBot::class)->sendSelected($allocation, $actor);
            $this->markNotified($order, $allocation, $log, $actor, 'Telegram göndərildi');

            return $log;
        }
        $values = [
            'product' => $this->productLabel($allocation), 'quantity' => $allocation->quantity,
            'link' => $this->portal->issue($warehouse, $actor).'?tab=selected', 'warehouse' => $warehouse->name_az,
        ];
        $message = SmsTemplate::message('warehouse_selected', $values)
            ?? 'Parfumshop: '.$values['product'].' x'.$values['quantity'].' sizden secildi. Rezerv edib tesdiqleyin: '.$values['link'];
        $log = $this->deliver($warehouse, $message, 'warehouse_selected', self::SUBJECT_ALLOCATION, $allocation->id, $actor);
        $this->markNotified($order, $allocation, $log, $actor, 'SMS göndərildi');

        return $log;
    }

    /** Bildiriş getdisə: "Seçilib" → "Anbara bildirildi" */
    private function markNotified(Order $order, OrderItemAllocation $allocation, SmsLog $log, int $actor, string $note): void
    {
        if ($log->isSent() && $allocation->status === OrderItemAllocation::SELECTED) {
            rescue(fn () => app(ProcurementService::class)
                ->transition($order, $allocation->id, OrderItemAllocation::NOTIFIED, ['note' => $note], $actor), report: false);
        }
    }

    public function notifyCancelled(OrderItemAllocation $allocation, int $actor): ?SmsLog
    {
        $allocation->loadMissing(['warehouse', 'orderItem.product', 'orderItem.variant.size']);
        $warehouse = $allocation->warehouse;
        if (!$this->reachable($warehouse)) {
            return null;
        }
        if ($this->viaTelegram($warehouse)) {
            return app(TelegramWarehouseBot::class)->sendCancelled($allocation, $actor);
        }
        $values = ['product' => $this->productLabel($allocation), 'quantity' => $allocation->quantity, 'warehouse' => $warehouse->name_az];
        $message = SmsTemplate::message('warehouse_cancelled', $values)
            ?? 'Parfumshop: '.$values['product'].' x'.$values['quantity'].' secimi legv edildi, rezerv lazim deyil.';

        return $this->deliver($warehouse, $message, 'warehouse_cancelled', self::SUBJECT_ALLOCATION, $allocation->id, $actor);
    }

    /**
     * Sifariş səhifəsi üçün hər sorğu/seçim üzrə son SMS.
     *
     * @return array{request: Collection<int, SmsLog>, allocation: Collection<int, SmsLog>}
     */
    public function lastLogs(iterable $requestIds, iterable $allocationIds): array
    {
        $load = fn (string $type, iterable $ids) => collect($ids)->isEmpty() ? collect()
            : SmsLog::where('subject_type', $type)->whereIn('subject_id', collect($ids)->all())
                ->orderBy('id')->get()->keyBy('subject_id'); // keyBy — sonuncu qalır

        return ['request' => $load(self::SUBJECT_REQUEST, $requestIds), 'allocation' => $load(self::SUBJECT_ALLOCATION, $allocationIds)];
    }

    /** Flash mesajı üçün qısa nəticə: "SMS: 2 anbara göndərildi; alınmadı: X (balans bitib)". */
    public static function summary(iterable $results): string
    {
        $sent = 0;
        $parts = [];
        $channels = [];
        foreach ($results as $name => $log) {
            $channels[$log?->channel ?? 'sms'] = true;
            if ($log === null) {
                $parts[] = $name.' — telefon yoxdur';
            } elseif ($log->isSent()) {
                $sent++;
            } else {
                $parts[] = $name.' — '.$log->error;
            }
        }
        // Bütün anbarlar Telegram-dadırsa "Telegram", qarışıqdırsa "Bildiriş", əks halda "SMS"
        $word = isset($channels['telegram']) ? (count($channels) > 1 ? 'Bildiriş' : 'Telegram') : 'SMS';
        $text = $sent ? $word.' '.$sent.' anbara göndərildi.' : '';

        return trim($text.($parts ? ' '.$word.' getmədi: '.implode('; ', $parts).'.' : ''));
    }

    private function reachable(?Warehouse $warehouse): bool
    {
        return $warehouse && $warehouse->active
            && trim((string) ($this->viaTelegram($warehouse) ? $warehouse->telegram_id : $warehouse->phone)) !== '';
    }

    /** Sorğu tipi Telegram-dırsa (ID olmadan seçilə bilmir — yoxlama anbar formasındadır) */
    private function viaTelegram(Warehouse $warehouse): bool
    {
        return $warehouse->request_channel === 'telegram';
    }

    private function productLabel(OrderItemAllocation $allocation): string
    {
        $item = $allocation->orderItem;
        $label = trim(($item?->product?->name ?? 'Mehsul').' '.($item?->variant?->size?->name_az ?? ''));

        return Str::limit(Str::ascii($label), 40, '');
    }

    private function deliver(Warehouse $warehouse, string $message, string $context, string $type, int $id, int $actor): SmsLog
    {
        $log = ['context' => $context, 'subject_type' => $type, 'subject_id' => $id, 'msisdn' => $warehouse->phone,
            'message' => $message, 'created_by' => $actor ?: null, 'created_at' => now()];
        try {
            $log += ['status' => SmsLog::SENT, 'provider_id' => $this->sms->send($warehouse->phone, $message)];
        } catch (Throwable $e) {
            report($e);
            $log += ['status' => SmsLog::FAILED, 'error' => Str::limit($e->getMessage(), 250)];
        }

        return SmsLog::create($log);
    }
}
