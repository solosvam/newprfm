<?php

namespace App\Services;

use App\Models\Order\Order;
use App\Models\Procurement\OrderItemAllocation;
use App\Models\Procurement\Warehouse;
use App\Models\Procurement\WarehouseAccessLink;
use App\Models\Procurement\WarehouseRequestItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WarehousePortalService
{
    public const OPEN_STATUSES = ['new', 'confirmed', 'preparing', 'warehouse_requested', 'warehouses_assigned', 'courier_assigned'];

    /**
     * Qısa link: /w/{12 simvol, a-z A-Z 0-9} — ~3×10²¹ variant, təxmin edilmir (+ throttle).
     * Bazada yalnız sha256 hash saxlanır; köhnə 64 simvolluq hex tokenlər də qəbul olunur.
     */
    public const TOKEN_PATTERN = '[A-Za-z0-9]{12}|[a-f0-9]{64}';

    public function issue(Warehouse $warehouse, int $actor): string
    {
        abort_unless($warehouse->active, 422, 'Anbar deaktivdir.');
        $token = \Illuminate\Support\Str::random(12);
        WarehouseAccessLink::create([
            'warehouse_id' => $warehouse->id, 'token_hash' => hash('sha256', $token),
            'created_by' => $actor, 'expires_at' => now()->addDays(7),
        ]);

        return route('warehouse.portal', ['token' => $token]);
    }

    public function resolve(string $token): WarehouseAccessLink
    {
        abort_unless(preg_match('/^(?:'.self::TOKEN_PATTERN.')$/D', $token), 404);
        return WarehouseAccessLink::with('warehouse')
            ->where('token_hash', hash('sha256', $token))->whereNull('revoked_at')
            ->where('expires_at', '>', now())->whereHas('warehouse', fn ($q) => $q->where('active', true))
            ->firstOrFail();
    }

    public function openItems(int $warehouseId)
    {
        return WarehouseRequestItem::whereHas('request', fn ($q) => $q->where('warehouse_id', $warehouseId))
            ->whereHas('orderItem', fn ($q) => $q->whereRaw('quantity > COALESCE(cancelled_quantity, 0)')
                ->whereHas('order', fn ($order) => $order->whereHas('status', fn ($s) => $s->whereIn('code', self::OPEN_STATUSES))));
    }

    /**
     * Anbar cavabını düzəldə bilərmi: son cavab anbarın özünündür (link) və
     * bu sorğu sətrinin cavablarından aktiv (ləğv olunmamış) anbar seçimi yoxdur.
     */
    public function canEdit(WarehouseRequestItem $item): bool
    {
        $offers = $item->relationLoaded('offers') ? $item->offers : $item->offers()->get();
        $latest = $offers->sortByDesc('id')->first();
        if (!$latest || $latest->source !== 'link') {
            return false;
        }

        return !OrderItemAllocation::whereIn('warehouse_offer_id', $offers->pluck('id'))
            ->where('status', '!=', 'cancelled')->exists();
    }

    /** Anbarın portalda seçə bildiyi problemlər (OrderItemAllocation::PROBLEM_TYPES-in alt çoxluğu) */
    public const PORTAL_PROBLEMS = [
        'not_found' => 'Məhsul artıq yoxdur',
        'price_changed' => 'Qiymət dəyişib',
        'other' => 'Digər',
    ];

    /** Anbarın təsdiqləyə biləcəyi mərhələlər: Seçilib / Anbara bildirildi */
    public const CONFIRMABLE = [OrderItemAllocation::SELECTED, OrderItemAllocation::NOTIFIED];

    /** Bu anbardan seçilən (ləğv olunmamış, götürülməmiş) hissələr — açıq sifarişlərdə */
    public function selections(int $warehouseId)
    {
        return OrderItemAllocation::where('warehouse_id', $warehouseId)
            ->whereIn('status', [...self::CONFIRMABLE, OrderItemAllocation::RESERVED, OrderItemAllocation::PROBLEM])
            ->whereHas('orderItem.order.status', fn ($q) => $q->whereIn('code', self::OPEN_STATUSES));
    }

    /**
     * Anbar portalda seçimi təsdiqləyir: "Rezerv etdim" → reserved, "Problem var" → problem.
     * Log user_id = 0 (anbarın özü). Eyni düyməni təkrar basmaq heç nə dəyişmir.
     */
    public function confirm(string $token, int $allocationId, string $action, array $data): OrderItemAllocation
    {
        $access = $this->resolve($token);
        $allocation = $this->selections($access->warehouse_id)->with('orderItem.order')->findOrFail($allocationId);
        $procurement = app(ProcurementService::class);
        $order = $allocation->orderItem->order;

        if ($action === 'reserve') {
            if ($allocation->status === OrderItemAllocation::RESERVED) {
                return $allocation;
            }
            $this->ensureConfirmable($allocation);

            return $procurement->transition($order, $allocation->id, OrderItemAllocation::RESERVED, ['note' => 'Anbar linkdə təsdiqlədi'], 0);
        }

        if ($allocation->status === OrderItemAllocation::PROBLEM) {
            return $allocation;
        }
        $this->ensureConfirmable($allocation);
        $type = (string) ($data['problem_type'] ?? '');
        if (!isset(self::PORTAL_PROBLEMS[$type])) {
            throw ValidationException::withMessages(['problem_type' => 'Problemin növünü seçin.']);
        }
        $note = trim((string) ($data['note'] ?? ''));
        if ($type === 'price_changed') {
            if (!isset($data['unit_cost']) || !is_numeric($data['unit_cost']) || $data['unit_cost'] <= 0) {
                throw ValidationException::withMessages(['unit_cost' => 'Yeni qiyməti yazın.']);
            }
            $note = 'yeni qiymət '.number_format((float) $data['unit_cost'], 2).' AZN'.($note !== '' ? '. '.$note : '');
        } elseif ($type === 'other' && $note === '') {
            throw ValidationException::withMessages(['note' => 'Nə baş verdiyini yazın.']);
        }

        return $procurement->transition($order, $allocation->id, OrderItemAllocation::PROBLEM, [
            'problem_type' => $type, 'note' => 'Anbar (link)'.($note !== '' ? ': '.$note : ''),
        ], 0);
    }

    private function ensureConfirmable(OrderItemAllocation $allocation): void
    {
        if (!in_array($allocation->status, self::CONFIRMABLE, true)) {
            throw ValidationException::withMessages(['allocation' => 'Bu seçim artıq dəyişib — səhifəni yeniləyin.']);
        }
    }

    public function answer(string $token, int $itemId, array $data): void
    {
        $access = $this->resolve($token);
        $item = $this->openItems($access->warehouse_id)->with('request')->findOrFail($itemId);
        DB::transaction(function () use ($access, $item, $token, $data) {
            // Match procurement/cancellation lock order so stale screens cannot answer closed orders.
            Order::whereKey($item->request->order_id)->lockForUpdate()->firstOrFail();
            Warehouse::whereKey($access->warehouse_id)->lockForUpdate()->firstOrFail();
            $access = $this->resolve($token);
            $item = $this->openItems($access->warehouse_id)->with('orderItem')->findOrFail($item->id);
            // İlk cavab: yalnız hələ cavab yoxdursa. Düzəliş ("replaces" = düzəldilən cavabın id-si):
            // yalnız anbarın öz son cavabı və operator ondan seçim etməyibsə. Təkrar göndərmə heç nə dəyişmir.
            $latest = $item->offers()->latest('id')->first();
            $replaces = isset($data['replaces']) ? (int) $data['replaces'] : null;
            if ($replaces === null) {
                if ($latest) {
                    return;
                }
            } else {
                if (!$latest || $latest->id !== $replaces) {
                    return;
                }
                if (!$this->canEdit($item)) {
                    throw ValidationException::withMessages(['availability' => 'Operator bu cavabdan seçim edib — dəyişiklik üçün bizimlə əlaqə saxlayın.']);
                }
            }
            $max = min($item->requested_quantity, $item->orderItem->activeQuantity());
            $quantity = match ($data['availability']) {
                'available' => $max,
                'unavailable' => 0,
                default => (int) ($data['quantity'] ?? 0),
            };
            if ($data['availability'] === 'partial' && ($quantity < 1 || $quantity >= $max)) {
                throw ValidationException::withMessages(['quantity' => 'Qismən mövcud say tələbdən az və sıfırdan böyük olmalıdır.']);
            }
            if ($quantity > 0 && (!isset($data['unit_cost']) || !is_numeric($data['unit_cost']) || $data['unit_cost'] <= 0)) {
                throw ValidationException::withMessages(['unit_cost' => 'Bir ədədin qiymətini daxil edin.']);
            }
            $item->offers()->create([
                'available_quantity' => $quantity, 'unit_cost' => $quantity ? $data['unit_cost'] : null,
                'source' => 'link', 'note' => $data['note'] ?? null,
                'recorded_by' => null, 'warehouse_access_link_id' => $access->id,
            ]);
        });
    }
}
