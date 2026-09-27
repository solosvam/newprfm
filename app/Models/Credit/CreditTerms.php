<?php

namespace App\Models\Credit;

use Illuminate\Database\Eloquent\Model;

class CreditTerms extends Model
{
    protected $table = 'credit_terms';

    protected $fillable = ['content_az', 'content_en', 'content_ru'];
}
