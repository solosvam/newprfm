<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Banners;
use App\Models\Faq;
use App\Models\Product\Product;
use Illuminate\Http\Request;

class MainController extends Controller
{
    public function index()
    {
        $banners = Banners::where('active', 1)->get();
        $products = Product::where('active',1)->paginate(9);
        $formattedBanners = [];
        foreach ($banners as $banner) {
            $key = $banner->location . $banner->device;
            $formattedBanners[$key] = $banner->url;
        }

        return view('frontend.main',[
            'banners'   => $formattedBanners,
            'products'  => $products
        ]);
    }

    public function credit()
    {
        $faqs = Faq::all();
        return view('frontend.internal-credit',[
            'faqs'  => $faqs
        ]);
    }
}
