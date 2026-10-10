<?php

namespace App\Console\Commands;

use App\Models\SmsLog;
use App\Services\SmsService;
use Illuminate\Console\Command;
use Throwable;

/**
 * Göndərilmiş SMS-lərin çatdırılma statusunu lsim hesabatından oxuyub jurnala yazır.
 * Yekun status (çatdırılıb / çatdırılmadı / ...) alınana qədər, ən çox --days gün yoxlanır.
 * lsim limiti: dəqiqədə 150 hesabat sorğusu — ona görə bir işə salmada ən çox --limit SMS.
 */
class CheckSmsDelivery extends Command
{
    protected $signature = 'sms:check-delivery
        {--limit=100 : Bir işə salmada yoxlanan SMS sayı (lsim limiti dəqiqədə 150)}
        {--days=2 : Bundan köhnə SMS-lər daha yoxlanmır}
        {--min-age=1 : Ən azı neçə dəqiqə əvvəl göndərilmiş SMS-lər}';

    protected $description = 'Göndərilmiş SMS-lərin çatdırılma statusunu lsim-dən yoxlayır';

    public function handle(SmsService $sms): int
    {
        $logs = SmsLog::where('status', SmsLog::SENT)->whereNotNull('provider_id')
            ->where(fn ($q) => $q->whereNull('delivery_status')->orWhereIn('delivery_status', SmsLog::DELIVERY_PENDING))
            ->where('created_at', '>=', now()->subDays(max(1, (int) $this->option('days'))))
            ->where('created_at', '<=', now()->subMinutes(max(0, (int) $this->option('min-age'))))
            // Ən çoxdan yoxlanmayanlar əvvəl — limit dolsa da heç biri növbədə ilişib qalmır
            ->orderByRaw('delivery_checked_at is not null')->orderBy('delivery_checked_at')->orderBy('id')
            ->limit(max(1, (int) $this->option('limit')))->get();

        $counts = ['delivered' => 0, 'failed' => 0, 'pending' => 0, 'error' => 0];
        foreach ($logs as $log) {
            try {
                $status = $sms->deliveryStatus($log->provider_id);
            } catch (Throwable $e) {
                $counts['error']++;
                $this->warn("#{$log->id}: {$e->getMessage()}");
                if ($e->getCode() === -110) {
                    break; // dəqiqəlik limit dolub — qalanı növbəti dəfə
                }
                continue;
            }
            $log->update(['delivery_status' => $status ?? $log->delivery_status, 'delivery_checked_at' => now()]);
            $counts[$status === SmsLog::DELIVERED ? 'delivered' : (in_array($status, SmsLog::DELIVERY_FAILED, true) ? 'failed' : 'pending')]++;
        }

        $this->info("Yoxlandı: {$logs->count()} — çatdırılıb {$counts['delivered']}, çatmayıb {$counts['failed']}, hələ gözləyir {$counts['pending']}, xəta {$counts['error']}");

        return self::SUCCESS;
    }
}
