<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Öz anbarımız" — öz istehsalımızın hazır malı (OwnStock). Adi depo kimi sifarişə seçilir, kuryer götürür,
 * amma SMS / sorğu / depo borcu yoxdur. Sətri OwnStock::warehouse() ilk lazım olanda yaradır.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('warehouses', function (Blueprint $table) {
            $table->boolean('is_own')->default(false)->after('active');
        });
    }

    public function down(): void
    {
        Schema::table('warehouses', function (Blueprint $table) {
            $table->dropColumn('is_own');
        });
    }
};
