<?php

namespace App\Models\Procurement;

use Illuminate\Database\Eloquent\Model;

class OrderItemAllocation extends Model
{
    protected $guarded = [];

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
