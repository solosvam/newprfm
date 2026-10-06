<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Müştərinin ehtiyat telefon nömrəsi (CRM → müştəri → Ayarlar): kuryer və operator əsas nömrəyə çata bilməyəndə.
 * CRM axtarışı və ps-side (WhatsApp nömrəsi ilə tanıma) bu nömrəni də tapır. Unikal deyil (məs. ailə üzvünün nömrəsi).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('mobile_2', 12)->nullable()->after('mobile')->index();
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex(['mobile_2']);
            $table->dropColumn('mobile_2');
        });
    }
};
