<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Köhnə "Stok → Alışlar / Hədiyyə kartları" (barkod aralığı ilə stoka giriş, 2026_11_02_120000) ləğv olunub — hədiyyə kartları üçün başqa plan var.
 * Cədvəllər yalnız boşdursa silinir (içində yazı varsa saxlanır, heç nə itmir); "stock" icazəsi silinir.
 */
return new class extends Migration
{
    private const TABLES = ['gift_card_events', 'gift_cards', 'stock_purchase_items', 'stock_purchases'];

    public function up(): void
    {
        $empty = collect(self::TABLES)->every(fn ($t) => !Schema::hasTable($t) || !DB::table($t)->exists());
        if ($empty) {
            foreach (self::TABLES as $table) {
                Schema::dropIfExists($table);
            }
        }
        if (Schema::hasTable('permissions')) {
            $id = DB::table('permissions')->where('name', 'stock')->where('guard_name', 'admin')->value('id');
            if ($id) {
                if (Schema::hasTable('role_has_permissions')) DB::table('role_has_permissions')->where('permission_id', $id)->delete();
                if (Schema::hasTable('model_has_permissions')) DB::table('model_has_permissions')->where('permission_id', $id)->delete();
                DB::table('permissions')->where('id', $id)->delete();
            }
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }

    public function down(): void
    {
        // Geri qaytarılmır: bölmənin kodu silinib
    }
};
