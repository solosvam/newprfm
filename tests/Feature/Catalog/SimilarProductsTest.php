<?php

namespace Tests\Feature\Catalog;

use App\Services\ProductDetailService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SimilarProductsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'cache.default' => 'array']);
        DB::purge('sqlite');
        Schema::create('brands', fn (Blueprint $t) => [$t->id(), $t->string('name')]);
        Schema::create('types', fn (Blueprint $t) => [$t->id(), $t->string('name_az')]);
        Schema::create('genders', fn (Blueprint $t) => [$t->id(), $t->string('name_az')]);
        Schema::create('sizes', fn (Blueprint $t) => [$t->id(), $t->string('name_az')]);
        Schema::create('ingredients', fn (Blueprint $t) => [$t->id(), $t->string('name_az')]);
        Schema::create('products', fn (Blueprint $t) => [$t->id(), $t->string('name'), $t->string('slug'), $t->integer('brand_id')->nullable(), $t->integer('type_id')->nullable(), $t->boolean('active')]);
        Schema::create('product_genders', fn (Blueprint $t) => [$t->integer('product_id'), $t->integer('gender_id')]);
        Schema::create('product_ingredients', fn (Blueprint $t) => [$t->integer('product_id'), $t->integer('ingredient_id')]);
        Schema::create('product_variants', fn (Blueprint $t) => [$t->id(), $t->integer('product_id'), $t->integer('size_id')->nullable(), $t->decimal('price'), $t->boolean('active')]);
        Schema::create('product_images', fn (Blueprint $t) => [$t->id(), $t->integer('product_id'), $t->string('image'), $t->integer('sort_order')->default(0)]);
        Schema::create('product_reviews', fn (Blueprint $t) => [$t->id(), $t->integer('product_id'), $t->integer('customer_id')->nullable(), $t->boolean('active')->default(true), $t->timestamps()]);
        Schema::create('product_categories', fn (Blueprint $t) => [$t->integer('product_id'), $t->integer('category_id')]);
        (require database_path('migrations/2026_10_07_170000_create_product_discounts_table.php'))->up();

        DB::table('brands')->insert([['id' => 1, 'name' => 'Chanel'], ['id' => 2, 'name' => 'Dior']]);
        DB::table('types')->insert([['id' => 1, 'name_az' => 'EDP'], ['id' => 2, 'name_az' => 'EDT']]);
        DB::table('genders')->insert([['id' => 1, 'name_az' => 'Kişi üçün'], ['id' => 2, 'name_az' => 'Qadın üçün'], ['id' => 3, 'name_az' => 'Unisex'], ['id' => 8, 'name_az' => 'Qadın']]);
        DB::table('ingredients')->insert([['id' => 1, 'name_az' => 'Gül']]);
        // [id, ad, brend, növ, cins, qiymət, aktiv]
        foreach ([
            [1, 'Chance', 1, 1, 2, 300, 1],
            [2, 'Qadın EDP 310', 2, 1, 8, 310, 1],      // "Qadın" = "Qadın üçün"
            [3, 'Unisex EDP 280', 2, 1, 3, 280, 1],
            [4, 'Kişi EDP 300', 2, 1, 1, 300, 1],       // kişi — düşmür
            [5, 'Qadın EDP 500', 2, 1, 2, 500, 1],      // qiymət uzaq — 2-ci addımda düşmür
            [6, 'Qadın EDT 300', 2, 2, 2, 300, 1],      // növ başqa
            [7, 'Coco', 1, 2, 2, 900, 1],               // brend — 3-cü addım
            [8, 'Qadın notlu', 2, 2, 2, 1000, 1],       // ortaq not — 1-ci addım
            [9, 'Deaktiv', 2, 1, 2, 300, 0],
        ] as [$id, $name, $brand, $type, $gender, $price, $active]) {
            DB::table('products')->insert(['id' => $id, 'name' => $name, 'slug' => 'p'.$id, 'brand_id' => $brand, 'type_id' => $type, 'active' => $active]);
            DB::table('product_genders')->insert(['product_id' => $id, 'gender_id' => $gender]);
            DB::table('product_variants')->insert(['product_id' => $id, 'price' => $price, 'active' => 1]);
        }
        DB::table('product_ingredients')->insert([['product_id' => 1, 'ingredient_id' => 1], ['product_id' => 8, 'ingredient_id' => 1], ['product_id' => 4, 'ingredient_id' => 1]]);
    }

    public function test_notes_then_type_gender_and_price_then_brand(): void
    {
        $service = app(ProductDetailService::class);
        $names = $service->similarProducts($service->findBySlug('p1'), 5)->pluck('name')->all();

        $this->assertSame(['Qadın notlu', 'Qadın EDP 310', 'Unisex EDP 280', 'Coco'], $names);
    }

    public function test_product_without_notes_still_gets_similar_products(): void
    {
        DB::table('product_ingredients')->delete();
        $service = app(ProductDetailService::class);

        $this->assertSame(['Qadın EDP 310', 'Unisex EDP 280'], $service->similarProducts($service->findBySlug('p1'), 2)->pluck('name')->all());
    }
}
