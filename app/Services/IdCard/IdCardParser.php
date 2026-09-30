<?php

namespace App\Services\IdCard;

use App\Models\Customer\CustomerCreditProfile;

/**
 * OCR mətnindən (Google Vision fullTextAnnotation) Azərbaycan şəxsiyyət vəsiqəsinin sahələrini çıxarır.
 *
 * Yeni kart (AA/AB…): başlıqlar "SOYADI/SURNAME", "ATASININ ADI/PATRONYMIC", "VƏSİQƏNİN NÖMRƏSİ/CARD NO",
 *   "FƏRDİ İDENTİFİKASİYA NÖMRƏSİ/PERSONAL NO" — dəyər başlıqdan sonrakı sətirlərdədir.
 * Köhnə kart (AZE): "Seriya AZE № …" yuxarıda, FİN aşağıdakı maşın oxunan sətirdədir (MRZ).
 * Hər sahə tapılmaya bilər — tapılmayan null qayıdır, müştəri əl ilə yazır.
 */
class IdCardParser
{
    /** Başlıq sətirlərini dəyərlərdən ayırmaq üçün açar sözlər (normallaşdırılmış) */
    private const LABEL_WORDS = [
        'SOYADI', 'SURNAME', 'GIVEN NAME', 'PATRONYMIC', 'ATASININ', 'DOGULDUGU', 'DATE OF', 'SEX', 'CINSI',
        'NATIONALITY', 'VETENDASLIGI', 'CARD NO', 'PERSONAL NO', 'IDENTIFIKASIYA', 'ETIBARLILIQ', 'EXPIRY',
        'IMZASI', 'SIGNATURE', 'VERILME', 'ISSUE', 'RESPUBLIKASI', 'REPUBLIC', 'IDENTITY CARD', 'SEXSIYYET',
        'VESIQESI', 'VESIQENIN', 'NOMRESI', 'SERIYA', 'YER VE TARIX', 'PLACE OF BIRTH',
    ];

    /** @return array{type: ?string, series: ?string, number: ?string, fin: ?string, surname: ?string, name: ?string, father_name: ?string} */
    public function parse(string $text): array
    {
        $lines = array_values(array_filter(array_map('trim', preg_split('/\R/u', $text)), fn ($l) => $l !== ''));
        $keys = array_map(fn ($l) => $this->key($l), $lines);
        $all = implode("\n", $keys);

        $mrz = $this->parseMrz($lines);
        [$series, $number] = $this->seriesAndNumber($lines, $mrz);

        $type = null;
        if ($series) {
            $type = CustomerCreditProfile::needsBackSide($series) ? 'old' : 'new';
        } elseif (str_contains($all, 'IDENTITY CARD')) {
            $type = 'new';
        } elseif (str_contains($all, 'SERIYA') || $mrz) {
            $type = 'old';
        }

        return [
            'type' => $type,
            'series' => $series,
            'number' => $number,
            'fin' => $this->fin($lines, $keys, $mrz, $number),
            'surname' => $this->name($this->valueAfter($lines, $keys, fn ($k) => str_contains($k, 'SOYADI') || str_contains($k, 'SURNAME'))),
            'name' => $this->name($this->valueAfter($lines, $keys, fn ($k) => (str_contains($k, 'GIVEN NAME') || preg_match('/(^|[ \/])ADI( |\/|$)/', $k))
                && !str_contains($k, 'ATASININ') && !str_contains($k, 'SOYADI') && !str_contains($k, 'PATRONYMIC'))),
            'father_name' => $this->fatherName($this->valueAfter($lines, $keys, fn ($k) => str_contains($k, 'ATASININ') || str_contains($k, 'PATRONYMIC'))),
        ];
    }

    /** Böyük hərf, Azərbaycan hərfləri latına (Ə→E, İ→I …), yalnız A-Z 0-9 boşluq / < */
    public function key(string $value): string
    {
        $value = mb_strtoupper(strtr($value, ['i' => 'İ', 'ı' => 'I']), 'UTF-8');
        $value = strtr($value, ['Ə' => 'E', 'İ' => 'I', 'Ö' => 'O', 'Ü' => 'U', 'Ğ' => 'G', 'Ş' => 'S', 'Ç' => 'C', 'I' => 'I', '№' => ' ']);

        return trim(preg_replace('/\s+/', ' ', preg_replace('/[^A-Z0-9<\/ ]/', ' ', $value)));
    }

    private function isLabel(string $key): bool
    {
        foreach (self::LABEL_WORDS as $word) {
            if (str_contains($key, $word)) {
                return true;
            }
        }

        return false;
    }

    /** Başlıqdan sonrakı ilk "dəyər" sətri (başlıq olmayan, hərflərdən ibarət) — 3 sətir aralığında */
    private function valueAfter(array $lines, array $keys, callable $isTarget): ?string
    {
        foreach ($keys as $i => $key) {
            if (!$isTarget($key)) {
                continue;
            }
            for ($j = $i + 1; $j <= $i + 3 && $j < count($lines); $j++) {
                if ($this->isLabel($keys[$j])) {
                    continue;
                }
                if (preg_match('/^[\p{L} \-\']{2,}$/u', $lines[$j])) {
                    return $lines[$j];
                }
            }
        }

        return null;
    }

    /**
     * Köhnə kartın MRZ-i: "07487318<6AZE8409207F1909203 1JBFSAT3" — sənəd nömrəsi, AZE, doğum, cins, müddət, FİN.
     * @return array{number: string, fin: ?string}|null
     */
    private function parseMrz(array $lines): ?array
    {
        foreach ($lines as $line) {
            $compact = strtoupper(preg_replace('/\s+/', '', $line));
            if (preg_match('/^([0-9<]{8,9})<?\d?AZE\d{6}\d[MF<]\d{6}\d([0-9A-Z]{7})?/', $compact, $m)) {
                return ['number' => trim($m[1], '<'), 'fin' => $m[2] ?? null];
            }
        }

        return null;
    }

    /** @return array{0: ?string, 1: ?string} */
    private function seriesAndNumber(array $lines, ?array $mrz): array
    {
        // OCR hərfləri ayıra bilər ("A A 6358185") — seriya hərfləri arasında və seriya ilə nömrə arasında boşluq ola bilər,
        // amma nömrənin özündə yox (eyni sətirdəki FİN nömrəyə yapışmasın: "AA1234567 5XYZ12A")
        $series = implode('|', array_map(
            fn ($s) => implode(' ?', array_map(fn ($ch) => preg_quote($ch, '/'), str_split($this->key($s)))),
            CustomerCreditProfile::ID_CARD_SERIES
        ));
        $found = [];
        foreach ($lines as $line) {
            if (preg_match_all('/(?<![A-Z0-9])('.$series.') ?(\d{7,9})(?![0-9A-Z])/', $this->key($line), $m, PREG_SET_ORDER)) {
                foreach ($m as $match) {
                    $found[] = [str_replace(' ', '', $match[1]), $match[2]];
                }
            }
        }
        // "Seriya AZE №" və nömrə ayrı sətirlərdə ola bilər
        if (!$found && $mrz && preg_match('/SERIYA\s*AZE/', implode(' ', array_map(fn ($l) => $this->key($l), $lines)))) {
            $found[] = ['AZE', $mrz['number']];
        }
        if (!$found) {
            return [null, null];
        }
        // ən uzun seriya üstündür (AZE > AZ, AAA > AA)
        usort($found, fn ($a, $b) => strlen($b[0]) <=> strlen($a[0]));
        [$code, $number] = $found[0];
        // key() "MYİ"/"DYİ"-ni MYI/DYI edir — siyahıdakı əsl yazılışı qaytarırıq
        foreach (CustomerCreditProfile::ID_CARD_SERIES as $original) {
            if ($this->key($original) === $code) {
                return [$original, $number];
            }
        }

        return [$code, $number];
    }

    /** FİN: 7 simvol, hərf və rəqəm qarışığı */
    private function fin(array $lines, array $keys, ?array $mrz, ?string $number): ?string
    {
        $isFin = fn (string $token) => (bool) preg_match('/^(?=.*\d)(?=.*[A-Z])[0-9A-Z]{7}$/', $token)
            && ($number === null || !str_contains($number, $token));

        // 1) "FƏRDİ İDENTİFİKASİYA NÖMRƏSİ / PERSONAL NO" başlığından sonra
        foreach ($keys as $i => $key) {
            if (!str_contains($key, 'IDENTIFIKASIYA') && !str_contains($key, 'PERSONAL NO')) {
                continue;
            }
            for ($j = $i; $j <= $i + 4 && $j < count($keys); $j++) {
                foreach (explode(' ', str_replace('/', ' ', $keys[$j])) as $token) {
                    if ($isFin($token)) {
                        return $token;
                    }
                }
            }
        }
        // 2) köhnə kart — MRZ
        if ($mrz && $mrz['fin']) {
            return $mrz['fin'];
        }
        // 3) mətnin istənilən yerində tək uyğun söz
        $candidates = [];
        foreach ($keys as $key) {
            if (preg_match('/^[0-9<A-Z]{25,}$/', str_replace(' ', '', $key))) {
                continue; // MRZ sətri
            }
            foreach (explode(' ', str_replace('/', ' ', $key)) as $token) {
                if ($isFin($token) && !preg_match('/^(AZE|AA|AB)\d/', $token)) {
                    $candidates[$token] = true;
                }
            }
        }

        return count($candidates) === 1 ? array_key_first($candidates) : null;
    }

    private function name(?string $value): ?string
    {
        return $value === null ? null : $this->titleCase($value);
    }

    /** "AĞAHÜSEYN OĞLU" → "Ağahüseyn", "MAHMUD QIZI" → "Mahmud", "VALEHOVİÇ" → "Valeh" */
    private function fatherName(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = preg_replace('/\s+(OĞLU|OGLU|QIZI|QİZİ|QIZİ)$/iu', '', trim($value));
        $value = preg_replace('/(OVİÇ|OVIÇ|OVIC|EVİÇ|EVIÇ|EVIC|OVNA|EVNA)$/iu', '', $value);

        return $value === '' ? null : $this->titleCase($value);
    }

    /** Azərbaycan qaydası ilə: I→ı, İ→i; sözün birinci hərfi böyük */
    private function titleCase(string $value): string
    {
        $lower = mb_strtolower(strtr($value, ['I' => 'ı', 'İ' => 'i']), 'UTF-8');

        return preg_replace_callback('/(^|[\s\-])(\p{L})/u', function ($m) {
            $upper = strtr($m[2], ['i' => 'İ', 'ı' => 'I']);

            return $m[1].mb_strtoupper($upper, 'UTF-8');
        }, $lower);
    }
}
