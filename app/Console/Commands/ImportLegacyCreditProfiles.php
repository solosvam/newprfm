<?php

namespace App\Console\Commands;

use App\Services\LegacyCreditProfileImporter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

class ImportLegacyCreditProfiles extends Command
{
    protected $signature = 'parfumshop:import-credit-profiles
        {--dry-run : Profil və faylları yazmadan yoxla (standart)}
        {--apply : Boş sahələri tamamla və şəkilləri saxla}
        {--batch=50 : Bir sorğuda 1-200 telefon qrupu}
        {--after-mobile= : Bu telefondan sonra davam et}
        {--snapshot-max-id= : Əvvəlki run-dakı snapshot həddi}';

    protected $description = 'Köhnə oc_credits2 qeydlərindən mövcud müştərilərin natamam kredit profillərini tamamlayır';

    public function handle(LegacyCreditProfileImporter $importer): int
    {
        $apply = (bool) $this->option('apply');
        $after = (string) $this->option('after-mobile');
        $batch = filter_var($this->option('batch'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 200]]);
        $snapshot = $this->option('snapshot-max-id') === null ? null
            : filter_var($this->option('snapshot-max-id'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        if (($apply && $this->option('dry-run')) || $batch === false || $snapshot === false
            || ($after !== '' && (!preg_match('/^994[0-9]{9}$/D', $after) || $snapshot === null))) {
            $this->error('Parametrləri yoxlayın. Davametmə üçün --after-mobile və --snapshot-max-id birlikdə verilməlidir.');
            return self::FAILURE;
        }
        foreach (['fin', 'id_card_series', 'id_card_number', 'id_card_front', 'id_card_back'] as $field) {
            if (!Schema::hasColumn('customer_credit_profiles', $field)) {
                $this->error('Kredit profili baza strukturu hazır deyil. Mövcud kredit profil migration-larını tətbiq edin.');
                return self::FAILURE;
            }
        }
        // Shares the customer-import lock so the two migrations cannot change the same customer together.
        $lock = Cache::lock('legacy-customer-import', 86400);
        if (!$lock->get()) {
            $this->error('Başqa müştəri və ya kredit profili importu işləyir.');
            return self::FAILURE;
        }
        $counts = array_fill_keys(['updated', 'would_update', 'complete', 'not_found', 'conflict', 'invalid', 'partial', 'unchanged', 'error'], 0);
        $review = 0;
        $stopped = false;
        $finished = false;
        $stream = null;
        $path = null;
        $error = null;
        if (extension_loaded('pcntl')) $this->trap([SIGINT, SIGTERM], function () use (&$stopped) { $stopped = true; });
        try {
            $directory = storage_path('app/private/imports');
            if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) throw new RuntimeException('Hesabat qovluğu yaradıla bilmədi.');
            $path = $directory.'/credit-profiles-'.now()->format('Ymd-His').'-'.bin2hex(random_bytes(4)).'.jsonl';
            $stream = fopen($path, 'x');
            if ($stream === false) throw new RuntimeException('Hesabat faylı yaradıla bilmədi.');
            chmod($path, 0600);
            $write = function (array $data) use ($stream): void {
                $line = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n";
                if (fwrite($stream, $line) !== strlen($line)) throw new RuntimeException('Hesabat yazılmadı.');
            };
            $write(['event' => 'start', 'mode' => $apply ? 'apply' : 'dry-run', 'after_mobile' => $after, 'snapshot_max_id' => $snapshot]);
            $this->info($apply ? 'Kredit profili importu başlayır.' : 'Sınaq rejimi: bazaya və şəkil qovluğuna yazılmır; şəkillər yaddaşda yoxlanılır.');
            do {
                if ($stopped) break;
                $page = $importer->page($after, $batch, $snapshot);
                $snapshot = $page['pagination']['snapshot_max_id'];
                foreach ($page['groups'] as $group) {
                    if ($stopped) break 2;
                    try {
                        $result = $importer->import($group, $apply);
                    } catch (Throwable $e) {
                        $result = ['status' => 'error', 'source_ids' => array_column($group['records'], 'id'),
                            'reason' => 'Profil saxlanmadı. Baza, fayl icazələrini və paralel profil dəyişikliklərini yoxlayın.'];
                    }
                    $counts[$result['status']]++;
                    if (!empty($result['missing']) || !empty($result['warnings'])) $review++;
                    $write(['event' => 'profile'] + $result);
                    $after = $group['mobile'];
                    $write(['event' => 'checkpoint', 'after_mobile' => $after, 'snapshot_max_id' => $snapshot]);
                    if (array_sum($counts) % 10 === 0) $this->line('Baxılan müştəri: '.array_sum($counts).'; '.($apply ? 'yenilənən' : 'yenilənəcək').': '.($counts['updated'] + $counts['would_update']));
                }
            } while ($page['pagination']['has_more']);
            $finished = !$stopped;
            if ($stopped) $this->warn('Import dayandırıldı. Kilid açılır.');
        } catch (Throwable $e) {
            $error = $e instanceof RuntimeException ? $e->getMessage() : 'Kredit importu alınmadı.';
            $this->error($error);
        } finally {
            if (is_resource($stream)) {
                fwrite($stream, json_encode(['event' => 'summary', 'complete' => $finished, 'counts' => $counts,
                    'needs_review' => $review, 'after_mobile' => $after, 'snapshot_max_id' => $snapshot, 'error' => $error], JSON_UNESCAPED_UNICODE)."\n");
                fclose($stream);
            }
            $lock->release();
            $this->untrap();
        }
        $labels = ['Yeniləndi', 'Yenilənəcək', 'Profil tamdır', 'Müştəri tapılmadı', 'Konflikt', 'Uyğunsuz', 'Natamam qaldı', 'Dəyişiklik yoxdur', 'Xəta'];
        $this->table(['Nəticə', 'Say'], array_map(fn ($label, $value) => [$label, $value], $labels, array_values($counts)));
        $this->line('Şəkil/sahə yoxlaması tələb edən profil: '.$review);
        if ($path) $this->line('Hesabat: '.$path);
        return !$finished || $review || $counts['not_found'] || $counts['conflict'] || $counts['invalid'] || $counts['partial'] || $counts['error']
            ? self::FAILURE : self::SUCCESS;
    }
}
