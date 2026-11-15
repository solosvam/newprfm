<?php

namespace App\Models\GiftCard;

use App\Models\Customer\Customer;
use App\Models\Order\Order;
use App\Models\Payment\Payment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Virtual hədiyyə kartı. pending (ödəniş gözlənilir) → active (kod var) → used (sifarişə bağlanıb)
 * → sifariş ləğv olunsa yenidən active. active → expired (müddət bitdi) və ya refunded (pul qaytarıldı).
 * Qaydalar: App\Services\GiftCard\GiftCardService.
 */
class GiftCard extends Model
{
    protected $table = 'virtual_gift_cards';

    public const PENDING = 'pending';
    public const ACTIVE = 'active';
    public const USED = 'used';
    public const EXPIRED = 'expired';
    public const REFUNDED = 'refunded';

    public const STATUSES = [
        self::PENDING => 'Ödəniş gözlənilir',
        self::ACTIVE => 'Aktiv',
        self::USED => 'İstifadə olunub',
        self::EXPIRED => 'Müddəti bitib',
        self::REFUNDED => 'Pulu qaytarılıb',
    ];

    public const EVENTS = [
        'created' => 'Yaradıldı',
        'paid' => 'Ödənildi, kod verildi',
        'used' => 'Sifarişdə istifadə olundu',
        'released' => 'Sifariş ləğv olundu — kart yenidən aktivdir',
        'extended' => 'Müddət uzadıldı',
        'expired' => 'Müddəti bitdi',
        'refunded' => 'Pulu karta qaytarıldı',
        'reminded' => 'Müddətin bitməsi barədə SMS göndərildi',
    ];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
            'refunded_at' => 'datetime',
            'reminded_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function usedOrder(): BelongsTo { return $this->belongsTo(Order::class, 'used_order_id'); }
    public function events(): HasMany { return $this->hasMany(GiftCardEvent::class, 'virtual_gift_card_id')->orderByDesc('id'); }
    public function payments(): HasMany { return $this->hasMany(Payment::class, 'virtual_gift_card_id'); }

    public function paidPayment(): ?Payment
    {
        return $this->payments()->where('status', Payment::PAID)->latest('id')->first();
    }

    /** Göstəriş üçün: ABCD-EFGH-JKLM */
    public function formattedCode(): ?string
    {
        return $this->code ? implode('-', str_split($this->code, 4)) : null;
    }

    public function statusLabel(): string
    {
        if ($this->status === self::ACTIVE && $this->isExpired()) {
            return self::STATUSES[self::EXPIRED];
        }

        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /** Səbətdə tətbiq oluna bilər */
    public function isUsable(): bool
    {
        return $this->status === self::ACTIVE && !$this->isExpired();
    }
}
