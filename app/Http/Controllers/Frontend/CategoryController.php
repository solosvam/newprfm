<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Banners;
use App\Models\Product\Brand;
use App\Models\Product\Category;
use App\Models\Product\Gender;
use App\Models\Product\Product;
use App\Models\Product\Type;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function show(Request $request, string $slug)
    {
        $category = Category::where('slug', $slug)->where('active', 1)->firstOrFail();

        $categories = Category::where('active', 1)->orderBy('id')->get();
        $genders = Gender::all();
        $types = Type::orderBy('id')->get();
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

        $query = Product::with([
            'brand',
            'type',
            'images',
            'genders',
            'variants' => fn ($query) => $query->where('active', 1)->orderBy('price'),
            'variants.size',
        ])
            ->where('active', 1)
            ->whereHas('categories', fn ($query) => $query->where('categories.id', $category->id));

        switch ($request->get('sort')) {
            case 'oldest':
                $query->orderBy('id');
                break;
            case 'price_asc':
                $query->orderBy(
                    Product::selectRaw('MIN(product_variants.price)')
                        ->join('product_variants', 'product_variants.product_id', '=', 'products.id')
                        ->whereColumn('products.id', 'product_variants.product_id')
                        ->where('product_variants.active', 1)
                );
                break;
            case 'price_desc':
                $query->orderByDesc(
                    Product::selectRaw('MIN(product_variants.price)')
                        ->join('product_variants', 'product_variants.product_id', '=', 'products.id')
                        ->whereColumn('products.id', 'product_variants.product_id')
                        ->where('product_variants.active', 1)
                );
                break;
            default:
                $query->orderByDesc('id');
                break;
        }

        $products = $query->paginate(12)->withQueryString();
        $formattedBanners = [];

        foreach (Banners::where('active', 1)->get() as $banner) {
            $formattedBanners[$banner->location . $banner->device] = $banner->imageForLocale();
        }

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

        return view('frontend.new.home', [
            'banners' => $formattedBanners,
            'products' => $products,
            'categories' => $categories,
            'brands' => $brands,
            'types' => $types,
            'genders' => $genders,
            'allBrands' => $allBrands,
            'bestSellers' => $bestSellers,
            'recommendedProducts' => $recommendedProducts,
            'selectedCategory' => $category,
        ]);
    }
}
