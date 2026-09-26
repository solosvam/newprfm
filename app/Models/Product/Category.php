<?php

namespace App\Models\Product;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Services\SeoUrl;

class Category extends Model
{
    protected $table = 'categories';
    public $timestamps = false;
    protected $fillable = [
        'id',
        'name_az',
        'name_en',
        'name_ru',
        'slug',
        'active',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $category) {
            if (!$category->slug) {
                $category->slug = SeoUrl::uniqueDatabaseSlug(
                    'categories',
                    $category->name_en ?: $category->name_az ?: $category->name_ru ?: 'category'
                );
            }
        });
    }

    public function products()
    {
        return $this->belongsToMany(Product::class, 'product_categories', 'category_id', 'product_id');
    }
}
