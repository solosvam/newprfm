<?php

namespace App\Services\Search;

use App\Models\Product\Product;
use App\Services\IdCard\GoogleVisionOcr;

/**
 * Şəkildən ətir axtarışı (WhatsApp: müştərinin göndərdiyi skrinşot və ya şüşə şəkli).
 * Google Vision bir sorğuda: WEB_DETECTION (oxşar şəkillərə görə "dior sauvage elixir") + TEXT_DETECTION (şəkildəki yazı).
 * Hər namizəd mətn lüğət süzgəcindən keçir: yalnız tanınan sözlər (brend, alias, məhsul adındakı söz) qalır —
 * "EAU DE PARFUM", "NATURAL SPRAY", qiymətlər, düymə yazıları atılır. Nəticə verən ilk namizəd seçilir.
 */
class ImageProductSearch
{
    /** Ətir şəkillərində/skrinşotlarda tez-tez olan, adı bildirməyən sözlər */
    private const GENERIC = [
        'eau', 'de', 'du', 'des', 'la', 'le', 'les', 'parfum', 'parfums', 'perfume', 'perfumes', 'fragrance', 'toilette', 'cologne',
        'edp', 'edt', 'edc', 'extrait', 'spray', 'vaporisateur', 'natural', 'naturel', 'ml', 'fl', 'oz', 'pour', 'homme', 'femme',
        'men', 'women', 'man', 'woman', 'for', 'him', 'her', 'the', 'and', 'by', 'new', 'tester', 'original', 'orijinal',
        'bottle', 'glass', 'product', 'brand', 'azn', 'manat', 'endirim', 'sebet', 'sebete', 'qiymet', 'kisi', 'qadin', 'unisex',
    ];

    private const MAX_CHECKS = 25;

    public function __construct(
        private GoogleVisionOcr $vision,
        private ProductSearchService $search,
    ) {}

    public function isConfigured(): bool
    {
        return $this->vision->isConfigured();
    }

    /**
     * @return array{query: ?string, size: ?string, found: bool, source: ?string, detected: array{label: ?string, entities: string[], text: string}}
     */
    public function find(string $imageBytes): array
    {
        $hints = $this->vision->productHints($imageBytes);

        // web təxmini şüşə şəkillərində daha dəqiqdir, OCR — skrinşotlarda
        $candidates = [];
        foreach ($hints['labels'] as $label) {
            $candidates[] = ['web', $label];
        }
        if ($hints['entities']) {
            $candidates[] = ['web', implode(' ', array_slice($hints['entities'], 0, 6))];
        }
        if (trim($hints['text']) !== '') {
            $candidates[] = ['text', $hints['text']];
        }

        $size = $this->size($hints['text']);
        $fallback = null;
        $checked = [];
        foreach ($candidates as [$source, $text]) {
            $query = $this->meaningful($text, $checked);
            if ($query === '') {
                continue;
            }
            $fallback ??= [$source, $query];
            if ($this->search->search($query, 1)['results']) {
                return $this->result($query, $size, true, $source, $hints);
            }
        }

        return $this->result($fallback[1] ?? null, $size, false, $fallback[0] ?? null, $hints);
    }

    /** "Dior Sauvage Eau de Parfum 100 ml 250 AZN" → "dior sauvage" (yalnız tanınan sözlər, sıra saxlanır) */
    private function meaningful(string $text, array &$checked): string
    {
        $tokens = array_unique(array_filter(
            explode(' ', ProductSearchNormalizer::normalize(mb_substr($text, 0, 2000))),
            fn (string $token) => strlen($token) >= 2 && !ctype_digit($token)
                && !in_array($token, self::GENERIC, true) && !in_array($token, ProductQueryParser::FILLER, true)
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

    /** Skrinşotdakı "100 ml" — ölçü önə çıxsın */
    private function size(string $text): ?string
    {
        return preg_match('/\b(\d{2,3})\s?ml\b/i', $text, $m) ? $m[1] : null;
    }

    private function result(?string $query, ?string $size, bool $found, ?string $source, array $hints): array
    {
        return [
            'query' => $query,
            'size' => $size,
            'found' => $found,
            'source' => $source,
            'detected' => [
                'label' => $hints['labels'][0] ?? null,
                'entities' => array_slice($hints['entities'], 0, 6),
                'text' => mb_substr(trim($hints['text']), 0, 300),
            ],
        ];
    }
}
