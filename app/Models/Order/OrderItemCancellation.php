<?php

namespace App\Models\Order;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Məhsul/miqdar ləğvi (OrderItemCancellationService) */
class OrderItemCancellation extends Model
{
    public const REASONS = [
        'not_in_stock' => 'Anbarlarda yoxdur',
        'customer_refused' => 'Müştəri imtina etdi',
        'other' => 'Digər',
    ];

    public const REFUND_PENDING = 'pending';       // karta qaytarılmalıdır
    public const REFUND_PROCESSING = 'processing'; // bankdan cavab gəlmədi — yoxlanılmalıdır
    public const REFUND_DONE = 'refunded';         // karta qaytarıldı
    public const REFUND_BONUS = 'bonus';           // bonus balansına qaytarıldı

    public const REFUND_LABELS = [
        self::REFUND_PENDING => 'Qaytarılmalıdır',
        self::REFUND_PROCESSING => 'Bankda yoxlanılır',
        self::REFUND_DONE => 'Karta qaytarıldı',
        self::REFUND_BONUS => 'Bonusa qaytarıldı',
    ];

    protected $fillable = ['order_id', 'order_item_id', 'quantity', 'amount', 'reason', 'note', 'customer_agreed', 'bonus_adjustment', 'refund_status', 'payment_operation_id', 'refunded_at', 'created_by'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'bonus_adjustment' => 'decimal:2', 'customer_agreed' => 'boolean', 'quantity' => 'integer', 'refunded_at' => 'datetime'];
    }

    public function order(): BelongsTo { return $this->belongsTo(Order::class); }
    public function orderItem(): BelongsTo { return $this->belongsTo(OrderItem::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }

    public function reasonLabel(): string
    {
        return self::REASONS[$this->reason] ?? $this->reason;
    }
}
