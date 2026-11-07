<?php

namespace App\Services\Courier;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Kuryerin götürmə şəkli (depodan malı götürəndə kuryer tətbiqinin kamerası ilə çəkilir).
 * Tətbiq şəkli artıq kiçildir; server yenə də GD ilə ən çox 1280 px və JPEG 72% edir (ölçü kiçik qalsın, EXIF silinsin).
 * Fayl public qovluqdan kənarda (storage/app/private/pick-photos); admin yalnız icazəli route ilə görür.
 * Bazada (order_item_allocations.pick_photo) yalnız fayl adı saxlanır.
 * Kuryer xərcinin qəbz şəkli də eyni qayda ilə — ayrıca qovluqda (EXPENSES, money_movements.photo).
 */
final class PickPhotoStorage
{
    private const MAX_SIDE = 1280;
    private const QUALITY = 72;

    public const PICKS = 'pick-photos';
    public const EXPENSES = 'expense-photos';

    public static function directory(string $folder = self::PICKS): string
    {
        return storage_path('app/private/'.$folder);
    }

    /** Şəkli kiçildib saxlayır və fayl adını qaytarır */
    public static function store(UploadedFile $file, string $folder = self::PICKS): string
    {
        $source = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));
        if (!$source) {
            throw ValidationException::withMessages(['photo' => 'Şəkil oxunmadı — yenidən çəkin.']);
        }
        [$width, $height] = [imagesx($source), imagesy($source)];
        $scale = min(1, self::MAX_SIDE / max($width, $height));
        [$w, $h] = [max(1, (int) round($width * $scale)), max(1, (int) round($height * $scale))];
        $image = imagecreatetruecolor($w, $h);
        imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255)); // şəffaf PNG — ağ fon
        imagecopyresampled($image, $source, 0, 0, 0, 0, $w, $h, $width, $height);

        $directory = self::directory($folder);
        if (!is_dir($directory)) {
            mkdir($directory, 0750, true);
        }
        $name = now()->format('Ymd').'-'.Str::lower(Str::random(20)).'.jpg';
        imagejpeg($image, $directory.'/'.$name, self::QUALITY);
        imagedestroy($image);
        imagedestroy($source);

        return $name;
    }

    /** Bazadakı ad — mütləq yol; ad şübhəlidirsə və ya fayl yoxdursa null */
    public static function path(?string $name, string $folder = self::PICKS): ?string
    {
        $name = basename((string) $name);
        if (!preg_match('/^\d{8}-[a-z0-9]{20}\.jpg$/', $name)) {
            return null;
        }
        $path = self::directory($folder).'/'.$name;

        return is_file($path) ? $path : null;
    }

    public static function delete(?string $name, string $folder = self::PICKS): void
    {
        if ($path = self::path($name, $folder)) {
            @unlink($path);
        }
    }
}
