<?php

namespace App\Models;

use App\Models\Customer\Customer;
use App\Models\Order\Order;
use Illuminate\Database\Eloquent\Model;
use App\Models\Common\PaymentRefund;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    public function operations()
    {
        return $this->hasMany(PaymentOperation::class);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(PaymentRefund::class, 'payment_id');
    }

    public function refundedAmount(): float
    {
        return (float) $this->refunds()->sum('amount');
    }

    public function refundableAmount(): float
    {
        return max(0, (float) $this->amount - $this->refundedAmount());
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
