<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Faq;
use App\Models\CreditTerms;
use App\Models\Product\Product;
use App\Services\CatalogService;
use App\Services\ProductDetailService;
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

    public function product(string $slug, ProductDetailService $details)
    {
        $product = $details->findBySlug($slug);

        if (!$product && preg_match('/^(\\d+)(?:-|$)/', $slug, $matches)) {
            $legacyProduct = Product::findOrFail((int) $matches[1]);

            return redirect()->route('product', $legacyProduct->slug, 301);
        }

        abort_unless($product, 404);

        if ($slug !== $product->slug) {
            return redirect()->route('product', $product->slug, 301);
        }

        return view('frontend.product', $details->viewData($product));
    }

    public function credit()
    {
        $faqs = Faq::all();
        $creditTerms = CreditTerms::first();

        return view('frontend.internal-credit', [
            'faqs' => $faqs,
            'creditTerms' => $creditTerms,
        ]);
    }
}
