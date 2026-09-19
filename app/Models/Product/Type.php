<?php

namespace App\Models\Product;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Type extends Model
{
    protected $table = 'types';
    public $timestamps = false;
    protected $fillable = [
        'id',
        'name_az',
        'name_en',
        'name_ru',
    ];

    public function products()
    {
        return $this->hasMany(Product::class, 'type_id');
    }
}
