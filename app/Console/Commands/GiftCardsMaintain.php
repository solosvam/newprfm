<?php

namespace App\Console\Commands;

use App\Services\GiftCard\GiftCardService;
use App\Services\SmsService;
use Illuminate\Console\Command;

/** Virtual hədiyyə kartları: müddəti bitənlər bağlanır, bitməsinə 30 gün qalanların alıcısına bir dəfə SMS gedir */
class GiftCardsMaintain extends Command
{
    protected $signature = 'gift-cards:maintain {--no-sms : Xatırlatma SMS-i göndərilmir}';

    protected $description = 'Hədiyyə kartlarının müddəti: bitənlər bağlanır, yaxınlaşanlara SMS';

    public function handle(GiftCardService $cards, SmsService $sms): int
    {
        $expired = $cards->expireDue();
        $reminded = $this->option('no-sms') ? 0 : $cards->remindExpiring($sms);
        $this->info("Müddəti bitən: {$expired}, xatırlatma SMS-i: {$reminded}");

        return self::SUCCESS;
    }
}
