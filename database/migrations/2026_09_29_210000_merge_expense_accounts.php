<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Hazır xərc kateqoriyaları (Maaş, İşıq, Su, İcarə, Digər) əvəzinə bir "Xərclər" hesabı.
 * Xərcin adı əməliyyatın qeydində yazılır. Köhnə kateqoriyalara yazılmış xərclər itmir:
 * "Xərclər"-ə köçürülür, kateqoriyanın adı qeydin əvvəlinə əlavə olunur.
 */
return new class extends Migration {
    public function up(): void
    {
        $now = now();
        $expenseId = DB::table('finance_accounts')->where('code', 'expense')->value('id')
            ?? DB::table('finance_accounts')->insertGetId(['type' => 'expense', 'code' => 'expense', 'name' => 'Xərclər', 'active' => true, 'created_at' => $now, 'updated_at' => $now]);

        $old = DB::table('finance_accounts')->where('type', 'expense')->where('id', '!=', $expenseId)->get();
        foreach ($old as $account) {
            foreach (['to_account_id', 'from_account_id'] as $column) {
                DB::table('money_movements')->where($column, $account->id)->orderBy('id')->get()->each(function ($m) use ($column, $expenseId, $account) {
                    DB::table('money_movements')->where('id', $m->id)->update([
                        $column => $expenseId,
                        'note' => trim($account->name.($m->note ? ' — '.$m->note : '')),
                    ]);
                });
            }
            DB::table('finance_accounts')->where('id', $account->id)->delete();
        }
    }

    public function down(): void
    {
        // Birləşdirmə geri qaytarılmır: xərclərin adları qeydlərdə qalır.
    }
};
