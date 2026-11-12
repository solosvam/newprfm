<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Deponun başlanğıc borcu (köhnə hesabat proqramından köçürmək üçün): depo borcuna əlavə olunur
 * (FinanceService::warehouseDebts). Mənfi — depo bizə borcludur (avans). Pul hərəkəti deyil — kassaya təsir etmir.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('warehouses', function (Blueprint $table) {
            $table->decimal('opening_debt', 12, 2)->default(0)->after('address');
        });
    }

    public function down(): void
    {
        Schema::table('warehouses', function (Blueprint $table) {
            $table->dropColumn('opening_debt');
        });
    }
};
