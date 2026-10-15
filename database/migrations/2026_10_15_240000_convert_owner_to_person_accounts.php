<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * "Sahibkar" sistem hesabı → adi şəxs hesabı (Şəxslər). "Sahibkar pul qoydu / qaytarıldı" əməliyyatları
 * "Mədaxil / Məxaric" olur — tarixçə itmir, sadəcə yeni adlarla görünür.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('finance_accounts')->where('type', 'owner')->update(['type' => 'person', 'code' => null]);
        DB::table('money_movements')->where('kind', 'owner_contribution')->update(['kind' => 'income']);
        DB::table('money_movements')->where('kind', 'owner_repayment')->update(['kind' => 'outgo']);
    }

    public function down(): void
    {
        DB::table('money_movements')->where('kind', 'income')->update(['kind' => 'owner_contribution']);
        DB::table('money_movements')->where('kind', 'outgo')->update(['kind' => 'owner_repayment']);
        DB::table('finance_accounts')->where('type', 'person')->where('name', 'Sahibkar')->update(['type' => 'owner', 'code' => 'owner']);
    }
};
