<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class TypeListCountTest extends TestCase
{
    public function test_type_list_shows_product_count_including_inactive(): void
    {
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'cache.default' => 'array']);
        DB::purge('sqlite');
        (require base_path('vendor/spatie/laravel-permission/database/migrations/create_permission_tables.php.stub'))->up();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::create(['name' => 'type.menu', 'guard_name' => 'admin']);
        Schema::create('types', fn (Blueprint $t) => [$t->id(), $t->string('name_az'), $t->string('name_en'), $t->string('name_ru')]);
        Schema::create('products', fn (Blueprint $t) => [$t->id(), $t->string('name'), $t->unsignedBigInteger('type_id'), $t->boolean('active')]);
        DB::table('types')->insert([
            ['id' => 1, 'name_az' => 'Parfum suyu', 'name_en' => 'Eau De Parfum', 'name_ru' => 'Парфюмерная вода'],
            ['id' => 2, 'name_az' => 'Digər', 'name_en' => 'Other', 'name_ru' => 'Другое'],
        ]);
        DB::table('products')->insert([
            ['name' => 'A', 'type_id' => 1, 'active' => 1],
            ['name' => 'B', 'type_id' => 1, 'active' => 0],
        ]);

        $user = (new User)->forceFill(['id' => 10, 'name' => 'Operator']);
        $user->setRelation('permissions', Permission::all());
        $user->setRelation('roles', collect());

        $this->actingAs($user, 'admin')->get(route('admin.type.list'))->assertOk()
            ->assertSee('Məhsul sayı')
            ->assertSeeInOrder(['Парфюмерная вода', '<td>2</td>', 'Другое', '<td>0</td>'], false);
    }
}
