<?php

namespace App\Services;

use App\Models\Product\Product;
use Illuminate\Support\Str;

/** Məhsul posteri üçün məlumat (public/backend/js/product-poster.js çəkir): məhsul siyahısı və operator paneli */
class ProductPosterData
{
    /** null — şəkil və ya aktiv ölçü yoxdur, poster çəkilə bilməz */
    public function for(Product $product): ?array
    {
        $product->load(['brand', 'type', 'genders', 'images', 'variants.size']);
        $image = $product->images->first()?->image;
        $variants = $product->variants->where('active', 1)->sortBy('price')->values();

        if (! $image || $variants->isEmpty()) {
            return null;
        }

        $genders = $product->genders->pluck('name_az')->filter()->unique();
        $subtitle = $genders->implode(', ')
            .($genders->isNotEmpty() && $product->type?->name_az ? ' | ' : '')
            .($product->type?->name_az ?? '');

        return [
            'brand' => $product->brand?->name ?? '',
            'name' => $product->name,
            'subtitle' => $subtitle,
            'caption' => $this->caption($product, $subtitle, $variants),
            'image' => asset('frontend/uploads/products/'.basename($image)),
            'logo' => asset('frontend/images/logo.svg'),
            'filename' => (Str::slug($product->brand?->name.' '.$product->name) ?: 'parfumshop-product').'.png',
            'variants' => $variants->map(fn ($variant) => [
                'size' => $variant->size?->name_az ?? 'Ölçü',
                'price' => number_format((float) $variant->price, 2, '.', ''),
            ]),
        ];
    }

    /**
     * Posterlə birgə göndərilən mətn — qiymət bildirişdə və söhbət siyahısında da görünsün:
     * "Trussardi Black Extreme\nKişi üçün | Eau De Toilette\n30 ml — 107 ₼\n…".
     */
    private function caption(Product $product, string $subtitle, $variants): string
    {
        $price = fn (float $value): string => number_format($value, fmod($value, 1) == 0 ? 0 : 2, '.', ' ').' ₼';

        return collect([trim(($product->brand?->name ?? '').' '.$product->name), $subtitle])
            ->merge($variants->map(fn ($variant) => ($variant->size?->name_az ?? 'Ölçü').' — '.$price((float) $variant->price)))
            ->filter()
            ->implode("\n");
    }
}
