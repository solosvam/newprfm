<?php

namespace App\Services\Credit;

use App\Models\Credit\CreditApplication;
use App\Models\Credit\CreditStatus;
use Illuminate\Support\Facades\DB;

/**
 * Kredit müraciətinin statusu: əl ilə (istənilən status) və avtomatik keçidlər.
 *  - Ferrum formasını extension doldurdu → "Yoxlanılır" (yalnız "Gözləmədə"dirsə);
 *  - Ferrum müqaviləsi (PDF) bağlandı → "Təsdiqlənib".
 * Hər dəyişiklik credit_status_logs-a yazılır.
 */
final class CreditStatusService
{
    public const PENDING = 'pending';
    public const REVIEWING = 'reviewing';
    public const APPROVED = 'approved';

    public const SOURCES = ['manual' => 'Əl ilə', 'ferrum_fill' => 'Ferrum-a dolduruldu', 'contract' => 'Müqavilə yükləndi'];

    /** Statusu dəyişir; $onlyFrom verilibsə yalnız indiki status onlardandırsa. Dəyişibsə true */
    public function set(CreditApplication $application, string $code, ?int $userId, string $source, ?array $onlyFrom = null): bool
    {
        $status = CreditStatus::where('code', $code)->firstOrFail();

        return DB::transaction(function () use ($application, $status, $userId, $source, $onlyFrom) {
            $application = CreditApplication::with('status')->lockForUpdate()->findOrFail($application->id);
            if ($application->credit_status_id === $status->id) {
                return false;
            }
            if ($onlyFrom !== null && !in_array($application->status?->code, $onlyFrom, true)) {
                return false;
            }
            DB::table('credit_status_logs')->insert([
                'credit_application_id' => $application->id, 'from_status_id' => $application->credit_status_id,
                'to_status_id' => $status->id, 'user_id' => $userId, 'source' => $source, 'created_at' => now(),
            ]);
            $application->update(['credit_status_id' => $status->id]);

            return true;
        });
    }
}
