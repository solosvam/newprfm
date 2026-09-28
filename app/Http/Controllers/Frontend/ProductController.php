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
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->take(100)
            ->values();

        $locale = app()->getLocale();

        $products = Product::whereIn('id', $ids)
            ->with([
                'images',
                'brand',
                'variants' => fn ($q) => $q->where('active', 1)->orderBy('price'),
                'variants.size',
            ])
            ->get()
            ->sortBy(fn ($p) => $ids->search($p->id))   // sevimlilərə əlavə olunma sırası
            ->values();

        return response()->json([
            'products' => $products->map(fn ($p) => [
                'id'       => $p->id,
                'name'     => $p->name,
                'brand'    => $p->brand?->name,
                'url'      => route('product', $p->slug),
                'image'    => ($img = $p->images->first()) ? asset('frontend/uploads/products/' . $img->image) : null,
                'variants' => $p->variants->map(fn ($v) => [
                    'id'    => $v->id,
                    'price' => (float) $v->price,
                    'size'  => $v->size?->{'name_' . $locale} ?: $v->size?->name_az,
                ])->values(),
            ]),
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
