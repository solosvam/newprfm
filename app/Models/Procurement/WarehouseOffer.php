<?php

namespace App\Models\Procurement;

use Illuminate\Database\Eloquent\Model;

class WarehouseOffer extends Model
{
    protected $guarded = [];

    public function requestItem()
    {
        return $this->belongsTo(WarehouseRequestItem::class, 'warehouse_request_item_id');
    }

    protected function casts(): array
    {
        return ['unit_cost' => 'decimal:2'];
    }
}
