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

    /**
     * "Yeni ətir yarat" formu üçün: brend seçilmiş, model adı yazılmış (adın brend, növ, cins, həcm, tester hissələri çıxılır).
     * Məs. "CHRISTIAN DIOR SAUVAGE EDP M 200ML" → brand_id = Dior, name = "Sauvage".
     */
    public function createProductUrl(): string
    {
        $core = \App\Services\PriceList\PriceListNameParser::parse((string) $this->raw_name, $this->brand_raw)['core'];

        return route('admin.product.add', array_filter([
            'brand_id' => $this->brand_id,
            'name' => $core !== '' ? mb_convert_case(mb_strtolower($core), MB_CASE_TITLE) : null,
        ]));
    }

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
