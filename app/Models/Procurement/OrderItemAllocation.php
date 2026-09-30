<?php

namespace App\Models\Procurement;

use Illuminate\Database\Eloquent\Model;

class OrderItemAllocation extends Model
{
    protected $guarded = [];

    /*
     | Təminat hissəsinin axını (hər keçid allocation_status_logs-a yazılır):
     |   selected → notified → reserved → picked
     |   problem — istənilən aktiv mərhələdən; həll: əvvəlki mərhələyə qayıdır və ya ləğv
     |   cancelled — picked-dən sonra mümkün deyil
     */
    public const SELECTED = 'selected';
    public const NOTIFIED = 'notified';
    public const RESERVED = 'reserved';
    public const PICKED = 'picked';
    public const PROBLEM = 'problem';
    public const CANCELLED = 'cancelled';

    public const LABELS = [
        self::SELECTED => 'Anbar seçilib',
        self::NOTIFIED => 'Anbara bildirilib',
        self::RESERVED => 'Anbar ayırıb',
        self::PICKED => 'Götürülüb',
        self::PROBLEM => 'Problem',
        self::CANCELLED => 'Ləğv edilib',
    ];

    /** Normal axın: hər mərhələdən irəli getmək olar (məs. telefonla dərhal "ayırdı") */
    public const FLOW = [self::SELECTED, self::NOTIFIED, self::RESERVED, self::PICKED];

    /** Ləğv etmək olar (götürüləndən sonra — yox) */
    public const CANCELLABLE = [self::SELECTED, self::NOTIFIED, self::RESERVED, self::PROBLEM];

    public const PROBLEM_TYPES = [
        'not_found' => 'Anbarda tapılmadı',
        'price_changed' => 'Qiymət dəyişdi',
        'wrong_item' => 'Yanlış məhsul verildi',
        'other' => 'Digər',
    ];

    public function label(): string
    {
        return self::LABELS[$this->status] ?? $this->status;
    }

    public function isActive(): bool
    {
        return $this->status !== self::CANCELLED;
    }

    public function orderItem()
    {
        return $this->belongsTo(\App\Models\Order\OrderItem::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function offer()
    {
        return $this->belongsTo(WarehouseOffer::class, 'warehouse_offer_id');
    }

    public function logs()
    {
        return $this->hasMany(AllocationStatusLog::class)->orderBy('id');
    }

    protected function casts(): array
    {
        return ['unit_cost' => 'decimal:2'];
    }
}
