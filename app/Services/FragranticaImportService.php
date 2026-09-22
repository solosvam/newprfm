<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
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

        if ((!$name || !$brand) && preg_match('/^(.+?)\s+(.+?)\s+for\s+(women and men|women|men)$/i', $title, $matches)) {
            $name = $this->clean($matches[1]);
            $brand = $this->clean($matches[2]);
            $gender = strtolower($matches[3]);
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
            'image_url' => $this->safeImageUrl($this->metaContent($html, 'og:image')),
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
        return $url && filter_var($url, FILTER_VALIDATE_URL) && parse_url($url, PHP_URL_SCHEME) === 'https'
            ? $url
            : null;
    }
}
