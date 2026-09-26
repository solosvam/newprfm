<?php

namespace App\Http\Controllers\Backend\Product;

use App\Http\Controllers\Controller;
use App\Models\Product\Product;
use App\Models\Product\ProductSearchTerm;
use App\Services\Search\ProductSearchNormalizer;
use App\Services\Search\ProductSearchService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProductSearchTermsController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->input('q'));
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

        return view('backend.product_menu.search_terms.index', compact('products', 'search'));
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
