<?php

namespace App\Services\Search;

class ProductSearchNormalizer
{
    public static function normalize(?string $value): string
    {
        $value = mb_strtolower(trim((string) $value), 'UTF-8');

        $value = strtr($value, [
            'ə' => 'e',
            'ı' => 'i',
            'ö' => 'o',
            'ü' => 'u',
            'ş' => 's',
            'ç' => 'c',
            'ğ' => 'g',
        ]);

        $transliterated = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if ($transliterated !== false) {
            $value = strtolower($transliterated);
        }

        $value = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $value) ?? '';

        return trim(preg_replace('/\s+/u', ' ', $value) ?? '');
    }

    public static function tokenSignature(?string $value): string
    {
        $tokens = array_values(array_unique(array_filter(explode(' ', self::normalize($value)))));
        sort($tokens, SORT_STRING);

        return implode(' ', $tokens);
    }

    public static function phonetic(?string $value): string
    {
        $value = self::normalize($value);

        $value = preg_replace([
            '/eaux?/',
            '/ou/',
            '/oo/',
            '/ph/',
            '/sch/',
            '/sh/',
            '/ch/',
            '/c/',
            '/g(?=[eiy])/',
            '/qu/',
            '/q/',
            '/w/',
            '/er/',
            '/lu/',
            '/e\b/',
        ], [
            'o',
            'u',
            'u',
            'f',
            's',
            's',
            's',
            'k',
            'j',
            'k',
            'k',
            'v',
            'e',
            'lyu',
            '',
        ], $value) ?? '';

        return trim(preg_replace('/\s+/u', ' ', $value) ?? '');
    }
}
