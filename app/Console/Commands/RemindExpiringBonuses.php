<?php

namespace App\Console\Commands;

use App\Services\BonusService;
use App\Services\SmsService;
use Illuminate\Console\Command;

class RemindExpiringBonuses extends Command
{
    protected $signature = 'bonus:remind-expiring';

    protected $description = 'Müddəti 3 gün ərzində bitəcək bonusu olan müştərilərə SMS xatırlatma göndərir';

    public function handle(BonusService $bonus, SmsService $sms): int
    {
        $this->info('Göndərilən SMS: '.$bonus->remindExpiring($sms));

        return self::SUCCESS;
    }
}
