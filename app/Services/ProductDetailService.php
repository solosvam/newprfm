<?php

namespace App\Services;

use App\Models\CreditPeriod;
use App\Models\CreditTermItem;
use App\Models\Product\Product;
use Illuminate\Support\Collection;

class ProductDetailService
{
    public function findBySlug(string $slug): ?Product
    {
        return Product::with([
            'brand',
            'type',
            'images',
            'genders',
            'ingredients',
            'variants' => fn ($query) => $query->where('active', 1)->orderBy('price'),
            'variants.size',
            'reviews' => fn ($query) => $query->where('active', true)->with('customer')->latest(),
        ])->where('slug', $slug)->first();
    }

    public function similarProducts(Product $product, int $limit = 5): Collection
    {
        $ingredientIds = $product->ingredients->pluck('id');

        if ($ingredientIds->isEmpty()) {
            return collect();
        }

        return Product::query()
            ->where('id', '!=', $product->id)
            ->where('active', 1)
            ->whereHas('ingredients', fn ($query) => $query->whereIn('ingredients.id', $ingredientIds))
            ->withCount([
                'ingredients as shared_ingredients_count' =>
                    fn ($query) => $query->whereIn('ingredients.id', $ingredientIds),
            ])
            ->with([
                'brand',
                'type',
                'images',
                'genders',
                'variants' => fn ($query) => $query->where('active', 1)->orderBy('price'),
                'variants.size',
            ])
            ->orderByDesc('shared_ingredients_count')
            ->limit($limit)
            ->get();
    }

    public function ratings(Product $product): array
    {
        $reviews = $product->reviews;

        return [
            'ratingAverage' => round((float) $reviews->avg('rating'), 1),
            'ratingCounts' => collect(range(1, 5))->mapWithKeys(
                fn ($rating) => [$rating => $reviews->where('rating', $rating)->count()]
            ),
        ];
    }

    public function creditPeriods(): Collection
    {
        return CreditPeriod::where('active', 1)
            ->orderBy('sort_order')
            ->orderBy('month')
            ->get();
    }

    public function viewData(Product $product): array
    {
        return array_merge([
            'product' => $product,
            'similarProducts' => $this->similarProducts($product),
            'creditPeriods' => $this->creditPeriods(),
            'creditTermItems' => CreditTermItem::orderBy('sort_order')->orderBy('id')->get(),
            'creditProfileComplete' => auth()->check() && (bool) auth()->user()->creditProfile?->isComplete(),
        ], $this->ratings($product));
    }
}
