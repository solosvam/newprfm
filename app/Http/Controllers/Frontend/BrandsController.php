<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Product\Brand;
use App\Models\Product\Category;
use App\Models\Product\Gender;
use App\Models\Product\Product;
use App\Models\Banners;
use App\Models\Product\Type;
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

        return view('frontend.brands', compact('brands'));
    }
    public function products(string $slug)
    {
        $brand = Brand::where('slug', $slug)->where('active', 1)->first();

        if (!$brand && preg_match('/^(\\d+)(?:-|$)/', $slug, $matches)) {
            $legacyBrand = Brand::whereKey((int) $matches[1])->where('active', 1)->firstOrFail();
            return redirect()->route('brand.products', $legacyBrand->slug, 301);
        }

        abort_unless($brand, 404);

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

        $brands = Brand::query()
            ->where('active', 1)
            ->withCount([
                'products as products_count' => fn ($query) => $query->where('active', 1),
            ])
            ->having('products_count', '>', 0)
            ->orderByDesc('products_count')
            ->orderBy('name')
            ->limit(12)
            ->get();
        $allBrands = Brand::where('active',1)->get();
        $categories = Category::where('active', 1)->orderBy('id')->get();
        $genders = Gender::all();
        $types = Type::orderBy('id')->get();

        $recommendedProducts = Product::with([
            'brand',
            'type',
            'images',
            'variants' => fn ($query) => $query
                ->where('active', 1)
                ->orderBy('price'),
            'variants.size',
        ])
            ->where('active', 1)
            ->whereHas('variants', fn ($query) => $query->where('active', 1))
            ->inRandomOrder()
            ->limit(6)
            ->get();

        $bestSellers = Product::with([
            'brand',
            'type',
            'images',
            'variants' => fn ($query) => $query
                ->where('active', 1)
                ->orderBy('price'),
            'variants.size',
        ])
            ->where('active', 1)
            ->whereHas('variants', fn ($query) => $query->where('active', 1))
            ->inRandomOrder()
            ->limit(6)
            ->get();

        return view('frontend.home', [
            'banners' => $formattedBanners,
            'products' => $products,
            'selectedBrand' => $brand,
            'brands' => $brands,
            'allBrands' => $allBrands,
            'categories' => $categories,
            'bestSellers' => $bestSellers,
            'recommendedProducts' => $recommendedProducts,
            'genders' => $genders,
            'types' => $types,
        ]);
    }
}
