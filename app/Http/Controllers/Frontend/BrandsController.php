<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Product\Brand;
use App\Models\Product\Product;
use App\Models\Banners;
use App\Services\SeoUrl;
use Illuminate\Http\Request;

class BrandsController extends Controller
{
    public function index()
    {
        $brands = Brand::where('active', 1)
            ->orderBy('name')
            ->get()
            ->groupBy(function($brand) {
                return strtoupper(substr($brand->name, 0, 1)); // İlk hərfə görə qrupla
            });

        return view('frontend.new.brands', compact('brands'));
    }
    public function products(string $slug)
    {
        $brandId = (int) SeoUrl::decodeSlug($slug);
        $brand = Brand::where('id', $brandId)->where('active', 1)->firstOrFail();

        $canonicalSlug = SeoUrl::generateImageName([
            'id' => $brand->id,
            'title' => $brand->name,
        ]);

        if ($slug !== $canonicalSlug) {
            return redirect()->route('brand.products', $canonicalSlug, 301);
        }

        $banners = Banners::where('active', 1)->get();

        $products = Product::with([
            'brand',
            'type',
            'images',
            'genders',
            'variants' => function ($query) {
                $query->where('active', 1)->orderBy('price');
            },
            'variants.size',
        ])
            ->where('active', 1)
            ->where('brand_id', $brand->id)
            ->orderByDesc('id')
            ->paginate(12)
            ->withQueryString();

        $formattedBanners = [];
        foreach ($banners as $banner) {
            $formattedBanners[$banner->location . $banner->device] = $banner->imageForLocale();
        }

        return view('frontend.main', [
            'banners' => $formattedBanners,
            'products' => $products,
            'selectedBrand' => $brand,
        ]);
    }
}
