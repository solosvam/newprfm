<?php

namespace App\Models\Product;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Services\SeoUrl;

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
    }

    public function products()
    {
        return $this->hasMany(Product::class, 'brand_id');
    }
}
