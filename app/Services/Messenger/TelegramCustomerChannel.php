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
        $callback = $update['callback_query'] ?? null;
        if (is_array($callback)) {
            if (($callback['message']['chat']['type'] ?? '') !== 'private' || !isset($callback['from']['id'])) {
                return null;
            }

            return new IncomingMessage(
                channel: $this->name(),
                senderId: (string) $callback['from']['id'],
                firstName: trim((string) ($callback['from']['first_name'] ?? '')),
                lastName: trim((string) ($callback['from']['last_name'] ?? '')),
                action: (string) ($callback['data'] ?? ''),
                actionId: (string) ($callback['id'] ?? ''),
                actionMessageId: isset($callback['message']['message_id']) ? (int) $callback['message']['message_id'] : null,
            );
        }

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
        $params = ['chat_id' => $recipientId, 'text' => $message->text, 'reply_markup' => $this->markup($message)];
        $this->api->call('sendMessage', $params);
    }

    private function markup(OutgoingMessage $message): array
    {
        if ($message->choices) {
            return ['inline_keyboard' => array_map(fn ($row) => array_map(fn ($c) => ['text' => $c[0], 'callback_data' => $c[1]], $row), $message->choices)];
        }
        if ($message->buttons) {
            return [
                'keyboard' => array_map(fn ($b) => [['text' => $b['label']] + (($b['contact'] ?? false) ? ['request_contact' => true] : [])], $message->buttons),
                'resize_keyboard' => true,
                'one_time_keyboard' => true,
            ];
        }

        return ['remove_keyboard' => true];
    }

    public function acknowledge(IncomingMessage $message, ?string $notice = null): void
    {
        if ($message->actionId) {
            rescue(fn () => $this->api->call('answerCallbackQuery', ['callback_query_id' => $message->actionId] + ($notice ? ['text' => $notice] : [])), null, false);
        }
    }

    public function replace(string $recipientId, int $messageId, OutgoingMessage $message): void
    {
        rescue(fn () => $this->api->call('editMessageText', ['chat_id' => $recipientId, 'message_id' => $messageId, 'text' => $message->text]
            + ($message->choices ? ['reply_markup' => $this->markup($message)] : [])), null, false);
    }

    public function setMenu(string $recipientId, array $commands): void
    {
        $scope = ['type' => 'chat', 'chat_id' => $recipientId];
        $commands
            ? $this->api->call('setMyCommands', ['scope' => $scope, 'commands' => array_map(fn ($d, $c) => ['command' => $c, 'description' => $d], $commands, array_keys($commands))])
            : $this->api->call('deleteMyCommands', ['scope' => $scope]);
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
