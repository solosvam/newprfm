<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Tests\TestCase;

class ImportLegacyReviewsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'services.legacy_site.url' => 'https://old.test']);
        DB::purge('sqlite');
        Schema::create('products', fn (Blueprint $t) => [$t->id(), $t->string('name'), $t->integer('old_id')->nullable()]);
        Schema::create('product_reviews', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('product_id'); $t->unsignedBigInteger('customer_id');
            $t->unsignedTinyInteger('rating'); $t->text('comment'); $t->boolean('active')->default(false); $t->timestamps();
        });
        (require database_path('migrations/2026_11_19_100000_legacy_product_reviews.php'))->up();
        DB::table('products')->insert([['id' => 1, 'name' => 'Acqua di Gio', 'old_id' => 67], ['id' => 2, 'name' => 'Yeni', 'old_id' => null]]);
    }

    private function review(string $author, int $stars, string $date, string $text): string
    {
        return '<div style="background: #F7F7F7;"><b>'.$author.'</b> | <img src="catalog/view/theme/cofran/image/stars_'.$stars.'.png" alt="" /><br />'
            ."\n {$date}<br />\n <br />\n {$text}</div>\n";
    }

    public function test_dry_run_then_apply_then_no_duplicates(): void
    {
        Http::fake([
            'old.test/index.php?route=product%2Fproduct%2Freview&product_id=67&page=1' => Http::response(
                $this->review('Ruslan', 5, '16.01.2014', 'əla iyi var, meslehetlidi')
                .$this->review('Ilqar &amp; Co', 4, '25.06.2012', "Cox gozel iyi var\nqizlarin da xowuna gelir")
                .'<div class="pagination"><div class="results">göstərilir 1 dən 6 cəmi 6 (səhifə sayı 2)</div></div>'),
            'old.test/index.php?route=product%2Fproduct%2Freview&product_id=67&page=2' => Http::response(
                $this->review('Nika', 3, '17.03.2012', 'normal').'<div class="pagination"><div class="results">(səhifə sayı 2)</div></div>'),
        ]);

        $this->artisan('parfumshop:import-reviews', ['--sleep' => 0])->assertSuccessful();
        $this->assertSame(0, DB::table('product_reviews')->count()); // yoxlama — yazılmır

        $this->artisan('parfumshop:import-reviews', ['--apply' => true, '--sleep' => 0])->assertSuccessful();
        $rows = DB::table('product_reviews')->orderBy('id')->get();
        $this->assertSame(['Ruslan', 'Ilqar & Co', 'Nika'], $rows->pluck('author_name')->all());
        $this->assertSame([5, 4, 3], $rows->pluck('rating')->map(fn ($r) => (int) $r)->all());
        $this->assertSame("Cox gozel iyi var\nqizlarin da xowuna gelir", $rows[1]->comment);
        $this->assertSame('2014-01-16', substr($rows[0]->created_at, 0, 10));
        $this->assertNull($rows[0]->customer_id);
        $this->assertSame([1, 1, 1], $rows->pluck('active')->map(fn ($a) => (int) $a)->all());
        $this->assertSame([1, 1, 1], $rows->pluck('product_id')->map(fn ($a) => (int) $a)->all());

        // Təkrar işlədəndə ikiqat düşmür
        $this->artisan('parfumshop:import-reviews', ['--apply' => true, '--sleep' => 0])->assertSuccessful();
        $this->assertSame(3, DB::table('product_reviews')->count());
    }
}
