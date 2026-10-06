<?php

use App\Services\IdCard\IdCardStorage;
use Illuminate\Database\Migrations\Migration;

/**
 * Vəsiqə şəkilləri public/frontend/uploads/customers-dən storage/app/private/id-cards-a köçürülür
 * (əvvəl fayl adını bilən hər kəs şəkli aça bilirdi). Qovluqda yalnız vəsiqə şəkilləri saxlanılır — hamısı köçür.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->move(public_path(IdCardStorage::LEGACY_PUBLIC_DIR), IdCardStorage::ensureDirectory());
    }

    public function down(): void
    {
        $legacy = public_path(IdCardStorage::LEGACY_PUBLIC_DIR);
        if (!is_dir($legacy)) {
            mkdir($legacy, 0755, true);
        }
        $this->move(IdCardStorage::directory(), $legacy);
    }

    private function move(string $from, string $to): void
    {
        if (!is_dir($from)) {
            return;
        }

        foreach (new DirectoryIterator($from) as $file) {
            if (!$file->isFile() || str_starts_with($file->getFilename(), '.')) {
                continue;
            }
            $target = $to.'/'.$file->getFilename();
            if (!is_file($target) && !rename($file->getPathname(), $target)) {
                throw new RuntimeException('Vəsiqə şəkli köçürülmədi: '.$file->getFilename());
            }
        }
    }
};
