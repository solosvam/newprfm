<?php

namespace App\Models\Procurement;

use App\Models\Order\OrderItem;
use Illuminate\Database\Eloquent\Model;

class WarehouseRequestItem extends Model
{
    protected $guarded = [];

    public function request()
    {
        return $this->belongsTo(WarehouseRequest::class, 'warehouse_request_id');
    }

    public function orderItem()
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function offers()
    {
        return $this->hasMany(WarehouseOffer::class)->orderByDesc('id');
    }
}
