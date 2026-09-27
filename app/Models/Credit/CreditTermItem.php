<?php

namespace App\Models\Credit;

use Illuminate\Database\Eloquent\Model;

class CreditTermItem extends Model
{
    protected $fillable = ['title_az', 'title_en', 'title_ru', 'content_az', 'content_en', 'content_ru', 'sort_order'];
}
