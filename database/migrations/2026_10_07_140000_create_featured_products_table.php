<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Ana səhifənin vitrini: admin-in seçdiyi ətirlər (ən çox 12) "Populyar" sıralamasında birinci səhifədə, seçilən ardıcıllıqla.
 * İcazə site.featured — Bannerlər icazəsi (site.banners) olan rollara da verilir.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('featured_products', function (Blueprint $table) {
            $table->id();
            $table->integer('product_id')->unique(); // products.id — signed INT (köhnə sxem)
            $table->unsignedSmallInteger('position');
            $table->timestamps();

            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->index('position');
        });

        $permission = Permission::findOrCreate('site.featured', 'admin');
        $permission->update(['description' => 'Ana səhifənin vitrini (Populyar ətirlər)']);
        $roles = DB::table('role_has_permissions')
            ->join('permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
            ->where('permissions.name', 'site.banners')->pluck('role_has_permissions.role_id');
        foreach ($roles as $roleId) {
            DB::table('role_has_permissions')->insertOrIgnore(['permission_id' => $permission->id, 'role_id' => $roleId]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('featured_products');
        Permission::where('name', 'site.featured')->where('guard_name', 'admin')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
