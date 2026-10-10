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

class BrandUpdateTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'cache.default' => 'array']);
        DB::purge('sqlite');
        Schema::create('users', fn (Blueprint $t) => [$t->id(), $t->string('name'), $t->string('surname')->nullable(), $t->boolean('active')->default(true), $t->timestamps()]);
        Schema::create('brands', fn (Blueprint $t) => [$t->id(), $t->string('name'), $t->string('slug')->nullable(), $t->string('image')->nullable(), $t->boolean('active')->default(true)]);
        (require base_path('vendor/spatie/laravel-permission/database/migrations/create_permission_tables.php.stub'))->up();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::create(['name' => 'brands.menu', 'guard_name' => 'admin']);
        Role::create(['name' => 'Admin', 'guard_name' => 'admin'])->givePermissionTo('brands.menu');
    }

    /** Formada "id" sahəsi yoxdur — brend ünvandakı id-dən tapılmalıdır ("Brend ID tapılmadı" xətası) */
    public function test_brand_is_updated_by_the_id_in_the_url(): void
    {
        $admin = User::forceCreate(['name' => 'Rufat']);
        $admin->assignRole('Admin');
        DB::table('brands')->insert(['id' => 177, 'name' => 'Van Cleef &amp; Arpels', 'slug' => 'van-cleef-and-arpels', 'active' => 1]);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.brand.update', 177), ['name' => 'Van Cleef & Arpels', 'slug' => 'van-cleef-and-arpels', 'active' => 1])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.brand.list'));

        $this->assertSame('Van Cleef & Arpels', DB::table('brands')->where('id', 177)->value('name'));
    }
}
