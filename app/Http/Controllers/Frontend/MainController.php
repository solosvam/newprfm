<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Banners;
use App\Models\Faq;
use App\Models\CreditTerms;
use App\Models\Product\Product;
use Illuminate\Http\Request;

class MainController extends Controller
{
    public function index(Request $request)
    {
        $banners = Banners::where('active', 1)->get();

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
            $categoryId = (int) $request->input('category');
            $query->whereHas('categories', fn ($categoryQuery) => $categoryQuery->where('categories.id', $categoryId));
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
            case 'newest':
                $query->orderByDesc('id');
                break;

            case 'oldest':
                $query->orderBy('id');
                break;

            case 'price_asc':
                $query->orderBy(
                    Product::selectRaw('MIN(product_variants.price)')
                        ->join('product_variants', 'product_variants.product_id', '=', 'products.id')
                        ->whereColumn('products.id', 'product_variants.product_id')
                        ->where('product_variants.active', 1)
                );
                break;

            case 'price_desc':
                $query->orderByDesc(
                    Product::selectRaw('MIN(product_variants.price)')
                        ->join('product_variants', 'product_variants.product_id', '=', 'products.id')
                        ->whereColumn('products.id', 'product_variants.product_id')
                        ->where('product_variants.active', 1)
                );
                break;

            default:
                $query->orderByDesc('id');
                break;
        }

        $products = $query
            ->paginate(12)
            ->withQueryString();

        $formattedBanners = [];

        foreach ($banners as $banner) {
            $key = $banner->location . $banner->device;
            $formattedBanners[$key] = $banner->url;
        }

        return view('frontend.main', [
            'banners' => $formattedBanners,
            'products' => $products,
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
