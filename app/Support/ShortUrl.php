<?php

namespace App\Support;

/**
 * Müştəriyə və anbara göndərilən qısa linklər (SMS ödəniş linki, referal, anbar portalı).
 *
 * SHORT_URL (.env, məs. https://paf.az) təyin olunubsa link həmin domenlə yaranır; domen yalnız yönləndiricidir —
 * App\Http\Middleware\RedirectShortDomain eyni yolu əsas sayta (APP_URL) ötürür. Təyin olunmayıbsa adi link qaytarılır.
 */
class ShortUrl
{
    public static function base(): ?string
    {
        $base = rtrim((string) config('app.short_url'), '/');

        return $base !== '' ? $base : null;
    }

    public static function host(): ?string
    {
        return ($base = self::base()) ? (parse_url($base, PHP_URL_HOST) ?: null) : null;
    }

    /** Adlı route üçün qısa link */
    public static function route(string $name, mixed $parameters = []): string
    {
        $base = self::base();

        return $base ? $base.route($name, $parameters, false) : route($name, $parameters);
    }
}
