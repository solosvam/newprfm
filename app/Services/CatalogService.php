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
        $sidebarQuery = fn () => Product::query()
            ->with([
                'brand',
                'type',
                'images',
                'variants' => fn ($query) => $query->where('active', 1)->orderBy('price'),
                'variants.size',
            ])
            ->where('active', 1)
            ->whereHas('variants', fn ($query) => $query->where('active', 1))
            ->inRandomOrder()
            ->limit(6)
            ->get();

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
            // Bestseller satış statistikası hələ qoşulmayıb; mövcud təsadüfi seçim saxlanılır.
            'recommendedProducts' => $sidebarQuery(),
            'bestSellers' => $sidebarQuery(),
        ];
    }
}
