<?php

namespace App\Services\Courier;

use App\Http\Controllers\Backend\CourierTeamController;
use App\Models\Finance\MoneyMovement;
use App\Models\User;
use App\Services\Admin\AdminNotifier;
use App\Services\FinanceService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Kuryer xərcləri (Taxi və s.): kuryer tətbiqində yazır — məbləğ kuryerin balansından dərhal çıxır
 * (Kuryer → Kənar, növ courier_expense, kateqoriya "Kuryer xərcləri"). Qəbzin şəkli istəyə görə.
 *  - Xərc yazılanda: adminə zəng bildirişi, baş kuryerlərə tətbiqdə bildiriş + push.
 *  - Ləğv (əks əməliyyat, pul balansa qayıdır): admin — istənilən xərci; baş kuryer — başqa kuryerin
 *    son COURIER_DAYS_BACK gündəki xərcini (öz xərcini yox). Kuryerin özü silə bilmir.
 */
class CourierExpenses
{
    public function __construct(private FinanceService $finance) {}

    /** $by — xərci kuryerin əvəzinə admin yazırsa (admin panel → Kuryerlər): kuryerə bildiriş gedir, adminə / baş kuryerə yox */
    public function record(User $courier, float $amount, string $note, ?UploadedFile $photo = null, ?User $by = null): MoneyMovement
    {
        $photoName = $photo ? PickPhotoStorage::store($photo, PickPhotoStorage::EXPENSES) : null;
        $staff = $by && $by->id !== $courier->id ? $by : null;
        try {
            $movement = $this->finance->record($this->finance->courierAccount($courier), $this->finance->system('external'), $amount, 'courier_expense',
                ['note' => trim($note), 'photo' => $photoName], $staff?->id ?? $courier->id);
        } catch (\Throwable $e) {
            PickPhotoStorage::delete($photoName, PickPhotoStorage::EXPENSES);
            throw $e;
        }

        $quiet = !app()->runningUnitTests();
        if ($staff) {
            rescue(fn () => app(CourierNotifier::class)->expenseWritten($movement, $courier, $staff), null, $quiet);

            return $movement;
        }
        $line = trim($courier->full_name).' · '.$this->money($movement).' · '.$movement->note;
        rescue(fn () => app(AdminNotifier::class)->notify('courier_expense', 'money_movement', $movement->id, AdminNotifier::TYPES['courier_expense'],
            $line, route('admin.courier.team.show', $courier, false).'#expenses'), null, $quiet);
        foreach ($this->heads()->reject(fn (User $head) => $head->id === $courier->id) as $head) {
            rescue(fn () => app(CourierNotifier::class)->expenseAdded($movement, $courier, $head), null, $quiet);
        }

        return $movement;
    }

    /** Kuryerin xərcləri (yenisi yuxarıda); $since — bu tarixdən (baş kuryerə məhdudiyyət) */
    public function of(User $courier, ?string $since = null, int $limit = 100): Collection
    {
        return MoneyMovement::with(['reversedBy.user', 'user'])
            ->where('kind', 'courier_expense')
            ->where('from_account_id', $this->finance->courierAccount($courier)->id)
            ->when($since, fn ($q, $d) => $q->whereDate('occurred_at', '>=', $d))
            ->orderByDesc('occurred_at')->orderByDesc('id')->limit($limit)->get();
    }

    public function canCancel(MoneyMovement $expense, User $actor): bool
    {
        if ($expense->kind !== 'courier_expense' || $expense->reversedBy()->exists()) {
            return false;
        }
        if (!FinanceService::isCourier($actor)) {
            return $actor->can('courier.team'); // rəhbər / admin
        }
        $owner = (int) $expense->from?->user_id;

        return $actor->can('courier.team') && $owner !== (int) $actor->id
            && $expense->occurred_at->toDateString() >= today()->subDays(CourierTeamController::COURIER_DAYS_BACK)->toDateString();
    }

    public function cancel(MoneyMovement $expense, User $actor): MoneyMovement
    {
        if (!$this->canCancel($expense, $actor)) {
            throw ValidationException::withMessages(['expense' => 'Bu xərci ləğv etmək olmaz.']);
        }
        $reversal = $this->finance->reverse($expense, $actor->id, 'Xərc ləğv edildi: '.$expense->note);
        $courier = $expense->from?->user;
        if ($courier && $courier->id !== $actor->id) {
            rescue(fn () => app(CourierNotifier::class)->expenseCancelled($expense, $courier, $actor), null, !app()->runningUnitTests());
        }

        return $reversal;
    }

    /** Baş kuryerlər (aktiv) */
    private function heads(): Collection
    {
        return rescue(fn () => User::whereHas('roles', fn ($q) => $q->where('name', FinanceService::HEAD_COURIER_ROLE)->where('guard_name', 'admin'))
            ->where('active', true)->get(), collect(), false);
    }

    private function money(MoneyMovement $movement): string
    {
        return number_format((float) $movement->amount, 2).' AZN';
    }
}
