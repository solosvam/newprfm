<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class ProductImageController extends Controller
{
    private const ALLOWED_SIZES = [200, 400, 800];

    public function show(int $size, string $image): Response
    {
        abort_unless(in_array($size, self::ALLOWED_SIZES, true), 404);

        $image = basename($image);
        $sourcePath = public_path('frontend/uploads/products/' . $image);

        abort_unless(is_file($sourcePath), 404);

        $cacheDirectory = public_path('frontend/cache/products/' . $size);
        $cachePath = $cacheDirectory . '/' . $image;

        if (!is_file($cachePath) || filemtime($cachePath) < filemtime($sourcePath)) {
            if (!is_dir($cacheDirectory)) {
                mkdir($cacheDirectory, 0755, true);
            }

            $manager = ImageManager::usingDriver(Driver::class);
            $manager
                ->read($sourcePath)
                ->scaleDown(width: $size, height: $size)
                ->toWebp(quality: 80)
                ->save($cachePath);
        }

        return response(file_get_contents($cachePath), 200, [
            'Content-Type' => 'image/webp',
            'Content-Length' => (string) filesize($cachePath),
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
