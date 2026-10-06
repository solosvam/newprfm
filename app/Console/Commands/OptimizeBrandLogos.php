<?php

namespace App\Console\Commands;

use App\Models\Product\Brand;
use App\Services\BrandLogoService;
use Illuminate\Console\Command;
use Throwable;

class OptimizeBrandLogos extends Command
{
    protected $signature = 'brands:optimize-logos';

    protected $description = 'Yüklənmiş brend loqolarını yenidən emal edir: kiçildir, kənar boşluğunu kəsir (SVG saxlanılır)';

    public function handle(BrandLogoService $logos): int
    {
        $done = 0;

        Brand::query()->whereNotNull('image')->where('image', '!=', '')->each(function (Brand $brand) use ($logos, &$done) {
            $original = public_path(BrandLogoService::DIR . '/' . $brand->image);
            if (!is_file($original)) {
                $this->warn("{$brand->name}: orijinal fayl yoxdur, keçildi");

                return;
            }

            $svgPath = public_path(BrandLogoService::LOGO_DIR . '/' . pathinfo($brand->image, PATHINFO_FILENAME) . '.svg');

            try {
                $image = $logos->store(file_get_contents($original), $brand, is_file($svgPath) ? file_get_contents($svgPath) : null);
            } catch (Throwable $e) {
                $this->warn("{$brand->name}: {$e->getMessage()}");

                return;
            }

            if ($image !== $brand->image) {
                $brand->image = $image;
                $brand->save();
            }
            $done++;
        });

        $this->info("Emal olunan loqo: {$done}");

        return self::SUCCESS;
    }
}
