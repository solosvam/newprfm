<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\CreditPeriod;
use App\Models\Faq;
use App\Models\CreditTerms;
use App\Models\Product\Product;
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

    public function product($slug)
    {
        $product = Product::with([
            'brand',
            'type',
            'images',
            'genders',
            'ingredients',
            'variants' => fn ($query) => $query->where('active', 1)->orderBy('price'),
            'variants.size',
            'reviews' => fn ($query) => $query->where('active', true)->with('customer')->latest(),
        ])->where('slug', $slug)->first();

        if (!$product && preg_match('/^(\\d+)(?:-|$)/', $slug, $matches)) {
            $legacyProduct = Product::findOrFail((int) $matches[1]);
            return redirect()->route('product', $legacyProduct->slug, 301);
        }

        abort_unless($product, 404);

        $canonicalSlug = $product->slug;
        if ($slug !== $canonicalSlug) {
            return redirect()->route('product', $canonicalSlug, 301);
        }

        $ingredientIds = $product->ingredients->pluck('id');

        $similarProducts = collect();

        if ($ingredientIds->isNotEmpty()) {
            $similarProducts = Product::query()
                ->where('id', '!=', $product->id)
                ->where('active', 1)
                ->whereHas('ingredients', fn ($query) => $query->whereIn('ingredients.id', $ingredientIds))
                ->withCount([
                    'ingredients as shared_ingredients_count' => fn ($query) =>
                    $query->whereIn('ingredients.id', $ingredientIds),
                ])
                ->with([
                    'brand',
                    'type',
                    'images',
                    'genders',
                    'variants' => fn ($query) => $query->where('active', 1)->orderBy('price'),
                    'variants.size',
                ])
                ->orderByDesc('shared_ingredients_count')
                ->limit(4)
                ->get();
        }

        $creditPeriods = CreditPeriod::where('active', 1)
            ->orderBy('sort_order')
            ->orderBy('month')
            ->get();

        $ratingAverage = round((float) $product->reviews->avg('rating'), 1);
        $ratingCounts = collect(range(1, 5))->mapWithKeys(
            fn ($rating) => [$rating => $product->reviews->where('rating', $rating)->count()]
        );

        return view('frontend.product', compact(
            'product',
            'similarProducts',
            'ratingAverage',
            'ratingCounts',
            'creditPeriods'
        ));
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
