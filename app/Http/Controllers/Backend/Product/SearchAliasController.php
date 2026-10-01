<?php

namespace App\Http\Controllers\Backend\Product;

use App\Http\Controllers\Controller;
use App\Models\Product\Brand;
use App\Models\Product\ProductSearchClick;
use App\Models\Product\ProductSearchLog;
use App\Models\Product\SearchAlias;
use App\Services\Search\ProductSearchNormalizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Axtarış idarəetməsi: lüğət (səhv yazılış → brend/model) + axtarış statistikası */
class SearchAliasController extends Controller
{
    public function index(): View
    {
        $from = now()->subDays(30);
        $logs = fn () => ProductSearchLog::query()->where('searched_at', '>=', $from);

        $analytics = [
            'total' => $logs()->count(),
            'with_results' => $logs()->where('result_count', '>', 0)->count(),
            'without_results' => $logs()->where('result_count', 0)->count(),
            'clicked' => $logs()->has('clicks')->count(),
        ];

        $popularQueries = $logs()
            ->select('normalized_query', DB::raw('MAX(query) as query'), DB::raw('COUNT(*) as search_count'))
            ->groupBy('normalized_query')
            ->orderByDesc('search_count')
            ->limit(8)
            ->get();

        $noResultQueries = $logs()
            ->where('result_count', 0)
            ->select('normalized_query', DB::raw('MAX(query) as query'), DB::raw('COUNT(*) as search_count'))
            ->groupBy('normalized_query')
            ->orderByDesc('search_count')
            ->limit(15)
            ->get();

        $popularProducts = ProductSearchClick::query()
            ->where('clicked_at', '>=', $from)
            ->select('product_id', DB::raw('COUNT(*) as click_count'))
            ->with('product.brand')
            ->groupBy('product_id')
            ->orderByDesc('click_count')
            ->limit(8)
            ->get();

        return view('backend.product_menu.search_aliases.index', [
            'analytics' => $analytics,
            'popularQueries' => $popularQueries,
            'noResultQueries' => $noResultQueries,
            'popularProducts' => $popularProducts,
            'aliases' => SearchAlias::query()->with('brand:id,name')->latest('id')->get(),
            'brands' => Brand::query()->orderBy('name')->get(['id', 'name']),
            'types' => SearchAlias::TYPES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge(['alias_normalized' => ProductSearchNormalizer::normalize((string) $request->input('alias'))]);

        $data = $request->validateWithBag('createAlias', [
            'alias' => ['required', 'string', 'max:100'],
            'alias_normalized' => ['required', 'string', Rule::unique('search_aliases', 'alias_normalized')],
            'type' => ['required', Rule::in(array_keys(SearchAlias::TYPES))],
            'brand_id' => [Rule::requiredIf($request->input('type') === SearchAlias::BRAND), 'nullable', 'integer', Rule::exists('brands', 'id')],
            'original' => [Rule::requiredIf($request->input('type') === SearchAlias::MODEL), 'nullable', 'string', 'max:150'],
            'from_query' => ['nullable', 'string', 'max:100'],
        ], [
            'alias_normalized.required' => 'Alias-da hərf və ya rəqəm olmalıdır.',
            'alias_normalized.unique' => 'Bu alias artıq lüğətdə var.',
            'brand_id.required' => 'Brendi seçin.',
            'original.required' => 'Modelin düzgün adını yazın.',
        ], ['alias' => 'Alias', 'type' => 'Növ', 'brand_id' => 'Brend', 'original' => 'Model']);

        $type = $data['type'];
        SearchAlias::create([
            'alias' => trim($data['alias']),
            'type' => $type,
            'brand_id' => $type === SearchAlias::IGNORE ? null : ($data['brand_id'] ?? null),
            'original' => $type === SearchAlias::MODEL ? trim($data['original']) : null,
            'created_by' => auth('admin')->id(),
        ]);

        // "Nəticəsiz axtarışlar"dan əlavə olunubsa — həmin qeyd siyahıdan çıxır
        $resolved = array_filter([$data['alias_normalized'], ProductSearchNormalizer::normalize($data['from_query'] ?? '')]);
        ProductSearchLog::query()->whereIn('normalized_query', $resolved)->where('result_count', 0)->delete();

        return redirect()->route('admin.product.search-aliases.index')->with('success', 'Alias lüğətə əlavə olundu.');
    }

    public function destroy(SearchAlias $alias): RedirectResponse
    {
        $alias->delete();

        return redirect()->route('admin.product.search-aliases.index')->with('success', 'Alias silindi.');
    }

    /** Bir və ya bir neçə nəticəsiz sorğunun qeydlərini silir (səhifədən AJAX ilə, yenilənmədən) */
    public function destroyNoResult(Request $request): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'queries' => ['required_without:query', 'array', 'max:200'],
            'queries.*' => ['string', 'max:100'],
            'query' => ['required_without:queries', 'string', 'max:100'],
        ]);

        $queries = collect($data['queries'] ?? [$data['query']])
            ->map(fn (string $query) => ProductSearchNormalizer::normalize($query))
            ->filter()
            ->unique()
            ->values();

        $deleted = ProductSearchLog::query()
            ->whereIn('normalized_query', $queries)
            ->where('result_count', 0)
            ->delete();

        if ($request->expectsJson()) {
            return response()->json(['deleted' => $deleted, 'queries' => $queries]);
        }

        return redirect()->route('admin.product.search-aliases.index')->with('success', 'Nəticəsiz axtarış qeydləri silindi.');
    }
}
