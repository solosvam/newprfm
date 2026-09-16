<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Banners extends Model
{
    use HasFactory;
    protected $table = 'banners';
    public $timestamps = false;
    protected $fillable = [
        'id',
        'url',
        'location',
        'device',
        'active',
    ];
}
