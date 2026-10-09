<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class SizeListCountTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'cache.default' => 'array']);
        DB::purge('sqlite');
        (require base_path('vendor/spatie/laravel-permission/database/migrations/create_permission_tables.php.stub'))->up();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::create(['name' => 'size.menu', 'guard_name' => 'admin']);
        Schema::create('sizes', fn (Blueprint $t) => [$t->id(), $t->string('name_az'), $t->string('name_en'), $t->string('name_ru')]);
        Schema::create('product_variants', fn (Blueprint $t) => [$t->id(), $t->unsignedBigInteger('product_id'), $t->unsignedBigInteger('size_id'), $t->decimal('price', 10, 2), $t->boolean('active')]);
        DB::table('sizes')->insert([
            ['id' => 1, 'name_az' => '50 ml', 'name_en' => '50 ml', 'name_ru' => '50 мл'],
            ['id' => 2, 'name_az' => 'L 75edp', 'name_en' => 'L 75edp', 'name_ru' => 'L 75edp'],
        ]);
        DB::table('product_variants')->insert([
            ['product_id' => 1, 'size_id' => 1, 'price' => 1, 'active' => 1],
            ['product_id' => 2, 'size_id' => 1, 'price' => 1, 'active' => 0], // deaktiv variant da sayılır
            ['product_id' => 2, 'size_id' => 1, 'price' => 1, 'active' => 1], // eyni məhsul bir dəfə sayılır
        ]);
    }

    public function test_size_list_shows_product_count_per_size(): void
    {
        $user = (new User)->forceFill(['id' => 10, 'name' => 'Operator']);
        $user->setRelation('permissions', Permission::all());
        $user->setRelation('roles', collect());

        $this->actingAs($user, 'admin')->get(route('admin.size.list'))->assertOk()
            ->assertSee('Məhsul sayı')
            ->assertSeeInOrder(['50 мл', '<td>2</td>', 'L 75edp', '<td>0</td>'], false);
    }
}
