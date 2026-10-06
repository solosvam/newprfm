<?php

namespace App\Services;

use App\Exceptions\PromoCodeException;
use Carbon\CarbonImmutable;
use App\Models\Product\ProductVariant;
use App\Models\PromoCode;

class PromoCodeService
{
    /** Promo kodun tətbiq olunduğu məbləğ: yalnız endirimsiz məhsullar (endirimli məhsula promo işləmir) */
    public function subtotalFor(array $items): float
    {
        $quantities = collect($items)
            ->groupBy(fn ($item) => (int) $item['variant_id'])
            ->map(fn ($group) => $group->sum(
                fn ($item) => (int) $item['quantity']
            ));

        $variants = ProductVariant::with('product.activeDiscount')->whereIn('id', $quantities->keys())
            ->where('active', 1)
            ->whereHas('product', fn ($query) => $query->where('active', 1))
            ->get();

        if ($variants->count() !== $quantities->count())
        {
            throw new PromoCodeException('Səbətdə mövcud olmayan məhsul var.');
        }

        $eligible = $variants->filter(fn ($variant) => $variant->salePrice() >= (float) $variant->price);
        if ($eligible->isEmpty()) {
            throw new PromoCodeException(__('promo_not_for_discounted'));
        }

        return round(
            $eligible->sum(
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

        if (!$promo || !$promo->is_active)
        {
            throw new PromoCodeException(__('promo_invalid'));
        }

        // Admin datetime-local fields are entered in Azerbaijan local time.
        // Interpret the stored DATETIME values in that timezone rather than
        // treating them as UTC when APP_TIMEZONE is UTC.
        $timezone = 'Asia/Baku';
        $now = CarbonImmutable::now($timezone);
        $startsAt = $promo->getRawOriginal('starts_at');
        $expiresAt = $promo->getRawOriginal('expires_at');

        if ($startsAt && $now->lt(CarbonImmutable::parse($startsAt, $timezone)))
        {
            throw new PromoCodeException(__('promo_invalid'));
        }

        if ($expiresAt && $now->gt(CarbonImmutable::parse($expiresAt, $timezone)))
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
