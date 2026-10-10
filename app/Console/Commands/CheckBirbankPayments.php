<?php

namespace App\Console\Commands;

use App\Models\Payment\Payment;
use App\Services\Payment\BirbankPaymentSync;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Müştəri bankdan sayta qayıtmasa (brauzeri bağlayıb, internet kəsilib), callback işləmir və ödəniş
 * "gözləyir" qalır — sifarişi nə ləğv etmək, nə də yenidən ödəmək olur. Bu əmr gözləyən Birbank
 * ödənişlərini bankdan soruşur və callback-dəki eyni yolla (BirbankPaymentSync) nəticəni yazır.
 *
 * Bank ödənilməyən sifarişi özü "Expired" edir; biz öz vaxt həddimizlə ödənişi ləğv etmirik
 * (bankda hələ açıq olan ödənişi ləğv saysaq, müştəri iki dəfə ödəyə bilər).
 */
class CheckBirbankPayments extends Command
{
    protected $signature = 'payments:check-birbank
        {--min-age=2 : Ən azı neçə dəqiqə əvvəl yaranmış ödənişlər (müştəri hələ ödəniş səhifəsində ola bilər)}
        {--stale-hours=24 : Bu qədər saatdan sonra hələ gözləyən ödəniş loga xəbərdarlıq kimi yazılır}';

    protected $description = 'Gözləyən Birbank ödənişlərinin nəticəsini bankdan yoxlayır';

    public function handle(BirbankPaymentSync $sync): int
    {
        $payments = Payment::where('provider', 'birbank')
            ->where('status', Payment::PENDING)
            ->where('created_at', '<=', now()->subMinutes(max(0, (int) $this->option('min-age'))))
            ->orderBy('id')
            ->get();

        $counts = ['paid' => 0, 'failed' => 0, 'cancelled' => 0, 'pending' => 0, 'error' => 0];
        $staleBefore = now()->subHours(max(1, (int) $this->option('stale-hours')));

        foreach ($payments as $payment) {
            try {
                $result = $payment->provider_order_id ? $sync->sync($payment) : $sync->failUnstarted($payment);
            } catch (Throwable $exception) {
                // Bank cavab vermədi və ya cavab uyğunsuzdur — ödəniş gözləyən qalır, növbəti dəfə yenə yoxlanır
                $counts['error']++;
                Log::warning('Birbank ödənişi yoxlanmadı', ['payment_id' => $payment->id, 'order_id' => $payment->order_id, 'error' => $exception->getMessage()]);
                $this->warn("#{$payment->id} (sifariş {$payment->order_id}): {$exception->getMessage()}");
                continue;
            }

            $counts[$result->status] = ($counts[$result->status] ?? 0) + 1;
            $this->line("#{$result->id} (sifariş {$result->order_id}): {$result->status}".($result->response_text ? " — {$result->response_text}" : ''));

            if ($result->status === Payment::PENDING && $result->created_at <= $staleBefore) {
                Log::warning('Birbank ödənişi uzun müddətdir gözləyir', [
                    'payment_id' => $result->id, 'order_id' => $result->order_id,
                    'bank_status' => $result->response_text, 'created_at' => (string) $result->created_at,
                ]);
            }
        }

        $this->info("Yoxlandı: {$payments->count()} — ödənilib {$counts['paid']}, uğursuz {$counts['failed']}, ləğv {$counts['cancelled']}, hələ gözləyir {$counts['pending']}, xəta {$counts['error']}");

        return self::SUCCESS;
    }
}
