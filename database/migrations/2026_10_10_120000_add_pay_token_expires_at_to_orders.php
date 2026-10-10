<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SMS ödəniş linkinin son istifadə vaxtı. Müddət Ayarlar → Sifariş və çatdırılma → "Ödəniş linkinin müddəti"
 * (pay_link_hours, standart 72 saat). Mövcud linklərə bu andan etibarən həmin müddət verilir.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('pay_token_expires_at')->nullable()->after('pay_token');
        });

        $hours = (int) (DB::table('settings')->where('key', 'pay_link_hours')->value('value') ?: 72);

        DB::table('orders')->whereNotNull('pay_token')->update(['pay_token_expires_at' => now()->addHours($hours)]);
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('pay_token_expires_at');
        });
    }
};
