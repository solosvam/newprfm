<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Banners;
use App\Models\Product\Category;
use App\Models\Product\Product;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function show(Request $request, Category $category, ?string $slug = null)
    {
        abort_unless($category->active, 404);

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
            $formattedBanners[$banner->location . $banner->device] = $banner->url;
        }

        return view('frontend.main', [
            'banners' => $formattedBanners,
            'products' => $products,
            'selectedCategory' => $category,
        ]);
    }
}
