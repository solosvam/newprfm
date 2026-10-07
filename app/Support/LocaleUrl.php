<?php

namespace App\Support;

/**
 * Dil versiyalarının ünvanı: az — əsas ünvan, en/ru — ?lang=en / ?lang=ru.
 * Sayt dili sessiyada saxlayır; ?lang= Google-un (hreflang, sitemap) hər dili ayrıca görməsi üçündür.
 */
class LocaleUrl
{
    public const LOCALES = ['az', 'en', 'ru'];
    public const DEFAULT = 'az';

    public static function to(string $url, string $locale): string
    {
        $url = self::strip($url);
        if ($locale === self::DEFAULT || !in_array($locale, self::LOCALES, true)) {
            return $url;
        }
        // "https://site.az" → "https://site.az/?lang=en"
        if (parse_url($url, PHP_URL_PATH) === null) {
            $url .= '/';
        }

        return $url.(str_contains($url, '?') ? '&' : '?').'lang='.$locale;
    }

    /** Ünvandan lang parametrini çıxarır */
    public static function strip(string $url): string
    {
        $url = preg_replace('/([?&])lang=[a-z]{2}(&|$)/', '$1', $url);

        return rtrim($url, '?&');
    }
}
