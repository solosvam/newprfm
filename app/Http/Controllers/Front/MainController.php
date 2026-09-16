<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Models\Banners;
use App\Models\Faq;
use App\Models\Product\Products;
use Illuminate\Http\Request;

class MainController extends Controller
{
    public function index()
    {
        $banners = Banners::where('active', 1)->get();
        $products = Products::where('active',1)->paginate(9);
        $formattedBanners = [];
        foreach ($banners as $banner) {
            $key = $banner->location . $banner->device;
            $formattedBanners[$key] = $banner->url;
        }

        return view('front.main',[
            'banners'   => $formattedBanners,
            'products'  => $products
        ]);
    }

    public function credit()
    {
        $faqs = Faq::all();
        return view('front.internal-credit',[
            'faqs'  => $faqs
        ]);
    }
}
