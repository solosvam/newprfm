<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Admin menyusu yenidən qurulandan sonra (config/admin_menu.php) qrup icazələri heç nəyi idarə etmir —
 * hər bənd öz icazəsi ilə görünür. Rollardan və icazə siyahısından silinir.
 */
return new class extends Migration
{
    private const NAMES = ['admin.menu', 'site.menu', 'role.perm.menu'];

    public function up(): void
    {
        // Permission::delete() rol əlaqələrini (role_has_permissions, model_has_permissions) da silir
        Permission::whereIn('name', self::NAMES)->where('guard_name', 'admin')->get()->each->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        foreach (self::NAMES as $name) {
            Permission::findOrCreate($name, 'admin');
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
