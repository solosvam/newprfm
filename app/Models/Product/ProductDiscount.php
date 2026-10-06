<?php

namespace App\Models\Product;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Məhsul endirimi: faiz bütün ölçülərə, [starts_at, ends_at) aralığında; hamı (qonaqlar da) görür */
class ProductDiscount extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['percent' => 'decimal:2', 'starts_at' => 'datetime', 'ends_at' => 'datetime'];
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('starts_at', '<=', now())->where('ends_at', '>', now());
    }

    public function isActive(): bool
    {
        return $this->starts_at->lte(now()) && $this->ends_at->gt(now());
    }

    public function status(): string
    {
        return match (true) {
            $this->starts_at->gt(now()) => 'scheduled',
            $this->ends_at->lte(now()) => 'ended',
            default => 'active',
        };
    }

    /** Kartda geri sayım bitməsinə bu qədər gündən az qalanda göstərilir (daha uzun endirimdə yalnız lent) */
    public const CARD_COUNTDOWN_DAYS = 7;

    public function showsCardCountdown(): bool
    {
        return $this->isActive() && $this->ends_at->lt(now()->addDays(self::CARD_COUNTDOWN_DAYS));
    }

    /** Endirimli qiymət — qəpiklə (yuvarlaqlaşdırılmır) */
    public function apply(float $price): float
    {
        return round($price * (100 - (float) $this->percent) / 100, 2);
    }

    /** Faiz göstərmək üçün: 15.00 → "15", 12.50 → "12.5" */
    public function percentLabel(): string
    {
        return rtrim(rtrim(number_format((float) $this->percent, 2, '.', ''), '0'), '.');
    }
}
