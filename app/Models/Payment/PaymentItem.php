<?php

namespace App\Models\Payment;

use App\Models\Order\OrderItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Ödənişə daxil olan sətir: məhsul (item), çatdırılma (delivery) və ya qablaşdırma (gift_wrap) */
class PaymentItem extends Model
{
    protected $fillable = ['payment_id', 'order_item_id', 'type', 'quantity', 'unit_price', 'amount'];

    protected function casts(): array
    {
        return ['unit_price' => 'decimal:2', 'amount' => 'decimal:2', 'quantity' => 'integer'];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function refundItems(): HasMany
    {
        return $this->hasMany(PaymentRefundItem::class);
    }

    /** Bu sətirdən qaytarılan (uğurlu və ya bankda yoxlanılan) məbləğ */
    public function refundedAmount(): float
    {
        return (float) $this->refundItems()
            ->whereHas('operation', fn ($q) => $q->whereIn('status', ['pending', 'succeeded']))
            ->sum('amount');
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }
}
