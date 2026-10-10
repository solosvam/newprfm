<?php

namespace App\Services\PriceList;

use Illuminate\Support\Str;

/**
 * Price listdəki adı hissələrə ayırır: "VERSACE EROS EDP L 50ML TESTER" →
 * ad "EROS", növ EDP, cins L, həcm 50, tester. Bizim məhsul adları da eyni qayda ilə təmizlənir
 * ("Crystal Noir L Tester" → "CRYSTAL NOIR"), ona görə iki tərəf müqayisə oluna bilir.
 */
class PriceListNameParser
{
    /** Növün yazılışları → kod. Sıra vacibdir: uzun ifadə əvvəl ("EXTRAIT DE PARFUM" "PARFUM"dan əvvəl). */
    private const KINDS = [
        'EXTRAIT' => ['EXTRAIT DE PARFUM', 'EXTRAIT'],
        'EDP' => ['LEAU DE PARFUM', 'L EAU DE PARFUM', 'EAU DE PARFUM', 'ESSENCE DE PARFUM', 'EDP'],
        'EDT' => ['LEAU DE TOILETTE', 'L EAU DE TOILETTE', 'EAU DE TOILETTE', 'EDT'],
        'EDC' => ['EAU DE COLOGNE', 'EDC', 'COLOGNE'],
        'PARFUM' => ['PARFUM'],
    ];

    /** Ada aid olmayan qeydlər */
    private const NOISE = ['NEW', 'TESTER', 'TEST', 'SET'];

    /** Müqayisə üçün açar: böyük latın hərfləri və rəqəmlər, tək boşluqla */
    public static function key(?string $value): string
    {
        $value = html_entity_decode((string) $value, ENT_QUOTES | ENT_HTML5);
        $value = str_replace(['&', "'", '`', '’'], [' AND ', '', '', ''], $value);

        return trim(preg_replace('/[^A-Z0-9]+/', ' ', Str::upper(Str::ascii($value))));
    }

    /**
     * @return array{core: string, kind: ?string, gender: ?string, volume: ?float, tester: bool, set: bool}
     */
    public static function parse(string $name, ?string $brand = null): array
    {
        $text = ' '.self::key($name).' ';
        $brandKey = self::key($brand);
        if ($brandKey !== '' && str_starts_with(ltrim($text), $brandKey.' ')) {
            $text = ' '.substr(ltrim($text), strlen($brandKey));
        }

        $tester = (bool) preg_match('/ (TESTER|TEST) /', $text);
        // Dəst: "SET", "100ML+2X30ML", "3 PCS"
        $set = (bool) preg_match('/ SET | \d+ ?PCS? | \d+ ?ML \d+ ?X ?\d+| \d+ ?X ?\d+ ?ML /', $text) || substr_count($text, 'ML') > 1;

        // Həcm: "100ML", "100 ML", "7 5ML" (7.5) — dəstdə birincisi
        $volume = null;
        if (preg_match('/ (\d+(?: \d)?) ?ML /', $text, $m)) {
            $volume = (float) str_replace(' ', '.', $m[1]);
        }
        $text = preg_replace('/ \d+(?: \d)? ?ML(?= )/', ' ', $text);

        $kind = null;
        foreach (self::KINDS as $code => $phrases) {
            foreach ($phrases as $phrase) {
                if (str_contains($text, ' '.$phrase.' ')) {
                    $kind ??= $code;
                    $text = str_replace(' '.$phrase.' ', ' ', $text);
                }
            }
        }

        // Cins: ayrıca dayanan L / M / W / UNISEX (adın içindəki "POUR HOMME" toxunulmur)
        $gender = null;
        if (preg_match('/ (UNISEX|L|W|M) /', $text, $m)) {
            $gender = ['UNISEX' => 'U', 'L' => 'L', 'W' => 'L', 'M' => 'M'][$m[1]];
            $text = preg_replace('/ (UNISEX|L|W|M)(?= )/', ' ', $text);
        }

        foreach (self::NOISE as $word) {
            $text = str_replace(' '.$word.' ', ' ', $text);
        }

        return [
            'core' => trim(preg_replace('/\s+/', ' ', $text)),
            'kind' => $kind,
            'gender' => $gender,
            'volume' => $volume,
            'tester' => $tester,
            'set' => $set,
        ];
    }
}
