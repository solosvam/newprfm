<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Product\Product;
use App\Services\SeoUrl;

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
            'variants' => function ($query) {
                $query
                    ->where('active', 1)
                    ->orderBy('price');
            },
            'variants.size',
        ])->findOrFail(SeoUrl::decodeSlug($slug));

        return view('frontend.product', [
            'product' => $product,
        ]);
    }
}
