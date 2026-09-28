<?php

namespace App\Models\Payment;

use App\Models\Customer\Customer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentSavedCard extends Model
{
    protected $fillable = [
        'customer_id', 'provider', 'provider_token_id', 'masked_pan', 'active',
    ];

    protected $hidden = ['provider_token_id'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
