<?php

namespace App\Services;

use App\Models\Product\Brand;
use enshrined\svgSanitize\Sanitizer;
use Illuminate\Support\Facades\File;
use RuntimeException;

/**
 * Brend loqolarının emalı (GD).
 *
 *  - frontend/uploads/brands/{ad}.webp        — orijinal (fon ağ, en çox 1200px; brend səhifəsinin og:image-i)
 *  - frontend/uploads/brand-logos/{ad}.webp   — sayt üçün raster: kənar boşluqları kəsilmiş, en çox 600×240
 *  - frontend/uploads/brand-logos/{ad}.svg    — vektor (varsa): təmizlənmiş SVG, saytda CSS mask ilə
 *                                               istənilən rəngə boyanır (light/dark tema)
 *
 * Şəffaf fonlar ağa çevrilir ki, kəsmə və CSS filtrləri (boz / dark temada ağ) düzgün işləsin.
 */
class BrandLogoService
{
    public const DIR = 'frontend/uploads/brands';
    public const LOGO_DIR = 'frontend/uploads/brand-logos';

    /** Fonun rəngindən bu qədər (0–255) fərqlənən piksel loqonun hissəsi sayılır */
    private const TRIM_TOLERANCE = 24;

    /**
     * Yeni loqonu (raster fayl məzmunu, istəyə görə SVG də) brend üçün saxlayır,
     * köhnə fayllarını silir və fayl adını qaytarır.
     * Brend modelində `image` sahəsini çağıran yeniləyib saxlamalıdır.
     */
    public function store(string $binary, Brand $brand, ?string $svg = null): string
    {
        $image = $this->decode($binary);
        $cleanSvg = $svg !== null ? $this->sanitizeSvg($svg) : null;

        $name = SeoUrl::generateImageName(['id' => $brand->id, 'title' => $brand->name]) . '.webp';
        $old = $brand->image;

        File::ensureDirectoryExists(public_path(self::LOGO_DIR));

        $original = $this->scaleDown($image, 1200, 1200);
        $this->saveWebp($original, public_path(self::DIR . '/' . $name), 85);

        $logo = $this->scaleDown($this->trim($original), 600, 240);
        $this->saveWebp($logo, public_path(self::LOGO_DIR . '/' . $name), 88);

        // SVG: yeni gəlibsə yaz, gəlməyibsə (Google-dan raster) köhnəsini sil ki, köhnə loqo görünməsin
        $svgPath = public_path(self::LOGO_DIR . '/' . self::svgName($name));
        $cleanSvg !== null ? File::put($svgPath, $cleanSvg) : File::delete($svgPath);

        if ($old && $old !== $name) {
            File::delete([
                public_path(self::DIR . '/' . $old),
                public_path(self::LOGO_DIR . '/' . $old),
                public_path(self::LOGO_DIR . '/' . self::svgName($old)),
            ]);
        }

        return $name;
    }

    /** Saytda göstəriləcək raster loqonun URL-i (versiya ilə), yoxdursa null */
    public static function url(?string $image): ?string
    {
        return $image ? self::versioned(self::LOGO_DIR . '/' . $image) : null;
    }

    /** Vektor (SVG) loqonun URL-i, yoxdursa null */
    public static function svgUrl(?string $image): ?string
    {
        return $image ? self::versioned(self::LOGO_DIR . '/' . self::svgName($image)) : null;
    }

    private static function svgName(string $image): string
    {
        return pathinfo($image, PATHINFO_FILENAME) . '.svg';
    }

    private static function versioned(string $relative): ?string
    {
        $path = public_path($relative);

        return is_file($path) ? asset($relative) . '?v=' . filemtime($path) : null;
    }

    /**
     * SVG-ni təhlükəsiz edir: skriptlər, event atributları, xarici istinadlar silinir.
     * Təmizlənə bilməyən və ya çox böyük fayl qəbul edilmir.
     */
    private function sanitizeSvg(string $svg): string
    {
        if (strlen($svg) > 2 * 1024 * 1024) {
            throw new RuntimeException('SVG faylı çox böyükdür.');
        }
        $sanitizer = new Sanitizer();
        $sanitizer->removeRemoteReferences(true);
        $sanitizer->minify(true);
        $clean = $sanitizer->sanitize($svg);

        if (!$clean || !str_contains($clean, '<svg')) {
            throw new RuntimeException('SVG faylı oxunmadı və ya təhlükəsiz deyil.');
        }

        return $clean;
    }

    // ---------------------------------------------------------------------

    /** Şəkli açır və şəffaf hissələri ağ fonla birləşdirir */
    private function decode(string $binary): \GdImage
    {
        if (!extension_loaded('gd') || !function_exists('imagewebp')) {
            throw new RuntimeException('Serverdə GD (webp dəstəyi ilə) quraşdırılmayıb.');
        }
        $source = @imagecreatefromstring($binary);
        if (!$source) {
            throw new RuntimeException('Fayl şəkil kimi oxunmadı (PNG, JPG və ya WEBP olmalıdır).');
        }

        $w = imagesx($source);
        $h = imagesy($source);
        $canvas = imagecreatetruecolor($w, $h);
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
        imagealphablending($canvas, true);
        imagecopy($canvas, $source, 0, 0, 0, 0, $w, $h);
        imagedestroy($source);

        return $canvas;
    }

    private function scaleDown(\GdImage $image, int $maxW, int $maxH): \GdImage
    {
        $w = imagesx($image);
        $h = imagesy($image);
        $ratio = min($maxW / $w, $maxH / $h, 1);
        if ($ratio >= 1) {
            return $image;
        }
        $scaled = imagescale($image, max(1, (int) round($w * $ratio)), max(1, (int) round($h * $ratio)), IMG_BICUBIC);

        return $scaled ?: $image;
    }

    /** Künc pikselinin rəngini fon sayıb, loqonun ətrafındakı boşluğu kəsir (kiçik haşiyə saxlayır) */
    private function trim(\GdImage $image): \GdImage
    {
        $w = imagesx($image);
        $h = imagesy($image);
        $bg = imagecolorat($image, 0, 0);
        [$br, $bgG, $bb] = [($bg >> 16) & 0xFF, ($bg >> 8) & 0xFF, $bg & 0xFF];

        $minX = $w; $minY = $h; $maxX = -1; $maxY = -1;
        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                $c = imagecolorat($image, $x, $y);
                $diff = max(abs((($c >> 16) & 0xFF) - $br), abs((($c >> 8) & 0xFF) - $bgG), abs(($c & 0xFF) - $bb));
                if ($diff > self::TRIM_TOLERANCE) {
                    if ($x < $minX) $minX = $x;
                    if ($x > $maxX) $maxX = $x;
                    if ($y < $minY) $minY = $y;
                    if ($y > $maxY) $maxY = $y;
                }
            }
        }
        if ($maxX < 0) {
            return $image; // boş şəkil — olduğu kimi
        }

        $pad = max(4, (int) round(max($maxX - $minX, $maxY - $minY) * 0.04));
        $x0 = max(0, $minX - $pad);
        $y0 = max(0, $minY - $pad);
        $x1 = min($w - 1, $maxX + $pad);
        $y1 = min($h - 1, $maxY + $pad);

        return imagecrop($image, ['x' => $x0, 'y' => $y0, 'width' => $x1 - $x0 + 1, 'height' => $y1 - $y0 + 1]) ?: $image;
    }

    private function saveWebp(\GdImage $image, string $path, int $quality): void
    {
        if (!imagewebp($image, $path, $quality)) {
            throw new RuntimeException('Şəkil saxlanılmadı: ' . $path);
        }
    }
}
