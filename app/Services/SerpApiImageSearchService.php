<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class SerpApiImageSearchService
{
    public function search(string $query): array
    {
        $apiKey = config('services.serpapi.api_key');

        if (!$apiKey) {
            throw new RuntimeException('SERPAPI_KEY .env faylında təyin edilməyib.');
        }

        $response = Http::timeout(30)
            ->acceptJson()
            ->get('https://serpapi.com/search.json', [
                'engine' => 'google_images',
                'q' => $query,
                'api_key' => $apiKey,
                'ijn' => 0,
            ]);

        if (!$response->successful()) {
            $message = data_get($response->json(), 'error', 'Şəkil axtarışı uğursuz oldu.');
            throw new RuntimeException($message);
        }

        return collect($response->json('images_results', []))
            ->map(function (array $image) {
                $original = $image['original'] ?? null;
                $thumbnail = $image['thumbnail'] ?? $original;

                if (!$this->isHttpsUrl($original) || !$this->isHttpsUrl($thumbnail)) {
                    return null;
                }

                return [
                    'original_url' => $original,
                    'thumbnail_url' => $thumbnail,
                    'title' => strip_tags((string) ($image['title'] ?? 'Məhsul şəkli')),
                    'source' => strip_tags((string) ($image['source'] ?? '')), 
                ];
            })
            ->filter()
            ->take(8)
            ->values()
            ->all();
    }

    private function isHttpsUrl(?string $url): bool
    {
        return $url && filter_var($url, FILTER_VALIDATE_URL) && parse_url($url, PHP_URL_SCHEME) === 'https';
    }
}
