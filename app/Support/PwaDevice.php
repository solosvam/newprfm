<?php

namespace App\Support;

/**
 * Ana ekrandan açılan saytın cihazı (pwa_events): User-Agent-dən platforma, sistem və brauzer;
 * iPhone modeli vermir — ekran ölçüsünə görə model qrupu.
 */
class PwaDevice
{
    /** iPhone ekranı (CSS piksel, kiçik x böyük) → eyni ölçülü modellər */
    public const IPHONES = [
        '320x568' => 'iPhone SE (1)',
        '375x667' => 'iPhone SE (2–3), 6–8',
        '414x736' => 'iPhone 6–8 Plus',
        '375x812' => 'iPhone X, XS, 11 Pro, 12–13 mini',
        '414x896' => 'iPhone XR, XS Max, 11, 11 Pro Max',
        '390x844' => 'iPhone 12–14, 16e',
        '428x926' => 'iPhone 12–13 Pro Max, 14 Plus',
        '393x852' => 'iPhone 14 Pro, 15, 15 Pro, 16',
        '430x932' => 'iPhone 14 Pro Max, 15 Plus, 15 Pro Max, 16 Plus',
        '402x874' => 'iPhone 16 Pro, 17, 17 Pro',
        '420x912' => 'iPhone Air',
        '440x956' => 'iPhone 16 Pro Max, 17 Pro Max',
    ];

    /** @return array{platform: string, os_version: ?string, browser: ?string, browser_version: ?string} */
    public static function parse(string $ua): array
    {
        if (preg_match('/iPhone|iPad|iPod/', $ua)) {
            $platform = 'ios';
            $os = preg_match('/OS (\d+)_(\d+)/', $ua, $m) ? $m[1].'.'.$m[2] : null;
            [$browser, $version] = match (true) {
                (bool) preg_match('/CriOS\/([\d.]+)/', $ua, $b) => ['Chrome', $b[1]],
                (bool) preg_match('/FxiOS\/([\d.]+)/', $ua, $b) => ['Firefox', $b[1]],
                (bool) preg_match('/EdgiOS\/([\d.]+)/', $ua, $b) => ['Edge', $b[1]],
                (bool) preg_match('/Version\/([\d.]+)/', $ua, $b) => ['Safari', $b[1]],
                default => ['Safari', null], // ana ekrandan açılanda versiya yazılmır
            };
            // Safari 26+ sistem versiyasını 18.6-da dondurub — həqiqi versiya Safari-nin versiyasıdır
            if ($browser === 'Safari' && $version && (int) $version >= 26) {
                $os = implode('.', array_slice(explode('.', $version), 0, 2));
            }
        } elseif (preg_match('/Android/', $ua)) {
            $platform = 'android';
            $os = preg_match('/Android (\d+(?:\.\d+)?)/', $ua, $m) ? $m[1] : null;
            [$browser, $version] = match (true) {
                (bool) preg_match('/SamsungBrowser\/([\d.]+)/', $ua, $b) => ['Samsung Internet', $b[1]],
                (bool) preg_match('/EdgA\/([\d.]+)/', $ua, $b) => ['Edge', $b[1]],
                (bool) preg_match('/OPR\/([\d.]+)/', $ua, $b) => ['Opera', $b[1]],
                (bool) preg_match('/YaBrowser\/([\d.]+)/', $ua, $b) => ['Yandex', $b[1]],
                (bool) preg_match('/Firefox\/([\d.]+)/', $ua, $b) => ['Firefox', $b[1]],
                (bool) preg_match('/Chrome\/([\d.]+)/', $ua, $b) => ['Chrome', $b[1]],
                default => [null, null],
            };
        } else {
            $platform = 'other';
            $os = null;
            [$browser, $version] = match (true) {
                (bool) preg_match('/Edg\/([\d.]+)/', $ua, $b) => ['Edge', $b[1]],
                (bool) preg_match('/Chrome\/([\d.]+)/', $ua, $b) => ['Chrome', $b[1]],
                (bool) preg_match('/Firefox\/([\d.]+)/', $ua, $b) => ['Firefox', $b[1]],
                default => [null, null],
            };
        }

        return [
            'platform' => $platform,
            'os_version' => $os,
            'browser' => $browser,
            'browser_version' => $version ? implode('.', array_slice(explode('.', $version), 0, 2)) : null,
        ];
    }

    /** Hesabatda cihazın adı: Android — model, iPhone — ekran ölçüsünə görə qrup */
    public static function label(string $platform, ?string $model, ?string $screen): string
    {
        if ($platform === 'ios') {
            if ($screen && isset(self::IPHONES[$screen])) {
                return self::IPHONES[$screen];
            }
            $width = (int) explode('x', (string) $screen)[0];

            return $width >= 600 ? 'iPad' : 'iPhone'.($screen ? " ($screen)" : '');
        }

        return $model ?: ($platform === 'android' ? 'Android (model bilinmir)' : 'Digər');
    }
}
