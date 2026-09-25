<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Support\LocalizedValidation;
use App\Models\Product\Product;
use App\Models\Product\ProductReview;
use App\Models\CreditPeriod;
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

        $canonicalSlug = $product->slug;
        if ($slug !== $canonicalSlug) {
            return redirect()->route('product', $canonicalSlug, 301);
        }

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

        $creditPeriods = CreditPeriod::where('active', 1)
            ->orderBy('sort_order')
            ->orderBy('month')
            ->get();

        $ratingAverage = round((float) $product->reviews->avg('rating'), 1);
        $ratingCounts = collect(range(1, 5))->mapWithKeys(
            fn ($rating) => [$rating => $product->reviews->where('rating', $rating)->count()]
        );

        return view('frontend.product', compact(
            'product',
            'similarProducts',
            'ratingAverage',
            'ratingCounts',
            'creditPeriods'
        ));
    }

    public function review(Request $request, Product $product)
    {
        $data = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['required', 'string', 'min:3', 'max:2000'],
        ], LocalizedValidation::messages(), LocalizedValidation::attributes());

        ProductReview::create([
            'product_id' => $product->id,
            'customer_id' => auth()->id(),
            'rating' => $data['rating'],
            'comment' => $data['comment'],
        ]);

        return back()->with('review_success', __('validation_your_review_has_been_added'));
    }
    public function wishlistProducts(\Illuminate\Http\Request $request)
    {
        $ids = collect(explode(',', (string) $request->query('ids')))
            ->filter()->map(fn ($id) => (int) $id)->unique()->values();

        return Product::with(['brand', 'images'])
            ->whereIn('id', $ids)
            ->where('active', 1)
            ->get()
            ->sortBy(fn ($product) => $ids->search($product->id))
            ->values()
            ->map(fn ($product) => [
                'id' => $product->id,
                'name' => $product->name,
                'brand' => $product->brand?->name,
                'url' => route('product', $product->slug),
                'image' => $product->images->first() ? asset('frontend/uploads/products/'.$product->images->first()->image) : null,
            ]);
    }

    public function cartProducts(\Illuminate\Http\Request $request)
    {
        $variantIds = collect(explode(',', (string) $request->query('variants')))
            ->filter()->map(fn ($id) => (int) $id)->unique()->values();

        return \App\Models\Product\ProductVariant::with(['product.brand','product.images','product.genders','product.type','size'])
            ->whereIn('id', $variantIds)
            ->where('active', 1)
            ->get()
            ->map(function ($variant) {
                $product = $variant->product;
                $locale = app()->getLocale();
                $gender = $product?->genders?->first();
                $image = $product?->images?->first();
                return [
                    'variant_id' => $variant->id,
                    'product_id' => $product?->id,
                    'name' => $product?->name,
                    'brand' => $product?->brand?->name,
                    'gender' => $gender ? ($gender->{'name_'.$locale} ?? $gender->name_az) : null,
                    'type' => $product?->type ? ($product->type->{'name_'.$locale} ?? $product->type->name_az) : null,
                    'size' => $variant->size ? ($variant->size->{'name_'.$locale} ?? $variant->size->name_az) : null,
                    'price' => (float) $variant->price,
                    'image' => $image ? asset('frontend/uploads/products/'.$image->image) : null,
                ];
            })->values();
    }

}
