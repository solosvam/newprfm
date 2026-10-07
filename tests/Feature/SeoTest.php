<?php

namespace Tests\Feature;

use App\Support\LocaleUrl;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SeoTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'cache.default' => 'array']);
        DB::purge('sqlite');

        Schema::create('brands', fn (Blueprint $t) => [$t->id(), $t->string('name'), $t->string('slug'), $t->string('image')->nullable(), $t->boolean('active')]);
        Schema::create('products', fn (Blueprint $t) => [$t->id(), $t->integer('old_id')->nullable(), $t->string('name'), $t->string('slug'), $t->integer('brand_id')->nullable(), $t->boolean('active')]);
        Schema::create('categories', fn (Blueprint $t) => [$t->id(), $t->string('name_az'), $t->string('name_en')->nullable(), $t->string('name_ru')->nullable(), $t->string('slug'), $t->boolean('active')]);
        Schema::create('faqs', fn (Blueprint $t) => [$t->id(), $t->string('title_az'), $t->string('title_en')->nullable(), $t->string('title_ru')->nullable(), $t->text('content_az')->nullable(), $t->text('content_en')->nullable(), $t->text('content_ru')->nullable()]);
        (require base_path('database/migrations/2026_09_21_130000_create_settings_table.php'))->up();
        (require base_path('database/migrations/2026_10_08_100000_create_popups_tables.php'))->up();
        (require base_path('database/migrations/2026_10_08_130000_create_pages_table.php'))->up();

        DB::table('brands')->insert([['id' => 1, 'name' => 'Amouage parfums', 'slug' => 'amouage-parfums', 'active' => 1], ['id' => 2, 'name' => 'Gizli', 'slug' => 'gizli', 'active' => 0]]);
        DB::table('products')->insert([['old_id' => 8734, 'name' => 'Dragon', 'slug' => 'dragon', 'brand_id' => 1, 'active' => 1], ['old_id' => 1, 'name' => 'Off', 'slug' => 'off', 'brand_id' => 1, 'active' => 0]]);
        DB::table('categories')->insert([['name_az' => 'Kişi ətirləri', 'slug' => 'mens-perfumes', 'active' => 1]]);
    }

    public function test_legacy_opencart_urls_redirect_permanently(): void
    {
        $cases = [
            'route=product/product&product_id=8734' => '/dragon',
            'route=information/information&information_id=3' => '/about',
            'route=information/information&information_id=10' => '/bonus',
            'route=information/information&information_id=99' => '/',
            'route=information/contact&language=ru' => '/contact?lang=ru',
            'route=product/category&path=36_80' => '/category/mens-perfumes',
            'route=product/category&path=35' => '/',                       // yeni saytda bu kateqoriya yoxdur
            'route=product/manufacturer&manufacturer_id=72' => '/brand/amouage-parfums',
            'route=product/manufacturer&manufacturer_id=123' => '/brands',  // brend yeni saytda yoxdur
            'route=product/search&keyword=dior' => '/?q=dior',
            'route=checkout/cart' => '/cart',
            'route=common/home' => '/',
        ];
        foreach ($cases as $query => $target) {
            $this->get('/index.php?'.$query)->assertStatus(301)->assertRedirect(url($target));
        }
        $this->get('/index.php?route=product/product&product_id=55555')->assertNotFound();
        $this->get('/index.php?route=foo/bar')->assertNotFound();
    }

    public function test_lang_query_switches_locale_and_head_has_hreflang(): void
    {
        $html = $this->get('/about?lang=ru')->assertOk()->getContent();
        $this->assertStringContainsString('<html lang="ru"', $html);
        $this->assertStringContainsString('<link rel="canonical" href="'.url('/about').'?lang=ru">', $html);
        $this->assertStringContainsString('hreflang="en" href="'.url('/about').'?lang=en"', $html);
        $this->assertStringContainsString('hreflang="x-default" href="'.url('/about').'"', $html);

        $this->assertSame(url('/').'/?lang=en', LocaleUrl::to(url('/'), 'en'));
        $this->assertSame('http://x.az/a?page=2&lang=ru', LocaleUrl::to('http://x.az/a?page=2&lang=en', 'ru'));
    }

    public function test_sitemap_lists_active_urls_in_all_languages(): void
    {
        $xml = $this->get(route('sitemap'))->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8')->getContent();
        foreach (['/dragon', '/brand/amouage-parfums', '/category/mens-perfumes', '/about', '/faq', '/installment'] as $path) {
            $this->assertStringContainsString('<loc>'.url($path).'</loc>', $xml);
        }
        $this->assertStringContainsString('<loc>'.url('/dragon').'?lang=ru</loc>', $xml);
        $this->assertStringNotContainsString('/off', $xml);
        $this->assertStringNotContainsString('/brand/gizli', $xml);
        $this->assertNotFalse(simplexml_load_string($xml));
    }

    public function test_cookie_bar_is_rendered_with_privacy_link(): void
    {
        $this->get('/about')->assertSee('id="cookieBar"', false)->assertSee(route('front.page.privacy'), false);
    }
}
