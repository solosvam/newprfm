<?php

namespace App\Models\Payment;

use App\Models\Order\OrderItemCancellation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Geri ödənişin hansı ödəniş sətrinə (məhsula) və ləğvə aid olduğu */
class PaymentRefundItem extends Model
{
    protected $fillable = ['payment_operation_id', 'payment_item_id', 'order_item_cancellation_id', 'quantity', 'amount'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'quantity' => 'integer'];
    }

    public function operation(): BelongsTo { return $this->belongsTo(PaymentOperation::class, 'payment_operation_id'); }
    public function paymentItem(): BelongsTo { return $this->belongsTo(PaymentItem::class); }
    public function cancellation(): BelongsTo { return $this->belongsTo(OrderItemCancellation::class, 'order_item_cancellation_id'); }
}
