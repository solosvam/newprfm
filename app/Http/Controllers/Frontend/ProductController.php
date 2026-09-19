<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Product\Product;
use App\Models\Product\ProductReview;
use App\Services\SeoUrl;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function product($slug)
    {
        $product = Product::with([
            'brand',
            'type',
            'images',
            'genders',
            'ingredients',
            'variants' => fn ($query) => $query->where('active', 1)->orderBy('price'),
            'variants.size',
            'reviews' => fn ($query) => $query->with('customer')->latest(),
        ])->findOrFail(SeoUrl::decodeSlug($slug));

        $ingredientIds = $product->ingredients->pluck('id');

        $similarProducts = collect();

        if ($ingredientIds->isNotEmpty()) {
            $similarProducts = Product::query()
                ->where('id', '!=', $product->id)
                ->where('active', 1)
                ->whereHas('ingredients', fn ($query) => $query->whereIn('ingredients.id', $ingredientIds))
                ->withCount([
                    'ingredients as shared_ingredients_count' => fn ($query) =>
                        $query->whereIn('ingredients.id', $ingredientIds),
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
                ->limit(5)
                ->get();
        }

        $ratingAverage = round((float) $product->reviews->avg('rating'), 1);
        $ratingCounts = collect(range(1, 5))->mapWithKeys(
            fn ($rating) => [$rating => $product->reviews->where('rating', $rating)->count()]
        );

        return view('frontend.product', compact(
            'product',
            'similarProducts',
            'ratingAverage',
            'ratingCounts'
        ));
    }

    public function review(Request $request, Product $product)
    {
        $data = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['required', 'string', 'min:3', 'max:2000'],
        ]);

        ProductReview::create([
            'product_id' => $product->id,
            'customer_id' => auth()->id(),
            'rating' => $data['rating'],
            'comment' => $data['comment'],
        ]);

        return back()->with('review_success', 'Rəyiniz əlavə edildi.');
    }
}
