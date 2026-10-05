<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bonusun istifadə müddəti:
 *  - expires_at — qazanılan bonusun (earn, register, …) bitmə vaxtı; NULL — müddətsiz
 *  - expired_at — bonus:expire bu bonusu emal edib (təkrar emal olunmasın)
 *  - type enum → string: yeni növlər 'expire' (müddəti bitdi) və 'refund' (ləğv olunan sifarişdən qaytarma)
 *
 * Köhnə bonuslara müddət yazılmır (müştəri gözlənilmədən bonus itirməsin).
 */
return new class extends Migration {
    public function up(): void
    {
        DB::statement("ALTER TABLE customer_bonus_transactions MODIFY type VARCHAR(20) NOT NULL");

        Schema::table('customer_bonus_transactions', function (Blueprint $table) {
            $table->timestamp('expires_at')->nullable()->after('note');
            $table->timestamp('expired_at')->nullable()->after('expires_at');
            $table->index(['expires_at', 'expired_at']);
        });

        // Bonusla ödənilmiş sifariş ləğvində qaytarma — əvvəllər 'adjustment' idi
        DB::table('customer_bonus_transactions')
            ->where('type', 'adjustment')->where('amount', '>', 0)
            ->where('note', 'like', 'Sifariş ləğvi — qaytarma%')
            ->update(['type' => 'refund']);
    }

    public function down(): void
    {
        DB::table('customer_bonus_transactions')->where('type', 'refund')->update(['type' => 'adjustment']);
        DB::table('customer_bonus_transactions')->where('type', 'expire')->delete();

        Schema::table('customer_bonus_transactions', function (Blueprint $table) {
            $table->dropIndex(['expires_at', 'expired_at']);
            $table->dropColumn(['expires_at', 'expired_at']);
        });

        DB::statement("ALTER TABLE customer_bonus_transactions MODIFY type ENUM('earn','spend','register','adjustment') NOT NULL");
    }
};
