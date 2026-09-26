<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Banners;
use App\Models\Product\Brand;
use App\Models\Product\Category;
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
        $selectedCategory = null;
        $categories = Category::where('active', 1)->orderBy('id')->get();
        $brands = Brand::where('active', 1)->whereHas('products', fn ($q) => $q->where('active', 1))->orderBy('name')->get();
        $selectedBrand = $request->integer('brand');
        $search = trim((string) $request->input('q', ''));

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

        if ($request->filled('category')) {
            $category = $categories->firstWhere('id', $request->integer('category'));
            if ($category) {
                $selectedCategory = $category;
                $query->whereHas('categories', fn ($q) => $q->where('categories.id', $category->id));
            }
        }
        if ($selectedBrand && $brands->contains('id', $selectedBrand)) {
            $query->where('brand_id', $selectedBrand);
        }
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                    ->orWhereHas('brand', fn ($b) => $b->where('name', 'like', '%' . $search . '%'));
            });
        }
        if ($request->filled('gender')) {
            $gender = $request->string('gender')->lower()->value();

            $genderNames = [
                'women' => ['qadın', 'qadin', 'women', 'woman', 'female'],
                'men' => ['kişi', 'kisi', 'men', 'man', 'male'],
                'unisex' => ['unisex'],
            ];

            if (isset($genderNames[$gender])) {
                $names = $genderNames[$gender];

                $query->whereHas('genders', function ($genderQuery) use ($names) {
                    $genderQuery->where(function ($nameQuery) use ($names) {
                        foreach (['name_az', 'name_en', 'name_ru'] as $column) {
                            foreach ($names as $name) {
                                $nameQuery->orWhereRaw('LOWER(' . $column . ') LIKE ?', ['%' . $name . '%']);
                            }
                        }
                    });
                });
            }
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
            $formattedBanners[$key] = $banner->imageForLocale();
        }

        return view('frontend.new.home', [
            'banners' => $formattedBanners,
            'products' => $products,
            'selectedCategory' => $selectedCategory,
            'categories' => $categories,
            'brands' => $brands,
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
        ])->findOrFail(SeoUrl::decodeSlug($slug));

        $canonicalSlug = $product->slug;
        if ($slug !== $canonicalSlug) {
            return redirect()->route('product', $canonicalSlug, 301);
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
                ->limit(5)
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
