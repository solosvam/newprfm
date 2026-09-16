<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Models\Banners;
use App\Models\Faq;
use App\Models\Product\Products;
use App\Services\SeoUrl;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function product($slug)
    {
        $product = Products::findOrFail(SeoUrl::decodeSlug($slug));

        return view('front.product',[
            'product'   => $product
        ]);
    }
}
