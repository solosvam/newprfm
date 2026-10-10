<?php

namespace App\Models\PriceList;

use App\Models\Procurement\Warehouse;
use App\Models\Product\ProductVariant;
use Illuminate\Database\Eloquent\Model;

class WarehousePriceItem extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['tester' => 'boolean', 'is_set' => 'boolean', 'price' => 'decimal:2'];
    }

    public function priceList()
    {
        return $this->belongsTo(WarehousePriceList::class, 'price_list_id');
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
