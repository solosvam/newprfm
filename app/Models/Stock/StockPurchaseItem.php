<?php

namespace App\Models\Stock;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Alışın sətri: hədiyyə kartları — nominal və barkod aralığı */
class StockPurchaseItem extends Model
{
    public const TYPES = ['gift_card' => 'Hədiyyə kartı'];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['denomination' => 'decimal:2', 'unit_cost' => 'decimal:2'];
    }

    public function purchase(): BelongsTo { return $this->belongsTo(StockPurchase::class, 'stock_purchase_id'); }
    public function giftCards(): HasMany { return $this->hasMany(GiftCard::class); }

    public function label(): string
    {
        return $this->item_type === 'gift_card' ? GiftCard::label($this->denomination) : (self::TYPES[$this->item_type] ?? $this->item_type);
    }
}
