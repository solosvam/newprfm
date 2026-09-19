<?php

namespace App\Models\Product;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ingredient extends Model
{
    protected $table = 'ingredients';
    public $timestamps = false;
    protected $fillable = [
        'id',
        'name_az',
        'name_en',
        'name_ru',
    ];

    public function products()
    {
        return $this->belongsToMany(
            Product::class,
            'product_ingredients',
            'ingredient_id',
            'product_id'
        );
    }
}
