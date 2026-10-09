<?php

namespace Tests\Feature\Admin;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DeleteProductsBySizeTest extends TestCase
{
    private string $file;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        Schema::create('sizes', fn (Blueprint $t) => [$t->id(), $t->string('name_az'), $t->string('name_en')->nullable(), $t->string('name_ru')->nullable()]);
        Schema::create('products', fn (Blueprint $t) => [$t->id(), $t->unsignedBigInteger('old_id')->nullable(), $t->string('name'), $t->unsignedBigInteger('type_id')]);
        Schema::create('product_images', fn (Blueprint $t) => [$t->id(), $t->unsignedBigInteger('product_id'), $t->string('image'), $t->integer('sort_order')->nullable()]);
        Schema::create('product_variants', fn (Blueprint $t) => [$t->id(), $t->unsignedBigInteger('product_id'), $t->unsignedBigInteger('size_id'), $t->decimal('price', 10, 2), $t->boolean('active')]);
        foreach (['categories' => 'category_id', 'genders' => 'gender_id', 'ingredients' => 'ingredient_id'] as $table => $key) {
            Schema::create('product_'.$table, fn (Blueprint $t) => [$t->unsignedBigInteger('product_id'), $t->unsignedBigInteger($key)]);
        }
        Schema::create('product_reviews', fn (Blueprint $t) => [$t->id(), $t->unsignedBigInteger('product_id')]);
        Schema::create('order_items', fn (Blueprint $t) => [$t->id(), $t->unsignedBigInteger('product_id'), $t->unsignedBigInteger('product_variant_id')]);

        DB::table('sizes')->insert([['id' => 1, 'name_az' => 'delete'], ['id' => 2, 'name_az' => 'delete'], ['id' => 3, 'name_az' => '50 ml']]);
        DB::table('products')->insert([
            ['id' => 1, 'name' => 'Silinəcək', 'type_id' => 1],
            ['id' => 2, 'name' => 'Sifarişdə', 'type_id' => 1],
            ['id' => 3, 'name' => 'Normal', 'type_id' => 1],
        ]);
        DB::table('product_variants')->insert([
            ['id' => 10, 'product_id' => 1, 'size_id' => 1, 'price' => 1, 'active' => 1],
            ['id' => 20, 'product_id' => 2, 'size_id' => 2, 'price' => 1, 'active' => 1],
            ['id' => 30, 'product_id' => 3, 'size_id' => 3, 'price' => 1, 'active' => 1],
        ]);
        DB::table('order_items')->insert(['product_id' => 2, 'product_variant_id' => 20]);
        $this->file = public_path('frontend/uploads/products/zz-delete-size-test.webp');
        File::put($this->file, 'x');
        DB::table('product_images')->insert(['product_id' => 1, 'image' => 'zz-delete-size-test.webp']);
    }

    protected function tearDown(): void
    {
        File::delete($this->file);
        parent::tearDown();
    }

    public function test_dry_run_and_apply(): void
    {
        $this->artisan('parfumshop:delete-products-by-size')
            ->expectsOutputToContain("name_az = 'delete': 2 ölçü, 2 məhsul, 1 şəkil")
            ->expectsOutputToContain('Silinməyəcək: #2 Sifarişdə')
            ->assertSuccessful();
        $this->assertSame(3, DB::table('products')->count());

        $this->artisan('parfumshop:delete-products-by-size --apply')
            ->expectsOutputToContain("Silindi: 1 məhsul, 1 şəkil faylı, 1 boş 'delete' ölçüsü.")
            ->assertSuccessful();

        $this->assertSame([2, 3], DB::table('products')->orderBy('id')->pluck('id')->map(fn ($v) => (int) $v)->all());
        $this->assertSame([2, 3], DB::table('sizes')->orderBy('id')->pluck('id')->map(fn ($v) => (int) $v)->all());
        $this->assertFileDoesNotExist($this->file);
    }
}
