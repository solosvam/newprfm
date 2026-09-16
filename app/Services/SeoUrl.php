<?php

namespace App\Services;

class SeoUrl
{
    public static function generateSlug($array)
    {
        return self::generate($array) . ".html";
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
