<?php

namespace App\Models\Product;

use App\Services\SeoUrl;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;
    protected $table = 'products';
    public $timestamps = false;
    protected $fillable = [
        'id',
        'brand_id',
        'type_id',
        'old_id',
        'name',
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
        return $this->belongsTo(ProductType::class, 'type_id');
    }

    public function images()
    {
        return $this->hasMany(ProductImage::class, 'product_id');
    }

    public function categories()
    {
        return $this->belongsToMany(Category::class, 'product_categories', 'product_id', 'category_id');
    }

    public function ingredients()
    {
        return $this->belongsToMany(Ingredient::class, 'product_ingredients', 'product_id', 'ingredient_id');
    }

    public function sizes()
    {
        return $this->belongsToMany(Size::class, 'product_sizes', 'product_id', 'size_id')
            ->withPivot('price');
    }

    public function genders()
    {
        return $this->belongsToMany(Gender::class, 'product_genders', 'product_id', 'gender_id');
    }

    public function getSlugAttribute()
    {
        return SeoUrl::generateSlug([
            'id'    => $this->id,
            'brand' => ($this->brand->name)?? null,
            'name'  => ($this->name)?? null,
        ]);
    }


    //$product->categories()->attach([1, 2, 3]);
}
