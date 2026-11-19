<?php

namespace App\Services\Telegram;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Telegram Bot API (https://core.telegram.org/bots/api). Standart token: TELEGRAM_BOT_TOKEN (işçi botu — depo, kuryer, sifariş qrupları).
 * Müştəri botu (TELEGRAM_CUSTOMER_BOT_TOKEN) — TelegramApi::customer().
 */
class TelegramApi
{
    public function __construct(private ?string $token = null)
    {
    }

    public static function customer(): self
    {
        return new self((string) config('services.telegram.customer_bot_token'));
    }

    private function token(): string
    {
        return $this->token ?? (string) config('services.telegram.bot_token');
    }

    public function configured(): bool
    {
        return $this->token() !== '';
    }

    /**
     * @throws RuntimeException cavab "ok" deyilsə (səbəbi Telegram-ın öz mətni ilə)
     */
    public function call(string $method, array $params = []): mixed
    {
        $token = $this->token();
        if ($token === '') {
            throw new RuntimeException('Telegram bot tokeni yazılmayıb.');
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
        return substr(hash_hmac('sha256', 'parfumshop-telegram-webhook', $this->token()), 0, 48);
    }
}
