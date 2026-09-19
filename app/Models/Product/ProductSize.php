<?php

namespace App\Models\Product;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductSize extends Model
{
    use HasFactory;
    protected $table = 'product_sizes';
    public $timestamps = false;
    protected $fillable = [
        'id',
        'product_id',
        'size_id',
        'price',
    ];
}
