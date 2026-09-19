<?php

namespace App\Models\Product;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ingredient extends Model
{
    use HasFactory;
    protected $table = 'ingredients';
    public $timestamps = false;
    protected $fillable = [
        'id',
        'name_az',
        'name_en',
        'name_ru',
    ];
}
