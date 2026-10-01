<?php

namespace App\Services\Search;

use App\Models\Product\Brand;
use App\Models\Product\Product;
use App\Models\Product\SearchAlias;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Məhsul axtarışı — lüğət əsasında (search_aliases), oxşarlıq hesablaması yoxdur.
 *
 * 1) Mətn normallaşdırılır: "Diyor SAVAJ" → "diyor savaj".
 * 2) Sözlər soldan sağa tanınır, ən uzun birləşmə birinci ("tom ford" → "tom"-dan əvvəl):
 *      lüğət (brend / model / artıq söz) → brendin tam adı → brend adının tək sözü ("dior" → Christian Dior).
 * 3) Brendlər filtr olur; model adları və tanınmayan sözlər məhsulun (və ya brendin) adında axtarılır — hər söz uyğun gəlməlidir.
 *
 * Sözlərin sırası rol oynamır: "creed aventus" = "aventus creed" = "krid aventos" (lüğətdə krid, aventos varsa).
 * Yazılmaqda olan son söz: lüğətdə tam yoxdursa, onunla BAŞLAYAN alias-lar da nəzərə alınır
 * ("krid" yazılıb, lüğətdə "kridd" → Creed də nəticəyə düşür); tam yazılanda ("kridd") yalnız konkret brend qalır.
 * Tanınmayan səhv yazılış nəticə vermir → "Nəticəsiz axtarışlar"a düşür → admin lüğətə əlavə edir.
 */
class ProductSearchService
{
    public const CACHE_KEY = 'product-search-vocabulary:v3';

    private const MAX_PHRASE = 4;

    /** Brend adlarında təkbaşına brend sayılmayan sözlər ("Parfums de Marly" → "parfums" Marly demək deyil) */
    /** Prefiks uyğunluğu üçün son sözün minimum uzunluğu */
    private const MIN_PREFIX = 2;

    private const BRAND_STOP_WORDS = ['parfums', 'parfum', 'perfumes', 'perfume', 'paris', 'london', 'fragrances', 'the', 'and'];

    /** @return array{results: array, suggestion: null, interpreted: ?string} */
    public function search(string $query, int $limit = 6): array
    {
        $parsed = $this->interpret($query);
        if ($parsed['brands'] === [] && $parsed['words'] === [] && $parsed['alternatives'] === []) {
            return ['results' => [], 'suggestion' => null, 'interpreted' => null];
        }

        $products = $this->find($parsed['brands'], $parsed['words'], $limit, $parsed['alternatives']);

        // "sauvage 100" — rəqəm adda yoxdursa rəqəmsiz yenidən (ölçü və s.); "212 VIP" kimi adlar isə birinci cəhddə tapılır
        if ($products->isEmpty()) {
            $withoutNumbers = array_values(array_filter($parsed['words'], fn (string $word) => !ctype_digit($word)));
            if ($withoutNumbers !== $parsed['words'] && ($withoutNumbers !== [] || $parsed['brands'] !== [] || $parsed['alternatives'] !== [])) {
                $products = $this->find($parsed['brands'], $withoutNumbers, $limit, $parsed['alternatives']);
            }
        }

        return [
            'results' => $products->map(fn (Product $product) => $this->formatProduct($product))->all(),
            'suggestion' => null,
            'interpreted' => $parsed['label'],
        ];
    }

    /**
     * Mətni brendlərə və axtarılacaq sözlərə ayırır.
     * "Diyor savaj orijinal" → brands [Dior id], words ["sauvage"], label "Christian Dior sauvage"
     *
     * alternatives — yazılmaqda olan son söz üçün variantlar (ən azı biri uyğun gəlməlidir):
     *   "krid" → [[brands: [Creed]], [words: ["krid"]]] — "kridd" alias-ı və adi LIKE
     *
     * @return array{brands: int[], words: string[], alternatives: array<array{brands: int[], words: string[]}>, label: ?string}
     */
    public function interpret(string $query): array
    {
        $tokens = array_values(array_filter(explode(' ', ProductSearchNormalizer::normalize(mb_substr($query, 0, 200))), 'strlen'));
        $vocabulary = $this->vocabulary();
        $brands = [];
        $words = [];
        $label = [];
        $alternatives = [];

        for ($i = 0, $count = count($tokens); $i < $count;) {
            [$entry, $length] = $this->match($tokens, $i, $vocabulary);
            if (!$entry && ($prefix = $this->prefixAlternatives($tokens, $i, $vocabulary))) {
                $alternatives = $prefix;
                $label[] = implode(' ', array_slice($tokens, $i));
                break; // qalan hissə (son söz) variantlara çevrildi
            }
            // "Artıq söz" kökü (lüğət, match_type = prefix): "göndərirsiniz" → "gonder…" — yalnız tanınmayan söz üçün,
            // ona görə brendin tam adı ("Parfums de Marly") və alias-lar əvvəlcə bütöv tanınır
            if (!$entry && $this->isStem($tokens[$i], $vocabulary['stems'])) {
                $i++;
                continue;
            }
            if (!$entry) {
                $words[] = $tokens[$i];
                $label[] = $tokens[$i];
                $i++;
                continue;
            }
            $i += $length;

            if ($entry['type'] === SearchAlias::IGNORE) {
                continue;
            }
            if ($entry['brand_id']) {
                $brands[$entry['brand_id']] = true;
            }
            if ($entry['type'] === SearchAlias::MODEL) {
                $original = ProductSearchNormalizer::normalize($entry['original']);
                array_push($words, ...array_filter(explode(' ', $original), 'strlen'));
                $label[] = $entry['original'];
            } else {
                $label[] = $vocabulary['names'][$entry['brand_id']] ?? '';
            }
        }

        return [
            'brands' => array_keys($brands),
            'words' => array_values(array_unique($words)),
            'alternatives' => $alternatives,
            'label' => $label ? trim(implode(' ', array_unique(array_filter($label)))) : null,
        ];
    }

    private function isStem(string $token, array $stems): bool
    {
        foreach ($stems as $stem) {
            if ($stem !== '' && str_starts_with($token, $stem)) {
                return true;
            }
        }

        return false;
    }

    public function forgetCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /** $i mövqeyindən başlayan ən uzun tanınan birləşmə: [entry, sözlərin sayı] */
    private function match(array $tokens, int $i, array $vocabulary): array
    {
        for ($length = min(self::MAX_PHRASE, count($tokens) - $i); $length >= 1; $length--) {
            $phrase = implode(' ', array_slice($tokens, $i, $length));
            $entry = $vocabulary['aliases'][$phrase] ?? $vocabulary['brands'][$phrase] ?? null;
            if ($entry) {
                return [$entry, $length];
            }
        }

        return [null, 1];
    }

    /**
     * Mətnin qalan hissəsi (son söz və ya "ermani ko" kimi birləşmə) lüğətdə tam yoxdursa —
     * onunla başlayan alias-lar variant olur, üstəlik mətnin özü adi LIKE ilə. Variant yoxdursa [].
     */
    private function prefixAlternatives(array $tokens, int $i, array $vocabulary): array
    {
        $tail = array_slice($tokens, $i);
        $phrase = implode(' ', $tail);
        if (count($tail) > self::MAX_PHRASE || strlen($phrase) < self::MIN_PREFIX) {
            return [];
        }

        $alternatives = [];
        foreach ($vocabulary['aliases'] as $key => $entry) {
            if ($entry['type'] === SearchAlias::IGNORE || $key === $phrase || !str_starts_with($key, $phrase)) {
                continue;
            }
            $alternative = [
                'brands' => $entry['brand_id'] ? [$entry['brand_id']] : [],
                'words' => $entry['type'] === SearchAlias::MODEL
                    ? array_values(array_filter(explode(' ', ProductSearchNormalizer::normalize($entry['original'])), 'strlen'))
                    : [],
            ];
            $alternatives[serialize($alternative)] = $alternative;
        }
        if (!$alternatives) {
            return [];
        }
        // "cree" → Creed adi LIKE ilə də tapılsın
        $alternatives[] = ['brands' => [], 'words' => $tail];

        return array_values($alternatives);
    }

    private function find(array $brandIds, array $words, int $limit, array $alternatives = []): Collection
    {
        $like = fn (string $word) => '%'.addcslashes($word, '%_\\').'%';
        $allWords = function (Builder $query, array $words) use ($like) {
            foreach ($words as $word) {
                $query->where(fn (Builder $match) => $match
                    ->where('products.name', 'like', $like($word))
                    ->orWhereHas('brand', fn (Builder $brand) => $brand->where('name', 'like', $like($word))));
            }
        };

        return Product::query()
            ->where('products.active', 1)
            ->when($brandIds, fn (Builder $query) => $query->whereIn('products.brand_id', $brandIds))
            ->where(fn (Builder $query) => $allWords($query, $words))
            // son söz: variantlardan ən azı biri
            ->when($alternatives, fn (Builder $query) => $query->where(function (Builder $any) use ($alternatives, $allWords) {
                foreach ($alternatives as $alternative) {
                    $any->orWhere(function (Builder $one) use ($alternative, $allWords) {
                        if ($alternative['brands']) {
                            $one->whereIn('products.brand_id', $alternative['brands']);
                        }
                        $allWords($one, $alternative['words']);
                    });
                }
            }))
            ->with([
                'brand', 'type', 'genders', 'images',
                'variants' => fn ($variants) => $variants->where('active', 1)->orderBy('price'),
                'variants.size',
            ])
            // qısa ad daha dəqiq uyğunluqdur: "Sauvage" → "Sauvage Elixir"-dən əvvəl
            ->orderByRaw('LENGTH(products.name)')
            ->orderByDesc('products.id')
            ->limit($limit)
            ->get();
    }

    /** Brend adları + lüğət — keşdə (brend və ya alias dəyişəndə təzələnir) */
    private function vocabulary(): array
    {
        return Cache::remember(self::CACHE_KEY, now()->addHour(), function () {
            $names = [];
            $brands = [];
            $wordOwners = [];
            foreach (Brand::query()->get(['id', 'name']) as $brand) {
                $key = ProductSearchNormalizer::normalize($brand->name);
                if ($key === '') {
                    continue;
                }
                $names[$brand->id] = $brand->name;
                $brands[$key] = ['type' => SearchAlias::BRAND, 'brand_id' => $brand->id, 'original' => null];
                foreach (array_unique(explode(' ', $key)) as $word) {
                    if (strlen($word) >= 3 && !in_array($word, self::BRAND_STOP_WORDS, true)) {
                        $wordOwners[$word][$brand->id] = true;
                    }
                }
            }
            // "dior" → Christian Dior: söz yalnız bir brendin adındadırsa və başqa brendin tam adı deyilsə
            foreach ($wordOwners as $word => $owners) {
                if (count($owners) === 1 && !isset($brands[$word])) {
                    $brands[$word] = ['type' => SearchAlias::BRAND, 'brand_id' => array_key_first($owners), 'original' => null];
                }
            }

            $aliases = [];
            $stems = [];
            foreach (SearchAlias::query()->get(['alias_normalized', 'type', 'match_type', 'brand_id', 'original']) as $alias) {
                if ($alias->isStem()) {
                    $stems[] = $alias->alias_normalized;
                }
                $aliases[$alias->alias_normalized] = [
                    'type' => $alias->type,
                    'brand_id' => $alias->brand_id,
                    'original' => $alias->original,
                ];
            }

            return ['aliases' => $aliases, 'brands' => $brands, 'names' => $names, 'stems' => $stems];
        });
    }

    private function formatProduct(Product $product): array
    {
        $locale = app()->getLocale();
        $variant = $product->variants->first();
        $gender = $product->genders->first();
        $image = $product->images->first();

        return [
            'id' => $product->id,
            'url' => route('product', $product->slug),
            'brand' => $product->brand?->name,
            'name' => $product->name,
            'type' => $product->type?->{'name_'.$locale} ?: $product->type?->name_az,
            'gender' => $gender?->{'name_'.$locale} ?: $gender?->name_az,
            'size' => $variant?->size?->{'name_'.$locale} ?: $variant?->size?->name_az,
            'price' => $variant ? number_format((float) $variant->price, 2, '.', '') : null,
            'image' => $image ? asset('frontend/uploads/products/'.$image->image) : null,
        ];
    }
}
