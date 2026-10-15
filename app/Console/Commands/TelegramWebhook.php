<?php

namespace App\Console\Commands;

use App\Services\Telegram\TelegramApi;
use Illuminate\Console\Command;
use Throwable;

/**
 * Botun webhook-unu bu sayta yönəldir. Bir botun yalnız BİR webhook-u ola bilər: qoşmazdan əvvəl --info ilə
 * hazırkı ünvana baxın — başqa sistem (köhnə sayt) ondan istifadə edirsə, qoşulandan sonra cavabları o almayacaq.
 */
class TelegramWebhook extends Command
{
    protected $signature = 'telegram:webhook {--info : Hazırkı webhook-u göstər, dəyişmə} {--delete : Webhook-u sil}';

    protected $description = 'Telegram botunun webhook-unu bu sayta qoşur (anbar cavabları üçün)';

    public function handle(TelegramApi $api): int
    {
        try {
            $info = $api->call('getWebhookInfo');
            $this->line('Hazırkı webhook: '.(($info['url'] ?? '') ?: '— yoxdur')
                .(isset($info['last_error_message']) ? ' · son xəta: '.$info['last_error_message'] : '')
                .' · gözləyən yeniləmə: '.($info['pending_update_count'] ?? 0));
            if ($this->option('info')) {
                return self::SUCCESS;
            }
            if ($this->option('delete')) {
                $api->call('deleteWebhook');
                $this->info('Webhook silindi.');

                return self::SUCCESS;
            }

            $url = route('telegram.webhook');
            if (!str_starts_with($url, 'https://')) {
                $this->error('Webhook yalnız https ünvanı ola bilər: '.$url.' (APP_URL-i yoxlayın).');

                return self::FAILURE;
            }
            if (($info['url'] ?? '') !== '' && $info['url'] !== $url && !$this->confirm('Bot hazırda başqa ünvana bağlıdır ('.$info['url'].'). Əvəz edilsin?')) {
                return self::SUCCESS;
            }
            $api->call('setWebhook', ['url' => $url, 'secret_token' => $api->webhookSecret(), 'allowed_updates' => ['message', 'callback_query']]);
            $this->info('Webhook qoşuldu: '.$url);

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
