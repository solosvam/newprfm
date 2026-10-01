<?php

namespace App\Services\Search;

use App\Models\Product\Product;

/**
 * Sərbəst mətndən (şəkildəki yazı, səsli mesajın mətni) ətir axtarış sorğusu:
 * yalnız tanınan sözlər qalır — brend/alias və ya hansısa aktiv məhsulun adındakı söz.
 * "Salam, Dior Sauvage-ın yüz millilik var?" → "dior sauvage"; artıq sözlər (lüğət) və ümumi sözlər atılır.
 */
class ProductTextExtractor
{
    /** Ətir şəkillərində/skrinşotlarda tez-tez olan, adı bildirməyən sözlər */
    private const GENERIC = [
        'eau', 'de', 'du', 'des', 'la', 'le', 'les', 'parfum', 'parfums', 'perfume', 'perfumes', 'fragrance', 'toilette', 'cologne',
        'edp', 'edt', 'edc', 'extrait', 'spray', 'vaporisateur', 'natural', 'naturel', 'ml', 'fl', 'oz', 'pour', 'homme', 'femme',
        'men', 'women', 'man', 'woman', 'for', 'him', 'her', 'the', 'and', 'by', 'new', 'tester', 'original', 'orijinal',
        'bottle', 'glass', 'product', 'brand', 'azn', 'manat', 'endirim', 'sebet', 'sebete', 'qiymet', 'kisi', 'qadin', 'unisex',
    ];

    private const MAX_CHECKS = 25;

    public function __construct(private ProductSearchService $search)
    {
    }

    /** "Dior Sauvage Eau de Parfum 100 ml 250 AZN" → "dior sauvage" (yalnız tanınan sözlər, sıra saxlanır) */
    public function extract(string $text, array &$checked = []): string
    {
        $tokens = array_unique(array_filter(
            explode(' ', ProductSearchNormalizer::normalize(mb_substr($text, 0, 2000))),
            fn (string $token) => strlen($token) >= 2 && !ctype_digit($token)
                && !in_array($token, self::GENERIC, true)
        ));

        $known = [];
        foreach ($tokens as $token) {
            $checked[$token] ??= count($checked) < self::MAX_CHECKS ? $this->isKnown($token) : false;
            if ($checked[$token]) {
                $known[] = $token;
            }
            if (count($known) >= 6) {
                break;
            }
        }

        return implode(' ', $known);
    }

    /** Söz brend/alias kimi tanınırsa və ya hansısa aktiv məhsulun adında varsa */
    private function isKnown(string $token): bool
    {
        $parsed = $this->search->interpret($token);
        if ($parsed['brands'] || ($parsed['words'] && $parsed['words'] !== [$token])) {
            return true;
        }
        if (strlen($token) < 3) {
            return false;
        }

        return Product::query()->where('active', 1)
            ->where('name', 'like', '%'.addcslashes($token, '%_\\').'%')
            ->exists();
    }

}
