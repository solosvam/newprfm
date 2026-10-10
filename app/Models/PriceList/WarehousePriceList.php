<?php

namespace App\Models\PriceList;

use App\Models\Procurement\Warehouse;
use Illuminate\Database\Eloquent\Model;

class WarehousePriceList extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['mapping' => 'array'];
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function items()
    {
        return $this->hasMany(WarehousePriceItem::class, 'price_list_id');
    }

    /** Hər anbarın cari (sonuncu) siyahısının id-ləri */
    public static function currentIds(): array
    {
        return static::query()->selectRaw('max(id) as id')->groupBy('warehouse_id')->pluck('id')->all();
    }
}
