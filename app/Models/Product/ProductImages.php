<?php

namespace App\Models\Product;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductImages extends Model
{
    use HasFactory;
    protected $table = 'product_images';
    public $timestamps = false;
    protected $fillable = [
        'id',
        'product_id',
        'image',
    ];
}
