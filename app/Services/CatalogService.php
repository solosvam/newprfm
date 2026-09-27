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

        if ($request->filled('volume')) {
            $range = collect($this->volumeRanges())->firstWhere('value', $request->input('volume'));

            if ($range) {
                $sizeIds = Size::query()->get(['id', 'name_az', 'name_en', 'name_ru'])
                    ->filter(function ($size) use ($range) {
                        $name = $size->name_az ?: ($size->name_en ?: $size->name_ru);
                        if (!preg_match('/^\\s*(\\d+(?:[.,]\\d+)?)\\s*(?:ml|мл)\\b/iu', (string) $name, $matches)) {
                            return false;
                        }
                        $volume = (float) str_replace(',', '.', $matches[1]);
                        return $volume >= $range['min']
                            && ($range['max'] === null || $volume <= $range['max']);
                    })->pluck('id');

                $query->whereHas('variants', fn ($q) => $q->where('active', 1)->whereIn('size_id', $sizeIds));
            }
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

    public function volumeRanges(): array
    {
        return [
            ['value' => '0-30', 'min' => 0, 'max' => 30, 'label' => '0–30 ml'],
            ['value' => '31-50', 'min' => 31, 'max' => 50, 'label' => '31–50 ml'],
            ['value' => '51-75', 'min' => 51, 'max' => 75, 'label' => '51–75 ml'],
            ['value' => '76-100', 'min' => 76, 'max' => 100, 'label' => '76–100 ml'],
            ['value' => '101-200', 'min' => 101, 'max' => 200, 'label' => '101–200 ml'],
            ['value' => '200+', 'min' => 200.01, 'max' => null, 'label' => '200+ ml'],
        ];
    }

    public function catalogData(): array
    {
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

        return [
            'banners' => $banners,
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
            'volumeRanges' => $this->volumeRanges(),
            // Bestseller satış statistikası hələ qoşulmayıb; mövcud təsadüfi seçim saxlanılır.
            'recommendedProducts' => $sidebarQuery(),
            'bestSellers' => $sidebarQuery(),
        ];
    }
}
