<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ProductPosterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'cache.default' => 'array']);
        DB::purge('sqlite');
        (require base_path('vendor/spatie/laravel-permission/database/migrations/create_permission_tables.php.stub'))->up();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::create(['name' => 'products.menu', 'guard_name' => 'admin']);
        Schema::create('products', fn (Blueprint $t) => [$t->id(), $t->string('name'), $t->unsignedBigInteger('brand_id'), $t->unsignedBigInteger('type_id')]);
        Schema::create('brands', fn (Blueprint $t) => [$t->id(), $t->string('name')]);
        foreach (['types', 'sizes', 'genders'] as $table) {
            Schema::create($table, fn (Blueprint $t) => [$t->id(), $t->string('name_az')]);
        }
        Schema::create('product_genders', fn (Blueprint $t) => [$t->unsignedBigInteger('product_id'), $t->unsignedBigInteger('gender_id')]);
        Schema::create('product_images', fn (Blueprint $t) => [$t->id(), $t->unsignedBigInteger('product_id'), $t->string('image'), $t->integer('sort_order')]);
        (require database_path('migrations/2026_10_07_170000_create_product_discounts_table.php'))->up(); // məhsul endirimi (posterdə)
        Schema::create('product_variants', fn (Blueprint $t) => [$t->id(), $t->unsignedBigInteger('product_id'), $t->unsignedBigInteger('size_id'), $t->decimal('price', 10, 2), $t->boolean('active')]);
        DB::table('brands')->insert(['id' => 1, 'name' => 'Burberry']);
        DB::table('types')->insert(['id' => 1, 'name_az' => 'Eau de Parfum']);
        DB::table('genders')->insert(['id' => 1, 'name_az' => 'Qadın üçün']);
        DB::table('products')->insert(['id' => 1, 'name' => 'Her Intense 2024', 'brand_id' => 1, 'type_id' => 1]);
        DB::table('product_genders')->insert(['product_id' => 1, 'gender_id' => 1]);
        DB::table('product_images')->insert([
            ['id' => 1, 'product_id' => 1, 'image' => 'second.webp', 'sort_order' => 2],
            ['id' => 2, 'product_id' => 1, 'image' => 'cover.webp', 'sort_order' => 1],
        ]);
        foreach ([['30 ml', 194, true], ['50 ml', 233, true], ['100 ml', 299, true], ['10 ml', 50, false]] as $i => [$size, $price, $active]) {
            DB::table('sizes')->insert(['id' => $i + 1, 'name_az' => $size]);
            DB::table('product_variants')->insert(['product_id' => 1, 'size_id' => $i + 1, 'price' => $price, 'active' => $active]);
        }
    }

    private function admin(bool $allowed = true): User
    {
        $user = (new User)->forceFill(['id' => 10, 'name' => 'Operator']);
        $user->setRelation('permissions', $allowed ? Permission::all() : collect());
        $user->setRelation('roles', collect());

        return $user;
    }

    public function test_requires_admin_login(): void
    {
        $this->getJson(route('admin.product.poster', 1))->assertUnauthorized();
    }

    public function test_requires_product_permission(): void
    {
        $this->actingAs($this->admin(false), 'admin')->getJson(route('admin.product.poster', 1))->assertForbidden();
    }

    public function test_returns_cover_and_all_active_prices(): void
    {
        $this->actingAs($this->admin(), 'admin')->getJson(route('admin.product.poster', 1))->assertOk()
            ->assertJsonPath('brand', 'Burberry')->assertJsonPath('name', 'Her Intense 2024')
            ->assertJsonPath('subtitle', 'Qadın üçün | Eau de Parfum')
            ->assertJsonPath('image', asset('frontend/uploads/products/cover.webp'))
            ->assertJsonCount(3, 'variants')->assertJsonPath('variants.0.size', '30 ml')
            ->assertJsonPath('variants.0.price', '194.00')->assertJsonPath('variants.2.price', '299.00');
        DB::table('product_variants')->where('size_id', 1)->update(['price' => 199.50]);
        $this->getJson(route('admin.product.poster', 1))->assertJsonPath('variants.0.price', '199.50');
    }

    public function test_caption_lists_every_active_size_with_price(): void
    {
        DB::table('product_variants')->where('size_id', 2)->update(['price' => 233.50]);

        $this->actingAs($this->admin(), 'admin')->getJson(route('admin.product.poster', 1))->assertOk()
            ->assertJsonPath('caption', "Burberry Her Intense 2024\nQadın üçün | Eau de Parfum\n30 ml — 194 ₼\n50 ml — 233.50 ₼\n100 ml — 299 ₼");
    }

    public function test_missing_image_or_active_sizes_cannot_create_poster(): void
    {
        $this->actingAs($this->admin(), 'admin');
        DB::table('product_variants')->update(['active' => false]);
        $this->getJson(route('admin.product.poster', 1))->assertUnprocessable();
        DB::table('product_variants')->update(['active' => true]);
        DB::table('product_images')->delete();
        $this->getJson(route('admin.product.poster', 1))->assertUnprocessable();
    }

    public function test_unknown_product_returns_not_found(): void
    {
        $this->actingAs($this->admin(), 'admin')->getJson(route('admin.product.poster', 999))->assertNotFound();
    }

    public function test_discount_shows_old_and_new_prices_and_end_date(): void
    {
        \App\Models\Product\ProductDiscount::create(['product_id' => 1, 'percent' => 10,
            'starts_at' => now()->subHour(), 'ends_at' => now()->setDate(2030, 1, 5)->setTime(18, 0)]);
        $data = app(\App\Services\ProductPosterData::class)->for(\App\Models\Product\Product::find(1));

        $this->assertSame(['percent' => '10', 'until' => 'Endirim 05.01.2030, 18:00-dək'], $data['discount']);
        $first = $data['variants']->first();
        $this->assertSame((float) $first['regular_price'] * 0.9, (float) $first['price']);
        $this->assertStringContainsString('Endirim 05.01.2030-dək', $data['caption']);
        $this->assertStringContainsString('~', $data['caption']);
    }
}

