<?php
namespace App\Models\Order;

use App\Models\Product\Product;
use App\Models\Product\ProductVariant;
use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    protected $guarded = [];

    /** Aktiv (ləğv olunmamış) miqdar; quantity — sifariş edilən, tarixçə üçün dəyişmir */
    public function activeQuantity(): int
    {
        return max(0, (int) $this->quantity - (int) $this->cancelled_quantity);
    }

    public const SUPPLY_LABELS = [
        'pending' => 'Gözləyir',
        'partly_allocated' => 'Qismən seçilib',
        'allocated' => 'Anbar seçilib',
        'reserved' => 'Ayrılıb',
        'partly_picked' => 'Qismən götürülüb',
        'picked' => 'Götürülüb',
        'problem' => 'Problem',
        'cancelled' => 'Ləğv edilib',
    ];

    /**
     * Məhsulun ümumi təminat vəziyyəti — hissələrdən hesablanır (saxlanmır).
     * Problem hər şeydən öndədir; sonra götürülən, ayrılan, seçilən miqdar aktiv miqdarla müqayisə olunur.
     */
    public function supplyStatus(): string
    {
        $need = $this->activeQuantity();
        if ($need === 0) return 'cancelled';

        $parts = $this->allocations->whereNotIn('status', \App\Models\Procurement\OrderItemAllocation::SUPPLY_INACTIVE);
        if ($parts->contains('status', \App\Models\Procurement\OrderItemAllocation::PROBLEM)) return 'problem';

        $sum = fn (array $statuses) => (int) $parts->whereIn('status', $statuses)->sum('quantity');
        $picked = $sum(['picked']);
        $reserved = $sum(['reserved', 'picked']);
        $allocated = (int) $parts->sum('quantity');

        return match (true) {
            $picked >= $need => 'picked',
            $picked > 0 => 'partly_picked',
            $reserved >= $need => 'reserved',
            $allocated >= $need => 'allocated',
            $allocated > 0 => 'partly_allocated',
            default => 'pending',
        };
    }

    public function supplyLabel(): string
    {
        return self::SUPPLY_LABELS[$this->supplyStatus()];
    }

    public function cancellations(){ return $this->hasMany(OrderItemCancellation::class)->orderBy('id'); }
    public function order(){ return $this->belongsTo(Order::class); }
    public function product(){ return $this->belongsTo(Product::class); }
    public function variant(){ return $this->belongsTo(ProductVariant::class, 'product_variant_id'); }

    public function allocations()
    {
        return $this->hasMany(\App\Models\Procurement\OrderItemAllocation::class);
    }
}
