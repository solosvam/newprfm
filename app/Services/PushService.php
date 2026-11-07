<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Web push (OneSignal REST API) — konkret adamlara xarici ID ilə:
 *  - müştərilər "customer-{id}" (sayt, push.js);
 *  - əməkdaşlar "user-{id}" (kuryer tətbiqi, public/courier-app/app.js).
 * services.onesignal.rest_api_key yoxdursa, heç nə göndərilmir (false) və loga yazılır.
 */
class PushService
{
    private const URL = 'https://api.onesignal.com/notifications?c=push';

    /** OneSignal bir sorğuda ən çox 20 000 xarici ID qəbul edir */
    private const CHUNK = 2000;

    public function isConfigured(): bool
    {
        return (bool) config('services.onesignal.app_id') && (bool) config('services.onesignal.rest_api_key');
    }

    /** @param int[] $customerIds  Uğurla göndərilibsə true */
    public function toCustomers(array $customerIds, string $title, string $message, ?string $url = null): bool
    {
        return $this->send('customer-', $customerIds, $title, $message, $url);
    }

    /** Əməkdaşlara (users.id) — kuryer tətbiqində icazə veriblərsə. @param int[] $userIds */
    public function toUsers(array $userIds, string $title, string $message, ?string $url = null): bool
    {
        return $this->send('user-', $userIds, $title, $message, $url);
    }

    private function send(string $prefix, array $ids, string $title, string $message, ?string $url): bool
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if (!$ids) {
            return true;
        }
        if (!$this->isConfigured()) {
            Log::warning('Push not sent: ONESIGNAL_REST_API_KEY is not configured', ['recipients' => count($ids), 'prefix' => $prefix]);

            return false;
        }

        foreach (array_chunk($ids, self::CHUNK) as $chunk) {
            $response = Http::withHeaders(['Authorization' => 'Key '.config('services.onesignal.rest_api_key')])
                ->acceptJson()->timeout(15)
                ->post(self::URL, array_filter([
                    'app_id' => config('services.onesignal.app_id'),
                    'target_channel' => 'push',
                    'include_aliases' => ['external_id' => array_map(fn ($id) => $prefix.$id, $chunk)],
                    'headings' => ['en' => $title, 'az' => $title],
                    'contents' => ['en' => $message, 'az' => $message],
                    'url' => $url,
                ]));
            if (!$response->successful()) {
                Log::error('OneSignal push failed', ['status' => $response->status(), 'body' => mb_substr($response->body(), 0, 500)]);

                return false;
            }
        }

        return true;
    }
}
