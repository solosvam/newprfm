<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CreditApplication extends Model
{
    protected $fillable = [
        'customer_id', 'product_variant_id', 'credit_period_id',
        'product_price', 'interest_rate', 'total', 'monthly', 'status',
    ];
}
