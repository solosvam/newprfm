<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Kuryer "Yola çıxdım" basanda müştəriyə gedən SMS ləğv olunub (1 noyabr 2026) — şablon silinir.
 * Köhnə göndərişlər SMS jurnalında (sms_logs, context = order_sent) qalır.
 */
return new class extends Migration {
    public function up(): void
    {
        DB::table('sms_templates')->where('code', 'order_sent')->delete();
    }

    public function down(): void
    {
        // Geri qaytarılmır: SMS kodda da yoxdur
    }
};
