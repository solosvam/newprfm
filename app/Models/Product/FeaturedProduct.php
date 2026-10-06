<?php

namespace App\Models\Product;

use Illuminate\Database\Eloquent\Model;

/** Ana səhifənin vitrini ("Populyar" sıralaması): admin-in seçdiyi ətirlər, position — göstərilmə ardıcıllığı */
class FeaturedProduct extends Model
{
    /** Ana səhifənin birinci səhifəsi (3 sıra × 4) */
    public const LIMIT = 12;

    protected $guarded = [];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
