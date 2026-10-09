<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class IngredientCategoryListCountTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'cache.default' => 'array']);
        DB::purge('sqlite');
        (require base_path('vendor/spatie/laravel-permission/database/migrations/create_permission_tables.php.stub'))->up();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::create(['name' => 'ingredient.menu', 'guard_name' => 'admin']);
        Permission::create(['name' => 'category.menu', 'guard_name' => 'admin']);
        Schema::create('products', fn (Blueprint $t) => [$t->id(), $t->string('name'), $t->boolean('active')]);
        Schema::create('ingredients', fn (Blueprint $t) => [$t->id(), $t->string('name_az'), $t->string('name_en'), $t->string('name_ru')]);
        Schema::create('categories', fn (Blueprint $t) => [$t->id(), $t->string('name_az'), $t->string('name_en'), $t->string('name_ru'), $t->string('slug')->nullable(), $t->boolean('active')]);
        Schema::create('product_ingredients', fn (Blueprint $t) => [$t->unsignedBigInteger('product_id'), $t->unsignedBigInteger('ingredient_id')]);
        Schema::create('product_categories', fn (Blueprint $t) => [$t->unsignedBigInteger('product_id'), $t->unsignedBigInteger('category_id')]);
        DB::table('products')->insert([['id' => 1, 'name' => 'A', 'active' => 1], ['id' => 2, 'name' => 'B', 'active' => 0]]);
        DB::table('ingredients')->insert([['id' => 1, 'name_az' => 'Bergamot', 'name_en' => 'Bergamot', 'name_ru' => 'Бергамот'], ['id' => 2, 'name_az' => 'Musk', 'name_en' => 'Musk', 'name_ru' => 'Мускус']]);
        DB::table('categories')->insert([['id' => 1, 'name_az' => 'Qadın', 'name_en' => 'Women', 'name_ru' => 'Женский', 'active' => 1], ['id' => 2, 'name_az' => 'Yeni', 'name_en' => 'New', 'name_ru' => 'Новинки', 'active' => 1]]);
        DB::table('product_ingredients')->insert([['product_id' => 1, 'ingredient_id' => 1], ['product_id' => 2, 'ingredient_id' => 1]]);
        DB::table('product_categories')->insert([['product_id' => 1, 'category_id' => 1], ['product_id' => 2, 'category_id' => 1]]);

        $user = (new User)->forceFill(['id' => 10, 'name' => 'Operator']);
        $user->setRelation('permissions', Permission::all());
        $user->setRelation('roles', collect());
        $this->actingAs($user, 'admin');
    }

    public function test_ingredient_list_shows_product_count(): void
    {
        $this->get(route('admin.ingredient.list'))->assertOk()->assertSeeInOrder(['Aktiv məhsul', 'Deaktiv məhsul'])
            ->assertSeeInOrder(['Бергамот', '<td>1</td>', '<td>1</td>', 'Мускус', '<td>0</td>', '<td>0</td>'], false);
    }

    public function test_category_list_shows_product_count(): void
    {
        $this->get(route('admin.category.list'))->assertOk()->assertSeeInOrder(['Aktiv məhsul', 'Deaktiv məhsul'])
            ->assertSeeInOrder(['Женский', '<td>1</td>', '<td>1</td>', 'Новинки', '<td>0</td>', '<td>0</td>'], false);
    }
}
