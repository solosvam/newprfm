<?php

namespace App\Services\Telegram;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/** Telegram Bot API (https://core.telegram.org/bots/api). Token: TELEGRAM_BOT_TOKEN. */
class TelegramApi
{
    public function configured(): bool
    {
        return (string) config('services.telegram.bot_token') !== '';
    }

    /**
     * @throws RuntimeException cavab "ok" deyilsə (səbəbi Telegram-ın öz mətni ilə)
     */
    public function call(string $method, array $params = []): mixed
    {
        $token = (string) config('services.telegram.bot_token');
        if ($token === '') {
            throw new RuntimeException('Telegram bot tokeni yazılmayıb (TELEGRAM_BOT_TOKEN).');
        }
        $response = Http::timeout(15)->asJson()->post('https://api.telegram.org/bot'.$token.'/'.$method, $params);
        $data = $response->json();
        if (!is_array($data) || !($data['ok'] ?? false)) {
            throw new RuntimeException('Telegram: '.(is_array($data) ? ($data['description'] ?? 'xəta') : 'HTTP '.$response->status()));
        }

        return $data['result'] ?? null;
    }

    /** Webhook sorğusunun həqiqətən Telegram-dan gəldiyini yoxlamaq üçün gizli açar (tokendən törənir, ayrıca .env lazım deyil) */
    public function webhookSecret(): string
    {
        return substr(hash_hmac('sha256', 'parfumshop-telegram-webhook', (string) config('services.telegram.bot_token')), 0, 48);
    }
}
