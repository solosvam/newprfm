<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Tests\TestCase;

class ImportLegacyReviewsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        Schema::create('products', fn (Blueprint $t) => [$t->id(), $t->string('name'), $t->integer('old_id')->nullable()]);
        Schema::create('product_reviews', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('product_id'); $t->unsignedBigInteger('customer_id');
            $t->unsignedTinyInteger('rating'); $t->text('comment'); $t->boolean('active')->default(false); $t->timestamps();
        });
        (require database_path('migrations/2026_11_19_100000_legacy_product_reviews.php'))->up();
        DB::table('products')->insert([['id' => 1, 'name' => 'Acqua di Gio', 'old_id' => 67], ['id' => 2, 'name' => 'Yeni', 'old_id' => null]]);
    }

    public function test_dry_run_then_apply_then_no_duplicates(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'rev');
        file_put_contents($file, json_encode([
            ['id' => 241, 'product' => 67, 'author' => 'Ruslan', 'text' => 'əla iyi var, meslehetlidi', 'rating' => 5, 'date' => '2014-01-16 10:33:28'],
            ['id' => 123, 'product' => 67, 'author' => 'Lala &amp; Co', 'text' => "Necə deyərlər, &quot;doşab aldım, bal çıxdı&quot;\r\nƏla", 'rating' => 4, 'date' => '2012-06-25 10:29:00'],
            ['id' => 999, 'product' => 12345, 'author' => 'Yox', 'text' => 'məhsulu yoxdur', 'rating' => 5, 'date' => '2015-01-01 00:00:00'],
        ]));

        $this->artisan('parfumshop:import-reviews', ['--file' => $file])->assertSuccessful();
        $this->assertSame(0, DB::table('product_reviews')->count()); // yoxlama — yazılmır

        $this->artisan('parfumshop:import-reviews', ['--file' => $file, '--apply' => true])->assertSuccessful();
        $rows = DB::table('product_reviews')->orderBy('id')->get();
        $this->assertSame(['Ruslan', 'Lala & Co'], $rows->pluck('author_name')->all());
        $this->assertSame([5, 4], $rows->pluck('rating')->map(fn ($r) => (int) $r)->all());
        $this->assertSame("Necə deyərlər, \"doşab aldım, bal çıxdı\"\nƏla", $rows[1]->comment);
        $this->assertSame('2014-01-16 10:33:28', (string) $rows[0]->created_at);
        $this->assertNull($rows[0]->customer_id);
        $this->assertSame([1, 1], $rows->pluck('active')->map(fn ($a) => (int) $a)->all());
        $this->assertSame([1, 1], $rows->pluck('product_id')->map(fn ($a) => (int) $a)->all());

        // Təkrar işlədəndə ikiqat düşmür
        $this->artisan('parfumshop:import-reviews', ['--file' => $file, '--apply' => true])->assertSuccessful();
        $this->assertSame(2, DB::table('product_reviews')->count());
        unlink($file);
    }

    public function test_repo_file_has_only_customer_reviews(): void
    {
        $reviews = json_decode(file_get_contents(database_path('data/legacy-reviews.json')), true);
        $this->assertNotEmpty($reviews);
        foreach ($reviews as $r) {
            $this->assertDoesNotMatchRegularExpression('/pa?r?fumshop/i', $r['author']); // mağazanın cavabları çıxarılıb
            $this->assertNotSame('', trim($r['text']));
        }
    }
}
