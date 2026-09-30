<?php

namespace App\Models\Payment;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentOperation extends Model
{
    protected $fillable = [
        'payment_id', 'type', 'amount', 'status',
        'bank_action_id', 'idempotency_key', 'response_text',
    ];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    public function refundItems()
    {
        return $this->hasMany(PaymentRefundItem::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
