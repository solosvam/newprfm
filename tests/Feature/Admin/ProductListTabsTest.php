<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ProductListTabsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'cache.default' => 'array']);
        DB::purge('sqlite');
        (require base_path('vendor/spatie/laravel-permission/database/migrations/create_permission_tables.php.stub'))->up();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::create(['name' => 'products.menu', 'guard_name' => 'admin']);
        Schema::create('products', fn (Blueprint $t) => [$t->id(), $t->string('name'), $t->unsignedBigInteger('brand_id'), $t->unsignedBigInteger('type_id'), $t->boolean('active')]);
        Schema::create('brands', fn (Blueprint $t) => [$t->id(), $t->string('name')]);
        Schema::create('types', fn (Blueprint $t) => [$t->id(), $t->string('name_az')]);
        Schema::create('categories', fn (Blueprint $t) => [$t->id()]);
        Schema::create('ingredients', fn (Blueprint $t) => [$t->id()]);
        Schema::create('product_categories', fn (Blueprint $t) => [$t->unsignedBigInteger('product_id'), $t->unsignedBigInteger('category_id')]);
        Schema::create('product_ingredients', fn (Blueprint $t) => [$t->unsignedBigInteger('product_id'), $t->unsignedBigInteger('ingredient_id')]);
        Schema::create('product_images', fn (Blueprint $t) => [$t->id(), $t->unsignedBigInteger('product_id'), $t->string('image'), $t->integer('sort_order')]);
        Schema::create('product_variants', fn (Blueprint $t) => [$t->id(), $t->unsignedBigInteger('product_id'), $t->unsignedBigInteger('size_id'), $t->decimal('price', 10, 2), $t->boolean('active')]);
        DB::table('brands')->insert(['id' => 1, 'name' => 'Dior']);
        DB::table('types')->insert(['id' => 1, 'name_az' => 'EDP']);
        DB::table('products')->insert([
            ['id' => 1, 'name' => 'Sauvage', 'brand_id' => 1, 'type_id' => 1, 'active' => 1],
            ['id' => 2, 'name' => 'Fahrenheit', 'brand_id' => 1, 'type_id' => 1, 'active' => 0],
            ['id' => 3, 'name' => 'Dune', 'brand_id' => 1, 'type_id' => 1, 'active' => 0],
        ]);
    }

    private function admin(): User
    {
        $user = (new User)->forceFill(['id' => 10, 'name' => 'Operator']);
        $user->setRelation('permissions', Permission::all());
        $user->setRelation('roles', collect());

        return $user;
    }

    public function test_list_data_is_filtered_by_tab(): void
    {
        $this->actingAs($this->admin(), 'admin');

        $this->getJson(route('admin.product.list.data'))->assertOk()
            ->assertJsonCount(1)->assertJsonPath('0.name', 'Sauvage');

        $this->getJson(route('admin.product.list.data', ['status' => 'inactive']))->assertOk()
            ->assertJsonCount(2)->assertJsonPath('0.name', 'Dune')->assertJsonPath('1.name', 'Fahrenheit');
    }

    public function test_list_page_shows_counts_and_selected_tab(): void
    {
        $this->actingAs($this->admin(), 'admin');

        $this->get(route('admin.product.list'))->assertOk()
            ->assertSee(route('admin.product.list.data', ['status' => 'active']), false)
            ->assertSeeInOrder(['Aktiv', '1', 'Deaktiv', '2']);

        $this->get(route('admin.product.list', ['status' => 'inactive']))->assertOk()
            ->assertSee(route('admin.product.list.data', ['status' => 'inactive']), false);
    }
}
