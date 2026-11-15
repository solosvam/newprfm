<?php

namespace App\Services\Production;

use App\Models\Finance\FinanceAccount;
use App\Models\Finance\MoneyMovement;
use App\Models\Order\Order;
use App\Models\Order\OrderItem;
use App\Models\Procurement\OrderItemAllocation;
use App\Models\Procurement\Warehouse;
use App\Models\Production\MaterialPurchase;
use App\Models\Production\OwnProduct;
use App\Models\Production\ProductionBatch;
use App\Models\Production\RawMaterial;
use App\Models\Production\RawMaterialMovement;
use App\Services\FinanceService;
use App\Services\ProcurementService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Öz istehsalımız.
 *  - Xammal: qalıq = hərəkətlərin cəmi (alış +, istehsala sərf −), maya — orta (FIFO yoxdur).
 *  - Xammal alışı xərcdir: ödənilirsə seçilən balansdan tədarükçüyə "supplier_payment" (kateqoriya "Xammal"),
 *    ödənilməyən hissə tədarükçüyə borcdur (borc = alışların cəmi − ödənişlər). "Başlanğıc qalıq" — pul hərəkəti yoxdur.
 *  - İstehsal partiyası xammalı silir (o anki orta maya ilə), əlavə xərci qatır → bir şüşənin mayası; məhsulun orta mayası yenilənir.
 *  - Hazır mal "Öz anbarımız"dadır. Qalıq = istehsal olunan − öz anbardan götürülən aktiv hissələr
 *    (ləğv olunan və qapıda imtinadan sonra anbara qaytarılan hissə yenə qalığa düşür).
 *  - Sifarişə götürmə adi "Depo seç" kimidir (sorğusuz), qiymət — orta maya: "Satılan mallar" hesabatı mayanı oradan alır.
 */
class OwnProduction
{
    /** Qalıq bu saydan azdırsa / bərabərdirsə admin ana səhifəsində qırmızı bildiriş */
    public const LOW_STOCK = 2;

    public function __construct(private FinanceService $finance) {}

    public function warehouse(): Warehouse
    {
        return Warehouse::firstOrCreate(['is_own' => true], ['name_az' => 'Öz anbarımız', 'active' => true]);
    }

    /** Xammalın qalığı: raw_material_id => miqdar */
    public function materialStocks(): Collection
    {
        return RawMaterialMovement::selectRaw('raw_material_id, SUM(quantity) as q')->groupBy('raw_material_id')
            ->pluck('q', 'raw_material_id')->map(fn ($q) => round((float) $q, 3));
    }

    /** Hazır malın qalığı: own_product_id => ['produced', 'held', 'stock'] */
    public function productStocks(): Collection
    {
        $produced = ProductionBatch::selectRaw('own_product_id, SUM(quantity) as q')->groupBy('own_product_id')->pluck('q', 'own_product_id');
        $held = $this->heldByVariant();

        return OwnProduct::get(['id', 'product_variant_id'])->mapWithKeys(function ($own) use ($produced, $held) {
            $p = (int) ($produced[$own->id] ?? 0);
            $h = (int) ($held[$own->product_variant_id] ?? 0);

            return [$own->id => ['produced' => $p, 'held' => $h, 'stock' => $p - $h]];
        });
    }

    public function stockOf(OwnProduct $own): int
    {
        return $this->productStocks()[$own->id]['stock'] ?? 0;
    }

    /** Öz anbardan götürülmüş (ləğv / anbara qaytarılma olmayan) say: product_variant_id => say */
    private function heldByVariant(): Collection
    {
        return OrderItemAllocation::query()
            ->join('order_items', 'order_items.id', '=', 'order_item_allocations.order_item_id')
            ->join('warehouses', 'warehouses.id', '=', 'order_item_allocations.warehouse_id')
            ->where('warehouses.is_own', true)
            ->whereNotIn('order_item_allocations.status', [OrderItemAllocation::CANCELLED, OrderItemAllocation::RETURNED])
            ->selectRaw('order_items.product_variant_id as v, SUM(order_item_allocations.quantity) as q')
            ->groupBy('order_items.product_variant_id')->pluck('q', 'v');
    }

    /**
     * Partiya gözləyən sifarişlər: hələ təhvilə qədər gəlməmiş (yeni və ya təminatda) sifarişlərdə öz məhsulumuzun
     * götürülməmiş miqdarı. own_product_id => ['orders' => sifariş sayı, 'quantity' => çatışmayan say]
     */
    public function waiting(): Collection
    {
        $owns = OwnProduct::pluck('id', 'product_variant_id');
        if ($owns->isEmpty()) {
            return collect();
        }
        $statusIds = DB::table('order_statuses')->whereIn('code', ['new', ...\App\Services\OrderStatusService::PROCUREMENT])->pluck('id');
        $items = OrderItem::with(['allocations' => fn ($q) => $q->whereNotIn('status', OrderItemAllocation::SUPPLY_INACTIVE)])
            ->whereIn('product_variant_id', $owns->keys())
            ->whereIn('order_id', Order::whereIn('order_status_id', $statusIds)->select('id'))
            ->get();

        return $items->map(fn ($item) => ['own' => $owns[$item->product_variant_id], 'order' => $item->order_id,
            'missing' => max(0, $item->activeQuantity() - (int) $item->allocations->sum('quantity'))])
            ->where('missing', '>', 0)->groupBy('own')
            ->map(fn ($rows) => ['orders' => $rows->pluck('order')->unique()->count(), 'quantity' => (int) $rows->sum('missing')]);
    }

    /** Ana səhifə: qalığı LOW_STOCK və ya az olan aktiv məhsullar (az qalan yuxarıda) */
    public function lowStock(): Collection
    {
        $stocks = $this->productStocks();
        $waiting = $this->waiting();

        return OwnProduct::with(['product.brand', 'variant.size'])->where('active', true)->get()
            ->map(fn ($own) => ['own' => $own, 'stock' => $stocks[$own->id]['stock'] ?? 0, 'waiting' => $waiting[$own->id] ?? null])
            ->filter(fn ($row) => $row['stock'] <= self::LOW_STOCK)
            ->sortBy('stock')->values();
    }

    /**
     * Xammal alışı. $data: purchased_at, payment (paid | debt | opening), supplier_account_id, account_id (paid), note.
     * $lines: [['raw_material_id', 'quantity', 'unit_cost' — 1 vahidin qiyməti]]; sətrin məbləği = miqdar × qiymət
     */
    public function purchase(array $data, array $lines, int $actor): MaterialPurchase
    {
        $payment = $data['payment'];
        $supplier = !empty($data['supplier_account_id']) ? FinanceAccount::where('type', 'supplier')->find($data['supplier_account_id']) : null;
        $this->ensure($payment === 'opening' || $supplier !== null, 'Tədarükçünü seçin.', 'supplier_account_id');
        $from = $payment === 'paid' && !empty($data['account_id']) ? FinanceAccount::find($data['account_id']) : null;
        $this->ensure($payment !== 'paid' || $from !== null, 'Pulun çıxdığı hesabı seçin.', 'account_id');

        return DB::transaction(function () use ($data, $lines, $actor, $payment, $supplier, $from) {
            $date = Carbon::parse($data['purchased_at']);
            $at = $date->isToday() ? now() : $date->copy()->setTimeFrom(now());
            $lines = array_map(fn ($l) => $l + ['amount' => round((float) $l['quantity'] * (float) $l['unit_cost'], 2)], $lines);
            $total = round(array_sum(array_column($lines, 'amount')), 2);
            $this->ensure($total > 0, 'Alışın məbləği 0 ola bilməz.', 'lines');
            $purchase = MaterialPurchase::create([
                'purchased_at' => $date->toDateString(), 'supplier_account_id' => $supplier?->id, 'payment' => $payment,
                'total' => $total, 'paid' => $payment === 'paid' ? $total : 0, 'note' => $data['note'] ?? null, 'created_by' => $actor,
            ]);
            $stocks = $this->materialStocks();
            foreach ($lines as $line) {
                $material = RawMaterial::lockForUpdate()->findOrFail($line['raw_material_id']);
                $qty = round((float) $line['quantity'], 3);
                $this->ensure($qty > 0, 'Miqdar 0-dan böyük olmalıdır.', 'lines');
                $unit = round((float) $line['unit_cost'], 4);
                $before = (float) ($stocks[$material->id] ?? 0);
                // Orta maya: qalıq varsa çəkili orta, yoxdursa (və ya mənfidirsə) yeni qiymət
                $avg = $before > 0 ? (($before * (float) $material->avg_cost) + (float) $line['amount']) / ($before + $qty) : $unit;
                $material->forceFill(['avg_cost' => round($avg, 4)])->save();
                $stocks[$material->id] = $before + $qty;
                $purchase->lines()->create([
                    'raw_material_id' => $material->id, 'quantity' => $qty, 'unit_cost' => $unit, 'kind' => 'purchase',
                    'created_by' => $actor, 'occurred_at' => $at,
                ]);
            }
            if ($payment === 'paid') {
                $this->finance->record($from, $supplier, $total, 'supplier_payment', [
                    'note' => 'Xammal alışı #'.$purchase->id, 'occurred_at' => $at,
                ], $actor);
            }

            return $purchase;
        });
    }

    /** Tədarükçüyə borcun ödənişi (borcdan çox ödənilmir) */
    public function paySupplier(FinanceAccount $supplier, FinanceAccount $from, float $amount, ?string $note, int $actor): MoneyMovement
    {
        $this->ensure($supplier->type === 'supplier', 'Tədarükçü seçilməyib.', 'amount');
        $debt = $this->supplierDebts()[$supplier->id] ?? 0;
        $this->ensure((int) round($amount * 100) <= $debt, 'Borc '.number_format(max(0, $debt) / 100, 2).' AZN-dir — ondan çox ödənilmir.', 'amount');

        return $this->finance->record($from, $supplier, $amount, 'supplier_payment', ['note' => $note ?: 'Xammal borcunun ödənişi'], $actor);
    }

    /** Tədarükçülərə borc (qəpiklə): account_id => cents. Müsbət — biz borcluyuq */
    public function supplierDebts(): Collection
    {
        $bought = MaterialPurchase::whereNotNull('supplier_account_id')->where('payment', '!=', 'opening')
            ->selectRaw('supplier_account_id, SUM(total) as t')->groupBy('supplier_account_id')->pluck('t', 'supplier_account_id');
        $balances = $this->finance->balances();

        return FinanceAccount::where('type', 'supplier')->pluck('id')
            ->mapWithKeys(fn ($id) => [$id => (int) round((float) ($bought[$id] ?? 0) * 100) - ($balances[$id] ?? 0)]);
    }

    /**
     * İstehsal partiyası. $lines: [['raw_material_id', 'quantity' — partiyaya sərf olunan ümumi miqdar]].
     * Xammal çatmırsa — xəta. Xammalsız partiya (yalnız əlavə xərc) — əvvəlcədən hazır olan malın başlanğıc qalığı üçün.
     */
    public function produce(OwnProduct $own, int $quantity, string $date, array $lines, float $extra, ?string $note, int $actor): ProductionBatch
    {
        $this->ensure($quantity > 0, 'Say 0-dan böyük olmalıdır.', 'quantity');

        return DB::transaction(function () use ($own, $quantity, $date, $lines, $extra, $note, $actor) {
            $own = OwnProduct::lockForUpdate()->findOrFail($own->id);
            $stocks = $this->materialStocks();
            $at = Carbon::parse($date)->isToday() ? now() : Carbon::parse($date)->setTimeFrom(now());
            $used = [];
            $consume = [];
            $materialsCost = 0.0;
            foreach ($lines as $line) {
                $qty = round((float) $line['quantity'], 3);
                if ($qty <= 0) {
                    continue;
                }
                $material = RawMaterial::lockForUpdate()->findOrFail($line['raw_material_id']);
                $left = (float) ($stocks[$material->id] ?? 0) - ($used[$material->id] ?? 0);
                $this->ensure($qty <= $left + 0.0005, $material->name.' çatmır: lazım '.self::qty($qty).' '.$material->unitLabel()
                    .', stokda '.self::qty($left).' '.$material->unitLabel().'.', 'lines');
                $used[$material->id] = ($used[$material->id] ?? 0) + $qty;
                $materialsCost += $qty * (float) $material->avg_cost;
                $consume[] = [$material, $qty];
            }
            $extra = round(max(0, $extra), 2);
            $materialsCost = round($materialsCost, 2);
            $unit = round(($materialsCost + $extra) / $quantity, 4);
            $this->ensure($unit > 0, 'Bir şüşənin mayası 0 çıxır — xammal və ya əlavə xərc yazın.', 'extra_cost');

            $stockBefore = $this->stockOf($own);
            $avg = $stockBefore > 0 ? (($stockBefore * (float) $own->avg_cost) + $quantity * $unit) / ($stockBefore + $quantity) : $unit;
            $own->forceFill(['avg_cost' => round($avg, 4)])->save();

            $batch = ProductionBatch::create([
                'own_product_id' => $own->id, 'quantity' => $quantity, 'produced_at' => Carbon::parse($date)->toDateString(),
                'materials_cost' => $materialsCost, 'extra_cost' => $extra, 'unit_cost' => $unit, 'note' => $note, 'created_by' => $actor,
            ]);
            foreach ($consume as [$material, $qty]) {
                $batch->materials()->create([
                    'raw_material_id' => $material->id, 'quantity' => -$qty, 'unit_cost' => $material->avg_cost, 'kind' => 'production',
                    'created_by' => $actor, 'occurred_at' => $at,
                ]);
            }

            return $batch;
        });
    }

    /** Sifarişin məhsulunu öz anbardan götürür (qalıq qədər). Depo SMS-i getmir, kuryer pul vermir */
    public function allocate(Order $order, OrderItem $item, int $actor): OrderItemAllocation
    {
        $own = OwnProduct::where('product_variant_id', $item->product_variant_id)->first();
        $this->ensure($own !== null, 'Bu məhsul öz istehsalımız deyil.', 'procurement');

        return DB::transaction(function () use ($order, $item, $own, $actor) {
            $own = OwnProduct::lockForUpdate()->findOrFail($own->id);
            $stock = $this->stockOf($own);
            $this->ensure($stock > 0, 'Öz anbarımızda bu məhsuldan qalmayıb — yeni partiya gözlənilir.', 'procurement');
            $allocation = app(ProcurementService::class)->recordDirect($order, $item->id, $this->warehouse()->id,
                number_format((float) $own->avg_cost, 2, '.', ''), $actor, $stock);
            // Öz anbar: bildirməyə ehtiyac yoxdur — mal dərhal ayrılır, növbəti addım kuryerin götürməsidir
            $allocation->forceFill(['courier_pays' => false, 'status' => OrderItemAllocation::RESERVED])->save();
            \App\Models\Procurement\AllocationStatusLog::create([
                'order_item_allocation_id' => $allocation->id, 'from_status' => OrderItemAllocation::SELECTED,
                'to_status' => OrderItemAllocation::RESERVED, 'user_id' => $actor, 'note' => 'Öz anbarımızdan ayrıldı', 'created_at' => now(),
            ]);

            return $allocation;
        });
    }

    public static function qty(float $value): string
    {
        return rtrim(rtrim(number_format($value, 3, '.', ''), '0'), '.');
    }

    private function ensure(bool $condition, string $message, string $field): void
    {
        if (!$condition) {
            throw ValidationException::withMessages([$field => $message]);
        }
    }
}
