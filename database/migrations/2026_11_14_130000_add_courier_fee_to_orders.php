<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Əlavə çatdırılma (uzaq ünvan): operator sifarişə yazır, müştərinin yekun ödənişinə əlavə olunur, tam məbləğ sifarişi
 * təhvil verən kuryerin qazancıdır — şirkətin gəlirinə, satışına daxil deyil (CourierFees, kateqoriya courier_fee).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('orders', 'courier_fee')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->decimal('courier_fee', 10, 2)->default(0)->after('delivery_fee');
            });
        }
        if (Schema::hasTable('finance_categories') && !DB::table('finance_categories')->where('code', 'courier_fee')->exists()) {
            $now = now();
            DB::table('finance_categories')->insert(['name' => 'Əlavə çatdırılma (kuryerin qazancı)', 'type' => 'both', 'code' => 'courier_fee',
                'system' => true, 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('finance_categories')) {
            DB::table('finance_categories')->where('code', 'courier_fee')->delete();
        }
        if (Schema::hasColumn('orders', 'courier_fee')) {
            Schema::table('orders', fn (Blueprint $table) => $table->dropColumn('courier_fee'));
        }
    }
};
