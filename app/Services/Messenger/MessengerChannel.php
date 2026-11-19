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

    /** Səsli mesajın faylı (IncomingMessage::$voiceFileId) */
    public function downloadVoice(string $fileId): string;

    /** Səsli cavab (OGG/Opus); caption — səsin mətni */
    public function sendVoice(string $recipientId, string $audio, ?string $caption = null): void;

    /** "Səs yazılır…" statusu — cavab hazırlanarkən */
    public function recordingVoice(string $recipientId): void;
}
