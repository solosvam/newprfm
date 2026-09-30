<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Asan sifarişdə qeydiyyat bonusu əvvəl "earn" (sifariş bonusu) tipi ilə yazılırdı.
 * "register" tipinə keçirilir: hesabat düz olsun və BonusService::grantRegistration bonusu təkrar verməsin.
 */
return new class extends Migration {
    public function up(): void
    {
        DB::table('customer_bonus_transactions')
            ->where('type', 'earn')->whereNull('order_id')->where('note', 'Qeydiyyat bonusu')
            ->update(['type' => 'register']);
    }

    public function down(): void
    {
        // Geri qaytarılmır: "register" düzgün tipdir.
    }
};
