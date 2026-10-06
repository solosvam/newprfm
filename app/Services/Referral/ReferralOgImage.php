<?php

namespace App\Services\Referral;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

/**
 * Dəvət linkinin paylaşma şəkli (og:image): WhatsApp, Telegram, Facebook önizləməsi.
 *  - 1200×630-a mərkəzdən kəsilir (Facebook/Telegram-ın tövsiyə etdiyi 1.91:1);
 *  - JPEG — bütün platformalar oxuyur; keyfiyyət 300 KB-a sığana qədər azaldılır (WhatsApp böyük şəkli göstərmir).
 */
class ReferralOgImage
{
    public const WIDTH = 1200;
    public const HEIGHT = 630;
    public const MAX_BYTES = 300 * 1024;

    private const QUALITIES = [85, 78, 70, 62, 55];

    /** Yüklənən şəkli emal edib OG_IMAGE_DIR-ə yazır; fayl adını qaytarır */
    public function store(string $sourcePath): string
    {
        $directory = public_path(ReferralSettings::OG_IMAGE_DIR);
        File::ensureDirectoryExists($directory);

        $name = 'referral-'.Str::lower(Str::random(10)).'.jpg';
        $this->process($sourcePath, $directory.'/'.$name);

        return $name;
    }

    /** Kəsir və sıxır; $target üzərinə yazılır (mənbə ilə eyni ola bilər) */
    public function process(string $sourcePath, string $target): void
    {
        $image = ImageManager::usingDriver(Driver::class)->decode(file_get_contents($sourcePath))
            ->cover(self::WIDTH, self::HEIGHT);

        foreach (self::QUALITIES as $quality) {
            $image->save($target, quality: $quality);
            clearstatcache(true, $target);
            if (filesize($target) <= self::MAX_BYTES) {
                return;
            }
        }
    }

    /** Köhnə şəkli silir (əvəz olunanda / silinəndə) */
    public function delete(?string $name): void
    {
        $name = basename((string) $name);
        if ($name !== '' && preg_match('/^referral-[a-z0-9]+\.(jpe?g|png|webp)$/', $name)) {
            File::delete(public_path(ReferralSettings::OG_IMAGE_DIR.'/'.$name));
        }
    }
}
