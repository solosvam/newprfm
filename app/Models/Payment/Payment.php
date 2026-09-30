<?php

namespace App\Models\Payment;

use App\Models\Customer\Customer;
use App\Models\Order\Order;
use App\Services\Payment\PaymentItemsBuilder;
use Illuminate\Database\Eloquent\Model;
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

    /**
     * Ödəniş yarananda ona daxil olanlar (məhsullar, çatdırılma, qablaşdırma) həmin anın vəziyyəti ilə saxlanır.
     * Birbank-da ödəniş bir neçə yerdə yaranır (adi, yadda saxlanmış kart, preavtorizasiya) — hamısı buradan keçir.
     */
    protected static function booted(): void
    {
        static::created(function (Payment $payment) {
            $order = $payment->order;
            if ($order && !$payment->items()->exists()) {
                $payment->items()->createMany(app(PaymentItemsBuilder::class)->lines($order, (float) $payment->amount));
            }
        });
    }

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    /** Ödənişə daxil olan məhsullar, çatdırılma və qablaşdırma (PaymentItemsBuilder) */
    public function items(): HasMany
    {
        return $this->hasMany(PaymentItem::class);
    }

    public function operations()
    {
        return $this->hasMany(PaymentOperation::class);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(PaymentOperation::class)
            ->where('type', 'refund')
            ->where('status', 'succeeded');
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
