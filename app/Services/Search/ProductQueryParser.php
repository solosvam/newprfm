<?php

namespace App\Services\Search;

/**
 * Müştəri mesajından axtarış sorğusu: "Salam, Dior Savaj 100 lük neçəyədi?" → "dior savaj" + ölçü 100.
 * WhatsApp paneli ("Ətri axtar") və şəkildən axtarış (OCR mətni) istifadə edir.
 */
class ProductQueryParser
{
    /** Müştəri mesajında ətir adından başqa sözlər — axtarışdan çıxarılır (normallaşdırılmış yazılış) */
    public const FILLER = [
        'salam', 'salamlar', 'sagolun', 'zehmet', 'olmasa', 'xahis', 'edirem', 'olar', 'olarmi',
        'qiymet', 'qiymeti', 'qiymetin', 'neceyedi', 'neceye', 'nece', 'nedir', 'ne', 'qeder',
        'var', 'varmi', 'sizde', 'etir', 'etiri', 'parfum', 'ml', 'luk', 'lik', 'lek', 'manat', 'azn',
        // "Mənə … Eau de Parfum 90 ml, məhz orijinal, zavod qablaşdırmasında lazımdır"
        'mene', 'bize', 'mehz', 'orijinal', 'original', 'zavod', 'qablasdirma', 'qablasdirmada', 'qablasdirmasinda',
        'lazimdir', 'lazim', 'eau', 'de', 'toilette', 'deyin', 'deyerdiniz', 'bilersiniz',
    ];

    /**
     * Şəkilçi ilə gələn sözlər: kökü bununla başlayan söz atılır.
     * "ətrinin", "ətri", "ətirdən" → etr/etir; "mlsi", "mllik" → ml; "qiymətini" → qiymet; "neçəyə" → nece.
     * Qısa və ad ola biləcək köklər (məs. "var" — John Varvatos) buraya düşmür.
     */
    public const FILLER_STEMS = [
        'etir', 'etr', 'ml', 'qiymet', 'nece', 'lazim', 'qablasdir', 'orijinal',
        // "100ml olanı Naxçıvana göndərirsiniz? Orijinaldır?" — olan/olanı, göndər-, çatdır-, şəhərlər (+a, +da, +dan)
        'olan', 'gonder', 'catdir', 'catdirilma', 'catdirma', 'kuryer',
        'naxcivan', 'baki', 'gence', 'sumqayit', 'lenkeran', 'mingecevir', 'sirvan', 'quba', 'qebele', 'zaqatala',
        'region', 'rayon', 'seher',
    ];

    /**
     * "Salam, Dior Savaj 100 lük neçəyədi?" → ['query' => 'dior savaj', 'size' => '100'].
     * Rəqəmlər axtarışa düşmür: saytın axtarışında hər söz uyğun gəlməlidir, "100" isə heç bir adda yoxdur.
     *
     * @return array{query: string, size: ?string}
     */
    public static function parse(string $text): array
    {
        $tokens = explode(' ', ProductSearchNormalizer::normalize(mb_substr($text, 0, 200)));
        $size = null;
        $words = [];
        foreach ($tokens as $token) {
            if ($token === '') {
                continue;
            }
            // "100", "100ml", "100mlsi", "50luk", "50lik"
            if (preg_match('/^(\d{1,3})(?:ml[a-z]*|luk|luq|lik|lek|liy[a-z]*|luy[a-z]*)?$/', $token, $m)) {
                if ($size === null && (int) $m[1] >= 5) {
                    $size = $m[1];
                }
                continue;
            }
            if (!in_array($token, self::FILLER, true) && !self::hasFillerStem($token)) {
                $words[] = $token;
            }
        }

        return ['query' => implode(' ', $words), 'size' => $size];
    }

    private static function hasFillerStem(string $token): bool
    {
        foreach (self::FILLER_STEMS as $stem) {
            if (str_starts_with($token, $stem)) {
                return true;
            }
        }

        return false;
    }
}
