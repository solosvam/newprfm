<?php

namespace Tests\Feature\PriceList;

use App\Models\PriceList\WarehousePriceItem;
use App\Models\Procurement\Warehouse;
use App\Models\User;
use App\Services\PriceList\PriceListImporter;
use App\Services\PriceList\PriceListNameParser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PriceListImportTest extends TestCase
{
    private string $file;
    private Warehouse $warehouse;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'cache.default' => 'array']);
        DB::purge('sqlite');
        Schema::create('users', fn (Blueprint $t) => [$t->id(), $t->string('name'), $t->string('surname')->nullable(), $t->boolean('active')->default(true), $t->timestamps()]);
        Schema::create('warehouses', fn (Blueprint $t) => [$t->id(), $t->string('name_az'), $t->string('phone')->nullable(), $t->boolean('active')->default(true), $t->timestamps()]);
        Schema::create('brands', fn (Blueprint $t) => [$t->id(), $t->string('name'), $t->string('slug')->nullable(), $t->string('image')->nullable(), $t->boolean('active')->default(true)]);
        Schema::create('types', fn (Blueprint $t) => [$t->id(), $t->string('name_az')]);
        Schema::create('sizes', fn (Blueprint $t) => [$t->id(), $t->string('name_az')]);
        Schema::create('products', fn (Blueprint $t) => [$t->id(), $t->integer('brand_id')->nullable(), $t->integer('type_id')->nullable(), $t->string('name'), $t->boolean('active')->default(true)]);
        Schema::create('product_variants', fn (Blueprint $t) => [$t->id(), $t->integer('product_id'), $t->integer('size_id'), $t->decimal('price', 10, 2)->default(0), $t->boolean('active')->default(true)]);
        Schema::create('product_genders', fn (Blueprint $t) => [$t->integer('product_id'), $t->integer('gender_id')]);
        Schema::create('product_categories', fn (Blueprint $t) => [$t->id(), $t->integer('product_id'), $t->integer('category_id')]);
        Schema::create('search_aliases', fn (Blueprint $t) => [$t->id(), $t->string('alias'), $t->string('type'), $t->integer('brand_id')->nullable()]);
        (require database_path('migrations/2026_10_11_100000_create_warehouse_price_lists.php'))->up();
        (require base_path('vendor/spatie/laravel-permission/database/migrations/create_permission_tables.php.stub'))->up();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->warehouse = Warehouse::create(['name_az' => 'Aksin']);
        DB::table('brands')->insert([['id' => 1, 'name' => 'Versace'], ['id' => 2, 'name' => 'Yves Saint Laurent'], ['id' => 3, 'name' => 'Azzaro']]);
        DB::table('search_aliases')->insert(['alias' => 'ysl', 'type' => 'brand', 'brand_id' => 2]);
        DB::table('types')->insert([['id' => 1, 'name_az' => 'Eau De Parfum'], ['id' => 2, 'name_az' => 'Eau De Toilette']]);
        DB::table('sizes')->insert([['id' => 1, 'name_az' => '50 ml'], ['id' => 2, 'name_az' => '100 ml']]);
        // id => [brend, növ, ad]; hər məhsulun 50 və 100 ml variantı: variant id = məhsul id × 10 + ölçü id
        foreach ([1 => [1, 1, 'Eros'], 2 => [1, 2, 'Eros'], 3 => [1, null, 'Eros L EDP Tester'], 4 => [2, 1, 'Black Opium'], 5 => [3, 2, 'Chrome'], 6 => [3, 2, 'Chrome']] as $id => [$brand, $type, $name]) {
            DB::table('products')->insert(['id' => $id, 'brand_id' => $brand, 'type_id' => $type, 'name' => $name]);
            DB::table('product_variants')->insert([['id' => $id * 10 + 1, 'product_id' => $id, 'size_id' => 1, 'price' => 100], ['id' => $id * 10 + 2, 'product_id' => $id, 'size_id' => 2, 'price' => 150]]);
        }

        $book = new Spreadsheet();
        $book->getActiveSheet()->fromArray([
            ['Номенклатура', 'AZN'],
            ['VERSACE', null],
            ['    VERSACE EROS EDP M 50ML', 87.723],
            ['    VERSACE EROS EDT M 100ML', 104.652],
            ['    VERSACE EROS EDP L 100ML TESTER', 60.5],
            ['    VERSACE EROS FLAME EDP M 100ML', 99],
            ['YSL', null],
            ['    YSL BLACK OPIUM EDP L 50ML', 141.588],
            ['AZZARO', null],
            ['    AZZARO CHROME EDT M 100ML', 55],
            ['ROJA', null],
            ['    ROJA ELYSIUM EDP M 100ML', 480],
        ]);
        $this->file = tempnam(sys_get_temp_dir(), 'pl').'.xlsx';
        (new Xlsx($book))->save($this->file);
    }

    protected function tearDown(): void
    {
        @unlink($this->file);
        parent::tearDown();
    }

    private function import(): \App\Models\PriceList\WarehousePriceList
    {
        $importer = app(PriceListImporter::class);
        $rows = app(\App\Services\PriceList\PriceListReader::class)->rows($this->file);

        return $importer->import($this->warehouse, $this->file, 'aksin.xlsx', $importer->guessMapping($rows), 7);
    }

    public function test_name_is_split_into_core_kind_gender_volume_and_flags(): void
    {
        $this->assertSame(['core' => 'EROS', 'kind' => 'EDP', 'gender' => 'L', 'volume' => 100.0, 'tester' => true, 'set' => false],
            PriceListNameParser::parse('VERSACE EROS EDP L 100ML TESTER', 'VERSACE'));
        $this->assertSame(['IDOLE', 'EDP'], array_values(array_intersect_key(PriceListNameParser::parse("LANCOME IDOLE L'EAU DE PARFUM L 50ML", 'LANCOME'), ['core' => 1, 'kind' => 1])));
        $this->assertSame('EXTRAIT', PriceListNameParser::parse('TIZIANA TERENZI CUBIA EXTRAIT DE PARFUM UNISEX 100ML', 'TIZIANA TERENZI')['kind']);
        $this->assertTrue(PriceListNameParser::parse('ALEXANDRE J BLACK MUSCS EDP UNISEX 100ML+2X30ML SET', 'ALEXANDRE J')['set']);
        // Bizim köhnə məhsul adları da eyni qayda ilə təmizlənir
        $this->assertSame(['core' => 'CRYSTAL NOIR', 'tester' => true], array_intersect_key(PriceListNameParser::parse('Crystal Noir L Tester'), ['core' => 1, 'tester' => 1]));
    }

    public function test_import_reads_group_rows_as_brands_and_matches_only_unambiguous_rows(): void
    {
        $list = $this->import();
        $items = WarehousePriceItem::where('price_list_id', $list->id)->get()->keyBy('raw_name');

        $this->assertSame(7, $list->rows_count);
        $this->assertSame(['sheet' => 0, 'first_row' => 1, 'name_col' => 0, 'price_col' => 1, 'brand_mode' => 'group', 'brand_col' => null], $list->mapping);
        // Qiymət 2 onluğa yuvarlaqlaşdırılır; brend qrup başlığından gəlir
        $this->assertSame('87.72', $items['VERSACE EROS EDP M 50ML']->price);
        $this->assertSame('VERSACE', $items['VERSACE EROS EDP M 50ML']->brand_raw);

        // Növ ayırır: EDP → məhsul 1, EDT → məhsul 2; tester → adında "Tester" olan məhsul 3
        $this->assertSame(11, $items['VERSACE EROS EDP M 50ML']->product_variant_id);
        $this->assertSame(22, $items['VERSACE EROS EDT M 100ML']->product_variant_id);
        $this->assertSame(32, $items['VERSACE EROS EDP L 100ML TESTER']->product_variant_id);
        // Brend axtarış aliası ilə tanınır (YSL → Yves Saint Laurent)
        $this->assertSame(41, $items['YSL BLACK OPIUM EDP L 50ML']->product_variant_id);
        $this->assertSame('auto', $items['YSL BLACK OPIUM EDP L 50ML']->matched_by);

        // Bizdə olmayan ad, iki eyni məhsul (şübhəli) və tanınmayan brend — bağlanmır
        $this->assertNull($items['VERSACE EROS FLAME EDP M 100ML']->product_variant_id);
        $this->assertNull($items['AZZARO CHROME EDT M 100ML']->product_variant_id);
        $this->assertNull($items['ROJA ELYSIUM EDP M 100ML']->brand_id);
    }

    public function test_operator_matches_are_remembered_for_the_next_import_and_brands_can_be_created(): void
    {
        Permission::create(['name' => 'crm', 'guard_name' => 'admin']);
        Permission::create(['name' => 'brands.menu', 'guard_name' => 'admin']);
        Role::create(['name' => 'Admin', 'guard_name' => 'admin'])->givePermissionTo(['crm', 'brands.menu']);
        $admin = User::forceCreate(['name' => 'Rufat']);
        $admin->assignRole('Admin');
        $list = $this->import();
        $chrome = WarehousePriceItem::where('raw_name', 'AZZARO CHROME EDT M 100ML')->sole();

        // Namizədlər: iki eyni "Chrome" — operator birini seçir
        $candidates = $this->actingAs($admin, 'admin')->getJson(route('admin.price-lists.items.candidates', $chrome))->assertOk()->json('items');
        $this->assertEqualsCanonicalizing([52, 62], array_column(array_filter($candidates, fn ($c) => $c['exact']), 'id'));
        $this->postJson(route('admin.price-lists.items.match', $chrome), ['variant_id' => 62])->assertOk();
        $this->assertSame('manual', $chrome->fresh()->matched_by);

        // Bizdə olmayan brend yaradılır və bağlanır
        $this->post(route('admin.price-lists.brands.create', $list), ['brand_raw' => 'ROJA', 'name' => 'Roja Parfums'])->assertSessionHasNoErrors();
        $roja = DB::table('brands')->where('name', 'Roja Parfums')->value('id');
        $this->assertNotNull($roja);
        $this->assertSame((int) $roja, WarehousePriceItem::where('raw_name', 'ROJA ELYSIUM EDP M 100ML')->value('brand_id'));

        // Növbəti həftənin importu: seçim yaddaşdan gəlir, yeni brend tanınır
        $next = $this->import();
        $again = WarehousePriceItem::where('price_list_id', $next->id)->where('raw_name', 'AZZARO CHROME EDT M 100ML')->sole();
        $this->assertSame([62, 'memory'], [$again->product_variant_id, $again->matched_by]);
        $this->assertSame((int) $roja, WarehousePriceItem::where('price_list_id', $next->id)->where('raw_name', 'ROJA ELYSIUM EDP M 100ML')->value('brand_id'));

        // Axtarış yalnız cari (sonuncu) siyahıda aparılır, sözlərin sırası vacib deyil
        $found = $this->getJson(route('admin.price-lists.search', ['q' => 'eros 50']))->assertOk()->json('items');
        $this->assertSame(['VERSACE EROS EDP M 50ML'], array_column($found, 'name'));
        $this->assertSame('87.72', $found[0]['price']);

        // Səhifələr açılır
        $this->get(route('admin.price-lists.index'))->assertOk()->assertSee('aksin.xlsx');
        $this->get(route('admin.price-lists.show', $next))->assertOk()->assertSee('VERSACE EROS FLAME EDP M 100ML');
        $this->get(route('admin.price-lists.show', [$next, 'tab' => 'matched']))->assertOk()->assertSee('yaddaşdan');
    }

    public function test_upload_goes_through_column_mapping_and_remembers_it_for_the_warehouse(): void
    {
        Permission::create(['name' => 'crm', 'guard_name' => 'admin']);
        Role::create(['name' => 'Admin', 'guard_name' => 'admin'])->givePermissionTo('crm');
        $admin = User::forceCreate(['name' => 'Rufat']);
        $admin->assignRole('Admin');
        // Yüklənən fayl müvəqqəti qovluğa köçürülür — test layihənin storage qovluğuna yazmasın
        $storage = sys_get_temp_dir().'/pl-test-'.uniqid();
        mkdir($storage.'/framework/views', 0777, true);
        $this->app->useStoragePath($storage);
        $upload = function () {
            copy($this->file, $copy = $this->file.'.'.uniqid().'.xlsx');

            return new \Illuminate\Http\UploadedFile($copy, 'aksin.xlsx', null, null, true);
        };

        $response = $this->actingAs($admin, 'admin')->post(route('admin.price-lists.upload'), ['warehouse_id' => $this->warehouse->id, 'file' => $upload()]);
        $response->assertSessionHasNoErrors();
        $mapUrl = $response->headers->get('Location');
        $this->assertStringContainsString('/price-lists/map/', $mapUrl);

        // Önizləmə: faylın sətirləri və təxmin edilmiş sütunlar
        $this->get($mapUrl)->assertOk()->assertSee('VERSACE EROS EDP M 50ML')->assertSee('Sütunlar təxmin edilib');

        $this->post($mapUrl, ['sheet' => 0, 'first_row' => 2, 'name_col' => 0, 'price_col' => 1, 'brand_mode' => 'group'])
            ->assertSessionHasNoErrors()->assertSessionHas('success', 'Price list yükləndi: 7 sətir.');
        $this->assertSame(2, \App\Models\PriceList\WarehousePriceList::sole()->mapping['first_row']);

        // Növbəti yükləmədə anbarın seçimi hazır gəlir; ad və qiymət eyni sütun ola bilməz
        $next = $this->post(route('admin.price-lists.upload'), ['warehouse_id' => $this->warehouse->id, 'file' => $upload()])->headers->get('Location');
        $this->get($next)->assertOk()->assertSee('əvvəlki seçim hazır gəlib');
        $this->post($next, ['sheet' => 0, 'first_row' => 1, 'name_col' => 1, 'price_col' => 1, 'brand_mode' => 'none'])->assertSessionHasErrors('name_col');
        \Illuminate\Support\Facades\File::deleteDirectory($storage);
    }
}
