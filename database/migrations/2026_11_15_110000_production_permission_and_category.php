<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** İstehsal: "Xammal" xərc kateqoriyası (supplier_payment) və "production" icazəsi (Super admin-ə verilir) */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('finance_categories')) {
            DB::table('finance_categories')->updateOrInsert(['code' => 'raw_material'],
                ['name' => 'Xammal', 'type' => 'expense', 'system' => true, 'created_at' => now(), 'updated_at' => now()]);
        }
        if (Schema::hasTable('permissions')) {
            DB::table('permissions')->updateOrInsert(['name' => 'production', 'guard_name' => 'admin'],
                (Schema::hasColumn('permissions', 'description') ? ['description' => 'İstehsal: xammal, alışlar, tədarükçülər, partiyalar'] : [])
                + ['created_at' => now(), 'updated_at' => now()]);
            // Super admin dərhal görsün; digər rollara Rollar səhifəsindən verilir
            $permission = DB::table('permissions')->where('name', 'production')->where('guard_name', 'admin')->value('id');
            $role = Schema::hasTable('roles') ? DB::table('roles')->where('name', 'Super admin')->where('guard_name', 'admin')->value('id') : null;
            if ($permission && $role && Schema::hasTable('role_has_permissions')) {
                DB::table('role_has_permissions')->insertOrIgnore(['permission_id' => $permission, 'role_id' => $role]);
            }
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }

    public function down(): void
    {
        DB::table('permissions')->where('name', 'production')->where('guard_name', 'admin')->delete();
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
