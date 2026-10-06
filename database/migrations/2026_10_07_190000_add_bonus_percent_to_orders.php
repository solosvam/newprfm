<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sifarişin bonus faizi: null — admin ayarı (order_bonus_percent). CRM-də operator qiyməti əl ilə dəyişəndə
 * bonus ləğv olunur və operator 0..ayar arasında faiz seçir (standart 0) — BonusService::amountForOrder bunu istifadə edir.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('bonus_percent', 5, 2)->nullable()->after('bonus_earned');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('bonus_percent');
        });
    }
};
