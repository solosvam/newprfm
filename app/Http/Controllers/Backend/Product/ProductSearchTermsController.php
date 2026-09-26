<?php

namespace App\Http\Controllers\Backend\Product;

use App\Http\Controllers\Controller;
use App\Models\Product\Product;
use App\Models\Product\ProductSearchClick;
use App\Models\Product\ProductSearchLog;
use App\Models\Product\ProductSearchTerm;
use App\Services\Search\ProductSearchNormalizer;
use App\Services\Search\ProductSearchService;
use Illuminate\Http\JsonResponse;
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
            ->leftJoin('product_search_terms as resolved_terms', function ($join) {
                $join->on('resolved_terms.normalized_term', '=', 'product_search_logs.normalized_query')
                    ->where('resolved_terms.active', 1);
            })
            ->where('searched_at', '>=', $from)
            ->where('result_count', 0)
            ->whereNull('resolved_terms.id')
            ->select('product_search_logs.normalized_query', DB::raw('MAX(product_search_logs.query) as query'), DB::raw('COUNT(*) as search_count'))
            ->groupBy('product_search_logs.normalized_query')
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

        $message = $this->upsertManualTerm($product, $term, (int) ($data['priority'] ?? 950));
        $search->forgetCachedTerms();

        return back()->with('success', $message);
    }

    public function productLookup(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:100'],
        ]);

        $query = trim($data['q']);
        $products = Product::query()
            ->with('brand')
            ->where(function ($productQuery) use ($query) {
                $productQuery->where('name', 'like', "%{$query}%")
                    ->orWhereHas('brand', fn ($brandQuery) => $brandQuery->where('name', 'like', "%{$query}%"));
            })
            ->orderByDesc('active')
            ->orderBy('name')
            ->limit(15)
            ->get()
            ->map(fn (Product $product) => [
                'id' => $product->id,
                'name' => trim(($product->brand?->name ?? '') . ' ' . $product->name),
            ]);

        return response()->json(['products' => $products]);
    }

    public function attachNoResult(Request $request, ProductSearchService $search): RedirectResponse
    {
        $data = $request->validate([
            'query' => ['required', 'string', 'max:100'],
            'product_id' => ['required', 'integer'],
        ]);

        $product = Product::findOrFail($data['product_id']);
        $message = $this->upsertManualTerm($product, trim($data['query']), 950);
        $search->forgetCachedTerms();

        return redirect()
            ->route('admin.product.search-terms.index')
            ->with('success', $message);
    }

    private function upsertManualTerm(Product $product, string $term, int $priority): string
    {
        $normalized = ProductSearchNormalizer::normalize($term);

        if ($normalized === '') {
            throw new \InvalidArgumentException('Axtarış ifadəsi boş ola bilməz.');
        }

        $attributes = [
            'term' => $term,
            'phonetic_term' => ProductSearchNormalizer::phonetic($term),
            'token_signature' => ProductSearchNormalizer::tokenSignature($term),
            'source' => 'manual',
            'priority' => $priority,
            'active' => (bool) $product->active,
        ];

        $existing = $product->searchTerms()->where('normalized_term', $normalized)->first();

        if ($existing) {
            $existing->update($attributes);
            return 'Mövcud alias əl ilə idarə olunan aliasa çevrildi.';
        }

        $product->searchTerms()->create($attributes + ['normalized_term' => $normalized]);

        return 'Axtarış aliası əlavə edildi.';
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

    public function updateTerm(Request $request, ProductSearchTerm $term, ProductSearchService $search): RedirectResponse
    {
        $data = $request->validate([
            'priority' => ['required', 'integer', 'min:1', 'max:1000'],
            'active' => ['nullable', 'boolean'],
        ]);

        $term->update([
            'priority' => $data['priority'],
            'active' => $request->boolean('active'),
        ]);
        $search->forgetCachedTerms();

        return back()->with('success', 'Alias parametrləri yeniləndi.');
    }
}
