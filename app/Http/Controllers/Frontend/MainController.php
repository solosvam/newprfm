<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Banners;
use App\Models\Faq;
use App\Models\CreditTerms;
use App\Models\Product\Product;
use App\Models\Product\ProductVariant;
use Illuminate\Http\Request;

class MainController extends Controller
{
    public function index(Request $request)
    {
        $banners = Banners::where('active', 1)->get();
        $selectedCategory = null;

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

        return view('frontend.main', [
            'banners' => $formattedBanners,
            'products' => $products,
            'selectedCategory' => $selectedCategory,
        ]);
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
