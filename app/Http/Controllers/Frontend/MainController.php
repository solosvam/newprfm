<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Services\CatalogService;
use Illuminate\Http\Request;

class MainController extends Controller
{
    public function index(Request $request, CatalogService $catalog)
    {
        $query = $catalog->applyFilters($catalog->productQuery(), $request);
        $products = $catalog->applySort($query, $request->input('sort'))
            ->paginate(12)
            ->withQueryString();

        return view('frontend.home', array_merge(
            $catalog->catalogData(),
            ['products' => $products]
        ));
    }
}
