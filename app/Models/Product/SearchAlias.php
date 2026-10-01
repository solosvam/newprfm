<?php

namespace App\Models\Product;

use App\Services\Search\ProductSearchNormalizer;
use App\Services\Search\ProductSearchService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Axtarış lüğəti: "diyor" → Christian Dior (brend), "savaj" → Sauvage (model), "orijinal" → artıq söz (axtarışda atılır).
 * Artıq söz kök kimi də ola bilər (match_type = prefix): "göndər" → göndərirsiniz, göndərin, göndərilir.
 */
class SearchAlias extends Model
{
    public const BRAND = 'brand';
    public const MODEL = 'model';
    public const IGNORE = 'ignore';

    public const TYPES = [
        self::BRAND => 'Brend',
        self::MODEL => 'Model',
        self::IGNORE => 'Artıq söz',
    ];

    public const EXACT = 'exact';
    public const PREFIX = 'prefix';

    /** Kök: bu uzunluqdan qısa olmasın — "va" kimi kök çox sözü atar */
    public const MIN_PREFIX_LENGTH = 3;

    protected $table = 'search_aliases';

    protected $fillable = ['alias', 'alias_normalized', 'type', 'match_type', 'brand_id', 'original', 'created_by'];

    protected $attributes = ['match_type' => self::EXACT];

    public function isStem(): bool
    {
        return $this->type === self::IGNORE && $this->match_type === self::PREFIX;
    }

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
