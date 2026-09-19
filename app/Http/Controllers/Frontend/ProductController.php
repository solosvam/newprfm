<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Banners;
use App\Models\Faq;
use App\Models\Product\Product;
use App\Services\SeoUrl;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function product($slug)
    {
        $product = Product::findOrFail(SeoUrl::decodeSlug($slug));

        return view('frontend.product',[
            'product'   => $product
        ]);
    }
}
