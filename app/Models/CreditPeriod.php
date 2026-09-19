<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CreditPeriod extends Model
{
    protected $fillable = ['month', 'interest_rate', 'active', 'sort_order'];

    protected $casts = [
        'month' => 'integer',
        'interest_rate' => 'decimal:2',
        'active' => 'boolean',
        'sort_order' => 'integer',
    ];
}
