<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Baş kuryer: "Baş kuryer" rolu (kuryerdir — öz sifarişləri) + icazələr:
 *  - courier.team — bütün kuryerlərin balansı, haqq-hesabı və sifarişləri (arxiv daxil);
 *  - courier.collect — kuryerin balansındakı pulu öz hesabına köçürmək (money_movements.kind = courier_collect).
 * users.telegram_id — kuryerə Telegram bildirişi (sifariş təhkim olunanda / ötürüləndə, pulu köçürüləndə).
 */
return new class extends Migration
{
    private const PERMISSIONS = [
        'courier.team' => 'Kuryerlər: hamının balansı, haqq-hesabı və sifarişləri (baş kuryer)',
        'courier.collect' => 'Kuryerlər: kuryerin balansındakı pulu öz hesabına köçürmək (baş kuryer)',
    ];

    public function up(): void
    {
        if (!Schema::hasColumn('users', 'telegram_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('telegram_id', 32)->nullable()->after('mobile');
            });
        }

        foreach (self::PERMISSIONS as $name => $description) {
            DB::table('permissions')->updateOrInsert(
                ['name' => $name, 'guard_name' => 'admin'],
                ['description' => $description, 'created_at' => now(), 'updated_at' => now()]
            );
        }
        // Prodda roles.description məcburidir (testdəki spatie cədvəlində sütun yoxdur)
        $roleExtra = Schema::hasColumn('roles', 'description') ? ['description' => 'Kuryer; digər kuryerlərin balansını görür və pulunu özünə köçürür'] : [];
        DB::table('roles')->updateOrInsert(['name' => 'Baş kuryer', 'guard_name' => 'admin'], $roleExtra + ['created_at' => now(), 'updated_at' => now()]);
        $role = DB::table('roles')->where('name', 'Baş kuryer')->where('guard_name', 'admin')->value('id');
        foreach (DB::table('permissions')->whereIn('name', array_keys(self::PERMISSIONS))->where('guard_name', 'admin')->pluck('id') as $permission) {
            DB::table('role_has_permissions')->insertOrIgnore(['permission_id' => $permission, 'role_id' => $role]);
        }
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        DB::table('roles')->where('name', 'Baş kuryer')->where('guard_name', 'admin')->delete();
        DB::table('permissions')->whereIn('name', array_keys(self::PERMISSIONS))->where('guard_name', 'admin')->delete();
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('telegram_id'));
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
