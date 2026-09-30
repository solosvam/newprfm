<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class RolePermissionsTest extends TestCase
{
    private User $admin;
    private Role $adminRole;
    private Role $courier;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'cache.default' => 'array']);
        DB::purge('sqlite');
        Schema::create('users', fn (Blueprint $t) => [$t->id(), $t->string('name'), $t->string('surname')->nullable(), $t->string('email')->nullable(), $t->string('password')->nullable(), $t->boolean('active')->default(true), $t->timestamps()]);
        (require base_path('vendor/spatie/laravel-permission/database/migrations/create_permission_tables.php.stub'))->up();
        Schema::table('permissions', fn (Blueprint $t) => $t->string('description')->nullable());
        Schema::table('roles', fn (Blueprint $t) => $t->string('description')->nullable());
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (['role.list' => 'Rollar səhifəsi', 'permission.list' => 'İcazələr', 'site.banners' => 'Sayt - Bannerlər', 'crm' => 'CRM', 'finance' => 'Kassa', 'zzz.custom' => 'Xüsusi'] as $name => $d) {
            Permission::create(['name' => $name, 'guard_name' => 'admin', 'description' => $d]);
        }
        $this->adminRole = Role::create(['name' => 'Admin', 'guard_name' => 'admin']);
        $this->adminRole->givePermissionTo(['role.list', 'permission.list']);
        $this->courier = Role::create(['name' => 'Kuryer', 'guard_name' => 'admin']);
        $this->admin = User::forceCreate(['name' => 'Rufat']);
        $this->admin->assignRole('Admin');
    }

    public function test_page_groups_permissions(): void
    {
        $html = $this->actingAs($this->admin, 'admin')->get(route('admin.role.permissions', $this->courier->id))->assertOk()->getContent();
        foreach (['İdarəetmə', 'Satış və müştərilər', 'Maliyyə və tərəfdaşlar', 'Sayt', 'Digər', 'Sayt - Bannerlər', 'zzz.custom'] as $text) {
            $this->assertStringContainsString($text, $html);
        }
        $this->assertLessThan(strpos($html, 'Sayt - Bannerlər'), strpos($html, 'İdarəetmə')); // bölmə sırası
        $this->assertStringContainsString('<b data-rp-count>0</b> / 6', $html);
    }

    public function test_toggle_single_and_bulk(): void
    {
        $ids = Permission::whereIn('name', ['crm', 'finance'])->pluck('id')->all();
        $url = route('admin.role.permissions.toggle', $this->courier->id);
        $this->actingAs($this->admin, 'admin')->postJson($url, ['permission_ids' => $ids, 'checked' => true])
            ->assertOk()->assertJsonCount(2, 'granted');
        $this->assertTrue($this->courier->fresh()->hasPermissionTo('finance'));
        $this->postJson($url, ['permission_ids' => [$ids[0]], 'checked' => false])->assertOk()->assertJsonCount(1, 'granted');
        $this->assertFalse($this->courier->fresh()->hasPermissionTo('crm'));
    }

    public function test_cannot_remove_role_list_from_own_role(): void
    {
        $id = Permission::where('name', 'role.list')->value('id');
        $this->actingAs($this->admin, 'admin')->postJson(route('admin.role.permissions.toggle', $this->adminRole->id), ['permission_ids' => [$id], 'checked' => false])
            ->assertStatus(422);
        $this->assertTrue($this->adminRole->fresh()->hasPermissionTo('role.list'));
    }

    public function test_user_without_role_list_cannot_change_permissions(): void
    {
        $courierUser = User::forceCreate(['name' => 'Kuryer']);
        $courierUser->assignRole('Kuryer');
        $finance = Permission::where('name', 'finance')->value('id');
        $this->actingAs($courierUser, 'admin')->postJson(route('admin.role.permissions.toggle', $this->courier->id), ['permission_ids' => [$finance], 'checked' => true])->assertForbidden();
        // Köhnə AJAX route da artıq qorunur (əvvəl istənilən admin özünə icazə verə bilərdi)
        $this->postJson(route('admin.ajax.set-role-permission'), ['role_id' => $this->courier->id, 'perm_id' => $finance, 'checked' => 'true'])->assertForbidden();
        $this->assertFalse($this->courier->fresh()->hasPermissionTo('finance'));
    }

    public function test_permission_list_and_create_with_admin_guard(): void
    {
        $this->actingAs($this->admin, 'admin')->get(route('admin.permission.list'))->assertOk()
            ->assertSee('zzz.custom')->assertSee('Rollar səhifəsi')->assertSee('heç bir rolda yoxdur')
            ->assertSeeInOrder(['permission.list', 'role.list', 'crm', 'finance', 'site.banners', 'zzz.custom']); // bölmə sırası
        $this->post(route('admin.permission.add'), ['name' => 'site.faq', 'description' => 'Sayt - FAQ'])->assertRedirect();
        $this->assertSame('admin', Permission::where('name', 'site.faq')->value('guard_name'));
        $this->from(route('admin.permission.list'))->post(route('admin.permission.add'), ['name' => 'Site FAQ!', 'description' => 'x'])
            ->assertSessionHasErrorsIn('create', ['name', 'description']);
    }
}
