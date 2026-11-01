<?php

namespace App\Services\IdCard;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Vəsiqədən oxunan addakı itmiş Azərbaycan hərflərinin bərpası: Google bəzən Ə, Ğ, Ş, Ç, Ö, Ü, İ hərflərini
 * E/A, G, S, C, O, U, I kimi oxuyur ("AGAHUSEYN"). Söz xüsusi hərfsiz gəlibsə:
 *  1) müştərinin özündəki yazılış (hesabdakı ad / soyad, profildəki ata adı) eyni sözdürsə — o götürülür;
 *  2) yoxsa bazadakı ən çox işlənən yazılış (müştərilərin adları, soyadları, ata adları; 12 saat keşdə).
 * Uyğun yazılış tapılmasa söz olduğu kimi qalır. Söz bölünür: "Məmməd Əli" iki söz kimi yoxlanır.
 */
class NameSpelling
{
    private const SPECIAL = '/[əğşçöüıƏĞŞÇÖÜİ]/u';

    /** @param  array<int, ?string>  $preferred  müştərinin öz yazılışları */
    public function restore(?string $value, array $preferred = []): ?string
    {
        if ($value === null || trim($value) === '') {
            return $value;
        }
        $own = [];
        foreach ($preferred as $spelling) {
            foreach (preg_split('/\s+/u', trim((string) $spelling)) ?: [] as $word) {
                if ($word !== '' && preg_match(self::SPECIAL, $word)) {
                    foreach ($this->keys($word) as $key) {
                        $own[$key] ??= $word;
                    }
                }
            }
        }

        return implode(' ', array_map(function (string $word) use ($own) {
            if (preg_match(self::SPECIAL, $word) && !str_starts_with($word, 'İ')) {
                return $word; // OCR hərfləri saxlayıb (baş "İ"-ni parser özü qoyur — sözün qalanı yoxlanır)
            }
            $key = $this->keys($word)[0];

            return $own[$key] ?? $this->dictionary()[$key] ?? $word;
        }, preg_split('/\s+/u', trim($value))));
    }

    /**
     * Müqayisə açarları: kiçik hərf, xüsusi hərflər latına. Ə həm "e", həm "a" kimi oxuna bilir — iki açar.
     * @return string[]
     */
    private function keys(string $word): array
    {
        $lower = mb_strtolower(strtr($word, ['I' => 'ı', 'İ' => 'i']), 'UTF-8');
        $base = strtr($lower, ['ı' => 'i', 'ö' => 'o', 'ü' => 'u', 'ğ' => 'g', 'ş' => 's', 'ç' => 'c']);
        $plain = fn (string $v) => preg_replace('/[^a-z]/', '', $v);

        return array_values(array_unique([$plain(str_replace('ə', 'e', $base)), $plain(str_replace('ə', 'a', $base))]));
    }

    /** @return array<string, string> açar => ən çox işlənən yazılış (yalnız xüsusi hərfli olanlar) */
    private function dictionary(): array
    {
        return Cache::remember('id-card-name-spellings', now()->addHours(12), fn () => rescue(function () {
            $counts = [];
            $add = function ($rows) use (&$counts) {
                foreach ($rows as $row) {
                    foreach (preg_split('/\s+/u', trim((string) $row->v)) ?: [] as $word) {
                        if (mb_strlen($word) >= 2 && preg_match(self::SPECIAL, $word)) {
                            $word = mb_convert_case(mb_strtolower(strtr($word, ['I' => 'ı', 'İ' => 'i']), 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
                            $word = str_starts_with($word, 'I') ? 'İ'.mb_substr($word, 1) : $word; // mb title "i" → "I" edir
                            $counts[$word] = ($counts[$word] ?? 0) + (int) $row->c;
                        }
                    }
                }
            };
            foreach (['name', 'surname'] as $column) {
                $add(DB::table('customers')->whereNotNull($column)->selectRaw("$column as v, count(*) as c")->groupBy($column)->get());
            }
            $add(DB::table('customer_credit_profiles')->whereNotNull('father_name')->selectRaw('father_name as v, count(*) as c')->groupBy('father_name')->get());

            arsort($counts);
            $map = [];
            foreach (array_keys($counts) as $word) {
                foreach ($this->keys($word) as $key) {
                    $map[$key] ??= $word; // ən çox işlənən birinci gəlir
                }
            }

            return $map;
        }, [], false));
    }
}
