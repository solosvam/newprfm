<?php

namespace App\Console\Commands;

use App\Models\Finance\MoneyMovement;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Köhnə hissə-hissə satışlarını xalis formaya çevirir (FinanceService::recordCreditReceivable indi belə yazır):
 * "Ferrum komissiyası" (credit_fee) sətri silinir, həmin sifarişin Ferrum-a yazılmış satışı (customer_payment) komissiya
 * qədər azaldılır. Ferrum balansı dəyişmir (−fee və +fee eyni hesabdadır), hesabatlarda gəlir xalis, komissiya xərci yox olur.
 * Toxunulmur: əks olunmuş (və ya əks əməliyyat olan) sətirlər, satışı tapılmayan / komissiyadan kiçik olan sifarişlər.
 *
 *   php artisan parfumshop:ferrum-net-sales --dry-run   — nə dəyişəcəyini göstərir
 *   php artisan parfumshop:ferrum-net-sales --apply     — dəyişir
 */
class FerrumNetSales extends Command
{
    protected $signature = 'parfumshop:ferrum-net-sales {--dry-run : Yalnız göstər} {--apply : Dəyiş}';

    protected $description = 'Köhnə Ferrum satışları: komissiya xərcini silib satışı xalis məbləğə endirir';

    public function handle(): int
    {
        if ($this->option('dry-run') === $this->option('apply')) {
            $this->error('--dry-run və ya --apply seçin.');

            return self::INVALID;
        }

        $fees = MoneyMovement::with(['order:id,order_no', 'reversedBy:id,reversal_of_id'])->where('kind', 'credit_fee')->orderBy('id')->get();
        $rows = [];
        $plan = [];
        foreach ($fees as $fee) {
            $sale = MoneyMovement::with('reversedBy:id,reversal_of_id')->where('kind', 'customer_payment')->where('order_id', $fee->order_id)
                ->where('to_account_id', $fee->from_account_id)->orderBy('id')->first();
            $skip = match (true) {
                $fee->reversedBy !== null => 'komissiya əks olunub',
                $sale === null => 'Ferrum satışı tapılmadı',
                $sale->reversedBy !== null => 'satış əks olunub',
                (float) $sale->amount <= (float) $fee->amount => 'satış komissiyadan kiçikdir',
                default => null,
            };
            $net = $sale ? round((float) $sale->amount - (float) $fee->amount, 2) : null;
            $rows[] = [$fee->order?->order_no ?? '#'.$fee->order_id, $sale ? number_format((float) $sale->amount, 2) : '—',
                number_format((float) $fee->amount, 2), $net !== null ? number_format($net, 2) : '—', $skip ?? ($this->option('apply') ? 'çevrildi' : 'çevriləcək')];
            if (!$skip) {
                $plan[] = [$fee, $sale, $net];
            }
        }

        if (!$rows) {
            $this->info('Ferrum komissiyası sətri yoxdur — hər şey artıq xalisdir.');

            return self::SUCCESS;
        }

        if ($this->option('apply')) {
            DB::transaction(function () use ($plan) {
                foreach ($plan as [$fee, $sale, $net]) {
                    $note = trim((string) $sale->note);
                    $sale->forceFill([
                        'amount' => $net,
                        'note' => mb_substr($note.($note !== '' ? ' · ' : '').'xalis: '.number_format((float) $sale->amount, 2, '.', '').' − '.number_format((float) $fee->amount, 2, '.', ''), 0, 500),
                    ])->save();
                    $fee->delete();
                }
            });
        }

        $this->table(['Sifariş', 'Satış (köhnə)', 'Komissiya', 'Satış (xalis)', 'Nəticə'], $rows);
        $this->info(($this->option('apply') ? 'Çevrildi: ' : 'Çevriləcək: ').count($plan).' / '.count($rows)
            .' · komissiya cəmi '.number_format(array_sum(array_map(fn ($p) => (float) $p[0]->amount, $plan)), 2).' AZN');

        return self::SUCCESS;
    }
}
