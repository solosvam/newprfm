<?php

namespace App\Services\Search;

use App\Models\Product\Product;
use App\Models\Product\ProductSearchTerm;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class ProductSearchService
{
    private const CACHE_KEY = 'product-search-terms:v1';

    public function search(string $query, int $limit = 6): array
    {
        $normalizedQuery = ProductSearchNormalizer::normalize($query);

        if (mb_strlen($normalizedQuery, 'UTF-8') < 2) {
            return ['results' => [], 'suggestion' => null];
        }

        $phoneticQuery = ProductSearchNormalizer::phonetic($query);
        $signature = ProductSearchNormalizer::tokenSignature($query);
        $scores = [];
        $suggestion = null;
        $suggestionScore = 0.0;

        foreach ($this->terms() as $term) {
            $score = max(
                $this->similarity($normalizedQuery, $term['normalized_term'], $signature, $term['token_signature']),
                $this->similarity($phoneticQuery, $term['phonetic_term'], $signature, $term['token_signature'])
            );

            if ($score < 55) {
                continue;
            }

            $score += min(((int) $term['priority']) / 1000, 1);
            $productId = (int) $term['product_id'];
            $scores[$productId] = max($scores[$productId] ?? 0, $score);

            if (
                $term['source'] === 'canonical'
                && $term['normalized_term'] !== $normalizedQuery
                && $score > $suggestionScore
            ) {
                $suggestion = $term['term'];
                $suggestionScore = $score;
            }
        }

        arsort($scores, SORT_NUMERIC);
        $bestScore = reset($scores);
        $relevanceFloor = max(68, $bestScore - 18);
        $scores = array_filter(
            $scores,
            fn (float $score) => $score >= $relevanceFloor
        );
        $scores = array_slice($scores, 0, max($limit * 4, 20), true);

        if ($scores === []) {
            return ['results' => [], 'suggestion' => null];
        }

        $products = Product::query()
            ->whereIn('id', array_keys($scores))
            ->where('active', 1)
            ->with([
                'brand',
                'type',
                'genders',
                'images',
                'variants' => fn ($variantQuery) => $variantQuery->where('active', 1)->orderBy('price'),
                'variants.size',
            ])
            ->get()
            ->sortByDesc(fn (Product $product) => $scores[$product->id] ?? 0)
            ->take($limit)
            ->values();

        return [
            'results' => $products->map(fn (Product $product) => $this->formatProduct($product))->all(),
            'suggestion' => $suggestionScore >= 72 ? $suggestion : null,
        ];
    }

    public function forgetCachedTerms(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    private function terms(): Collection
    {
        return Cache::remember(self::CACHE_KEY, now()->addMinutes(10), fn () => ProductSearchTerm::query()
            ->where('active', 1)
            ->get([
                'product_id',
                'term',
                'normalized_term',
                'phonetic_term',
                'token_signature',
                'source',
                'priority',
            ]));
    }

    private function similarity(string $needle, ?string $haystack, string $needleSignature, ?string $haystackSignature): float
    {
        $haystack = (string) $haystack;

        if ($needle === '' || $haystack === '') {
            return 0;
        }

        if ($needle === $haystack) {
            return 100;
        }

        if ($needleSignature !== '' && $needleSignature === $haystackSignature) {
            return 98;
        }

        if (str_contains($haystack, $needle)) {
            return 90;
        }

        $phraseScore = $this->distanceScore($needle, $haystack);
        $needleTokens = array_filter(explode(' ', $needle));
        $haystackTokens = array_filter(explode(' ', $haystack));

        if ($needleTokens === [] || $haystackTokens === []) {
            return $phraseScore;
        }

        $tokenScores = [];
        foreach ($needleTokens as $needleToken) {
            $tokenScores[] = max(array_map(
                fn (string $haystackToken) => $this->distanceScore($needleToken, $haystackToken),
                $haystackTokens
            ));
        }

        // İki və daha çox sözlü sorğuda hər söz eyni məhsulla əlaqəli olmalıdır.
        // Məsələn "aventus creed" yazanda təkcə "vertus" oxşarlığı kifayət etmir.
        if (count($needleTokens) > 1 && min($tokenScores) < 75) {
            return 0;
        }

        return max($phraseScore, array_sum($tokenScores) / count($tokenScores));
    }

    private function distanceScore(string $first, string $second): float
    {
        $length = max(strlen($first), strlen($second));

        if ($length === 0) {
            return 0;
        }

        return max(0, 100 - (levenshtein($first, $second) / $length * 100));
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
