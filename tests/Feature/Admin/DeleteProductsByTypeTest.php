<?php

namespace Tests\Feature\Admin;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DeleteProductsByTypeTest extends TestCase
{
    private array $files = [];

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        Schema::create('types', fn (Blueprint $t) => [$t->id(), $t->string('name_az')]);
        Schema::create('products', fn (Blueprint $t) => [$t->id(), $t->unsignedBigInteger('old_id')->nullable(), $t->string('name'), $t->unsignedBigInteger('type_id')]);
        Schema::create('product_images', fn (Blueprint $t) => [$t->id(), $t->unsignedBigInteger('product_id'), $t->string('image'), $t->integer('sort_order')->nullable()]);
        Schema::create('product_variants', fn (Blueprint $t) => [$t->id(), $t->unsignedBigInteger('product_id'), $t->unsignedBigInteger('size_id'), $t->decimal('price', 10, 2), $t->boolean('active')]);
        foreach (['categories' => 'category_id', 'genders' => 'gender_id', 'ingredients' => 'ingredient_id'] as $table => $key) {
            Schema::create('product_'.$table, fn (Blueprint $t) => [$t->unsignedBigInteger('product_id'), $t->unsignedBigInteger($key)]);
        }
        Schema::create('product_reviews', fn (Blueprint $t) => [$t->id(), $t->unsignedBigInteger('product_id')]);
        foreach (['product_favorites', 'product_discounts', 'featured_products', 'product_search_clicks'] as $table) {
            Schema::create($table, fn (Blueprint $t) => [$t->id(), $t->unsignedBigInteger('product_id')]);
        }
        foreach (['price_alerts', 'customer_cart_items', 'credit_applications'] as $table) {
            Schema::create($table, fn (Blueprint $t) => [$t->id(), $t->unsignedBigInteger('product_variant_id')]);
        }
        Schema::create('order_items', fn (Blueprint $t) => [$t->id(), $t->unsignedBigInteger('product_id'), $t->unsignedBigInteger('product_variant_id')]);

        DB::table('types')->insert([['id' => 26, 'name_az' => 'X'], ['id' => 84, 'name_az' => 'Y'], ['id' => 85, 'name_az' => 'Z']]);
        DB::table('products')->insert([
            ['id' => 1, 'old_id' => 10, 'name' => 'Silinəcək', 'type_id' => 26],
            ['id' => 2, 'old_id' => 11, 'name' => 'Sifarişdə', 'type_id' => 84],
            ['id' => 3, 'old_id' => 12, 'name' => 'Aralıqdan kənar', 'type_id' => 85],
        ]);
        foreach ([1, 2, 3] as $id) {
            $name = 'zz-delete-test-'.$id.'.webp';
            File::put(public_path('frontend/uploads/products/'.$name), 'x');
            $this->files[] = public_path('frontend/uploads/products/'.$name);
            DB::table('product_images')->insert(['product_id' => $id, 'image' => $name]);
            DB::table('product_variants')->insert(['id' => $id * 10, 'product_id' => $id, 'size_id' => 1, 'price' => 1, 'active' => 1]);
            DB::table('product_categories')->insert(['product_id' => $id, 'category_id' => 1]);
            DB::table('product_reviews')->insert(['product_id' => $id]);
            DB::table('featured_products')->insert(['product_id' => $id]);
            DB::table('customer_cart_items')->insert(['product_variant_id' => $id * 10]);
        }
        DB::table('order_items')->insert(['product_id' => 2, 'product_variant_id' => 20]);
    }

    protected function tearDown(): void
    {
        File::delete($this->files);
        parent::tearDown();
    }

    public function test_dry_run_deletes_nothing(): void
    {
        $this->artisan('parfumshop:delete-products-by-type 26 84')
            ->expectsOutputToContain('type_id 26–84: 2 məhsul, 2 şəkil')
            ->expectsOutputToContain('Silinməyəcək: #2 Sifarişdə (old_id 11) — sifarişdə var')
            ->expectsOutputToContain('Silinəcək: 1, silinməyəcək: 1')
            ->assertSuccessful();

        $this->assertSame(3, DB::table('products')->count());
        $this->assertFileExists($this->files[0]);
    }

    public function test_apply_deletes_product_related_rows_and_files(): void
    {
        $this->artisan('parfumshop:delete-products-by-type 26 84 --apply')
            ->expectsOutputToContain('Silindi: 1 məhsul, 1 şəkil faylı.')
            ->assertSuccessful();

        $this->assertSame([2, 3], DB::table('products')->orderBy('id')->pluck('id')->all());
        foreach (['product_images', 'product_variants', 'product_categories', 'product_reviews', 'featured_products'] as $table) {
            $this->assertSame([2, 3], DB::table($table)->orderBy('product_id')->pluck('product_id')->all(), $table);
        }
        $this->assertSame([20, 30], DB::table('customer_cart_items')->orderBy('product_variant_id')->pluck('product_variant_id')->all());
        $this->assertFileDoesNotExist($this->files[0]);
        $this->assertFileExists($this->files[1]);
        $this->assertFileExists($this->files[2]);
    }
}
