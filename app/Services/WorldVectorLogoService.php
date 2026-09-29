<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Worldvectorlogo API — brend loqoları üçün vektor mənbə.
 * Axtarış və PNG çevirmə 1 kredit (pulsuz plan: ayda 1000); SVG CDN-dən kreditsiz gəlir.
 * SVG BrandLogoService-də təmizlənib saxlanılır, PNG-dən raster (webp) versiya düzəlir.
 */
class WorldVectorLogoService
{
    private const BASE = 'https://worldvectorlogo.com/api/v1';

    public function search(string $query, int $limit = 12): array
    {
        $response = $this->client()->get(self::BASE . '/logos/search', ['q' => $query, 'per_page' => $limit]);
        $this->ensureOk($response);

        return collect($response->json('data', []))
            ->filter(fn ($logo) => isset($logo['slug'], $logo['svg_url']) && $this->isValidSlug($logo['slug']))
            ->map(fn ($logo) => [
                'slug' => $logo['slug'],
                'name' => strip_tags((string) ($logo['name'] ?? $logo['slug'])),
                'preview_url' => $logo['svg_url'],
                'downloads' => (int) ($logo['downloads'] ?? 0),
            ])
            ->values()
            ->all();
    }

    /** Loqonun PNG faylı (xam baytlar) */
    public function png(string $slug): string
    {
        if (!$this->isValidSlug($slug)) {
            throw new RuntimeException('Yanlış loqo identifikatoru.');
        }
        $response = $this->client()->timeout(30)->get(self::BASE . '/logos/' . $slug . '/png');
        $this->ensureOk($response);

        if (!str_starts_with((string) $response->header('Content-Type'), 'image/')) {
            throw new RuntimeException('Worldvectorlogo PNG qaytarmadı.');
        }

        return $response->body();
    }

    /** SVG məzmunu — yalnız Worldvectorlogo CDN-dən (axtarış nəticəsindəki svg_url) */
    public function svg(string $url): string
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (parse_url($url, PHP_URL_SCHEME) !== 'https' || !in_array($host, ['cdn.worldvectorlogo.com', 'worldvectorlogo.com'], true)) {
            throw new RuntimeException('Yanlış SVG ünvanı.');
        }
        $response = Http::timeout(20)->connectTimeout(5)->withoutRedirecting()->get($url);
        if (!$response->successful() || strlen($response->body()) > 2 * 1024 * 1024) {
            throw new RuntimeException('SVG yüklənmədi.');
        }

        return $response->body();
    }

    private function client()
    {
        $key = config('services.worldvectorlogo.api_key');
        if (!$key) {
            throw new RuntimeException('WORLDVECTORLOGO_API_KEY .env faylında təyin edilməyib.');
        }

        return Http::timeout(15)->connectTimeout(5)->acceptJson()->withToken($key);
    }

    private function ensureOk($response): void
    {
        if ($response->status() === 429) {
            throw new RuntimeException('Worldvectorlogo limiti bitib (kredit və ya dəqiqəlik limit). Bir az sonra yenidən cəhd edin.');
        }
        if (!$response->successful()) {
            throw new RuntimeException(data_get($response->json(), 'message', 'Worldvectorlogo sorğusu alınmadı (HTTP ' . $response->status() . ').'));
        }
    }

    private function isValidSlug(string $slug): bool
    {
        return (bool) preg_match('/^[a-z0-9][a-z0-9-]{0,120}$/', $slug);
    }
}
