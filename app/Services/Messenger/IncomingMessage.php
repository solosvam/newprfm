<?php

namespace App\Services\Messenger;

/**
 * Kanaldan asılı olmayan gələn mesaj (Telegram indi, WhatsApp Business sonra).
 * verifiedPhone — kanalın zəmanət verdiyi, göndərənin ÖZ nömrəsi (994XXXXXXXXX və ya tanınmayan rəqəmlər):
 *   Telegram — müştəri "Nömrəmi paylaş" ilə öz kontaktını göndərəndə; WhatsApp — hər mesajda (göndərənin nömrəsi).
 */
final class IncomingMessage
{
    public function __construct(
        public readonly string $channel,
        public readonly string $senderId,
        public readonly string $firstName = '',
        public readonly string $lastName = '',
        public readonly string $text = '',
        public readonly ?string $verifiedPhone = null,
        public readonly bool $foreignContact = false,  // başqasının kontaktını göndərib
        public readonly bool $voice = false,
    ) {
    }
}
