<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Banners;
use App\Models\Product\Brand;
use App\Models\Product\Category;
use App\Models\Product\Gender;
use App\Models\Product\Type;
use App\Models\CreditPeriod;
use App\Models\Faq;
use App\Models\CreditTerms;
use App\Models\Product\Product;
use App\Models\Product\ProductVariant;
use App\Services\SeoUrl;
use Illuminate\Http\Request;

class NewMainController extends Controller
{
    public function index(Request $request)
    {
        $banners = Banners::where('active', 1)->get();
        $categories = Category::where('active', 1)->orderBy('id')->get();
        $genders = Gender::all();
        $types = Type::orderBy('id')->get();
        $brands = Brand::query()
            ->where('active', 1)
            ->withCount([
                'products as products_count' => fn ($query) => $query->where('active', 1),
            ])
            ->having('products_count', '>', 0)
            ->orderByDesc('products_count')
            ->orderBy('name')
            ->limit(12)
            ->get();
        $allBrands = Brand::where('active',1)->get();

        $recommendedProducts = Product::with([
            'brand',
            'type',
            'images',
            'variants' => fn ($query) => $query
                ->where('active', 1)
                ->orderBy('price'),
            'variants.size',
        ])
            ->where('active', 1)
            ->whereHas('variants', fn ($query) => $query->where('active', 1))
            ->inRandomOrder()
            ->limit(6)
            ->get();

        $bestSellers = Product::with([
            'brand',
            'type',
            'images',
            'variants' => fn ($query) => $query
                ->where('active', 1)
                ->orderBy('price'),
            'variants.size',
        ])
            ->where('active', 1)
            ->whereHas('variants', fn ($query) => $query->where('active', 1))
            ->inRandomOrder()
            ->limit(6)
            ->get();

//        $bestSellers = Product::with([
//            'brand',
//            'type',
//            'images',
//            'variants' => fn ($query) => $query
//                ->where('active', 1)
//                ->orderBy('price'),
//            'variants.size',
//        ])
//            ->where('active', 1)
//            ->whereHas('variants', fn ($query) => $query->where('active', 1))
//            ->withSum('orderItems as sold_count', 'quantity')
//            ->orderByDesc('sold_count')
//            ->limit(6)
//            ->get();

        $query = Product::with([
            'brand',
            'type',
            'images',
            'genders',
            'variants' => function ($query) {
                $query
                    ->where('active', 1)
                    ->orderBy('price');
            },
            'variants.size',
        ])->where('active', 1);

        if ($request->filled('gender')) {
            $query->whereHas(
                'genders',
                fn ($q) => $q->whereKey($request->integer('gender'))
            );
        }


        if ($request->filled('min_price') || $request->filled('max_price')) {
            $min = max(0, (float) $request->input('min_price', 0));
            $max = (float) $request->input('max_price', 0);
            $query->whereHas('variants', function ($q) use ($min, $max) {
                $q->where('active', 1)->where('price', '>=', $min);
                if ($max > 0) $q->where('price', '<=', $max);
            });
        }

        if ($request->filled('type')) {
            $query->whereHas('type', fn ($q) => $q->where('id', (int) $request->input('type')));
        }

        switch ($request->get('sort')) {
            case 'oldest':
                $query->orderBy('products.id');
                break;

            case 'price_asc':
            case 'price_desc':
                $priceQuery = ProductVariant::query()
                    ->selectRaw('MIN(price)')
                    ->whereColumn('product_id', 'products.id')
                    ->where('active', 1);

                $query->orderBy(
                    $priceQuery,
                    $request->get('sort') === 'price_asc' ? 'asc' : 'desc'
                );
                break;

            case 'newest':
            default:
                $query->orderByDesc('products.id');
                break;
        }


        $products = $query
            ->paginate(12)
            ->withQueryString();

        $formattedBanners = [];

        foreach ($banners as $banner) {
            $key = $banner->location . $banner->device;
            $formattedBanners[$key] = ['image' => $banner->imageForLocale(), 'url' => $banner->link_url];
        }

        return view('frontend.new.home', [
            'banners' => $formattedBanners,
            'products' => $products,
            'categories' => $categories,
            'brands' => $brands,
            'types' => $types,
            'genders' => $genders,
            'allBrands' => $allBrands,
            'bestSellers' => $bestSellers,
            'recommendedProducts' => $recommendedProducts
        ]);
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
            return redirect()->route('newproduct', $legacyProduct->slug, 301);
        }

        abort_unless($product, 404);

        $canonicalSlug = $product->slug;
        if ($slug !== $canonicalSlug) {
            return redirect()->route('newproduct', $canonicalSlug, 301);
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

        $categories = Category::where('active', 1)->orderBy('id')->get();
        return view('frontend.new.product', compact(
            'product',
            'similarProducts',
            'ratingAverage',
            'ratingCounts',
            'creditPeriods',
            'categories'
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
