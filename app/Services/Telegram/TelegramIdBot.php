<?php

namespace App\Services\Telegram;

/**
 * Botun şəxsi çatında "@idver" (və ya /idver): yazana öz adı və Telegram ID-si qayıdır —
 * əməkdaş onu adminə verir, admin "Telegram ID" xanasına yazır (kuryer bildirişləri).
 */
class TelegramIdBot
{
    public function __construct(private TelegramApi $api)
    {
    }

    /** Yeniləmə bu funksiyaya aiddirsə işləyir və true qaytarır */
    public function handle(array $update): bool
    {
        $message = $update['message'] ?? null;
        if (!$message || ($message['chat']['type'] ?? '') !== 'private'
            || !preg_match('~^[@/]idver(@\w+)?$~i', trim((string) ($message['text'] ?? '')))) {
            return false;
        }
        $from = $message['from'] ?? [];
        $name = trim(($from['first_name'] ?? '').' '.($from['last_name'] ?? ''));
        $this->api->call('sendMessage', ['chat_id' => $from['id'] ?? $message['chat']['id'], 'parse_mode' => 'HTML',
            'text' => 'Ad: '.e($name ?: '—')
                .(isset($from['username']) ? "\nUsername: @".e($from['username']) : '')
                ."\nTelegram ID: <code>".e((string) ($from['id'] ?? ''))."</code>"]);

        return true;
    }
}
