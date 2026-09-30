<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kredit profili: şəxsiyyət vəsiqəsinin seriya + nömrəsi əlavə olunur (Ferrum formasında lazımdır).
 * "Vəzifə" formadan çıxarıldı — sütun silinmir (köhnə məlumat qalır), sadəcə nullable olur.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_credit_profiles', function (Blueprint $table) {
            $table->string('id_card_series', 5)->nullable()->after('fin');
            $table->string('id_card_number', 8)->nullable()->after('id_card_series');
            $table->string('position', 150)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('customer_credit_profiles', function (Blueprint $table) {
            $table->dropColumn(['id_card_series', 'id_card_number']);
        });
    }
};
