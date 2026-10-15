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

    public function discounts()
    {
        return $this->hasMany(ProductDiscount::class)->orderByDesc('starts_at');
    }

    /** Saytda göstərilən endirim (aktiv olan) — kart lenti, məhsul səhifəsi, axtarış */
    public function visibleDiscount(): ?ProductDiscount
    {
        return $this->activeDiscount?->isActive() ? $this->activeDiscount : null;
    }

    /** Hazırda aktiv endirim (dövrlər üst-üstə düşmür — ən çox biri) */
    public function activeDiscount()
    {
        return $this->hasOne(ProductDiscount::class)->where('starts_at', '<=', now())->where('ends_at', '>', now())->orderByDesc('starts_at');
    }

    public function images()
    {
        return $this->hasMany(ProductImage::class, 'product_id')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    /**
     * Ətir testerdirsə: "Tester" kateqoriyasındadır və ya adında "tester" sözü var (köhnə məhsullarda yalnız adda yazılıb).
     * Saytda şəklin küncündə "Tester" lenti üçün (frontend.partials.tester-ribbon).
     */
    public function isTester(): bool
    {
        if (preg_match('/\btester\b/iu', (string) $this->name)) {
            return true;
        }
        if ($this->relationLoaded('categories')) {
            return $this->categories->contains(fn ($category) => mb_strtolower(trim((string) $category->name_az)) === 'tester');
        }
        // "Tester" kateqoriyasındakı məhsullar — bir sorğu ilə, tətbiq konteynerində (hər istək üçün təzə)
        if (!app()->bound('catalog.tester_ids')) {
            app()->instance('catalog.tester_ids', rescue(fn () => \Illuminate\Support\Facades\DB::table('product_categories as pc')
                ->join('categories as c', 'c.id', '=', 'pc.category_id')->whereRaw('lower(c.name_az) = ?', ['tester'])
                ->pluck('pc.product_id')->flip(), collect(), false));
        }

        return app('catalog.tester_ids')->has($this->id);
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
        // chaperone: ölçü öz məhsulunu (və onun endirimini) əlavə sorğusuz tanısın — ProductVariant::salePrice
        return $this->hasMany(ProductVariant::class, 'product_id')->chaperone('product');
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
