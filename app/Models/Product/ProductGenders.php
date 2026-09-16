<?php

namespace App\Models\Product;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductGenders extends Model
{
    use HasFactory;
    protected $table = 'product_genders';
    public $timestamps = false;
    protected $fillable = [
        'id',
        'product_id',
        'gender_id'
    ];
}
