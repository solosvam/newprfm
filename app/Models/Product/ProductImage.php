<?php

namespace App\Models\Product;

use Illuminate\Database\Eloquent\Model;

class ProductImage extends Model
{
    protected $table = 'product_images';
    public $timestamps = false;
    protected $fillable = [
        'product_id',
        'image',
        'sort_order',
    ];
}
