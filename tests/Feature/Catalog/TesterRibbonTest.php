<?php

namespace Tests\Feature\Catalog;

use App\Models\Product\Product;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** Tester ətir: adında "tester" sözü və ya "Tester" kateqoriyası — saytda şəklin küncündə lent */
class TesterRibbonTest extends TestCase
{
    public function test_tester_is_recognised_by_name_or_category(): void
    {
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        Schema::create('categories', fn (Blueprint $t) => [$t->id(), $t->string('name_az')]);
        Schema::create('product_categories', fn (Blueprint $t) => [$t->id(), $t->integer('product_id'), $t->integer('category_id')]);
        DB::table('categories')->insert([['id' => 1, 'name_az' => 'Qadın ətirləri'], ['id' => 7, 'name_az' => 'Tester']]);
        DB::table('product_categories')->insert([['product_id' => 2, 'category_id' => 7], ['product_id' => 3, 'category_id' => 1]]);

        $this->assertTrue((new Product(['name' => 'Hypnotic Poison TESTER']))->forceFill(['id' => 1])->isTester());
        $this->assertTrue((new Product(['name' => 'Libre']))->forceFill(['id' => 2])->isTester(), 'Tester kateqoriyasında');
        $this->assertFalse((new Product(['name' => 'Testerossa']))->forceFill(['id' => 3])->isTester(), 'sözün içində olması sayılmır');

        $html = view('frontend.partials.tester-ribbon', ['product' => (new Product(['name' => 'Eros Tester']))->forceFill(['id' => 4])])->render();
        $this->assertStringContainsString('<span class="tester-ribbon">Tester</span>', $html);
    }
}
