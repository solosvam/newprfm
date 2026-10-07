<?php

namespace Tests\Feature\Catalog;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BrandsPageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        Schema::create('brands', fn (Blueprint $t) => [$t->id(), $t->string('name'), $t->string('slug'), $t->string('image')->nullable(), $t->boolean('active')]);
        Schema::create('products', fn (Blueprint $t) => [$t->id(), $t->string('name'), $t->integer('brand_id'), $t->boolean('active')]);
        (require base_path('database/migrations/2026_10_08_100000_create_popups_tables.php'))->up();
        (require base_path('database/migrations/2026_10_08_130000_create_pages_table.php'))->up();
        (require base_path('database/migrations/2026_09_21_130000_create_settings_table.php'))->up();
        Schema::create('categories', fn (Blueprint $t) => [$t->id(), $t->string('name_az'), $t->string('name_en')->nullable(), $t->string('name_ru')->nullable(), $t->string('slug'), $t->boolean('active')]);
        foreach (['Chanel', 'Christian Dior', 'Ömür Parfum', '4711', 'Şəki Ətirləri', 'Armani', 'Zara', 'Gizli'] as $i => $name) {
            DB::table('brands')->insert(['id' => $i + 1, 'name' => $name, 'slug' => 'b'.($i + 1), 'active' => $name !== 'Gizli']);
        }
        DB::table('products')->insert([['name' => 'A', 'brand_id' => 1, 'active' => 1], ['name' => 'B', 'brand_id' => 1, 'active' => 1], ['name' => 'C', 'brand_id' => 1, 'active' => 0]]);
    }

    public function test_groups_letters_counts_and_no_sidebar(): void
    {
        $html = $this->get(route('brands'))->assertOk()->getContent();
        $this->assertStringContainsString('7 brend', $html);
        $this->assertStringNotContainsString('Gizli', $html);
        $this->assertStringNotContainsString('data-brands-group="Ö"', $html); // yalnız ingilis əlifbası
        $this->assertStringNotContainsString('>Ə<', $html);
        $this->assertStringContainsString('data-brands-group="0–9"', $html);
        $this->assertStringContainsString('2 məhsul', $html);               // yalnız aktiv məhsullar
        // Ömür → O, Şəki → S; sıra: 0–9, A–Z
        preg_match_all('/data-brands-group="([^"]+)"/u', $html, $m);
        $this->assertSame(['0–9', 'A', 'C', 'O', 'S', 'Z'], $m[1]);
        $this->assertStringNotContainsString('account-nav', $html);          // sol panel yoxdur
        $this->assertStringNotContainsString('internal-credit', $html);
    }
}
