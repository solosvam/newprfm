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
        public readonly ?string $voiceFileId = null,     // səsli mesaj (kanalın fayl ID-si)
        public readonly int $voiceDuration = 0,         // saniyə
        public readonly ?string $action = null,          // mesajın altındakı seçim düyməsi basıldı (dəyəri, məs. "voice:onyx")
        public readonly ?string $actionId = null,        // kanala "qəbul olundu" cavabı üçün
        public readonly ?int $actionMessageId = null,    // düymələrin olduğu mesaj — seçim işarəsi yenilənir
    ) {
    }
}
