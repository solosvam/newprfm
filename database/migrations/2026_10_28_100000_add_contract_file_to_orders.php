<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ferrum kredit müqaviləsi (PDF): extensions/ferrum-filler Ferrum-dan çəkib yükləyir.
 * Fayl storage/app/private/contracts-dədir (public deyil); burada disk daxilindəki yol saxlanır.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('contract_file')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn('contract_file'));
    }
};
