<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;

/**
 * Müştərinin WhatsApp-da göndərdiyi lokasiya → koordinat.
 * Qəbul olunur:
 *  - WhatsApp lokasiya mesajı (Web / Desktop-da açanda): maps.google.com/maps?q=40.37,49.83&z=17
 *  - Google Maps-dən "Paylaş" linki: maps.app.goo.gl/…, goo.gl/maps/… (yönləndirmə izlənir)
 *  - adi Google Maps linki: …/@40.37,49.83,17z, …!3d40.37!4d49.83, ?query= / ?ll= / ?destination=, /search/40.37,+49.83
 *  - sadəcə koordinat: "40.3712, 49.8345"
 * Linkdə koordinat yoxdursa (məkanın adı ilə paylaşılıb), axtarış mətni qaytarılır — brauzer onu Google Geocoder ilə tapır.
 * Yalnız Google domenlərinə müraciət olunur (yönləndirmə; səhifənin özü yüklənmir).
 */
class GoogleMapsLink
{
    private const SHORT_HOSTS = ['maps.app.goo.gl', 'goo.gl', 'g.co'];
    private const MAX_REDIRECTS = 5;

    /** @return array{lat: float, lng: float}|array{query: string}|null */
    public function resolve(string $input): ?array
    {
        $input = trim($input);
        if ($input === '') {
            return null;
        }
        if ($point = self::coordinates($input)) {
            return $point;
        }
        $url = $this->expand($input);
        if ($url === null) {
            return null;
        }
        if ($point = self::coordinates($url)) {
            return $point;
        }
        $query = self::query($url);

        return $query !== null ? ['query' => $query] : null;
    }

    /** Mətndə / linkdə koordinat (Azərbaycan hüdudları təxminən — CustomerAddress::formRules ilə eyni) */
    public static function coordinates(string $text): ?array
    {
        $text = urldecode(urldecode($text));
        $patterns = [
            '/!3d(-?\d+(?:\.\d+)?)!4d(-?\d+(?:\.\d+)?)/',                                   // məkanın pin-i
            '/[?&](?:q|query|ll|sll|destination|daddr|center)=(?:loc:)?\s*(-?\d+(?:\.\d+)?)\s*,\s*\+?(-?\d+(?:\.\d+)?)/',
            '#/(?:search|place|dir)/(?:[^/]*?/)?(-?\d+\.\d+)\s*,\s*\+?(-?\d+\.\d+)#',
            '/@(-?\d+\.\d+),(-?\d+\.\d+)/',                                                 // xəritənin mərkəzi
            '/^\s*(-?\d{1,2}\.\d{3,})\s*[,;\s]\s*(-?\d{1,3}\.\d{3,})\s*$/',                 // sadəcə "40.37, 49.83"
        ];
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $m)) {
                $lat = (float) $m[1];
                $lng = (float) $m[2];
                if ($lat >= 38 && $lat <= 42 && $lng >= 44 && $lng <= 51) {
                    return ['lat' => round($lat, 7), 'lng' => round($lng, 7)];
                }
            }
        }

        return null;
    }

    /** Koordinatsız linkdə məkanın adı / ünvanı (?q=… və ya /place/…) */
    public static function query(string $url): ?string
    {
        $parts = parse_url($url);
        parse_str($parts['query'] ?? '', $params);
        $text = $params['q'] ?? $params['query'] ?? $params['destination'] ?? null;
        if (!$text && preg_match('#/maps/(?:place|search)/([^/@]+)#', $parts['path'] ?? '', $m)) {
            $text = urldecode(str_replace('+', ' ', $m[1]));
        }
        $text = trim((string) $text);

        return $text !== '' ? mb_substr($text, 0, 300) : null;
    }

    /** Linki yoxlayır, qısa linkin yönləndirməsini izləyir; Google xəritə linki deyilsə null */
    private function expand(string $url): ?string
    {
        for ($i = 0; $i <= self::MAX_REDIRECTS; $i++) {
            $host = strtolower((string) parse_url($url, PHP_URL_HOST));
            if (!self::isGoogle($host) || !in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true)) {
                return null;
            }
            if (!in_array($host, self::SHORT_HOSTS, true)) {
                return $url; // tam Google Maps linki
            }
            try {
                $response = Http::timeout(5)->withOptions(['allow_redirects' => false])
                    ->withHeaders(['User-Agent' => 'Mozilla/5.0'])->get($url);
            } catch (\Throwable) {
                return null;
            }
            $location = $response->header('Location');
            if (!$location) {
                return null;
            }
            $url = str_starts_with($location, '/') ? 'https://'.$host.$location : $location;
        }

        return null;
    }

    private static function isGoogle(string $host): bool
    {
        return in_array($host, self::SHORT_HOSTS, true)
            || (bool) preg_match('/^(?:www\.|maps\.)?google\.(?:com|az|ru|com\.tr)$/', $host);
    }
}
