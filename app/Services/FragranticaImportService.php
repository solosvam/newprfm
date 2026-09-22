<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class FragranticaImportService
{
    public function import(string $url): array
    {
        $this->validateUrl($url);

        $response = Http::timeout(15)
            ->connectTimeout(5)
            ->withoutRedirecting()
            ->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (compatible; ParfumShopProductImporter/1.0)',
                'Accept-Language' => 'en-US,en;q=0.9',
            ])
            ->get($url);

        if (!$response->successful()) {
            throw new RuntimeException('Fragrantica səhifəsi hazırda oxuna bilmədi. Linki yoxlayıb yenidən cəhd edin.');
        }

        return $this->parse($response->body(), $url);
    }

    private function validateUrl(string $url): void
    {
        $parts = parse_url($url);
        $host = strtolower($parts['host'] ?? '');

        if (($parts['scheme'] ?? '') !== 'https' || !in_array($host, ['fragrantica.com', 'www.fragrantica.com'], true)) {
            throw new RuntimeException('Yalnız Fragrantica məhsul linki qəbul olunur.');
        }

        if (!str_starts_with($parts['path'] ?? '', '/perfume/')) {
            throw new RuntimeException('Bu Fragrantica məhsul linki deyil.');
        }
    }

    private function parse(string $html, string $sourceUrl): array
    {
        $text = $this->textFromHtml($html);
        $title = $this->metaContent($html, 'og:title') ?: $this->firstMatch('/<h1[^>]*>(.*?)<\/h1>/is', $html);
        $title = $this->clean($title);

        $name = null;
        $brand = null;
        $gender = null;

        $descriptionPattern = '/(.+?)\s+by\s+(.+?)\s+is\s+(?:an?|the)\s+.+?\s+fragrance\s+for\s+(women and men|women|men)\.\s+.+?\s+was launched in\s+(\d{4})\.\s*(?:The nose behind this fragrance is\s+(.+?)\.)?\s*(?:The fragrance features\s+(.+?)\.)?/is';

        $year = null;
        $perfumer = null;
        $notes = [];

        if (preg_match($descriptionPattern, $text, $matches)) {
            $name = $this->clean($matches[1]);
            $brand = $this->clean($matches[2]);
            $gender = strtolower($matches[3]);
            $year = isset($matches[4]) ? (int) $matches[4] : null;
            $perfumer = $this->clean($matches[5] ?? '');
            $notes = $this->splitNotes($matches[6] ?? '');
        }

        if (preg_match('/(?:perfume\s*-\s*)?a\s+fragrance\s+for\s+(women and men|women|men)/i', $title . ' ' . $text, $matches)) {
            $gender ??= strtolower($matches[1]);
        }

        if (!$notes) {
            $notes = $this->extractNotesFromLinks($html);
        }

        if (!$name || !$brand) {
            [$urlName, $urlBrand] = $this->productFromUrl($sourceUrl);
            $name ??= $urlName;
            $brand ??= $urlBrand;
        }

        if (!$name || !$brand) {
            throw new RuntimeException('Məhsul məlumatları səhifədən çıxarıla bilmədi.');
        }

        return [
            'source_url' => $sourceUrl,
            'name' => $name,
            'brand' => $brand,
            'gender' => $gender,
            'year' => $year,
            'perfumer' => $perfumer ?: null,
            'notes' => $notes,
            'accords' => $this->extractAccords($text),
            'image_url' => $this->extractProductImage($html) ?? $this->safeImageUrl($this->metaContent($html, 'og:image')),
        ];
    }

    private function textFromHtml(string $html): string
    {
        return trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    }

    private function metaContent(string $html, string $property): ?string
    {
        $pattern = '/<meta[^>]+(?:property|name)=["\']' . preg_quote($property, '/') . '["\'][^>]+content=["\']([^"\']+)["\'][^>]*>/i';
        $content = $this->firstMatch($pattern, $html);

        if (!$content) {
            $pattern = '/<meta[^>]+content=["\']([^"\']+)["\'][^>]+(?:property|name)=["\']' . preg_quote($property, '/') . '["\'][^>]*>/i';
            $content = $this->firstMatch($pattern, $html);
        }

        return $content ? $this->clean($content) : null;
    }

    private function extractAccords(string $text): array
    {
        if (!preg_match('/main accords\s+(.+?)\s+User Ratings/is', $text, $matches)) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn (string $accord) => $this->clean($accord),
            preg_split('/\s{2,}|(?<=\pL)\s+(?=\pL)/u', trim($matches[1])) ?: []
        )));
    }

    private function splitNotes(string $notes): array
    {
        $notes = preg_replace('/\s+(?:and|&|ilə)\s+/iu', ',', $notes);

        return array_values(array_filter(array_map(
            fn (string $note) => $this->clean($note),
            preg_split('/,\s*/', $notes) ?: []
        )));
    }

    private function extractNotesFromLinks(string $html): array
    {
        preg_match_all('/<a[^>]+href=["\'][^"\']*\/notes\/[^"\']+["\'][^>]*>(.*?)<\/a>/is', $html, $matches);

        return collect($matches[1] ?? [])
            ->map(fn (string $note) => $this->clean($note))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function productFromUrl(string $url): array
    {
        $segments = array_values(array_filter(explode('/', parse_url($url, PHP_URL_PATH) ?? '')));
        $perfumeIndex = array_search('perfume', $segments, true);

        if ($perfumeIndex === false || !isset($segments[$perfumeIndex + 1], $segments[$perfumeIndex + 2])) {
            return [null, null];
        }

        $brand = Str::of(urldecode($segments[$perfumeIndex + 1]))->replace('-', ' ')->squish()->title()->toString();
        $productSlug = preg_replace('/-\d+\.html$/i', '', urldecode($segments[$perfumeIndex + 2]));
        $name = Str::of($productSlug)->replace('-', ' ')->squish()->title()->toString();

        return [$name ?: null, $brand ?: null];
    }

    private function firstMatch(string $pattern, string $value): ?string
    {
        return preg_match($pattern, $value, $matches) ? $matches[1] : null;
    }

    private function clean(string $value): string
    {
        return trim(html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    private function safeImageUrl(?string $url): ?string
    {
        if ($url && str_starts_with($url, '//')) {
            $url = 'https:' . $url;
        }

        return $url && filter_var($url, FILTER_VALIDATE_URL) && parse_url($url, PHP_URL_SCHEME) === 'https'
            ? $url
            : null;
    }

    private function extractProductImage(string $html): ?string
    {
        preg_match_all('/<img\b[^>]*>/i', $html, $tags);

        foreach ($tags[0] ?? [] as $tag) {
            preg_match('/\balt=["\']([^"\']*)["\']/i', $tag, $alt);

            if (!str_contains(Str::lower($alt[1] ?? ''), 'perfume')) {
                continue;
            }

            preg_match('/\b(?:src|data-src)=["\']([^"\']+)["\']/i', $tag, $src);
            $url = $this->safeImageUrl(html_entity_decode($src[1] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8'));

            if ($url) {
                return $url;
            }
        }

        return null;
    }
}
