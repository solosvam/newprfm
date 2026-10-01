<?php

namespace App\Models\Product;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Services\Search\ProductSearchService;
use App\Services\SeoUrl;
use Illuminate\Support\Facades\Cache;

class Brand extends Model
{
    use HasFactory;
    protected $table = 'brands';
    public $timestamps = false;
    protected $fillable = [
        'id',
        'name',
        'slug',
        'image',
        'active'
    ];

    protected static function booted(): void
    {
        static::creating(function (self $brand) {
            if (!$brand->slug) {
                $brand->slug = SeoUrl::uniqueDatabaseSlug('brands', $brand->name);
            }
        });
        // brend adları axtarış lüğətinin bir hissəsidir (ProductSearchService)
        static::saved(fn () => Cache::forget(ProductSearchService::CACHE_KEY));
        static::deleted(fn () => Cache::forget(ProductSearchService::CACHE_KEY));
    }

    public function products()
    {
        return $this->hasMany(Product::class, 'brand_id');
    }

    public function searchAliases()
    {
        return $this->hasMany(SearchAlias::class, 'brand_id')->where('type', SearchAlias::BRAND);
    }
}
