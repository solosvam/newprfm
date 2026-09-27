<?php

namespace App\Models;

use App\Models\Customer\Customer;
use App\Models\Order\Order;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    public const PENDING = 'pending';
    public const PAID = 'paid';
    public const FAILED = 'failed';
    public const CANCELLED = 'cancelled';

    protected $fillable = [
        'customer_id',
        'order_id',
        'provider',
        'provider_order_id',
        'amount',
        'session_id',
        'card_pan',
        'response_text',
        'status',
    ];

    protected $hidden = ['session_id'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
