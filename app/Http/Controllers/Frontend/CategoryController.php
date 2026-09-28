<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Product\Category;
use App\Services\CatalogService;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function show(Request $request, string $slug, CatalogService $catalog)
    {
        $category = Category::where('slug', $slug)->where('active', 1)->firstOrFail();

        $query = $catalog->productQuery()
            ->whereHas('categories', fn ($q) => $q->where('categories.id', $category->id));

        $query = $catalog->applyFilters($query, $request);
        $products = $catalog->applySort($query, $request->input('sort'))
            ->paginate(12)
            ->withQueryString();

        return view('frontend.category', array_merge(
            $catalog->catalogData(),
            [
                'products' => $products,
                'selectedCategory' => $category,
            ]
        ));
    }
}
