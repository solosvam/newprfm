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

        if (!$image || $variants->isEmpty()) {
            return null;
        }

        $genders = $product->genders->pluck('name_az')->filter()->unique();

        return [
            'brand' => $product->brand?->name ?? '',
            'name' => $product->name,
            'subtitle' => $genders->implode(', ')
                .($genders->isNotEmpty() && $product->type?->name_az ? ' | ' : '')
                .($product->type?->name_az ?? ''),
            'image' => asset('frontend/uploads/products/'.basename($image)),
            'logo' => asset('frontend/images/logo.svg'),
            'filename' => (Str::slug($product->brand?->name.' '.$product->name) ?: 'parfumshop-product').'.png',
            'variants' => $variants->map(fn ($variant) => [
                'size' => $variant->size?->name_az ?? 'Ölçü',
                'price' => number_format((float) $variant->price, 2, '.', ''),
            ]),
        ];
    }
}
