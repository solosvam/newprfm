<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CreditTerms extends Model
{
    protected $table = 'credit_terms';

    protected $fillable = ['content_az', 'content_en', 'content_ru'];
}
