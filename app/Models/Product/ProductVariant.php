<?php

namespace App\Models\Product;

use Illuminate\Database\Eloquent\Model;

class ProductVariant extends Model
{
    protected $table = 'product_variants';

    public $timestamps = false;

    protected $fillable = [
        'product_id',
        'size_id',
        'price',
        'active',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function size()
    {
        return $this->belongsTo(Size::class, 'size_id');
    }
}
