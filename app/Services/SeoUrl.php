<?php

namespace App\Services;

class SeoUrl
{
    public static function uniqueDatabaseSlug(string $table, string $name, ?int $exceptId = null): string
    {
        $base = substr(\Illuminate\Support\Str::slug($name) ?: $table, 0, 235);
        $slug = $base;
        $suffix = 2;

        while (\Illuminate\Support\Facades\DB::table($table)->where('slug', $slug)->when($exceptId, fn ($query) => $query->where('id', '!=', $exceptId))->exists()) {
            $slug = $base . '-' . $suffix++;
        }

        return $slug;
    }

    public static function generateSlug($array)
    {
        return self::generate($array);
    }

    public static function generateImageName($array)
    {
        return self::generate($array);
    }

    private static function generate($array)
    {
        $slug = '';
        foreach ($array as $key => $value) {
            if ($key == 'id') {
                $slug .= $value;
            } else {
                $slug .= '-' . self::prepareSlug($value);
            }
        }
        return $slug;
    }

    public static function prepareSlug($slug)
    {
        $slug = self::unicodeletters($slug);
        $slug = preg_replace('/[^a-z0-9\-]/', '', str_replace(" ", "-", $slug)); // Boşluqları tire edir və digər simvolları silir
        return strtolower($slug);
    }

    public static function decodeSlug($slug)
    {
        return explode("-", $slug)[0];
    }

    public static function unicodeletters($data)
    {
        $data = strtolower($data);
        $data = str_replace(
            ['ü', 'ö', 'ğ', 'ç', 'ş', 'ı', 'ə', '?', ',', '.', '!', '&', '%', '#'],
            ['u', 'o', 'g', 'c', 's', 'i', 'e', '', '', '', '', 'and', '', ''],
            $data
        );

        return $data;
    }
}
