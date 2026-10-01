<?php

namespace App\Services\Search;

use Illuminate\Support\Str;

class ProductSearchNormalizer
{
    private const CYRILLIC_MAP = [
        'а' => 'a',  'б' => 'b',  'в' => 'v',
        'г' => 'g',  'д' => 'd',  'е' => 'e',
        'ё' => 'yo', 'ж' => 'j',  'з' => 'z',
        'и' => 'i',  'й' => 'y',  'к' => 'k',
        'л' => 'l',  'м' => 'm',  'н' => 'n',
        'о' => 'o',  'п' => 'p',  'р' => 'r',
        'с' => 's',  'т' => 't',  'у' => 'u',
        'ф' => 'f',  'х' => 'kh', 'ц' => 'ts',
        'ч' => 'ch', 'ш' => 'sh', 'щ' => 'sh',
        'ъ' => '',   'ы' => 'i',  'ь' => '',
        'э' => 'e',  'ю' => 'yu', 'я' => 'ya',

        // Azərbaycan kiril əlifbası
        'ә' => 'e',  'ғ' => 'g',  'ҝ' => 'g',
        'ө' => 'o',  'ү' => 'u',  'һ' => 'h',
        'ҹ' => 'c',
    ];

    public static function normalize(?string $value): string
    {
        $value = mb_strtolower(
            trim((string) $value),
            'UTF-8'
        );

        // Kiril -> latın
        $value = strtr($value, self::CYRILLIC_MAP);

        // Azərbaycan və digər latın hərfləri
        $value = strtr($value, [
            'ə' => 'e',
            'ı' => 'i',
            'ö' => 'o',
            'ü' => 'u',
            'ş' => 's',
            'ç' => 'c',
            'ğ' => 'g',
        ]);

        // Digər aksentli latın hərfləri: é → e, ñ → n, ß → ss.
        // iconv(ASCII//TRANSLIT) yox: macOS-un iconv-u "é"-ni "'e" edir → "Privés" → "priv es" olurdu.
        $value = strtolower(Str::ascii($value));

        $value = preg_replace(
            '/[^a-z0-9]+/',
            ' ',
            $value
        ) ?? '';

        return trim(
            preg_replace('/\s+/', ' ', $value) ?? ''
        );
    }
}
