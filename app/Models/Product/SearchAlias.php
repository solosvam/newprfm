<?php

namespace App\Models\Product;

use App\Services\Search\ProductSearchNormalizer;
use App\Services\Search\ProductSearchService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/** Axtarış lüğəti: "diyor" → Christian Dior (brend), "savaj" → Sauvage (model), "orijinal" → nəzərə alma */
class SearchAlias extends Model
{
    public const BRAND = 'brand';
    public const MODEL = 'model';
    public const IGNORE = 'ignore';

    public const TYPES = [
        self::BRAND => 'Brend',
        self::MODEL => 'Model',
        self::IGNORE => 'Nəzərə alma',
    ];

    protected $table = 'search_aliases';

    protected $fillable = ['alias', 'alias_normalized', 'type', 'brand_id', 'original', 'created_by'];

    protected static function booted(): void
    {
        static::saving(function (self $alias) {
            $alias->alias_normalized = ProductSearchNormalizer::normalize($alias->alias);
        });
        // lüğət keşdədir — dəyişəndə təzələnir
        static::saved(fn () => Cache::forget(ProductSearchService::CACHE_KEY));
        static::deleted(fn () => Cache::forget(ProductSearchService::CACHE_KEY));
    }

    public function brand()
    {
        return $this->belongsTo(Brand::class, 'brand_id');
    }
}
