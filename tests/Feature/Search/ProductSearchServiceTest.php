<?php

namespace Tests\Feature\Search;

use App\Models\Product\SearchAlias;
use App\Models\Product\Brand;
use App\Http\Controllers\Backend\Product\BrandsController;
use App\Services\Search\ProductSearchService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Lüğət əsaslı axtarış: brend/model sözün yerinə görə yox, lüğətə görə tanınır */
class ProductSearchServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'cache.default' => 'array']);
        DB::purge('sqlite');

        Schema::create('brands', fn (Blueprint $t) => [$t->integer('id')->primary(), $t->string('name'), $t->string('slug')->nullable(), $t->boolean('active')->default(true)]);
        Schema::create('types', fn (Blueprint $t) => [$t->id(), $t->string('name_az'), $t->string('name_en')->nullable(), $t->string('name_ru')->nullable()]);
        Schema::create('genders', fn (Blueprint $t) => [$t->id(), $t->string('name_az'), $t->string('name_en')->nullable(), $t->string('name_ru')->nullable()]);
        Schema::create('product_genders', fn (Blueprint $t) => [$t->integer('product_id'), $t->integer('gender_id')]);
        Schema::create('sizes', fn (Blueprint $t) => [$t->id(), $t->string('name_az'), $t->string('name_en')->nullable(), $t->string('name_ru')->nullable()]);
        Schema::create('products', fn (Blueprint $t) => [$t->integer('id')->primary(), $t->integer('brand_id'), $t->integer('type_id')->nullable(),
            $t->string('name'), $t->string('slug'), $t->boolean('active')->default(true), $t->timestamps()]);
        Schema::create('product_variants', fn (Blueprint $t) => [$t->id(), $t->integer('product_id'), $t->integer('size_id')->nullable(),
            $t->decimal('price', 10, 2), $t->boolean('active')->default(true)]);
        Schema::create('product_images', fn (Blueprint $t) => [$t->id(), $t->integer('product_id'), $t->string('image'), $t->integer('sort_order')->default(0)]);
        (require database_path('migrations/2026_09_30_160000_create_search_aliases_table.php'))->up();
        (require database_path('migrations/2026_10_01_190000_add_match_type_to_search_aliases.php'))->up(); // artıq sözlər
        (require database_path('migrations/2026_10_01_200000_make_safe_extra_words_stems.php'))->up();
        (require database_path('migrations/2026_10_02_100000_add_conjunction_extra_words.php'))->up();

        DB::table('brands')->insert([
            ['id' => 1, 'name' => 'Christian Dior'], ['id' => 2, 'name' => 'Creed'], ['id' => 3, 'name' => 'Tom Ford'],
            ['id' => 4, 'name' => 'Carolina Herrera'], ['id' => 5, 'name' => 'Parfums de Marly'],
        ]);
        DB::table('types')->insert(['id' => 1, 'name_az' => 'Parfum suyu']);
        DB::table('sizes')->insert(['id' => 1, 'name_az' => '100 ml']);
        $products = [
            [26, 1, 'Sauvage'], [42, 1, 'Sauvage'], [50, 1, 'Sauvage Parfum'], [71, 1, 'Sauvage Elixir'], [80, 1, 'Miss Dior'],
            [90, 2, 'Aventus'], [91, 3, 'Oud Wood'], [92, 4, '212 VIP'], [93, 5, 'Layton'],
        ];
        foreach ($products as [$id, $brand, $name]) {
            DB::table('products')->insert(['id' => $id, 'brand_id' => $brand, 'type_id' => 1, 'name' => $name, 'slug' => "p-$id"]);
            DB::table('product_variants')->insert(['product_id' => $id, 'size_id' => 1, 'price' => 100]);
        }

        foreach ([
            ['diyor', 'brand', 1, null], ['krid', 'brand', 2, null], ['kridd', 'brand', 2, null], ['kreed', 'brand', 2, null], ['tom', 'brand', 3, null],
            ['ermani kod', 'model', 4, '212 VIP'],
            ['savaj', 'model', 1, 'Sauvage'], ['aventos', 'model', null, 'Aventus'],
        ] as [$alias, $type, $brand, $original]) {
            SearchAlias::create(['alias' => $alias, 'type' => $type, 'brand_id' => $brand, 'original' => $original]);
        }
    }

    private function ids(string $query, int $limit = 10): array
    {
        return array_column(app(ProductSearchService::class)->search($query, $limit)['results'], 'id');
    }

    public function test_alias_and_word_order_do_not_matter(): void
    {
        $sauvage = [26, 42, 50, 71];
        foreach (['diyor savaj', 'savaj diyor', 'dior savaj', 'Christian Dior Sauvage', 'DİYOR SAVAJ orijinal'] as $query) {
            $this->assertEqualsCanonicalizing($sauvage, $this->ids($query), $query);
        }
        foreach (['creed aventus', 'aventus creed', 'krid aventos', 'aventos'] as $query) {
            $this->assertSame([90], $this->ids($query), $query);
        }
    }

    public function test_shorter_name_first(): void
    {
        $ids = $this->ids('savaj');
        $this->assertLessThan(array_search(50, $ids), array_search(26, $ids)); // "Sauvage" → "Sauvage Parfum"-dan əvvəl
        $this->assertLessThan(array_search(71, $ids), array_search(42, $ids));
    }

    public function test_brand_word_alone_and_multi_word_brand(): void
    {
        $this->assertEqualsCanonicalizing([26, 42, 50, 71, 80], $this->ids('dior'));
        $this->assertSame([91], $this->ids('tom ford'));
        $this->assertSame([91], $this->ids('tom oud wood'));
        $this->assertSame([93], $this->ids('marly layton')); // "parfums" brend sözü sayılmır, "marly" sayılır
    }

    public function test_numbers_and_unknown_words(): void
    {
        $this->assertSame([92], $this->ids('212 vip'));           // rəqəm adın hissəsidir
        $this->assertSame([90], $this->ids('aventus 100'));       // ölçü — rəqəmsiz yenidən
        $this->assertSame([], $this->ids('savaz'));              // lüğətdə yoxdur → nəticəsiz (admin əlavə edəcək)
        $this->assertSame([], $this->ids('orijinal'));           // yalnız nəzərə alınmayan söz
    }

    public function test_interpretation_label(): void
    {
        $parsed = app(ProductSearchService::class)->interpret('Diyor savaj');
        $this->assertSame([1], $parsed['brands']);
        $this->assertSame(['sauvage'], $parsed['words']);
        $this->assertSame('Christian Dior Sauvage', $parsed['label']);
    }

    public function test_alias_change_refreshes_vocabulary(): void
    {
        $this->assertSame([], $this->ids('savaz'));
        SearchAlias::create(['alias' => 'Savaz', 'type' => 'model', 'original' => 'Sauvage']);
        $this->assertCount(4, $this->ids('savaz'));
    }

    public function test_admin_store_alias_and_resolve_no_result(): void
    {
        Schema::create('permissions', fn (Blueprint $t) => [$t->id(), $t->string('name'), $t->string('guard_name'), $t->timestamps()]);
        Schema::create('product_search_logs', fn (Blueprint $t) => [$t->id(), $t->string('query'), $t->string('normalized_query'), $t->string('visitor_id'),
            $t->integer('user_id')->nullable(), $t->integer('result_count'), $t->json('matched_product_ids')->nullable(), $t->timestamp('searched_at')]);
        DB::table('product_search_logs')->insert(['query' => 'Sovaj', 'normalized_query' => 'sovaj', 'visitor_id' => 'x', 'result_count' => 0, 'searched_at' => now()]);
        $user = new \App\Models\User(['name' => 'Admin']);
        $user->id = 1;
        Gate::before(fn () => true);
        $this->actingAs($user, 'admin');

        $this->post('/admin/product/search-aliases', ['alias' => 'Sovaj', 'type' => 'model', 'original' => 'Sauvage', 'from_query' => 'Sovaj'])
            ->assertRedirect(route('admin.product.search-aliases.index'));
        $this->assertSame(0, DB::table('product_search_logs')->count());
        $this->assertCount(4, $this->ids('sovaj'));

        // təkrar və natamam
        $this->post('/admin/product/search-aliases', ['alias' => 'SOVAJ', 'type' => 'model', 'original' => 'X'])
            ->assertSessionHasErrors('alias_normalized', null, 'createAlias');
        $this->post('/admin/product/search-aliases', ['alias' => 'dyor', 'type' => 'brand'])
            ->assertSessionHasErrors('brand_id', null, 'createAlias');
    }

    public function test_last_word_prefix_while_typing(): void
    {
        // "kree" lüğətdə yoxdur, "kreed" isə var → Creed; adi LIKE ("creed" içində "kree" yoxdur) tapmazdı
        $this->assertSame([90], $this->ids('kree'));
        $this->assertSame([], $this->ids('kree aventus'));     // yalnız SON söz prefiks olur — ortadakı "kree" səhvdir
        $this->assertSame([90], $this->ids('aventus kree'));
        $this->assertSame([92], $this->ids('ermani ko'));       // çox sözlü alias-ın yarımçıq yazılışı
        $this->assertSame([90], $this->ids('cree'));            // adi LIKE variantı da qalır
        $this->assertEqualsCanonicalizing([90], $this->ids('kri'));

        // tam yazılanda — konkret brend, prefiks yox
        $parsed = app(ProductSearchService::class)->interpret('kridd');
        $this->assertSame([2], $parsed['brands']);
        $this->assertSame([], $parsed['alternatives']);

        $parsed = app(ProductSearchService::class)->interpret('kree');
        $this->assertSame([], $parsed['brands']);
        $this->assertNotEmpty($parsed['alternatives']);
    }

    private function authorizeBrandAliases(bool $allowSearch = true): void
    {
        Schema::create('permissions', fn (Blueprint $t) => [$t->id(), $t->string('name'), $t->string('guard_name'), $t->timestamps()]);
        $user = new \App\Models\User(['name' => 'Admin']);
        $user->id = 1;
        Gate::before(fn ($user, $ability) => $allowSearch || $ability !== 'product.search');
        $this->actingAs($user, 'admin');
    }

    public function test_brand_ai_suggestions_do_not_save_and_filter_duplicates(): void
    {
        $this->authorizeBrandAliases();
        config(['services.openai.api_key' => 'test', 'services.openai.model' => 'test-model']);
        Http::preventStrayRequests();
        Http::fake(['api.openai.com/*' => Http::response(['output' => [['content' => [[
            'type' => 'output_text',
            'text' => json_encode(['aliases' => ['Diyor', 'Christian Dior', 'Creed', 'Dyor', 'DYOR', 'Диёр', 'Манчерa', 'Дйорр', '!!', 'di']]),
        ]]]]])]);
        $before = SearchAlias::count();
        $this->postJson('/admin/brand/1/aliases/suggest')->assertOk()->assertExactJson(['aliases' => ['DYOR']]);
        $this->assertSame($before, SearchAlias::count());
        Http::assertSent(fn ($request) => !isset($request['tools']) && str_contains($request['input'], 'Christian Dior'));
    }

    public function test_confirmed_brand_aliases_are_saved_and_immediately_searchable(): void
    {
        $this->authorizeBrandAliases();
        $this->assertSame([], $this->ids('dyor'));
        $this->postJson('/admin/brand/1/aliases', ['aliases' => ['Dyor', 'DYOR', 'Dyorr']])->assertOk();
        $this->assertDatabaseHas('search_aliases', ['alias_normalized' => 'dyor', 'type' => 'brand', 'brand_id' => 1, 'created_by' => 1]);
        $this->assertEqualsCanonicalizing([26, 42, 50, 71, 80], $this->ids('dyor'));
        $this->assertSame(1, SearchAlias::where('alias_normalized', 'dyor')->count());
        $this->assertSame(3, Brand::withCount('searchAliases')->find(1)->search_aliases_count);
    }

    public function test_brand_alias_conflicts_reject_the_entire_batch(): void
    {
        $this->authorizeBrandAliases();
        foreach ([['dyor', 'KRID'], ['dyor', 'Creed'], ['dyor', '!!']] as $aliases) {
            $this->postJson('/admin/brand/1/aliases', compact('aliases'))->assertUnprocessable();
            $this->assertDatabaseMissing('search_aliases', ['alias_normalized' => 'dyor']);
        }
    }

    public function test_brand_alias_deletion_is_scoped_to_brand_and_type(): void
    {
        $this->authorizeBrandAliases();
        $krid = SearchAlias::where('alias', 'krid')->first();
        $model = SearchAlias::where('alias', 'savaj')->first();
        $diyor = SearchAlias::where('alias', 'diyor')->first();
        $this->deleteJson("/admin/brand/1/aliases/{$krid->id}")->assertNotFound();
        $this->deleteJson("/admin/brand/1/aliases/{$model->id}")->assertNotFound();
        $this->deleteJson("/admin/brand/1/aliases/{$diyor->id}")->assertOk();
        $this->assertDatabaseMissing('search_aliases', ['id' => $diyor->id]);
    }

    public function test_brand_list_counts_only_brand_aliases_and_filters_missing(): void
    {
        $controller = app(BrandsController::class);
        $brands = $controller->index()->getData()['brands'];
        $this->assertSame(1, $brands->firstWhere('id', 1)->search_aliases_count);
        $this->assertSame(0, $brands->firstWhere('id', 4)->search_aliases_count);
        request()->merge(['aliases' => 'missing']);
        $this->assertEqualsCanonicalizing([4, 5], $controller->index()->getData()['brands']->pluck('id')->all());
        $this->assertTrue($controller->edit(1)->getData()['brand']->relationLoaded('searchAliases'));
    }

    public function test_alias_mutations_require_search_permission(): void
    {
        $this->authorizeBrandAliases(false);
        Http::preventStrayRequests();
        $this->postJson('/admin/brand/1/aliases/suggest')->assertForbidden();
        $this->postJson('/admin/brand/1/aliases', ['aliases' => ['dyor']])->assertForbidden();
    }

    public function test_assistant_poster_data(): void
    {
        Schema::create('permissions', fn (Blueprint $t) => [$t->id(), $t->string('name'), $t->string('guard_name'), $t->timestamps()]);
        $user = new \App\Models\User(['name' => 'Operator']);
        $user->id = 1;
        Gate::before(fn () => true);
        $this->actingAs($user, 'admin');

        $this->getJson('/admin/assistant/poster/90')->assertStatus(422); // şəkil yoxdur

        DB::table('product_images')->insert(['product_id' => 90, 'image' => 'aventus.webp']);
        $this->getJson('/admin/assistant/poster/90')->assertOk()->assertJson([
            'brand' => 'Creed', 'name' => 'Aventus', 'filename' => 'creed-aventus.png',
            'variants' => [['size' => '100 ml', 'price' => '100.00']],
        ]);
    }

    private function vision(array $hints): void
    {
        $this->mock(\App\Services\IdCard\GoogleVisionOcr::class, function ($mock) use ($hints) {
            $mock->shouldReceive('isConfigured')->andReturn(true);
            $mock->shouldReceive('productHints')->andReturn($hints + ['text' => '', 'labels' => [], 'entities' => []]);
        });
    }

    public function test_image_search_prefers_web_guess_for_bottle_photo(): void
    {
        $this->vision(['labels' => ['dior sauvage elixir'], 'entities' => ['Christian Dior', 'Perfume', 'Bottle'], 'text' => 'SAUVAGE']);
        $r = app(\App\Services\Search\ImageProductSearch::class)->find('img');

        $this->assertTrue($r['found']);
        $this->assertSame('web', $r['source']);
        $this->assertSame('dior sauvage elixir', $r['query']);
        $this->assertSame([71], array_column(app(ProductSearchService::class)->search($r['query'])['results'], 'id'));
    }

    public function test_image_search_falls_back_to_text_for_screenshot(): void
    {
        // web təxmini ümumi ("perfume") — skrinşotdakı yazı işə yarayır; "EAU DE PARFUM", qiymət, düymə atılır
        $this->vision(['labels' => ['perfume'], 'entities' => ['Perfume', 'Bottle'],
            'text' => "parfumeriya.az\nCREED\nAventus Eau de Parfum\n100 ml\n450 AZN\nSəbətə at"]);
        $r = app(\App\Services\Search\ImageProductSearch::class)->find('img');

        $this->assertTrue($r['found']);
        $this->assertSame('text', $r['source']);
        $this->assertSame('creed aventus', $r['query']);
        $this->assertSame('100', $r['size']);
    }

    public function test_image_search_nothing_recognized(): void
    {
        $this->vision(['labels' => ['glass bottle'], 'entities' => ['Glass'], 'text' => 'HELLO']);
        $r = app(\App\Services\Search\ImageProductSearch::class)->find('img');

        $this->assertFalse($r['found']);
        $this->assertNull($r['query']);
        $this->assertSame('glass bottle', $r['detected']['label']);
    }

    public function test_image_search_endpoint(): void
    {
        Schema::create('permissions', fn (Blueprint $t) => [$t->id(), $t->string('name'), $t->string('guard_name'), $t->timestamps()]);
        $user = new \App\Models\User(['name' => 'Operator']);
        $user->id = 1;
        Gate::before(fn () => true);
        $this->actingAs($user, 'admin');
        $this->vision(['labels' => ['creed aventus']]);

        $this->post('/admin/assistant/search-image', ['image' => \Illuminate\Http\UploadedFile::fake()->image('p.jpg', 400, 600)], ['Accept' => 'application/json'])
            ->assertOk()->assertJson(['query' => 'creed aventus', 'found' => true, 'source' => 'web']);
        $this->postJson('/admin/assistant/search-image', [])->assertStatus(422)->assertJsonValidationErrors('image');
    }

    public function test_whatsapp_message_with_conversational_words(): void
    {
        DB::table('brands')->insert(['id' => 6, 'name' => 'Initio Parfums Privés']);
        DB::table('products')->insert(['id' => 95, 'brand_id' => 6, 'type_id' => 1, 'name' => 'Side Effect', 'slug' => 'p-95']);
        DB::table('product_variants')->insert(['product_id' => 95, 'size_id' => 1, 'price' => 520]);
        app(ProductSearchService::class)->forgetCache();

        $parsed = \App\Services\Search\ProductQueryParser::parse(
            'Salam. Mənə INITIO Parfums Privés – Side Effect Eau de Parfum 90 ml, məhz orijinal, zavod qablaşdırmasında lazımdır.Sizde Varmi?'
        );
        $this->assertSame([95], $this->ids($parsed['query']));
    }

    public function test_normalizer_accents(): void
    {
        $n = fn (string $value) => \App\Services\Search\ProductSearchNormalizer::normalize($value);
        $this->assertSame('initio parfums prives', $n('Initio Parfums Privés'));
        $this->assertSame('hermes terre d hermes', $n("Hermès Terre d'Hermès"));
        $this->assertSame('ceyhun gul sekerbura', $n('Ceyhun Gül Şəkərbura'));
        $this->assertSame('savaj', $n('Саваж'));
    }

    public function test_generic_words_next_to_brand_are_ignored(): void
    {
        DB::table('brands')->insert(['id' => 7, 'name' => 'Essential Parfums']);
        DB::table('products')->insert(['id' => 96, 'brand_id' => 7, 'type_id' => 1, 'name' => 'Bois Imperial', 'slug' => 'p-96']);
        DB::table('product_variants')->insert(['product_id' => 96, 'size_id' => 1, 'price' => 348]);
        app(ProductSearchService::class)->forgetCache();

        $this->assertSame([96], $this->ids('essential paris bois imperial'));
        $this->assertSame([96], $this->ids('Essential Parfums Bois Impérial eau de parfum'));
        $this->assertSame([93], $this->ids('parfums de marly layton')); // brendin tam adı yenə tanınır
    }

    public function test_whatsapp_messages_with_extra_words_and_suffixes(): void
    {
        DB::table('products')->insert(['id' => 97, 'brand_id' => 3, 'type_id' => 1, 'name' => 'Lost Cherry', 'slug' => 'p-97']);
        DB::table('product_variants')->insert(['product_id' => 97, 'size_id' => 1, 'price' => 690]);
        $search = fn (string $message) => $this->ids(\App\Services\Search\ProductQueryParser::parse($message)['query']);

        $this->assertSame([97], $search('Salam sizdə tom ford lost cherry ətrinin 100 mlsi var?'));
        $this->assertSame([90], $search('Salam, Krid aventos 100 lük neçəyədi?'));
        $this->assertSame([91], $search('Oud Wood olanı Naxçıvana göndərirsiniz? Orijinaldır?')); // kök: olan-, naxcivan-, gonder-, orijinal-
        $this->assertSame([], $search('salam neçəyədi 100'));                                     // ad yoxdur
    }

    public function test_admin_adds_stem_with_conflict_confirmation(): void
    {
        Schema::create('permissions', fn (Blueprint $t) => [$t->id(), $t->string('name'), $t->string('guard_name'), $t->timestamps()]);
        Schema::create('product_search_logs', fn (Blueprint $t) => [$t->id(), $t->string('query'), $t->string('normalized_query'), $t->string('visitor_id'),
            $t->integer('user_id')->nullable(), $t->integer('result_count'), $t->json('matched_product_ids')->nullable(), $t->timestamp('searched_at')]);
        $user = new \App\Models\User(['name' => 'Admin']);
        $user->id = 1;
        Gate::before(fn () => true);
        $this->actingAs($user, 'admin');

        // "çatdır" kökü heç bir adla toqquşmur
        $this->post('/admin/product/search-aliases', ['alias' => 'yolla', 'type' => 'ignore', 'match_type' => 'prefix'])
            ->assertSessionHasNoErrors();
        $this->assertSame([90], $this->ids('aventus yollayirsiniz'));

        // "oud" kökü "Oud Wood"u da atar — təsdiqsiz rədd, təsdiqlə əlavə
        $this->post('/admin/product/search-aliases', ['alias' => 'Oud', 'type' => 'ignore', 'match_type' => 'prefix'])
            ->assertSessionHasErrors('confirm_prefix', null, 'createAlias');
        $this->post('/admin/product/search-aliases', ['alias' => 'Oud', 'type' => 'ignore', 'match_type' => 'prefix', 'confirm_prefix' => '1'])
            ->assertSessionHasNoErrors();
        $this->assertTrue(SearchAlias::where('alias_normalized', 'oud')->where('match_type', 'prefix')->exists());

        // qısa kök
        $this->post('/admin/product/search-aliases', ['alias' => 'xy', 'type' => 'ignore', 'match_type' => 'prefix'])
            ->assertSessionHasErrors('alias', null, 'createAlias');

        // uyğunluq seçilməyibsə: uzun söz — kök, qısa söz — tam
        $this->post('/admin/product/search-aliases', ['alias' => 'təcili', 'type' => 'ignore'])->assertSessionHasNoErrors();
        $this->post('/admin/product/search-aliases', ['alias' => 'pls', 'type' => 'ignore'])->assertSessionHasNoErrors();
        $this->assertSame('prefix', SearchAlias::where('alias_normalized', 'tecili')->value('match_type'));
        $this->assertSame('exact', SearchAlias::where('alias_normalized', 'pls')->value('match_type'));
    }

    public function test_long_extra_words_are_stems_short_ones_exact(): void
    {
        $this->assertSame([90], $this->ids('salamlar krid aventus'));                 // salam… kök
        $this->assertSame([90], $this->ids('xahiş edirəm aventus göndərin'));        // xahis…, edirem…, gonder…
        $this->assertSame([93], $this->ids('marly layton'));                          // "la" tam söz — Layton qalır
        $this->assertSame('exact', SearchAlias::where('alias_normalized', 'la')->value('match_type'));
    }

    public function test_brand_name_word_repeated_is_model_and_short_words_are_whole(): void
    {
        DB::table('brands')->insert(['id' => 8, 'name' => 'Nina Ricci']);
        DB::table('products')->insert([
            ['id' => 98, 'brand_id' => 8, 'type_id' => 1, 'name' => 'Nina', 'slug' => 'p-98'],
            ['id' => 99, 'brand_id' => 8, 'type_id' => 1, 'name' => 'Love in Paris', 'slug' => 'p-99'],
        ]);
        DB::table('product_variants')->insert([['product_id' => 98, 'size_id' => 1, 'price' => 150], ['product_id' => 99, 'size_id' => 1, 'price' => 178]]);
        app(ProductSearchService::class)->forgetCache();
        $search = fn (string $message) => $this->ids(\App\Services\Search\ProductQueryParser::parse($message)['query']);

        $this->assertSame([98], $search('Salam nina ricci nina etiri var? Ve qiymer'));
        $this->assertEqualsCanonicalizing([98, 99], $this->ids('nina ricci'));   // yalnız brend — hamısı
        $this->assertSame([99], $this->ids('nina ricci love'));
        $this->assertSame([], $this->ids('nina ricci ov'));                       // qısa söz bütöv olmalıdır ("Love" içində yox)
        $this->assertSame([92], $this->ids('212 vip'));
    }
}
