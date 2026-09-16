<?php

namespace App\Models\Product;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Brands extends Model
{
    use HasFactory;
    protected $table = 'brands';
    public $timestamps = false;
    protected $fillable = [
        'id',
        'name',
        'image',
        'active'
    ];

    public function products()
    {
        return $this->hasMany(Products::class, 'brand_id');
    }
}
