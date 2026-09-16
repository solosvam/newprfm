<?php

namespace App\Models\Product;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductIngredients extends Model
{
    use HasFactory;
    protected $table = 'product_ingredients';
    public $timestamps = false;
    protected $fillable = [
        'id',
        'product_id',
        'ingredient_id'
    ];
}
