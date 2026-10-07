<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Məlumat səhifələri (Marketinq → Məlumat səhifələri): çatdırılma və ödəniş, qaytarma, haqqımızda, əlaqə,
 * istifadə şərtləri, məxfilik siyasəti. Ünvanlar sabitdir (App\Models\Page::PAGES), mətn admindən 3 dildə.
 * İlkin mətnlər — database/data/pages.php.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->string('key', 30)->unique();
            foreach (['az', 'en', 'ru'] as $locale) {
                $table->string('title_'.$locale)->nullable();
                $table->longText('body_'.$locale)->nullable();
                $table->string('meta_description_'.$locale, 300)->nullable();
            }
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });

        $now = now();
        foreach (require database_path('data/pages.php') as $key => $page) {
            $row = ['key' => $key, 'created_at' => $now, 'updated_at' => $now];
            foreach (['az', 'en', 'ru'] as $locale) {
                $row['title_'.$locale] = $page['title'][$locale];
                $row['body_'.$locale] = $page['body'][$locale];
                $row['meta_description_'.$locale] = $page['meta'][$locale];
            }
            DB::table('pages')->insert($row);
        }

        if (Schema::hasTable('permissions')) {
            $permission = Permission::findOrCreate('site.pages', 'admin');
            $permission->update(['description' => 'Məlumat səhifələri']);
            $roles = DB::table('role_has_permissions')
                ->join('permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
                ->where('permissions.name', 'site.faq')->pluck('role_has_permissions.role_id');
            foreach ($roles as $roleId) {
                DB::table('role_has_permissions')->insertOrIgnore(['permission_id' => $permission->id, 'role_id' => $roleId]);
            }
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pages');
        if (Schema::hasTable('permissions')) {
            Permission::where('name', 'site.pages')->where('guard_name', 'admin')->delete();
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }
};
