<?php

namespace App\Services\IdCard;

/**
 * Vəsiqədəki ad-soyad ↔ hesabdakı ad-soyad. Sərt bərabərlik deyil:
 * "ƏZİZOV" ~ "Azizov", "Gulnar" ~ "Gülnar". Hərflər latına endirilir, sonra oxşarlıq faizi.
 */
class NameMatcher
{
    public const THRESHOLD = 80;

    /** null — müqayisə üçün məlumat yoxdur */
    public function matches(?string $cardName, ?string $cardSurname, ?string $name, ?string $surname): ?bool
    {
        if (!$cardName && !$cardSurname) {
            return null;
        }
        $scores = [];
        if ($cardName && $name) {
            $scores[] = $this->similarity($cardName, $name);
        }
        if ($cardSurname && $surname) {
            $scores[] = $this->similarity($cardSurname, $surname);
        }

        return $scores ? min($scores) >= self::THRESHOLD : null;
    }

    public function similarity(string $a, string $b): float
    {
        $a = $this->plain($a);
        $b = $this->plain($b);
        if ($a === '' || $b === '') {
            return 0;
        }
        similar_text($a, $b, $percent);

        return $percent;
    }

    private function plain(string $value): string
    {
        $value = mb_strtolower(strtr($value, ['I' => 'ı', 'İ' => 'i']), 'UTF-8');
        $value = strtr($value, ['ə' => 'a', 'ı' => 'i', 'ö' => 'o', 'ü' => 'u', 'ğ' => 'g', 'ş' => 's', 'ç' => 'c', 'e' => 'e']);

        return preg_replace('/[^a-z]/', '', $value);
    }
}
