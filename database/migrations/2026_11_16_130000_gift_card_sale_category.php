<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Hədiyyə kartının alışı satış deyil (alıcıya borcdur): Pul axınında ayrıca "Hədiyyə kartı satışı" kateqoriyası.
 * Əvvəl "Satış" kimi yazılmış kart ödənişləri bu kateqoriyaya keçirilir (məbləğ və hesab dəyişmir).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('finance_categories')) return;
        DB::table('finance_categories')->updateOrInsert(['code' => 'gift_card_sale'],
            ['name' => 'Hədiyyə kartı satışı', 'type' => 'income', 'system' => true, 'created_at' => now(), 'updated_at' => now()]);
        $category = DB::table('finance_categories')->where('code', 'gift_card_sale')->value('id');
        if ($category && Schema::hasColumn('payments', 'virtual_gift_card_id')) {
            DB::table('money_movements')->where('kind', 'customer_payment')
                ->whereIn('payment_id', DB::table('payments')->whereNotNull('virtual_gift_card_id')->select('id'))
                ->update(['category_id' => $category]);
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('finance_categories')) return;
        $sale = DB::table('finance_categories')->where('code', 'sale')->value('id');
        $category = DB::table('finance_categories')->where('code', 'gift_card_sale')->value('id');
        if ($category) {
            DB::table('money_movements')->where('category_id', $category)->update(['category_id' => $sale]);
            DB::table('finance_categories')->where('id', $category)->delete();
        }
    }
};
