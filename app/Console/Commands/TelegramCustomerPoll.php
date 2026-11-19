<?php

namespace App\Console\Commands;

use App\Services\Messenger\CustomerBot;
use App\Services\Messenger\TelegramCustomerChannel;
use Illuminate\Console\Command;
use Throwable;

/**
 * Müştəri botunu webhook-suz sınamaq (lokal — sayt xaricdən açılmır): getUpdates ilə mesajları oxuyub CustomerBot-a verir.
 * Webhook qoşulubsa Telegram getUpdates-i qaytarmır — əvvəl: php artisan telegram:webhook --customer --delete.
 */
class TelegramCustomerPoll extends Command
{
    protected $signature = 'telegram:customer-poll';

    protected $description = 'Müştəri botu: webhook-suz (lokal sınaq) — mesajları getUpdates ilə oxuyur, Ctrl+C ilə dayanır';

    public function handle(TelegramCustomerChannel $channel, CustomerBot $bot): int
    {
        $offset = 0;
        $this->info('Müştəri botu dinlənilir (Ctrl+C — dayandır)…');
        while (true) {
            try {
                $updates = $channel->api()->call('getUpdates', ['offset' => $offset, 'timeout' => 10, 'allowed_updates' => ['message', 'callback_query']]);
            } catch (Throwable $e) {
                $this->error($e->getMessage());

                return self::FAILURE;
            }
            foreach ($updates ?: [] as $update) {
                $offset = $update['update_id'] + 1;
                try {
                    $message = $channel->parse($update);
                    if ($message) {
                        $this->line(now()->format('H:i:s').' '.$message->senderId.': '.($message->text ?: ($message->verifiedPhone ? 'kontakt '.$message->verifiedPhone : '…')));
                        $bot->handle($message, $channel);
                    }
                } catch (Throwable $e) {
                    $this->error($e->getMessage());
                }
            }
        }
    }
}
