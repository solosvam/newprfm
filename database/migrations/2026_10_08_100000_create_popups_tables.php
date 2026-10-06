<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Admin → Sayt → Popup-lar.
 *  - popups: məzmun (3 dil), mövqe (mobil/desktop ayrıca), auditoriya, səhifə, dövr, gecikmə, tezlik;
 *  - popup_dismissals: daxil olmuş müştərinin "Bir daha göstərmə" seçimi (cihazlar arası; qonaqda — localStorage);
 *  - popup_stats: gün üzrə sayğaclar (göstərilmə / klik / bağlama / bir daha göstərmə) — adbaad log yoxdur.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('popups', function (Blueprint $table) {
            $table->id();
            $table->string('name');                                   // admin üçün ad
            foreach (['az', 'en', 'ru'] as $locale) {
                $table->string('title_'.$locale)->nullable();
                $table->text('body_'.$locale)->nullable();
                $table->string('button_'.$locale, 60)->nullable();
            }
            $table->string('image')->nullable();
            $table->string('link_url', 2048)->nullable();
            $table->string('position_desktop', 10)->default('center');   // center | corner | bar
            $table->string('position_mobile', 10)->default('bar');
            $table->string('audience', 10)->default('all');              // all | guests | customers
            $table->string('pages', 100)->default('all');                // all və ya vergüllə: home,cart,checkout,success
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->unsignedSmallInteger('delay_seconds')->default(3);
            $table->string('frequency', 10)->default('once');            // once | session | daily
            $table->boolean('active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('popup_dismissals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('popup_id')->constrained()->cascadeOnDelete();
            $table->integer('customer_id');
            $table->timestamp('created_at')->nullable();
            $table->unique(['popup_id', 'customer_id']);
            $table->index('customer_id');
        });

        Schema::create('popup_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('popup_id')->constrained()->cascadeOnDelete();
            $table->date('day');
            $table->unsignedInteger('shown')->default(0);
            $table->unsignedInteger('clicked')->default(0);
            $table->unsignedInteger('closed')->default(0);
            $table->unsignedInteger('dismissed')->default(0);
            $table->unique(['popup_id', 'day']);
        });

        // icazə: "Bannerlər" icazəsi olan rollara da verilir
        if (Schema::hasTable('permissions')) {
            $permission = Permission::findOrCreate('site.popups', 'admin');
            $permission->update(['description' => 'Popup-lar']);
            $roles = DB::table('role_has_permissions')
                ->join('permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
                ->where('permissions.name', 'site.banners')->pluck('role_has_permissions.role_id');
            foreach ($roles as $roleId) {
                DB::table('role_has_permissions')->insertOrIgnore(['permission_id' => $permission->id, 'role_id' => $roleId]);
            }
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('popup_stats');
        Schema::dropIfExists('popup_dismissals');
        Schema::dropIfExists('popups');
        Permission::where('name', 'site.popups')->where('guard_name', 'admin')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
