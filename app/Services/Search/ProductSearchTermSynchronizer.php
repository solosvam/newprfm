<?php

namespace App\Services\Search;

use App\Models\Product\Product;
use App\Models\Product\ProductSearchTerm;

class ProductSearchTermSynchronizer
{
    private const GENERATED_SOURCES = ['canonical', 'brand', 'product'];

    public function sync(Product $product): int
    {
        $product->loadMissing('brand');

        $terms = [];

        $this->addTerm($terms, trim(($product->brand?->name ?? '').' '.$product->name), 'canonical', 1000);
        $this->addTerm($terms, $product->name, 'product', 800);
        $this->addTerm($terms, $product->brand?->name, 'brand', 500);

        $product->searchTerms()
            ->whereIn('source', self::GENERATED_SOURCES)
            ->delete();

        foreach ($terms as $term) {
            $product->searchTerms()->create([
                ...$term,
                'active' => (bool) $product->active,
            ]);
        }

        return count($terms);
    }

    private function addTerm(array &$terms, ?string $value, string $source, int $priority): void
    {
        $value = trim((string) $value);
        $normalized = ProductSearchNormalizer::normalize($value);

        if ($normalized === '') {
            return;
        }

        $candidate = [
            'term' => $value,
            'normalized_term' => $normalized,
            'phonetic_term' => ProductSearchNormalizer::phonetic($value),
            'token_signature' => ProductSearchNormalizer::tokenSignature($value),
            'source' => $source,
            'priority' => $priority,
        ];

        if (!isset($terms[$normalized]) || $terms[$normalized]['priority'] < $priority) {
            $terms[$normalized] = $candidate;
        }
    }
}
