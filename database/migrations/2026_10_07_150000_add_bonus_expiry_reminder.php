<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bonus müddəti bitməzdən 3 gün əvvəl SMS (BonusService::remindExpiring, bonus:remind-expiring):
 *  - customer_bonus_transactions.reminded_at — paket üçün xatırlatma göndərilib (təkrar olmasın);
 *  - SMS şablonu "bonus_expiring" — "SMS şablonları"ndan redaktə olunur.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('customer_bonus_transactions', function (Blueprint $table) {
            $table->timestamp('reminded_at')->nullable()->after('expired_at');
        });

        DB::table('sms_templates')->updateOrInsert(['code' => 'bonus_expiring'], [
            'name' => 'Bonusun müddəti bitir (3 gün əvvəl)',
            'template' => 'Hormetli {name}, Parfumshop hesabinizdaki {amount} AZN bonusun muddeti {date} tarixinde bitir. Istifade etmeyi unutmayin: parfumshop.az',
            'active' => 1,
        ]);
    }

    public function down(): void
    {
        DB::table('sms_templates')->where('code', 'bonus_expiring')->delete();
        Schema::table('customer_bonus_transactions', function (Blueprint $table) {
            $table->dropColumn('reminded_at');
        });
    }
};
