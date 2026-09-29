<?php

namespace App\Models\Procurement;

use Illuminate\Database\Eloquent\Model;

class WarehouseRequest extends Model
{
    protected $guarded = [];

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function items()
    {
        return $this->hasMany(WarehouseRequestItem::class);
    }
}
