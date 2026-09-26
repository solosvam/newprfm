<?php

namespace App\Services\Search;

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

        // Digər aksentli latın hərfləri
        $transliterated = iconv(
            'UTF-8',
            'ASCII//TRANSLIT//IGNORE',
            $value
        );

        if ($transliterated !== false) {
            $value = strtolower($transliterated);
        }

        $value = preg_replace(
            '/[^a-z0-9]+/',
            ' ',
            $value
        ) ?? '';

        return trim(
            preg_replace('/\s+/', ' ', $value) ?? ''
        );
    }

    public static function tokenSignature(?string $value): string
    {
        $tokens = array_values(array_unique(
            array_filter(
                explode(' ', self::normalize($value))
            )
        ));

        sort($tokens, SORT_STRING);

        return implode(' ', $tokens);
    }

    public static function phonetic(?string $value): string
    {
        $value = self::normalize($value);

        if ($value === '') {
            return '';
        }

        $tokens = explode(' ', $value);

        $tokens = array_map(
            fn (string $token) => self::phoneticToken($token),
            $tokens
        );

        return implode(' ', $tokens);
    }

    private static function phoneticToken(string $word): string
    {
        // Ətir adlarında rast gəlinən fonetik variantlar
        $aliases = [
            'rose' => 'roz',
            'roze' => 'roz',
            'ros' => 'roz',
            'rouz' => 'roz',
            'rouze' => 'roz',
            'roz' => 'roz',

            'aristocrat' => 'aristokrat',
            'aristokrat' => 'aristokrat',

            'ajmal' => 'ajmal',
            'adjmal' => 'ajmal',
            'adzhmal' => 'ajmal',
            'ejmal' => 'ajmal',
            'ecmel' => 'ajmal',
        ];

        if (isset($aliases[$word])) {
            return $aliases[$word];
        }

        // Ümumi fonetik çevrilmələr
        $word = preg_replace([
            '/eaux?/',
            '/ou/',
            '/oo/',
            '/ph/',
            '/ee/',
            '/sch/',
            '/sh/',
            '/ch/',
            '/qu/',
            '/q/',
            '/w/',
            '/c/',
            '/g(?=[eiy])/',
        ], [
            'o',
            'u',
            'u',
            'f',
            'i',
            's',
            's',
            's',
            'k',
            'k',
            'v',
            'k',
            'j',
        ], $word) ?? $word;

        return $word;
    }
}
