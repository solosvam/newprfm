<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        Permission::findOrCreate('product.review', 'admin')
            ->update(['description' => 'Məhsul rəylərini təsdiqləmək və silmək']);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::findByName('product.review', 'admin')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
