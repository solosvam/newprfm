<?php

namespace App\Services\Messenger;

/** Müştəri botunun kanalı: gələn yeniləməni IncomingMessage-ə çevirir, cavabı göndərir */
interface MessengerChannel
{
    /** customer_messenger_accounts.channel */
    public function name(): string;

    /** Kanalın xam yeniləməsi → mesaj; bot üçün olmayan yeniləmədə null (qrup, redaktə, status və s.) */
    public function parse(array $update): ?IncomingMessage;

    public function send(string $recipientId, OutgoingMessage $message): void;
}
