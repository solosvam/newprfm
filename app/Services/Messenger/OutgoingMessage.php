<?php

namespace App\Services\Messenger;

/**
 * Bota cavab: mətn + düymələr. Kanal düymələri öz formatına çevirir (Telegram — reply klaviatura;
 * WhatsApp — "reply button", ən çox 3). "Nömrəni paylaş" yalnız Telegram-da var — WhatsApp-da nömrə onsuz da məlumdur.
 */
final class OutgoingMessage
{
    /** @param list<array{label: string, contact?: bool}> $buttons */
    public function __construct(
        public readonly string $text,
        public readonly array $buttons = [],
        public readonly bool $keepButtons = false,   // düymələr basılandan sonra da qalsın (məs. səsli cavabın üsulu)
    ) {
    }

    public static function text(string $text): self
    {
        return new self($text);
    }

    /** @param list<string> $labels */
    public static function withButtons(string $text, array $labels, ?string $contactLabel = null, bool $keep = false): self
    {
        $buttons = $contactLabel ? [['label' => $contactLabel, 'contact' => true]] : [];
        foreach ($labels as $label) {
            $buttons[] = ['label' => $label];
        }

        return new self($text, $buttons, $keep);
    }
}
