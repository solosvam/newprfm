<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration {
    public function up(): void
    {
        Permission::findOrCreate('promo.list', 'admin');
        Permission::findOrCreate('promo.manage', 'admin');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::where('guard_name','admin')->whereIn('name',['promo.list','promo.manage'])->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
