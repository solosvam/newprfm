<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Faq extends Model
{
    use HasFactory;
    protected $table = 'faqs';
    public $timestamps = false;
    protected $fillable = [
        'id',
        'title_az',
        'title_en',
        'title_ru',
        'content_az',
        'content_en',
        'content_ru',
    ];
}
