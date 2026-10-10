<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

// Bonusun istifadə müddəti: vaxtı çatan bonuslar silinir (BonusService::expireDue)
\Illuminate\Support\Facades\Schedule::command('bonus:expire')->hourly()->withoutOverlapping();

// Bonusun müddəti bitməzdən 3 gün əvvəl SMS (BonusService::remindExpiring) — gündə bir dəfə, səhər (Bakı vaxtı)
\Illuminate\Support\Facades\Schedule::command('bonus:remind-expiring')->dailyAt('11:00')->timezone('Asia/Baku')->withoutOverlapping();

// Müştəri bankdan sayta qayıtmayanda gözləyən Birbank ödənişlərinin nəticəsi (callback-in əvəzi)
\Illuminate\Support\Facades\Schedule::command('payments:check-birbank')->everyFiveMinutes()->withoutOverlapping();
