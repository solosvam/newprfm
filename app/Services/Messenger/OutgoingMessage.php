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
        /** @var list<list<array{0: string, 1: string}>> mesajın altındakı seçim düymələri (sıralar; [yazı, dəyər]) — basılanda çata mətn yazılmır */
        public readonly array $choices = [],
    ) {
    }

    public static function text(string $text): self
    {
        return new self($text);
    }

    /** @param list<string> $labels */
    /** @param list<list<array{0: string, 1: string}>> $rows */
    public static function choices(string $text, array $rows): self
    {
        return new self($text, [], $rows);
    }

    public static function withButtons(string $text, array $labels, ?string $contactLabel = null): self
    {
        $buttons = $contactLabel ? [['label' => $contactLabel, 'contact' => true]] : [];
        foreach ($labels as $label) {
            $buttons[] = ['label' => $label];
        }

        return new self($text, $buttons);
    }
}
