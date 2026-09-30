<?php

namespace App\Console\Commands;

use App\Services\IdCard\GoogleVisionOcr;
use App\Services\IdCard\IdCardParser;
use Illuminate\Console\Command;

/** Test: php artisan idcard:ocr /yol/vesiqe.jpg [--raw] — Vision + parser nəticəsi (heç nə saxlanmır) */
class IdCardOcr extends Command
{
    protected $signature = 'idcard:ocr {path : Şəkil faylı} {--raw : Vision-un qaytardığı mətni də göstər}';

    protected $description = 'Şəxsiyyət vəsiqəsi şəklini Google Vision ilə oxuyub sahələri göstərir';

    public function handle(GoogleVisionOcr $ocr, IdCardParser $parser): int
    {
        $path = $this->argument('path');
        if (!is_file($path)) {
            $this->error("Fayl tapılmadı: $path");

            return self::FAILURE;
        }
        $text = $ocr->text(file_get_contents($path));
        if ($this->option('raw')) {
            $this->line('--- Vision mətni ---');
            $this->line($text);
            $this->line('--------------------');
        }
        $fields = $parser->parse($text);
        $this->table(['Sahə', 'Dəyər'], collect($fields)->map(fn ($v, $k) => [$k, $v ?? '—'])->values()->all());

        return self::SUCCESS;
    }
}
