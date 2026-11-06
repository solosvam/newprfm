<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Hissə-hissə ödəniş (Ferrum):
 *  - credit_applications.bank_fee_rate — sifariş yarananda müddətin "çıxılacaq faizi" (sonra dəyişsə də köhnə sifarişin hesabı dəyişmir);
 *  - "Ferrum komissiyası" xərc kateqoriyası (credit_fee);
 *  - Ferrum hesabı: adı "Ferrum" olan hesab varsa o istifadə olunur (FinanceService::ferrumAccount), yoxdursa yaradılır.
 * Təhvildə: Müştəri → Ferrum (yekun, "Satış"), Ferrum → Kənar (komissiya) — Ferrum-un qalığı = bizə köçürəcəyi pul.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('credit_applications', 'bank_fee_rate')) {
            Schema::table('credit_applications', function (Blueprint $table) {
                $table->decimal('bank_fee_rate', 5, 2)->nullable()->after('interest_rate');
            });
        }
        // Mövcud müraciətlər: müddətin indiki faizi
        if (Schema::hasColumn('credit_periods', 'bank_fee_rate')) {
            foreach (DB::table('credit_periods')->whereNotNull('bank_fee_rate')->get(['id', 'bank_fee_rate']) as $period) {
                DB::table('credit_applications')->where('credit_period_id', $period->id)->whereNull('bank_fee_rate')->update(['bank_fee_rate' => $period->bank_fee_rate]);
            }
        }

        $now = now();
        if (!DB::table('finance_categories')->where('code', 'credit_fee')->exists()) {
            DB::table('finance_categories')->insert(['name' => 'Ferrum komissiyası', 'type' => 'expense', 'code' => 'credit_fee', 'system' => true, 'created_at' => $now, 'updated_at' => $now]);
        }
        $named = DB::table('finance_accounts')->whereRaw('lower(name) = ?', ['ferrum'])->whereIn('type', ['bank', 'card', 'cash', 'online'])->exists();
        if (!$named && !DB::table('finance_accounts')->where('code', 'ferrum')->exists()) {
            DB::table('finance_accounts')->insert(['type' => 'bank', 'code' => 'ferrum', 'name' => 'Ferrum', 'description' => 'Hissə-hissə ödəniş — Ferrum-un köçürəcəyi pul',
                'active' => true, 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    public function down(): void
    {
        DB::table('finance_categories')->where('code', 'credit_fee')->delete();
        Schema::table('credit_applications', fn (Blueprint $table) => $table->dropColumn('bank_fee_rate'));
    }
};
