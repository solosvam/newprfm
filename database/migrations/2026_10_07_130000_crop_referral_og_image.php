<?php

use App\Services\Referral\ReferralOgImage;
use App\Services\Referral\ReferralSettings;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Artıq yüklənmiş dəvət şəkli (og:image) yeni qaydaya salınır: 1200×630, ≤300 KB JPEG.
 * Ölçüsü artıq düzgündürsə və ya fayl yoxdursa — heç nə.
 */
return new class extends Migration {
    public function up(): void
    {
        $name = (string) DB::table('settings')->where('key', 'referral_og_image')->value('value');
        $path = public_path(ReferralSettings::OG_IMAGE_DIR.'/'.basename($name));
        if ($name === '' || !is_file($path)) {
            return;
        }
        [$width, $height] = getimagesize($path) ?: [0, 0];
        if ($width === ReferralOgImage::WIDTH && $height === ReferralOgImage::HEIGHT && filesize($path) <= ReferralOgImage::MAX_BYTES) {
            return;
        }

        $og = app(ReferralOgImage::class);
        DB::table('settings')->where('key', 'referral_og_image')->update(['value' => $og->store($path), 'updated_at' => now()]);
        $og->delete($name);
    }

    public function down(): void
    {
        // köhnə ölçülü şəkil saxlanmır
    }
};
