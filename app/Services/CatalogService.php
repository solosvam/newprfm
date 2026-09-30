<?php

namespace App\Services;

use App\Models\Banners;
use App\Models\Product\Brand;
use App\Models\Product\Gender;
use App\Models\Product\Product;
use App\Models\Product\ProductVariant;
use App\Models\Product\Size;
use App\Models\Product\Type;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class CatalogService
{
    public function productQuery(): Builder
    {
        return Product::query()
            ->with([
                'brand',
                'type',
                'images',
                'genders',
                'variants' => fn ($query) => $query->where('active', 1)->orderBy('price'),
                'variants.size',
            ])
            ->where('active', 1);
    }

    public function applyFilters(Builder $query, Request $request): Builder
    {
        if ($request->filled('gender')) {
            $query->whereHas('genders', fn ($q) => $q->whereKey($request->integer('gender')));
        }

        if ($request->filled('min_price') || $request->filled('max_price')) {
            $min = max(0, (float) $request->input('min_price', 0));
            $max = (float) $request->input('max_price', 0);

            $query->whereHas('variants', function ($q) use ($min, $max) {
                $q->where('active', 1)->where('price', '>=', $min);

                if ($max > 0) {
                    $q->where('price', '<=', $max);
                }
            });
        }

        $selectedSizes = array_filter(
            (array) $request->input('size', []),
            fn ($id) => is_scalar($id) && ctype_digit((string) $id) && (int) $id > 0
        );

        if ($selectedSizes) {
            $query->whereHas('variants', fn ($q) => $q
                ->where('active', 1)
                ->whereIn('size_id', array_map('intval', $selectedSizes)));
        }

        if ($request->filled('type')) {
            $query->whereHas('type', fn ($q) => $q->whereKey($request->integer('type')));
        }

        return $query;
    }

    public function applySort(Builder $query, ?string $sort): Builder
    {
        switch ($sort) {
            case 'oldest':
                return $query->orderBy('products.id');

            case 'price_asc':
            case 'price_desc':
                $priceQuery = ProductVariant::query()
                    ->selectRaw('MIN(price)')
                    ->whereColumn('product_id', 'products.id')
                    ->where('active', 1);

                return $query->orderBy($priceQuery, $sort === 'price_asc' ? 'asc' : 'desc');

            default:
                return $query->orderByDesc('products.id');
        }
    }

    public function catalogData(): array
    {
        $priceBounds = ProductVariant::query()
            ->join('products', 'products.id', '=', 'product_variants.product_id')
            ->where('products.active', 1)
            ->where('product_variants.active', 1)
            ->selectRaw('MIN(product_variants.price) as min_price, MAX(product_variants.price) as max_price')
            ->first();

        $priceMin = (float) ($priceBounds?->min_price ?? 0);
        $priceMax = (float) ($priceBounds?->max_price ?? $priceMin);
        $banners = [];
        foreach (Banners::where('active', 1)->get() as $banner) {
            $banners[$banner->location . $banner->device] = [
                'image' => $banner->imageForLocale(),
                'url' => $banner->link_url,
            ];
        }

        $bannerDimensionKeys = [
            'topweb' => ['banner_web_top_width', 'banner_web_top_height'],
            'topmobile' => ['banner_mobile_top_width', 'banner_mobile_top_height'],
            'bottomweb' => ['banner_web_bottom_width', 'banner_web_bottom_height'],
            'bottommobile' => ['banner_mobile_bottom_width', 'banner_mobile_bottom_height'],
        ];
        $dimensionSettings = DB::table('settings')
            ->whereIn('key', array_merge(...array_values($bannerDimensionKeys)))
            ->pluck('value', 'key');
        $bannerDimensions = [];

        foreach ($bannerDimensionKeys as $location => [$widthKey, $heightKey]) {
            $bannerDimensions[$location] = [
                'width' => max(1, (int) ($dimensionSettings[$widthKey] ?? 1)),
                'height' => max(1, (int) ($dimensionSettings[$heightKey] ?? 1)),
            ];
        }

        return [
            'bannerDimensions' => $bannerDimensions,
            'banners' => $banners,
            'priceMin' => $priceMin,
            'priceMax' => $priceMax,
            'brands' => Brand::query()
                ->where('active', 1)
                ->withCount(['products as products_count' => fn ($query) => $query->where('active', 1)])
                ->having('products_count', '>', 0)
                ->orderByDesc('products_count')
                ->orderBy('name')
                ->limit(12)
                ->get(),
            'allBrands' => Brand::where('active', 1)->get(),
            'genders' => Gender::all(),
            'types' => Type::orderBy('id')->get(),
            'filterSizes' => Size::query()
                ->whereIn('id', ProductVariant::query()
                    ->select('size_id')
                    ->where('active', 1)
                    ->whereIn('product_id', Product::query()->select('id')->where('active', 1)))
                ->get()
                ->sortBy(fn ($size) => (float) ($size->name_az ?: $size->name_en))
                ->values(),
            ...$this->sidebarProducts(),
        ];
    }

    public const SIDEBAR_LIMIT = 5;

    /**
     * Sidebar: "Ən çox satılanlar" — son 90 gündə satılan say (ləğv olunan miqdar və ləğv olunmuş sifarişlər çıxılır);
     * satış azdırsa ən yeni məhsullarla tamamlanır.
     * "Tövsiyə olunanlar" — login olan müştəriyə sifariş etdiyi və bəyəndiyi ətirlərə tərkibcə oxşar ətirlər
     * (ProductRecommendationService); qonağa əvvəlcə ümumi seçim, sonra brauzer bəyəndiklərinə görə
     * /recommendations ilə əvəz olunur (main.js).
     * Ümumi ID-lər 1 saat keşlənir (thumbnail-lər sabit qalsın, hər request-də ağır sorğu olmasın).
     */
    private function sidebarProducts(): array
    {
        $reco = app(ProductRecommendationService::class);
        $ids = $this->sidebarIds();
        $customer = auth('web')->user();
        $recommended = $customer
            ? $this->recommendationsFor($reco->seedsForCustomer($customer))
            : $reco->load(array_slice(array_values(array_diff($ids['pool'], $ids['best'])), 0, self::SIDEBAR_LIMIT));

        return ['recommendedProducts' => $recommended, 'bestSellers' => $reco->load($ids['best'])];
    }

    /**
     * Mənbə məhsullara (sifariş + bəyənilən) tərkibcə oxşar ətirlər; azdırsa ümumi seçimlə tamamlanır.
     * Mənbə məhsullar heç vaxt göstərilmir. Mənbə yoxdursa — ümumi seçim.
     */
    public function recommendationsFor(array $seedIds): \Illuminate\Support\Collection
    {
        $reco = app(ProductRecommendationService::class);
        $list = $reco->similarTo($seedIds, self::SIDEBAR_LIMIT);
        if ($list->count() < self::SIDEBAR_LIMIT) {
            $ids = $this->sidebarIds();
            $taken = array_merge($seedIds, $list->pluck('id')->all());
            // Əvvəl ən çox satılanlarda olmayanlar, sonra lazım gələrsə onlar da
            $fill = array_values(array_diff(array_merge(array_diff($ids['pool'], $ids['best']), $ids['best']), $taken));
            $list = $list->concat($reco->load(array_slice($fill, 0, self::SIDEBAR_LIMIT - $list->count())));
        }

        return $list->values();
    }

    /** @return array{best: int[], pool: int[]} keşlənmiş ümumi seçim */
    private function sidebarIds(): array
    {
        return Cache::remember('catalog.sidebar.v2', 3600, function () {
            $available = fn () => ProductRecommendationService::available();
            $sold = DB::table('order_items')
                ->join('orders', 'orders.id', '=', 'order_items.order_id')
                ->where('orders.created_at', '>=', now()->subDays(90))
                ->whereNotIn('orders.order_status_id', DB::table('order_statuses')->where('code', 'cancelled')->select('id'))
                ->whereIn('order_items.product_id', $available()->select('id'))
                ->groupBy('order_items.product_id')
                ->havingRaw('SUM(order_items.quantity - COALESCE(order_items.cancelled_quantity, 0)) > 0')
                ->orderByRaw('SUM(order_items.quantity - COALESCE(order_items.cancelled_quantity, 0)) DESC')
                ->limit(self::SIDEBAR_LIMIT)
                ->pluck('order_items.product_id')->map(fn ($id) => (int) $id)->all();
            if (count($sold) < self::SIDEBAR_LIMIT) {
                $sold = array_merge($sold, $available()->whereNotIn('id', $sold)->orderByDesc('id')
                    ->limit(self::SIDEBAR_LIMIT - count($sold))->pluck('id')->all());
            }
            // Təsadüfi ehtiyat: qonağa göstərilir və oxşar ətir az olanda boşluğu doldurur
            $pool = $available()->whereNotIn('id', $sold)->inRandomOrder()->limit(60)->pluck('id')->all();

            return ['best' => $sold, 'pool' => $pool];
        });
    }
}
