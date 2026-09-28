<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Product\ProductVariant;
use App\Support\LocalizedValidation;
use App\Models\Product\Product;
use App\Models\Product\ProductReview;
use App\Services\ProductDetailService;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function product(string $slug, ProductDetailService $details)
    {
        $product = $details->findBySlug($slug);

        if (!$product && preg_match('/^(\\d+)(?:-|$)/', $slug, $matches)) {
            $legacyProduct = Product::findOrFail((int) $matches[1]);

            return redirect()->route('product', $legacyProduct->slug, 301);
        }

        abort_unless($product, 404);

        if ($slug !== $product->slug) {
            return redirect()->route('product', $product->slug, 301);
        }

        return view('frontend.product', $details->viewData($product));
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
            'active' => false,
        ]);

        return back()->with('review_success', __('reviews_submitted_for_approval'));
    }
    public function wishlist(Request $request)
    {
        $ids = collect(explode(',', (string) $request->query('ids')))
            ->filter()->map(fn ($id) => (int) $id)->unique()->values();

        return Product::with([
            'images',
            'brand',
            'variants' => fn ($q) => $q->where('active', 1)->orderBy('price'),
            'variants.size',
        ])
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

    public function cart(Request $request)
    {
        $variantIds = collect(explode(',', (string) $request->query('variants')))
            ->filter()->map(fn ($id) => (int) $id)->unique()->values();

        return ProductVariant::with(['product.brand','product.images','product.genders','product.type','size'])
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
