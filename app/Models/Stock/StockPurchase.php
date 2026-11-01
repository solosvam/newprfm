<?php

namespace App\Models\Stock;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Stoklu mal alışı (Stok → Alışlar) */
class StockPurchase extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['purchased_at' => 'date', 'total_cost' => 'decimal:2'];
    }

    public function items(): HasMany { return $this->hasMany(StockPurchaseItem::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}
