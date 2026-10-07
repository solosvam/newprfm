<?php

namespace Tests\Feature;

use App\Services\OpenAiPerfumeService;
use App\Services\PerfumeAdvisorService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class AdvisorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'cache.default' => 'array']);
        DB::purge('sqlite');
        Schema::create('brands', fn (Blueprint $t) => [$t->id(), $t->string('name'), $t->string('slug'), $t->string('image')->nullable(), $t->boolean('active')->default(true)]);
        Schema::create('products', fn (Blueprint $t) => [$t->id(), $t->string('name'), $t->string('slug'), $t->integer('brand_id')->nullable(), $t->integer('type_id')->nullable(), $t->boolean('active')->default(true)]);
        Schema::create('product_variants', fn (Blueprint $t) => [$t->id(), $t->integer('product_id'), $t->integer('size_id')->nullable(), $t->decimal('price', 10, 2), $t->boolean('active')->default(true)]);
        Schema::create('product_genders', fn (Blueprint $t) => [$t->id(), $t->integer('product_id'), $t->integer('gender_id')]);
        Schema::create('genders', fn (Blueprint $t) => [$t->id(), $t->string('name_az'), $t->string('name_en')->nullable(), $t->string('name_ru')->nullable()]);
        Schema::create('order_items', fn (Blueprint $t) => [$t->id(), $t->integer('order_id'), $t->integer('product_id'), $t->integer('quantity')]);
        foreach (['product_images' => fn (Blueprint $t) => [$t->id(), $t->integer('product_id'), $t->string('image'), $t->integer('sort_order')->default(0)],
            'product_ingredients' => fn (Blueprint $t) => [$t->id(), $t->integer('product_id'), $t->integer('ingredient_id')],
            'ingredients' => fn (Blueprint $t) => [$t->id(), $t->string('name_az'), $t->string('name_en')->nullable(), $t->string('name_ru')->nullable()],
            'types' => fn (Blueprint $t) => [$t->id(), $t->string('name_az'), $t->string('name_en')->nullable(), $t->string('name_ru')->nullable()],
            'sizes' => fn (Blueprint $t) => [$t->id(), $t->string('name_az')],
        ] as $table => $def) {
            Schema::create($table, $def);
        }
        (require database_path('migrations/2026_10_07_170000_create_product_discounts_table.php'))->up();

        DB::table('genders')->insert([['id' => 1, 'name_az' => 'Kişi'], ['id' => 2, 'name_az' => 'Qadın'], ['id' => 3, 'name_az' => 'Unisex']]);
        DB::table('brands')->insert(['id' => 1, 'name' => 'Lancome', 'slug' => 'lancome']);
        DB::table('products')->insert([
            ['id' => 1, 'name' => 'La Vie Est Belle', 'slug' => 'la-vie', 'brand_id' => 1],
            ['id' => 2, 'name' => 'Sauvage', 'slug' => 'sauvage', 'brand_id' => 1],
            ['id' => 3, 'name' => 'Pahalı', 'slug' => 'pahali', 'brand_id' => 1],
        ]);
        DB::table('product_genders')->insert([['product_id' => 1, 'gender_id' => 2], ['product_id' => 2, 'gender_id' => 1], ['product_id' => 3, 'gender_id' => 2]]);
        DB::table('product_variants')->insert([['product_id' => 1, 'price' => 150], ['product_id' => 2, 'price' => 160], ['product_id' => 3, 'price' => 900]]);
    }

    private function answers(array $extra = []): array
    {
        return $extra + ['for' => 'women', 'occasion' => 'evening', 'season' => 'any', 'families' => ['floral'], 'strength' => 'any', 'budget' => 'b200', 'liked' => ''];
    }

    public function test_candidates_respect_gender_and_budget(): void
    {
        $ids = app(PerfumeAdvisorService::class)->candidates($this->answers())->pluck('id')->all();
        $this->assertSame([1], $ids);   // kişi ətri və büdcədən baha olan çıxır
    }

    public function test_recommend_returns_cards_with_reasons_and_ignores_unknown_ids(): void
    {
        $openAi = Mockery::mock(OpenAiPerfumeService::class);
        $openAi->shouldReceive('json')->once()->andReturn([
            'intro' => 'Çiçəkli ətirləri sevirsən.',
            'picks' => [['id' => 1, 'reason' => 'Romantik və şirin.'], ['id' => 999, 'reason' => 'uydurma']],
        ]);
        $this->app->instance(OpenAiPerfumeService::class, $openAi);

        $data = $this->postJson(route('front.advisor.recommend'), $this->answers())->assertOk()->json();
        $this->assertSame('Çiçəkli ətirləri sevirsən.', $data['intro']);
        $this->assertStringContainsString('La Vie Est Belle', $data['html']);
        $this->assertStringContainsString('Romantik və şirin.', $data['html']);
        $this->assertStringNotContainsString('uydurma', $data['html']);

        // eyni cavablar — keşdən (json yalnız bir dəfə çağırılır)
        $this->postJson(route('front.advisor.recommend'), $this->answers())->assertOk();
    }

    public function test_validation_and_ai_failure(): void
    {
        $this->postJson(route('front.advisor.recommend'), $this->answers(['budget' => 'cheap']))->assertUnprocessable();
        $this->postJson(route('front.advisor.recommend'), $this->answers(['families' => ['floral', 'fresh', 'woody', 'sweet']]))->assertUnprocessable();

        $openAi = Mockery::mock(OpenAiPerfumeService::class);
        $openAi->shouldReceive('json')->andThrow(new \RuntimeException('quota'));
        $this->app->instance(OpenAiPerfumeService::class, $openAi);
        $this->postJson(route('front.advisor.recommend'), $this->answers(['occasion' => 'daily']))
            ->assertStatus(503)->assertJson(['message' => __('advisor_error')]);
    }
}
