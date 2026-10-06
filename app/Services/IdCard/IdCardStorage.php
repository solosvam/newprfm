<?php

namespace App\Services\IdCard;

use RuntimeException;

/**
 * Şəxsiyyət vəsiqəsi şəkilləri — public qovluqdan kənarda (storage/app/private/id-cards).
 * Birbaşa URL ilə açılmır; yalnız icazə yoxlayan route-lar response()->file() ilə verir.
 * Bazada (customer_credit_profiles.id_card_front|back) yalnız fayl adı saxlanır.
 */
final class IdCardStorage
{
    /** Köhnə açıq qovluq — yalnız köçürmə migrasiyası üçün */
    public const LEGACY_PUBLIC_DIR = 'frontend/uploads/customers';

    public static function directory(): string
    {
        return storage_path('app/private/id-cards');
    }

    /** Qovluğu yaradır və mütləq yolunu (sonunda "/" olmadan) qaytarır */
    public static function ensureDirectory(): string
    {
        $directory = self::directory();
        if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
            throw new RuntimeException('Vəsiqə şəkilləri qovluğu yaradıla bilmədi.');
        }

        return $directory;
    }

    /** Bazadakı fayl adından mütləq yol; ad şübhəlidirsə və ya fayl yoxdursa null */
    public static function path(?string $filename): ?string
    {
        $name = self::name($filename);
        if ($name === null) {
            return null;
        }
        $path = self::directory().'/'.$name;

        return is_file($path) ? $path : null;
    }

    public static function delete(?string $filename): void
    {
        $name = self::name($filename);
        if ($name !== null) {
            @unlink(self::directory().'/'.$name);
        }
    }

    /** Brauzer keşi köhnə şəkli göstərməsin deyə URL-ə əlavə olunan versiya */
    public static function version(?string $filename): string
    {
        return substr(md5((string) $filename), 0, 8);
    }

    private static function name(?string $filename): ?string
    {
        $name = basename(str_replace('\\', '/', (string) $filename));

        return preg_match('/^[A-Za-z0-9_-]+\.(webp|jpe?g|png)$/i', $name) ? $name : null;
    }
}
