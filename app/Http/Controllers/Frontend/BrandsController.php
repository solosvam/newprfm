<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Product\Brand;
use App\Services\CatalogService;
use Illuminate\Http\Request;

class BrandsController extends Controller
{
    /** Hərf zolağı: rəqəmlər + ingilis əlifbası A–Z */
    public const LETTERS = ['0–9', 'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M',
        'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z'];

    public function index()
    {
        $brands = Brand::where('active', 1)
            ->withCount(['products as products_count' => fn ($q) => $q->where('active', 1)])
            ->orderBy('name')
            ->get();

        // İlk hərf latına çevrilir (Ö → O, Ş → S, É → E), rəqəmlə başlayanlar "0–9", qalanı "#"
        $groups = $brands->groupBy(function ($brand) {
            $first = strtoupper(\Illuminate\Support\Str::ascii(mb_substr(trim($brand->name), 0, 1)));

            return match (true) {
                ctype_digit($first) => '0–9',
                (bool) preg_match('/^[A-Z]$/', $first) => $first,
                default => '#',
            };
        });
        $order = array_flip(self::LETTERS);
        $groups = $groups->sortBy(fn ($items, $letter) => [$order[$letter] ?? 1000, $letter]);

        return view('frontend.brands', [
            'groups' => $groups,
            'total' => $brands->count(),
            'letters' => collect(self::LETTERS)->merge($groups->keys())->unique()->values(),
        ]);
    }

    public function products(Request $request, string $slug, CatalogService $catalog)
    {
        $brand = Brand::where('slug', $slug)->where('active', 1)->first();

        if (!$brand && preg_match('/^(\\d+)(?:-|$)/', $slug, $matches)) {
            $legacyBrand = Brand::whereKey((int) $matches[1])->where('active', 1)->firstOrFail();
            return redirect()->route('brand.products', $legacyBrand->slug, 301);
        }

        abort_unless($brand, 404);

        $query = $catalog->productQuery()->where('brand_id', $brand->id);
        $query = $catalog->applyFilters($query, $request);
        $products = $catalog->applySort($query, $request->input('sort'))
            ->paginate(12)
            ->withQueryString();

        return view('frontend.brand', array_merge(
            $catalog->catalogData(),
            [
                'products'      => $products,
                'selectedBrand' => $brand,
            ]
        ));
    }
}
