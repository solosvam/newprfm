<?php

namespace App\Services;

use App\Models\Order\Order;
use App\Models\Order\OrderItem;
use App\Models\Procurement\AllocationStatusLog;
use App\Models\Procurement\OrderItemAllocation;
use App\Models\Procurement\Warehouse;
use App\Models\Procurement\WarehouseOffer;
use App\Models\Procurement\WarehouseRequest;
use App\Models\Procurement\WarehouseRequestItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProcurementService
{
    private function ensure(bool $condition, string $message): void
    {
        if (! $condition) {
            throw ValidationException::withMessages(['procurement' => $message]);
        }
    }

    // Lock the parent first in every mutation, serializing allocation and answer changes.
    private function lockOrder(Order $order, array $allowed = OrderStatusService::PROCUREMENT): Order
    {
        $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
        // Sorğu/seçim yalnız "İcraya götür"dən sonra; götürmə mərhələləri kuryer təyinindən sonra da
        $this->ensure($locked->status?->code !== 'new', 'Əvvəlcə sifarişi icraya götürün ("İcraya götür").');
        $this->ensure(in_array($locked->status?->code, $allowed, true),
            'Bu sifarişin cari mərhələsində anbar seçimi dəyişdirilə bilməz.');
        $this->ensure((bool) $locked->customer_id, 'Əvvəlcə sifarişi müştəriyə bağlayın.');

        return $locked;
    }

    /** @return \Illuminate\Support\Collection<int, WarehouseRequest> yaradılan sorğular (SMS üçün) */
    public function createRequests(Order $order, array $warehouseIds, array $itemIds, int $actor): \Illuminate\Support\Collection
    {
        return DB::transaction(function () use ($order, $warehouseIds, $itemIds, $actor) {
            $this->lockOrder($order);
            $warehouseIds = array_unique($warehouseIds);
            $itemIds = array_unique($itemIds);
            $warehouses = Warehouse::whereIn('id', $warehouseIds)->where('active', true)->get();
            $items = $order->items()->whereIn('id', $itemIds)->get();
            $this->ensure(count($warehouseIds) > 0 && $warehouses->count() === count($warehouseIds), 'Aktiv anbar seçin.');
            $this->ensure(count($itemIds) > 0 && $items->count() === count($itemIds), 'Məhsullar bu sifarişə aid olmalıdır.');
            $this->ensure($items->every(fn ($item) => $item->activeQuantity() > 0), 'Ləğv olunmuş məhsula sorğu göndərilə bilməz.');
            $created = collect();
            foreach ($warehouses as $warehouse) {
                $request = WarehouseRequest::create([
                    'order_id' => $order->id, 'warehouse_id' => $warehouse->id, 'created_by' => $actor,
                ]);
                foreach ($items as $item) {
                    $request->items()->create(['order_item_id' => $item->id, 'requested_quantity' => $item->activeQuantity()]);
                }
                $created->push($request->setRelation('warehouse', $warehouse));
            }
            app(OrderStatusService::class)->syncSupply($order, $actor);

            return $created;
        });
    }

    public function recordOffer(Order $order, int $requestItemId, array $data, int $actor): WarehouseOffer
    {
        return DB::transaction(function () use ($order, $requestItemId, $data, $actor) {
            $this->lockOrder($order);
            $item = WarehouseRequestItem::whereHas('request', fn ($q) => $q->where('order_id', $order->id))
                ->findOrFail($requestItemId);
            $quantity = (int) $data['available_quantity'];
            $this->ensure($quantity >= 0 && $quantity <= $item->requested_quantity, 'Miqdar sorğudakı saydan çox ola bilməz.');
            $this->ensure($quantity === 0 || (isset($data['unit_cost']) && is_numeric($data['unit_cost']) && $data['unit_cost'] > 0),
                'Məhsul varsa, vahid alış qiymətini daxil edin.');

            return $item->offers()->create([
                'available_quantity' => $quantity,
                'unit_cost' => $quantity > 0 ? $data['unit_cost'] : null,
                'source' => $data['source'], 'note' => $data['note'] ?? null, 'recorded_by' => $actor,
            ]);
        });
    }

    public function allocate(Order $order, int $offerId, int $quantity, int $actor, ?string $key = null): OrderItemAllocation
    {
        $key ??= (string) Str::uuid();

        return DB::transaction(function () use ($order, $offerId, $quantity, $actor, $key) {
            $this->lockOrder($order);
            $existing = OrderItemAllocation::where('idempotency_key', $key)->first();
            if ($existing) {
                $this->ensure((int) $existing->warehouse_offer_id === $offerId && (int) $existing->quantity === $quantity
                    && $order->items()->whereKey($existing->order_item_id)->exists(), 'Əməliyyat açarı başqa seçimə aiddir.');

                return $existing;
            }
            $offer = WarehouseOffer::with('requestItem.request.warehouse')->findOrFail($offerId);
            $requestItem = $offer->requestItem;
            $this->ensure((int) $requestItem->request->order_id === (int) $order->id, 'Təklif bu sifarişə aid deyil.');
            $this->ensure($requestItem->request->warehouse->active, 'Anbar deaktivdir.');
            $item = OrderItem::where('order_id', $order->id)->findOrFail($requestItem->order_item_id);
            // A new round/answer for the same warehouse and product supersedes old offers.
            $latest = WarehouseOffer::whereHas('requestItem', function ($q) use ($item, $requestItem) {
                $q->where('order_item_id', $item->id)->whereHas('request', fn ($r) => $r->where('warehouse_id', $requestItem->request->warehouse_id));
            })->max('id');
            $this->ensure((int) $latest === (int) $offer->id, 'Təklif yenilənib. Son cavabı seçin.');
            $active = $item->allocations()->where('status', '!=', 'cancelled');
            $selected = (int) (clone $active)->sum('quantity');
            $warehouseSelected = (int) (clone $active)->where('warehouse_id', $requestItem->request->warehouse_id)->sum('quantity');
            $this->ensure($quantity > 0 && $quantity <= $item->activeQuantity() - $selected, 'Seçilən say sifarişin qalan miqdarından çoxdur.');
            $this->ensure($offer->unit_cost !== null && $quantity <= $offer->available_quantity - $warehouseSelected,
                'Seçilən say anbarın təklif etdiyi qalan miqdardan çoxdur.');
            $allocation = $item->allocations()->create([
                'warehouse_id' => $requestItem->request->warehouse_id,
                'warehouse_offer_id' => $offer->id, 'quantity' => $quantity,
                'unit_cost' => $offer->unit_cost, 'status' => 'selected', 'created_by' => $actor, 'idempotency_key' => $key,
            ]);
            $allocation->logs()->create(['from_status' => null, 'to_status' => 'selected', 'user_id' => $actor, 'created_at' => now()]);
            app(OrderStatusService::class)->syncSupply($order, $actor);

            return $allocation;
        });
    }

    /**
     * Qapıda imtina edilən məhsul anbara qaytarıldı (kuryer "Qaytardım"): returning → returned.
     * Anbara borc bu hissə üzrə silinir (FinanceService::warehouseDebts yalnız götürülmüş/qaytarılmamışı sayır).
     */
    public function markReturned(Order $order, int $allocationId, int $actor): OrderItemAllocation
    {
        return DB::transaction(function () use ($order, $allocationId, $actor) {
            $this->lockOrder($order, ['sent', 'at_address', 'delivered']); // yoldan sonra (təhvildən sonra da)
            $allocation = OrderItemAllocation::with(['warehouse', 'orderItem.product'])
                ->whereIn('order_item_id', $order->items()->select('id'))->lockForUpdate()->findOrFail($allocationId);
            if ($allocation->status === OrderItemAllocation::RETURNED) {
                return $allocation; // təkrar klik
            }
            $this->ensure($allocation->status === OrderItemAllocation::RETURNING, 'Bu hissə anbara qaytarılmalı deyil.');
            $allocation->update(['status' => OrderItemAllocation::RETURNED]);
            AllocationStatusLog::create([
                'order_item_allocation_id' => $allocation->id, 'from_status' => OrderItemAllocation::RETURNING,
                'to_status' => OrderItemAllocation::RETURNED, 'user_id' => $actor, 'note' => 'Anbara qaytarıldı', 'created_at' => now(),
            ]);
            DB::table('order_status_logs')->insert([
                'order_id' => $order->id, 'status_id' => $order->fresh()->order_status_id, 'user_id' => $actor, 'kind' => 'warehouse_return',
                'note' => 'Anbara qaytarıldı: '.($allocation->warehouse?->name_az ?? 'anbar').' — '
                    .($allocation->orderItem?->product?->name ?? 'Məhsul').' ×'.$allocation->quantity,
                'created_at' => now(), 'updated_at' => now(),
            ]);

            return $allocation;
        });
    }

    /** @return string|null ləğvdən əvvəlki status (artıq ləğv olunubsa null) */
    public function cancelAllocation(Order $order, int $allocationId, string $note, int $actor): ?string
    {
        return DB::transaction(function () use ($order, $allocationId, $note, $actor) {
            $this->lockOrder($order);
            $allocation = OrderItemAllocation::whereIn('order_item_id', $order->items()->select('id'))->findOrFail($allocationId);
            if ($allocation->status === 'cancelled') {
                return null;
            }
            $this->ensure(in_array($allocation->status, OrderItemAllocation::CANCELLABLE, true), 'Götürülmüş məhsulun anbar seçimi ləğv edilə bilməz.');
            $from = $allocation->status;
            $allocation->update(['status' => 'cancelled', 'problem_type' => null]);
            AllocationStatusLog::create([
                'order_item_allocation_id' => $allocation->id, 'from_status' => $from,
                'to_status' => 'cancelled', 'user_id' => $actor, 'note' => $note, 'created_at' => now(),
            ]);
            app(OrderStatusService::class)->syncSupply($order, $actor);

            return $from;
        });
    }

    /**
     * Təminat hissəsinin növbəti mərhələsi (OrderItemAllocation::FLOW) və problemlər.
     *  - notified / reserved / picked: yalnız irəli (addım atlamaq olar — məs. telefonla dərhal "ayırdı");
     *  - problem: istənilən aktiv mərhələdən, növ və qeyd ilə;
     *  - resume: problemdən əvvəlki mərhələyə qayıdır; "qiymət dəyişdi"də yeni alış qiyməti yazılır.
     * Artıq həmin mərhələdədirsə — heç nə etmir (təkrar klik).
     */
    public function transition(Order $order, int $allocationId, string $action, array $data, int $actor): OrderItemAllocation
    {
        return DB::transaction(function () use ($order, $allocationId, $action, $data, $actor) {
            $this->lockOrder($order, OrderStatusService::SUPPLY_FLOW);
            $allocation = OrderItemAllocation::whereIn('order_item_id', $order->items()->select('id'))
                ->lockForUpdate()->findOrFail($allocationId);
            $from = $allocation->status;
            $note = isset($data['note']) && trim((string) $data['note']) !== '' ? trim((string) $data['note']) : null;
            $flow = OrderItemAllocation::FLOW;

            if (in_array($action, [OrderItemAllocation::NOTIFIED, OrderItemAllocation::RESERVED, OrderItemAllocation::PICKED], true)) {
                if ($from === $action) {
                    return $allocation;
                }
                $this->ensure(in_array($from, $flow, true) && array_search($action, $flow, true) > array_search($from, $flow, true),
                    'Bu mərhələyə keçid mümkün deyil ('.$allocation->label().').');
                $allocation->update(['status' => $action]);
            } elseif ($action === OrderItemAllocation::PROBLEM) {
                $type = (string) ($data['problem_type'] ?? '');
                $this->ensure(isset(OrderItemAllocation::PROBLEM_TYPES[$type]), 'Problemin növünü seçin.');
                $this->ensure(in_array($from, [OrderItemAllocation::SELECTED, OrderItemAllocation::NOTIFIED, OrderItemAllocation::RESERVED], true),
                    'Bu mərhələdə problem qeyd edilə bilməz.');
                $allocation->update(['status' => OrderItemAllocation::PROBLEM, 'problem_type' => $type]);
                $note = OrderItemAllocation::PROBLEM_TYPES[$type].($note ? ': '.$note : '');
            } elseif ($action === 'resume') {
                $this->ensure($from === OrderItemAllocation::PROBLEM, 'Seçimdə problem yoxdur.');
                // Problemdən əvvəlki mərhələ
                $back = AllocationStatusLog::where('order_item_allocation_id', $allocation->id)
                    ->where('to_status', OrderItemAllocation::PROBLEM)->orderByDesc('id')->value('from_status') ?? OrderItemAllocation::SELECTED;
                $changes = ['status' => $back, 'problem_type' => null];
                if ($allocation->problem_type === 'price_changed') {
                    $cost = $data['unit_cost'] ?? null;
                    $this->ensure(is_numeric($cost) && (float) $cost > 0, 'Yeni alış qiymətini daxil edin.');
                    $cost = round((float) $cost, 2);
                    $note = 'Alış qiyməti '.number_format((float) $allocation->unit_cost, 2).' → '.number_format($cost, 2).' AZN'.($note ? '. '.$note : '');
                    $changes['unit_cost'] = $cost;
                }
                $allocation->update($changes);
                $action = $back;
            } else {
                $this->ensure(false, 'Naməlum əməliyyat.');
            }

            AllocationStatusLog::create([
                'order_item_allocation_id' => $allocation->id, 'from_status' => $from, 'to_status' => $allocation->status,
                'user_id' => $actor, 'note' => $note, 'created_at' => now(),
            ]);

            return $allocation;
        });
    }
}
