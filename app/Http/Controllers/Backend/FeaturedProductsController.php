<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Product\FeaturedProduct;
use App\Models\Product\Product;
use App\Services\Search\ProductSearchNormalizer;
use App\Services\Search\ProductSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Sayt → Vitrin: ana səhifədə "Populyar" sıralamasında birinci səhifədə görünən ətirlər (ən çox 12).
 * Əlavə et (axtarışla), sürüşdürərək sırala, çıxar. Deaktiv məhsul siyahıda qalır, amma saytda görünmür.
 */
class FeaturedProductsController extends Controller
{
    public function index(): View
    {
        $featured = FeaturedProduct::query()
            ->with(['product.brand', 'product.images' => fn ($images) => $images->limit(1),
                'product.variants' => fn ($variants) => $variants->where('active', 1)->orderBy('price')])
            ->orderBy('position')
            ->get();

        return view('backend.featured-products.index', [
            'featured' => $featured,
            'limit' => FeaturedProduct::LIMIT,
        ]);
    }

    /**
     * Canlı axtarış (yazdıqca): rəhbərin qaydası — boşluqdan əvvəl brend, sonra model ("dol int");
     * boşluq yoxdursa həm brenddə, həm adda (ProductSearchService::shortcutIds). Vitrində olanlar çıxarılır.
     */
    public function search(Request $request, ProductSearchService $search): JsonResponse
    {
        $q = trim((string) $request->query('q'));
        if (mb_strlen($q) < 2) {
            return response()->json(['results' => []]);
        }
        $ids = $search->shortcutIds(ProductSearchNormalizer::normalize($q), 30);
        $featured = FeaturedProduct::pluck('product_id')->all();

        $results = Product::query()
            ->with(['brand', 'images' => fn ($images) => $images->limit(1),
                'variants' => fn ($variants) => $variants->where('active', 1)->orderBy('price')])
            ->whereIn('id', array_diff($ids, $featured))
            ->get()
            ->sortBy(fn (Product $product) => array_search($product->id, $ids))
            ->take(20)
            ->values();

        return response()->json(['results' => $results->map(fn (Product $product) => [
            'id' => $product->id,
            'name' => trim($product->brand?->name.' '.$product->name),
            'price' => $product->variants->first() ? number_format((float) $product->variants->first()->price, 2) : null,
            'image' => ($image = $product->images->first()) ? asset('frontend/uploads/products/'.$image->image) : null,
        ])]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $data = $request->validate(['product_id' => ['required', 'integer', 'exists:products,id']]);

        return DB::transaction(function () use ($data, $request) {
            $count = FeaturedProduct::query()->lockForUpdate()->count();
            if ($count >= FeaturedProduct::LIMIT) {
                $message = 'Vitrində ən çox '.FeaturedProduct::LIMIT.' ətir ola bilər — əvvəlcə birini çıxarın.';

                return $request->expectsJson() ? response()->json(['message' => $message], 422) : back()->with('error', $message);
            }
            FeaturedProduct::firstOrCreate(['product_id' => $data['product_id']], ['position' => $count + 1]);
            // AJAX-dan sonra səhifə yenilənir — bildiriş (və səs) həmin yenilənmədə göstərilsin
            session()->flash('success', 'Vitrinə əlavə olundu.');

            return $request->expectsJson()
                ? response()->json(['ok' => true])
                : redirect()->route('admin.featured.index');
        });
    }

    /** Sürüşdürmədən sonra yeni ardıcıllıq: ids — featured_products.id-lər yuxarıdan aşağı */
    public function reorder(Request $request): JsonResponse
    {
        $ids = $request->validate(['ids' => ['required', 'array', 'max:'.FeaturedProduct::LIMIT], 'ids.*' => ['integer']])['ids'];

        DB::transaction(function () use ($ids) {
            foreach (array_values($ids) as $index => $id) {
                FeaturedProduct::whereKey($id)->update(['position' => $index + 1]);
            }
        });

        return response()->json(['ok' => true]);
    }

    public function destroy(FeaturedProduct $featured): RedirectResponse
    {
        DB::transaction(function () use ($featured) {
            $featured->delete();
            // boşluq qalmasın: 1, 2, 3…
            FeaturedProduct::orderBy('position')->get()->each(fn ($row, $i) => $row->update(['position' => $i + 1]));
        });

        return back()->with('success', 'Vitrindən çıxarıldı.');
    }
}
