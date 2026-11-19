<?php

namespace App\Services\Messenger;

use App\Models\Customer\CustomerMessengerAccount;
use App\Services\Telegram\TelegramApi;

/** Müştəri botu — Telegram (@parfumshopai_bot, TELEGRAM_CUSTOMER_BOT_TOKEN). Yalnız şəxsi çat. */
class TelegramCustomerChannel implements MessengerChannel
{
    private TelegramApi $api;

    public function __construct(?TelegramApi $api = null)
    {
        $this->api = $api ?? TelegramApi::customer();
    }

    public function api(): TelegramApi
    {
        return $this->api;
    }

    public function name(): string
    {
        return CustomerMessengerAccount::TELEGRAM;
    }

    public function parse(array $update): ?IncomingMessage
    {
        $message = $update['message'] ?? null;
        if (!is_array($message) || ($message['chat']['type'] ?? '') !== 'private' || !isset($message['from']['id'])) {
            return null;
        }
        $from = $message['from'];
        if ($from['is_bot'] ?? false) {
            return null;
        }
        $contact = $message['contact'] ?? null;
        // Öz kontaktı: Telegram kontaktdakı user_id-ni göndərənin ID-si ilə eyni verir — nömrə Telegram-da təsdiqlənib
        $own = is_array($contact) && (string) ($contact['user_id'] ?? '') === (string) $from['id'];

        return new IncomingMessage(
            channel: $this->name(),
            senderId: (string) $from['id'],
            firstName: trim((string) ($from['first_name'] ?? '')),
            lastName: trim((string) ($from['last_name'] ?? '')),
            text: trim((string) ($message['text'] ?? '')),
            verifiedPhone: $own ? preg_replace('/\D+/', '', (string) ($contact['phone_number'] ?? '')) : null,
            foreignContact: is_array($contact) && !$own,
            voiceFileId: $message['voice']['file_id'] ?? null,
            voiceDuration: (int) ($message['voice']['duration'] ?? 0),
        );
    }

    public function send(string $recipientId, OutgoingMessage $message): void
    {
        $params = ['chat_id' => $recipientId, 'text' => $message->text];
        if ($message->buttons) {
            $params['reply_markup'] = [
                'keyboard' => array_map(fn ($b) => [['text' => $b['label']] + (($b['contact'] ?? false) ? ['request_contact' => true] : [])], $message->buttons),
                'resize_keyboard' => true,
                'one_time_keyboard' => true,
            ];
        } else {
            $params['reply_markup'] = ['remove_keyboard' => true];
        }
        $this->api->call('sendMessage', $params);
    }

    public function downloadVoice(string $fileId): string
    {
        return $this->api->downloadFile($fileId);
    }

    public function sendVoice(string $recipientId, string $audio, ?string $caption = null): void
    {
        $params = ['chat_id' => $recipientId];
        if ($caption !== null && $caption !== '') {
            $params['caption'] = mb_substr($caption, 0, 1000);
        }
        $this->api->upload('sendVoice', $params, 'voice', $audio, 'cavab.ogg');
    }

    public function recordingVoice(string $recipientId): void
    {
        rescue(fn () => $this->api->call('sendChatAction', ['chat_id' => $recipientId, 'action' => 'record_voice']), null, false);
    }
}
