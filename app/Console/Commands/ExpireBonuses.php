<?php

namespace App\Console\Commands;

use App\Services\BonusService;
use Illuminate\Console\Command;

class ExpireBonuses extends Command
{
    protected $signature = 'bonus:expire';

    protected $description = 'Vaxtı çatmış bonusları balansdan silir (loqa "Bonusun müddəti bitdi" yazılır)';

    public function handle(BonusService $bonus): int
    {
        $count = $bonus->expireDue();
        $this->info("Emal olunan bonus: {$count}");

        return self::SUCCESS;
    }
}
