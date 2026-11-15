<?php

namespace App\Models\GiftCard;

use App\Models\Order\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GiftCardEvent extends Model
{
    protected $table = 'virtual_gift_card_events';
    public $timestamps = false;
    protected $guarded = [];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function order(): BelongsTo { return $this->belongsTo(Order::class); }

    public function label(): string
    {
        return GiftCard::EVENTS[$this->event] ?? $this->event;
    }
}
