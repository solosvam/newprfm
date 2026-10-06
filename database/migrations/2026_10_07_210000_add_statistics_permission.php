<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/** Admin → Statistika (dövriyyə, mənfəət, satış analitikası) — "Maliyyə" və "Admin" menyusu olan rollara verilir */
return new class extends Migration
{
    public function up(): void
    {
        $permission = Permission::findOrCreate('statistics', 'admin');
        $permission->update(['description' => 'Statistika']);
        $roles = DB::table('role_has_permissions')
            ->join('permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
            ->whereIn('permissions.name', ['finance', 'admin.menu'])->pluck('role_has_permissions.role_id')->unique();
        foreach ($roles as $roleId) {
            DB::table('role_has_permissions')->insertOrIgnore(['permission_id' => $permission->id, 'role_id' => $roleId]);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::where('name', 'statistics')->where('guard_name', 'admin')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
