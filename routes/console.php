<?php

use Illuminate\Support\Facades\Schedule;

// Bonusun istifadə müddəti: vaxtı çatan bonuslar silinir (BonusService::expireDue)
//Schedule::command('bonus:expire')->hourly()->withoutOverlapping();

// Bonusun müddəti bitməzdən 3 gün əvvəl SMS (BonusService::remindExpiring) — gündə bir dəfə, səhər (Bakı vaxtı)
//Schedule::command('bonus:remind-expiring')->dailyAt('11:00')->timezone('Asia/Baku')->withoutOverlapping();

// Müştəri bankdan sayta qayıtmayanda gözləyən Birbank ödənişlərinin nəticəsi (callback-in əvəzi)
Schedule::command('payments:check-birbank')->everyMinute()->withoutOverlapping();

// Göndərilmiş SMS-lərin çatdırılma statusu (lsim hesabatı) — SMS səhifəsindəki "çatmayanlar" üçün
Schedule::command('sms:check-delivery')->everyFiveMinutes()->withoutOverlapping();
