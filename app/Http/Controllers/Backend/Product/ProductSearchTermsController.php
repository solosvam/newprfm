<?php

namespace App\Http\Controllers\Backend\Product;

use App\Http\Controllers\Controller;
use App\Models\Product\Product;
use App\Models\Product\ProductSearchClick;
use App\Models\Product\ProductSearchLog;
use App\Models\Product\ProductSearchTerm;
use App\Services\Search\ProductSearchNormalizer;
use App\Services\Search\ProductSearchService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductSearchTermsController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->input('q'));
        $from = now()->subDays(30);
        $products = Product::query()
            ->with('brand')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($productQuery) use ($search) {
                    $productQuery->where('name', 'like', "%{$search}%")
                        ->orWhereHas('brand', fn ($brandQuery) => $brandQuery->where('name', 'like', "%{$search}%"));
                });
            })
            ->orderBy('id', 'desc')
            ->paginate(20)
            ->withQueryString();

        $analytics = [
            'total' => ProductSearchLog::query()->where('searched_at', '>=', $from)->count(),
            'with_results' => ProductSearchLog::query()->where('searched_at', '>=', $from)->where('result_count', '>', 0)->count(),
            'without_results' => ProductSearchLog::query()->where('searched_at', '>=', $from)->where('result_count', 0)->count(),
            'clicked' => ProductSearchLog::query()->where('searched_at', '>=', $from)->has('clicks')->count(),
        ];

        $popularQueries = ProductSearchLog::query()
            ->where('searched_at', '>=', $from)
            ->select('normalized_query', DB::raw('MAX(query) as query'), DB::raw('COUNT(*) as search_count'))
            ->groupBy('normalized_query')
            ->orderByDesc('search_count')
            ->limit(8)
            ->get();

        $noResultQueries = ProductSearchLog::query()
            ->where('searched_at', '>=', $from)
            ->where('result_count', 0)
            ->select('normalized_query', DB::raw('MAX(query) as query'), DB::raw('COUNT(*) as search_count'))
            ->groupBy('normalized_query')
            ->orderByDesc('search_count')
            ->limit(8)
            ->get();

        $popularProducts = ProductSearchClick::query()
            ->where('clicked_at', '>=', $from)
            ->select('product_search_clicks.product_id', DB::raw('COUNT(*) as click_count'))
            ->with(['product.brand'])
            ->groupBy('product_search_clicks.product_id')
            ->orderByDesc('click_count')
            ->limit(8)
            ->get();

        return view('backend.product_menu.search_terms.index', compact(
            'products', 'search', 'analytics', 'popularQueries', 'noResultQueries', 'popularProducts'
        ));
    }

    public function show(Product $product)
    {
        $product->load(['brand', 'searchTerms' => fn ($query) => $query->orderByDesc('priority')->orderBy('term')]);

        return view('backend.product_menu.search_terms.show', compact('product'));
    }

    public function store(Request $request, Product $product, ProductSearchService $search): RedirectResponse
    {
        $data = $request->validate([
            'term' => ['required', 'string', 'max:255'],
            'priority' => ['nullable', 'integer', 'min:1', 'max:1000'],
        ]);

        $term = trim($data['term']);
        $normalized = ProductSearchNormalizer::normalize($term);

        if ($normalized === '') {
            return back()->withErrors(['term' => 'Axtarış ifadəsi boş ola bilməz.']);
        }

        $attributes = [
            'term' => $term,
            'phonetic_term' => ProductSearchNormalizer::phonetic($term),
            'token_signature' => ProductSearchNormalizer::tokenSignature($term),
            'source' => 'manual',
            'priority' => $data['priority'] ?? 950,
            'active' => (bool) $product->active,
        ];

        $existing = $product->searchTerms()->where('normalized_term', $normalized)->first();

        if ($existing) {
            $existing->update($attributes);
            $message = 'Mövcud alias əl ilə idarə olunan aliasa çevrildi.';
        } else {
            $product->searchTerms()->create($attributes + ['normalized_term' => $normalized]);
            $message = 'Axtarış aliası əlavə edildi.';
        }

        $search->forgetCachedTerms();

        return back()->with('success', $message);
    }

    public function destroy(ProductSearchTerm $term, ProductSearchService $search): RedirectResponse
    {
        $product = $term->product;
        $term->delete();
        $search->forgetCachedTerms();

        return redirect()
            ->route('admin.product.search-terms.show', $product)
            ->with('success', 'Axtarış aliası silindi.');
    }
}
