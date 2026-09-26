<?php

namespace App\Models\Product;

use App\Services\SeoUrl;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $table = 'products';
    public $timestamps = false;
    protected $fillable = [
        'brand_id',
        'type_id',
        'old_id',
        'name',
        'slug',
        'content_az',
        'content_en',
        'content_ru',
        'active',
    ];

    public function brand()
    {
        return $this->belongsTo(Brand::class, 'brand_id');
    }

    public function type()
    {
        return $this->belongsTo(Type::class, 'type_id');
    }

    public function images()
    {
        return $this->hasMany(ProductImage::class, 'product_id')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function categories()
    {
        return $this->belongsToMany(
            Category::class,
            'product_categories',
            'product_id',
            'category_id'
        );
    }

    public function genders()
    {
        return $this->belongsToMany(
            Gender::class,
            'product_genders',
            'product_id',
            'gender_id'
        );
    }

    public function ingredients()
    {
        return $this->belongsToMany(
            Ingredient::class,
            'product_ingredients',
            'product_id',
            'ingredient_id'
        );
    }

    public function variants()
    {
        return $this->hasMany(ProductVariant::class, 'product_id');
    }

    public function reviews()
    {
        return $this->hasMany(ProductReview::class, 'product_id');
    }

    public function activeVariants()
    {
        return $this->hasMany(ProductVariant::class, 'product_id')
            ->where('active', 1);
    }

    protected static function booted(): void
    {
        static::creating(function (self $product) {
            if (!$product->slug) {
                $brandName = Brand::query()->whereKey($product->brand_id)->value('name') ?? '';
                $product->slug = SeoUrl::uniqueDatabaseSlug(
                    'products',
                    trim($brandName . ' ' . $product->name)
                );
            }
        });
    }
}
