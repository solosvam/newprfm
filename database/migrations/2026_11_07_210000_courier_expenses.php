<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Kuryer xərcləri: kuryer tətbiqində "Xərc yaz" (məbləğ, açıqlama, istəyə görə qəbz şəkli).
 *  - money_movements.photo — qəbzin şəkli (storage/app/private/expense-photos, yalnız fayl adı);
 *  - "Kuryer xərcləri" sistem kateqoriyası (courier_expense) — xərc avtomatik bura düşür.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('money_movements', function (Blueprint $table) {
            $table->string('photo', 60)->nullable()->after('note');
        });

        if (!DB::table('finance_categories')->where('code', 'courier_expense')->exists()) {
            $now = now();
            DB::table('finance_categories')->insert([
                'name' => 'Kuryer xərcləri', 'type' => 'expense', 'code' => 'courier_expense', 'system' => true,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('money_movements', function (Blueprint $table) {
            $table->dropColumn('photo');
        });
        if (!DB::table('money_movements')->whereIn('category_id', DB::table('finance_categories')->where('code', 'courier_expense')->select('id'))->exists()) {
            DB::table('finance_categories')->where('code', 'courier_expense')->delete();
        }
    }
};
