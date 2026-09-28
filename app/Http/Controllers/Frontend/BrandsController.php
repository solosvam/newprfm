<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Product\Brand;
use App\Services\CatalogService;
use Illuminate\Http\Request;

class BrandsController extends Controller
{
    public function index()
    {
        $brands = Brand::where('active', 1)
            ->orderBy('name')
            ->get()
            ->groupBy(fn ($brand) => strtoupper(substr($brand->name, 0, 1)));

        return view('frontend.brands', compact('brands'));
    }

    public function products(Request $request, string $slug, CatalogService $catalog)
    {
        $brand = Brand::where('slug', $slug)->where('active', 1)->first();

        if (!$brand && preg_match('/^(\\d+)(?:-|$)/', $slug, $matches)) {
            $legacyBrand = Brand::whereKey((int) $matches[1])->where('active', 1)->firstOrFail();
            return redirect()->route('brand.products', $legacyBrand->slug, 301);
        }

        abort_unless($brand, 404);

        $query = $catalog->productQuery()->where('brand_id', $brand->id);
        $query = $catalog->applyFilters($query, $request);
        $products = $catalog->applySort($query, $request->input('sort'))
            ->paginate(12)
            ->withQueryString();

        return view('frontend.brand', array_merge(
            $catalog->catalogData(),
            [
                'products'      => $products,
                'selectedBrand' => $brand,
            ]
        ));
    }
}
