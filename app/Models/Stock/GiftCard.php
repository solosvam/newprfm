<?php

namespace App\Models\Stock;

use App\Models\Order\Order;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Hədiyyə kartı (fiziki, öz barkodu ilə). in_stock → sold (satıldı) → yenidən in_stock (kart geri alındı / satış səhv idi).
 * Kartla ödəniş, qalıq və müddət qaydaları hələ qərarlaşdırılmayıb.
 */
class GiftCard extends Model
{
    public const IN_STOCK = 'in_stock';
    public const SOLD = 'sold';
    public const STATUSES = [self::IN_STOCK => 'Stokda', self::SOLD => 'Satılıb'];

    /** Nominallar (AZN) */
    public const DENOMINATIONS = [100, 200, 500, 1000];

    public const EVENTS = ['received' => 'Stoka daxil oldu', 'sold' => 'Satıldı', 'returned' => 'Stoka qaytarıldı'];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['denomination' => 'decimal:2', 'sold_at' => 'datetime'];
    }

    public function events(): HasMany { return $this->hasMany(GiftCardEvent::class)->orderByDesc('id'); }
    public function soldOrder(): BelongsTo { return $this->belongsTo(Order::class, 'sold_order_id'); }
    public function purchaseItem(): BelongsTo { return $this->belongsTo(StockPurchaseItem::class, 'stock_purchase_item_id'); }

    public static function label(float|string|null $denomination): string
    {
        return 'HK '.(int) $denomination;
    }

    /** Barkod qaydası: 1 + nominal (6 rəqəm) + sıra (6 rəqəm) — HK 100-ün 1-ci kartı 1000100000001 */
    public static function barcodeFor(int $denomination, int $sequence): string
    {
        return '1'.str_pad((string) $denomination, 6, '0', STR_PAD_LEFT).str_pad((string) $sequence, 6, '0', STR_PAD_LEFT);
    }

    /** Barkodun nominal hissəsi (ilk 7 rəqəm) bu nominala uyğundurmu */
    public static function barcodeMatches(string $barcode, int $denomination): bool
    {
        return strlen($barcode) === 13 && str_starts_with($barcode, substr(self::barcodeFor($denomination, 1), 0, 7));
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }
}
