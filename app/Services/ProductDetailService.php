<?php

namespace App\Services;

use App\Models\Credit\CreditPeriod;
use App\Models\Credit\CreditTermItem;
use App\Models\Product\Gender;
use App\Models\Product\Product;
use Illuminate\Support\Collection;

class ProductDetailService
{
    public function findBySlug(string $slug): ?Product
    {
        return Product::with([
            'brand',
            'activeDiscount',
            'type',
            'images',
            'genders',
            'ingredients',
            'variants' => fn ($query) => $query->where('active', 1)->orderBy('price'),
            'variants.size',
            'reviews' => fn ($query) => $query->where('active', true)->with('customer')->latest(),
        ])->where('slug', $slug)->first();
    }

    /**
     * Bənzər məhsullar (məhsul səhifəsi və satışda olmayan ətrin əvəzləri). Ardıcıllıqla doldurulur:
     *  1) tərkibcə (notlar) oxşar — ən çox ortaq not;
     *  2) eyni növ, qiyməti ±30% — qiymətcə ən yaxın;
 * Hamısında cins nəzərə alınır (qadın / kişi + unisex).
     *  3) həmin brendin digər ətirləri.
     * Notlar yalnız az məhsulda yazıldığı üçün təkcə notlara görə seçəndə siyahı adətən boş qalırdı.
     */
    public function similarProducts(Product $product, int $limit = 5): Collection
    {
        // Cins: qadın ətrinə qadın + unisex, kişiyə kişi + unisex, unisex-ə hamısı.
        // "Qadın üçün" və "Qadın" bazada ayrı cinslərdir — eyni sayılır.
        $gender = fn ($name) => mb_strtolower(preg_replace('/\s+üçün$/u', '', trim((string) $name)));
        $names = $product->genders->map(fn ($g) => $gender($g->name_az))->unique();
        $allowed = $names->concat(['unisex']);
        $genderIds = $names->isEmpty() || $product->genders->contains(fn ($g) => $gender($g->name_az) === 'unisex') ? collect()
            : Gender::get(['id', 'name_az'])->filter(fn ($g) => $allowed->contains($gender($g->name_az)))->pluck('id');

        $base = fn () => Product::query()
            ->where('active', 1)->where('products.id', '!=', $product->id)
            ->whereHas('variants', fn ($q) => $q->where('active', 1))
            ->when($genderIds->isNotEmpty(), fn ($q) => $q->whereHas('genders', fn ($g) => $g->whereIn('genders.id', $genderIds)))
            ->with(['brand', 'activeDiscount', 'type', 'images', 'genders',
                'variants' => fn ($q) => $q->where('active', 1)->orderBy('price'), 'variants.size']);
        $list = collect();
        $add = function ($query) use (&$list, $limit) {
            if ($list->count() < $limit) {
                $list = $list->concat($query->whereNotIn('products.id', $list->pluck('id'))->limit($limit - $list->count())->get());
            }
        };

        $ingredientIds = $product->ingredients->pluck('id');
        if ($ingredientIds->isNotEmpty()) {
            $add($base()
                ->whereHas('ingredients', fn ($q) => $q->whereIn('ingredients.id', $ingredientIds))
                ->withCount(['ingredients as shared_ingredients_count' => fn ($q) => $q->whereIn('ingredients.id', $ingredientIds)])
                ->orderByDesc('shared_ingredients_count'));
        }

        $price = (float) $product->variants->where('active', 1)->min('price');
        if ($price > 0) {
            $minPrice = '(select min(pv.price) from product_variants pv where pv.product_id = products.id and pv.active = 1)';
            $add($base()
                ->when($product->type_id, fn ($q) => $q->where('type_id', $product->type_id))
                // rəqəmlər birbaşa (bizim hesabladığımız float): SQLite bağlanan dəyəri mətn kimi müqayisə edir
                ->whereRaw(sprintf('%s between %.2F and %.2F', $minPrice, $price * 0.7, $price * 1.3))
                ->orderByRaw(sprintf('abs(%s - %.2F)', $minPrice, $price)));
        }

        if ($product->brand_id) {
            $add($base()->where('brand_id', $product->brand_id)->latest('products.id'));
        }

        return $list->values();
    }

    /** Satışda olmayan ətir üçün əvəzlər — bənzər məhsullarla eyni qayda, daha çox */
    public function alternatives(Product $product, int $limit = 8): Collection
    {
        return $this->similarProducts($product, $limit);
    }

    public function ratings(Product $product): array
    {
        $reviews = $product->reviews;

        return [
            'ratingAverage' => round((float) $reviews->avg('rating'), 1),
            'ratingCounts' => collect(range(1, 5))->mapWithKeys(
                fn ($rating) => [$rating => $reviews->where('rating', $rating)->count()]
            ),
        ];
    }

    public function creditPeriods(): Collection
    {
        return CreditPeriod::where('active', 1)
            ->orderBy('sort_order')
            ->orderBy('month')
            ->get();
    }

    public function viewData(Product $product): array
    {
        return array_merge([
            'product' => $product,
            'similarProducts' => $this->similarProducts($product),
            'creditPeriods' => $this->creditPeriods(),
            'creditTermItems' => CreditTermItem::orderBy('sort_order')->orderBy('id')->get(),
            'creditProfileComplete' => auth()->check() && (bool) auth()->user()->creditProfile?->isComplete(),
            // "Qiymət enəndə xəbər ver": müştərinin bu məhsulda aktiv abunə olduğu ölçülər
            'priceAlertVariantIds' => auth()->check()
                ? \App\Models\Product\PriceAlert::where('customer_id', auth()->id())->whereNull('notified_at')
                    ->whereIn('product_variant_id', $product->variants->pluck('id'))->pluck('product_variant_id')->map(fn ($id) => (int) $id)->all()
                : [],
        ], $this->ratings($product));
    }
}
