<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/** Məhsul endirimləri (yarat / bitir / sil, "Endirimdəki məhsullar") — "Məhsullar" icazəsi olan rollara da verilir */
return new class extends Migration
{
    public function up(): void
    {
        $permission = Permission::findOrCreate('product.discount', 'admin');
        $permission->update(['description' => 'Məhsul endirimləri']);
        $roles = DB::table('role_has_permissions')
            ->join('permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
            ->where('permissions.name', 'products.menu')->pluck('role_has_permissions.role_id');
        foreach ($roles as $roleId) {
            DB::table('role_has_permissions')->insertOrIgnore(['permission_id' => $permission->id, 'role_id' => $roleId]);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::where('name', 'product.discount')->where('guard_name', 'admin')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
