<?php

namespace App\Services;

use App\Models\Credit\CreditPeriod;
use App\Models\Product\Product;
use Illuminate\Support\Str;

/** Məhsul posteri üçün məlumat (public/backend/js/product-poster.js çəkir): məhsul siyahısı və operator paneli */
class ProductPosterData
{
    /**
     * null — şəkil və ya aktiv ölçü yoxdur, poster çəkilə bilməz.
     * $variantId — bir ölçünün posteri: yalnız o ölçü (iri) və hissə-hissə ödəniş qrafiki (credit).
     */
    public function for(Product $product, ?int $variantId = null): ?array
    {
        $product->load(['brand', 'activeDiscount', 'type', 'genders', 'images', 'variants.size']);
        $discount = $product->visibleDiscount(); // məhsul endirimi: köhnə/yeni qiymət, lent, bitmə tarixi
        $image = $product->images->first()?->image;
        $variants = $product->variants->where('active', 1)->sortBy('price')->values();
        if ($variantId !== null) {
            $variants = $variants->where('id', $variantId)->values();
        }

        if (! $image || $variants->isEmpty()) {
            return null;
        }

        $genders = $product->genders->pluck('name_az')->filter()->unique();
        $subtitle = $genders->implode(', ')
            .($genders->isNotEmpty() && $product->type?->name_az ? ' | ' : '')
            .($product->type?->name_az ?? '');

        $credit = $variantId ? $this->credit((float) $variants->first()->price) : null;

        return [
            'brand' => $product->brand?->name ?? '',
            'name' => $product->name,
            'subtitle' => $subtitle,
            'caption' => $this->caption($product, $subtitle, $variants, $discount, $credit),
            'discount' => $discount ? [
                'percent' => $discount->percentLabel(),
                'until' => 'Endirim '.$discount->ends_at->format('d.m.Y, H:i').'-dək',
            ] : null,
            'image' => asset('frontend/uploads/products/'.basename($image)),
            'logo' => asset('frontend/images/logo.svg'),
            'filename' => (Str::slug($product->brand?->name.' '.$product->name.($variantId ? ' '.$variants->first()?->size?->name_az : '')) ?: 'parfumshop-product').'.png',
            'credit' => $credit,
            'variants' => $variants->map(fn ($variant) => [
                'size' => $variant->size?->name_az ?? 'Ölçü',
                'price' => number_format($variant->salePrice(), 2, '.', ''),
                'regular_price' => $discount ? number_format((float) $variant->price, 2, '.', '') : null,
            ]),
        ];
    }

    /**
     * Hissə-hissə ödəniş qrafiki (öz kreditimiz): adi qiymətlə — məhsul endirimi tətbiq olunmur (checkout ilə eyni),
     * yalnız məbləğə uyğun müddətlər (CreditPeriod::availableFor). Aylıq yuxarı yuvarlaqlaşdırılır (checkout.js kimi).
     * @return array<int, array{months: int, monthly: string, total: string, free: bool}>
     */
    private function credit(float $price): array
    {
        return CreditPeriod::where('active', 1)->orderBy('month')->get()
            ->filter(fn (CreditPeriod $period) => $period->availableFor($price))
            ->map(function (CreditPeriod $period) use ($price) {
                $total = round($price * (1 + (float) $period->interest_rate / 100), 2);

                return [
                    'months' => $period->month,
                    'monthly' => number_format(ceil($total / $period->month * 100) / 100, 2, '.', ''),
                    'total' => number_format($total, 2, '.', ''),
                    'free' => (float) $period->interest_rate == 0.0,
                ];
            })->values()->all();
    }

    /**
     * Posterlə birgə göndərilən mətn — qiymət bildirişdə və söhbət siyahısında da görünsün:
     * "Trussardi Black Extreme\nKişi üçün | Eau De Toilette\n30 ml — 107 ₼\n…".
     * Endirimdə: "30 ml — ~107~ 91 ₼" (WhatsApp üstündən xətt) və sonda "Endirim 08.10.2026-dək".
     */
    private function caption(Product $product, string $subtitle, $variants, $discount = null, ?array $credit = null): string
    {
        $number = fn (float $value): string => number_format($value, fmod($value, 1) == 0 ? 0 : 2, '.', ' ');

        return collect([
            trim(($product->brand?->name ?? '').' '.$product->name),
            $subtitle,
            $discount ? '🔥 '.$discount->percentLabel().'% endirim' : null,
        ])
            ->merge($variants->map(fn ($variant) => ($variant->size?->name_az ?? 'Ölçü').' — '
                .($discount ? '~'.$number((float) $variant->price).'~ ' : '').$number($variant->salePrice()).' ₼'))
            ->push($discount ? 'Endirim '.$discount->ends_at->format('d.m.Y').'-dək' : null)
            // bir ölçünün posteri: hissə-hissə qrafiki
            ->merge($credit ? array_merge(['', 'Hissə-hissə ödəniş:'], array_map(fn ($row) => $row['months'].' ay — '.$row['monthly'].' ₼/ay, cəmi '
                .$row['total'].' ₼ ('.($row['free'] ? 'faizsiz' : 'faizlə').')', $credit)) : [])
            ->filter()
            ->implode("\n");
    }
}
