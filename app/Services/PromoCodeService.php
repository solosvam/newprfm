<?php

namespace App\Services;

use App\Exceptions\PromoCodeException;
use App\Models\Product\ProductVariant;
use App\Models\PromoCode;

class PromoCodeService
{
    public function subtotalFor(array $items): float
    {
        $quantities = collect($items)
            ->groupBy(fn ($item) => (int) $item['variant_id'])
            ->map(fn ($group) => $group->sum(
                fn ($item) => (int) $item['quantity']
            ));

        $variants = ProductVariant::whereIn('id', $quantities->keys())
            ->where('active', 1)
            ->whereHas('product', fn ($query) => $query->where('active', 1))
            ->get();

        if ($variants->count() !== $quantities->count())
        {
            throw new PromoCodeException('Səbətdə mövcud olmayan məhsul var.');
        }

        return round(
            $variants->sum(
                fn ($variant) => (float) $variant->price * $quantities[$variant->id]
            ),
            2
        );
    }

    public function resolve(string $code, float $subtotal, bool $lock = false): array
    {
        $query = PromoCode::whereRaw(
            'UPPER(code) = ?',
            [mb_strtoupper(trim($code))]
        );

        $promo = ($lock ? $query->lockForUpdate() : $query)->first();

        if (!$promo || !$promo->is_active || ($promo->starts_at && now()->lt($promo->starts_at)))
        {
            throw new PromoCodeException(__('promo_invalid'));
        }

        if ($promo->expires_at && now()->gt($promo->expires_at))
        {
            throw new PromoCodeException(__('promo_expired'));
        }

        if ($promo->usage_limit !== null && $promo->used_count >= $promo->usage_limit)
        {
            throw new PromoCodeException(__('promo_limit_reached'));
        }

        if ($promo->min_amount && $subtotal < (float) $promo->min_amount)
        {
            throw new PromoCodeException(
                __('promo_min_amount', [
                    'amount' => number_format($promo->min_amount, 2) . ' ₼',
                ])
            );
        }

        $discount = $promo->type === 'percent'
            ? $subtotal * (float) $promo->value / 100
            : (float) $promo->value;

        if ($promo->max_discount !== null)
        {
            $discount = min($discount, (float) $promo->max_discount);
        }

        return [
            'promo' => $promo,
            'discount' => round(max(0, min($discount, $subtotal)), 2),
        ];
    }
}
