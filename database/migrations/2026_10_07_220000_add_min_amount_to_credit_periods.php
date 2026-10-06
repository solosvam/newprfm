<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Hissə-hissə ödəniş: hər müddətin minimum məbləği (admin → Kredit → Faizlər).
 * Müddət yalnız məbləğ bu həddən YUXARI olanda təklif olunur; boş — məhdudiyyət yoxdur.
 * Əvvəlki sabit qayda (≤ 200 AZN-də yalnız 3 və 6 ay) dəyərlərə köçürülür.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('credit_periods', function (Blueprint $table) {
            $table->decimal('min_amount', 10, 2)->nullable()->after('interest_rate');
        });

        DB::table('credit_periods')->whereNotIn('month', [3, 6])->update(['min_amount' => 200]);
    }

    public function down(): void
    {
        Schema::table('credit_periods', function (Blueprint $table) {
            $table->dropColumn('min_amount');
        });
    }
};
