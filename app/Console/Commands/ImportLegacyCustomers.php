<?php

namespace App\Console\Commands;

use App\Services\LegacyCustomerImporter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

class ImportLegacyCustomers extends Command
{
    protected $signature = 'parfumshop:import-customers
        {--dry-run : Bazaya yazmadan yoxla (standart rejim)}
        {--apply : Müştəriləri və ilkin bonus tarixçəsini bazaya yaz}
        {--batch=200 : Bir API sorğusunda 1-1000 müştəri}
        {--after-id=0 : Bu köhnə customer_id-dən sonra davam et}
        {--snapshot-max-id= : Davametmə zamanı əvvəlki snapshot həddi}';

    protected $description = 'Köhnə ParfumShop API-sindən müştəriləri təkrarsız import edir';

    public function handle(LegacyCustomerImporter $importer): int
    {
        if ($this->option('apply') && $this->option('dry-run')) {
            $this->error('--apply və --dry-run birlikdə istifadə edilmir.');
            return self::FAILURE;
        }
        $batch = filter_var($this->option('batch'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1000]]);
        $after = filter_var($this->option('after-id'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        $snapshot = $this->option('snapshot-max-id') === null ? null
            : filter_var($this->option('snapshot-max-id'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        if ($batch === false || $after === false || $snapshot === false || ($after > 0 && $snapshot === null) || ($snapshot !== null && $after > $snapshot)) {
            $this->error('Pagination parametrləri yanlışdır. --after-id ilə davam edəndə --snapshot-max-id də verilməlidir.');
            return self::FAILURE;
        }
        if (!Schema::hasColumn('customers', 'old_customer_id')) {
            $this->error('Əvvəl customers.old_customer_id migration-ını tətbiq edin.');
            return self::FAILURE;
        }
        $apply = (bool) $this->option('apply');
        $lock = Cache::lock('legacy-customer-import', 86400);
        if (!$lock->get()) {
            $this->error('Başqa müştəri importu işləyir.');
            return self::FAILURE;
        }

        $stream = null;
        $path = null;
        $counts = array_fill_keys(['created', 'would_create', 'already_imported', 'conflict', 'invalid', 'dates_updated', 'would_update_date'], 0);
        $bonus = 0;
        $finished = false;
        $error = null;
        $stopped = false;
        if (extension_loaded('pcntl')) {
            $this->trap([SIGINT, SIGTERM], function () use (&$stopped): void {
                $stopped = true;
            });
        }
        try {
            $directory = storage_path('app/private/imports');
            if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
                throw new RuntimeException('Import hesabatı üçün qovluq yaradıla bilmədi.');
            }
            $path = $directory.'/customers-'.now()->format('Ymd-His').'-'.bin2hex(random_bytes(4)).'.jsonl';
            $stream = fopen($path, 'x');
            if ($stream === false) throw new RuntimeException('Import hesabatı yaradıla bilmədi.');
            chmod($path, 0600);
            $write = function (array $entry) use ($stream): void {
                $line = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n";
                if (fwrite($stream, $line) !== strlen($line)) throw new RuntimeException('Hesabat faylına yazmaq alınmadı.');
            };
            $write(['event' => 'start', 'mode' => $apply ? 'apply' : 'dry-run', 'after_id' => $after, 'snapshot_max_id' => $snapshot]);
            $this->info($apply ? 'Real import başlayır.' : 'Sınaq rejimi: müştəri və bonus məlumatları bazaya yazılmır.');
            do {
                if ($stopped) break;
                $page = $importer->page($after, $batch, $snapshot);
                $snapshot = $page['pagination']['snapshot_max_id'];
                foreach ($page['customers'] as $row) {
                    if ($stopped) break 2;
                    try {
                        $result = $importer->import($row, $apply);
                    } catch (Throwable $e) {
                        $write(['event' => 'database_error', 'old_customer_id' => $row['customer_id']]);
                        throw new RuntimeException('Customer ID '.$row['customer_id'].' üçün bazaya yazmaq alınmadı. Həmin müştəri üzrə transaction geri qaytarıldı. Baza strukturunu və unique konfliktlərini yoxlayın.');
                    }
                    $counts[$result['status']]++;
                    $bonus += $result['bonus_cents'] ?? 0;
                    $write(['event' => 'customer'] + $result);
                }
                $after = $page['pagination']['last_customer_id'];
                $write(['event' => 'page_complete', 'after_id' => $after, 'snapshot_max_id' => $snapshot]);
                $this->line('Son köhnə ID: '.$after.' / '.$snapshot.'; baxılan: '.array_sum($counts));
            } while ($page['pagination']['has_more']);
            $finished = !$stopped;
            if ($stopped) $this->warn('Import dayandırıldı. Kilid açılır; əvvəl köçürülənlər saxlanılır.');
        } catch (Throwable $e) {
            $error = $e instanceof RuntimeException ? $e->getMessage() : 'Import alınmadı. Baza və fayl sistemini yoxlayın.';
            $this->error($error);
        } finally {
            if (is_resource($stream)) {
                fwrite($stream, json_encode(['event' => 'summary', 'complete' => $finished, 'counts' => $counts,
                    'bonus_total' => LegacyCustomerImporter::decimal($bonus), 'after_id' => $after,
                    'snapshot_max_id' => $snapshot, 'error' => $error], JSON_UNESCAPED_UNICODE)."\n");
                fclose($stream);
            }
            $lock->release();
            $this->untrap();
        }
        $this->table(['Yaradıldı', 'Yaradılacaq', 'Əvvəl köçürülüb', 'Konflikt', 'Uyğunsuz', 'Tarix yeniləndi', 'Tarix yenilənəcək'], [array_values($counts)]);
        $this->line(($apply ? 'Köçürülən' : 'Köçürüləcək').' bonus: '.LegacyCustomerImporter::decimal($bonus));
        if ($path) $this->line('Hesabat: '.$path);
        if (!$finished || $counts['conflict'] > 0 || $counts['invalid'] > 0) {
            $this->warn('İmport tam problemsiz deyil. Hesabatı nəzərdən keçirin; əvvəl yaradılan hesablar təkrar yazılmayacaq.');
            return self::FAILURE;
        }
        return self::SUCCESS;
    }
}
