<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Çıxılacaq faiz: hissə-hissə ödəniş təsdiqlənəndən sonra Ferrum müştərinin ümumi məbləğindən bu faizi çıxıb qalanını bizə köçürür.
 * Məs. 6 ay: müştəri 17.6% artıq ödəyir, cəmi 500 ₼; Ferrum 16% çıxır → hesabımıza 420 ₼.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('credit_periods', 'bank_fee_rate')) {
            Schema::table('credit_periods', function (Blueprint $table) {
                $table->decimal('bank_fee_rate', 5, 2)->nullable()->after('interest_rate');
            });
        }
    }

    public function down(): void
    {
        Schema::table('credit_periods', fn (Blueprint $table) => $table->dropColumn('bank_fee_rate'));
    }
};
