<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class SerperImageSearchService
{
    public function search(string $query): array
    {
        $apiKey = config('services.serper.api_key');

        if (!$apiKey) {
            throw new RuntimeException('SERPER_API_KEY .env faylında təyin edilməyib.');
        }

        $response = Http::timeout(30)
            ->acceptJson()
            ->withHeaders([
                'X-API-KEY' => $apiKey,
            ])
            ->post('https://google.serper.dev/images', [
                'q' => $query,
                'num' => 8,
                'autocorrect' => false,
            ]);

        if (!$response->successful()) {
            $message = data_get($response->json(), 'message', 'Şəkil axtarışı uğursuz oldu.');
            throw new RuntimeException($message);
        }

        return collect($response->json('images', []))
            ->map(function (array $image) {
                $original = $image['imageUrl'] ?? null;
                $thumbnail = $image['thumbnailUrl'] ?? $original;

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
