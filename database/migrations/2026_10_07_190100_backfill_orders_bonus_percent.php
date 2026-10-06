<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Mövcud sifarişlərin bonus faizi indiki admin ayarı ilə sabitlənir (yeni sifarişlərdə yaradılan yer yazır) */
return new class extends Migration {
    public function up(): void
    {
        $percent = (float) (DB::table('settings')->where('key', 'order_bonus_percent')->value('value') ?? 5);
        DB::table('orders')->whereNull('bonus_percent')->update(['bonus_percent' => $percent]);
    }

    public function down(): void
    {
        // geri qaytarılmır: hansı faizin ayardan gəldiyi bilinmir
    }
};
