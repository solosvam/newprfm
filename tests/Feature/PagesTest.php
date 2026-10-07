<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PagesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'cache.default' => 'array']);
        DB::purge('sqlite');

        Schema::create('users', fn (Blueprint $t) => [$t->id(), $t->string('name'), $t->string('surname')->nullable(), $t->string('email')->nullable(), $t->string('password')->nullable(), $t->boolean('active')->default(true), $t->timestamps()]);
        Schema::create('categories', fn (Blueprint $t) => [$t->id(), $t->string('name_az'), $t->string('name_en')->nullable(), $t->string('name_ru')->nullable(), $t->string('slug'), $t->boolean('active')]);
        Schema::create('faqs', fn (Blueprint $t) => [$t->id(), $t->string('title_az'), $t->string('title_en')->nullable(), $t->string('title_ru')->nullable(), $t->text('content_az')->nullable(), $t->text('content_en')->nullable(), $t->text('content_ru')->nullable()]);
        Schema::create('payment_methods', fn (Blueprint $t) => [$t->id(), $t->string('code'), $t->string('name_az'), $t->string('name_en')->nullable(), $t->string('name_ru')->nullable(), $t->boolean('active')->default(true), $t->integer('sort_order')->default(0)]);
        Schema::create('credit_periods', fn (Blueprint $t) => [$t->id(), $t->unsignedSmallInteger('month'), $t->decimal('interest_rate', 8, 2)->default(0), $t->decimal('min_amount', 10, 2)->nullable(), $t->boolean('active')->default(true), $t->unsignedSmallInteger('sort_order')->default(0), $t->timestamps()]);
        Schema::create('credit_term_items', fn (Blueprint $t) => [$t->id(), $t->string('title_az')->nullable(), $t->string('title_en')->nullable(), $t->string('title_ru')->nullable(), $t->text('content_az')->nullable(), $t->text('content_en')->nullable(), $t->text('content_ru')->nullable(), $t->integer('sort_order')->default(0), $t->timestamps()]);
        Schema::create('settings', fn (Blueprint $t) => [$t->id(), $t->string('key')->unique(), $t->text('value')->nullable(), $t->timestamps()]);
        (require base_path('vendor/spatie/laravel-permission/database/migrations/create_permission_tables.php.stub'))->up();
        Schema::table('permissions', fn (Blueprint $t) => $t->string('description')->nullable());
        (require base_path('database/migrations/2026_10_08_100000_create_popups_tables.php'))->up();
        (require base_path('database/migrations/2026_10_08_130000_create_pages_table.php'))->up();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        DB::table('payment_methods')->insert([['code' => 'cash', 'name_az' => 'Qapıda ödəniş', 'active' => true, 'sort_order' => 1], ['code' => 'old', 'name_az' => 'Köhnə üsul', 'active' => false, 'sort_order' => 2]]);
        DB::table('faqs')->insert(['title_az' => 'Çatdırılma nə qədər çəkir?', 'content_az' => '2 saata qədər.']);
    }

    public function test_all_info_pages_and_faq_render(): void
    {
        $this->get(route('front.page.terms'))->assertSee(route('front.installment'))->assertSee(route('front.bonus'));
        foreach (array_keys(Page::PAGES) as $key) {
            $page = Page::findByKey($key);
            $this->get(route('front.page.'.$key))->assertOk()->assertSee($page->title_az)->assertSee(route('front.faq'));
        }

        $delivery = $this->get(route('front.page.delivery'))->getContent();
        $this->assertStringContainsString('Qapıda ödəniş', $delivery);
        $this->assertStringNotContainsString('Köhnə üsul', $delivery);

        $this->assertStringContainsString('Pulsuz', $delivery);   // ayar yoxdur — pulsuz rejim

        $this->get(route('front.faq'))->assertOk()->assertSee('Çatdırılma nə qədər çəkir?')->assertSee('"@type":"FAQPage"', false);
    }

    public function test_page_html_is_sanitized(): void
    {
        $dirty = '<h2 class="x" onclick="alert(1)">Başlıq</h2><script>alert(1)</script><p>Mətn <a href="javascript:alert(1)">pis</a> '
            .'<a href="https://example.com" onmouseover="x()">xarici</a> <a href="/brands">daxili</a> <img src=x onerror=alert(1)></p>';
        $clean = Page::clean($dirty);

        $this->assertSame('<h2>Başlıq</h2>alert(1)<p>Mətn <a>pis</a> <a href="https://example.com" target="_blank" rel="noopener">xarici</a> <a href="/brands">daxili</a> </p>', $clean);
    }

    public function test_admin_updates_page_and_requires_permission(): void
    {
        $page = Page::findByKey('about');
        $user = User::forceCreate(['name' => 'Operator']);
        $this->actingAs($user, 'admin')->get(route('admin.pages.index'))->assertForbidden();

        Role::create(['name' => 'Admin', 'guard_name' => 'admin'])->givePermissionTo(Permission::findByName('site.pages', 'admin'));
        $user->assignRole('Admin');
        $this->actingAs($user->fresh(), 'admin')->get(route('admin.pages.index'))->assertOk()->assertSee('/about');
        $this->get(route('admin.pages.edit', $page))->assertOk();

        $this->post(route('admin.pages.update', $page), ['title_az' => 'Biz kimik', 'body_az' => '<p>Salam<script>x</script></p>', 'body_en' => '<p><br></p>'])
            ->assertRedirect(route('admin.pages.edit', $page));
        $page->refresh();
        $this->assertSame(['Biz kimik', '<p>Salamx</p>', null], [$page->title_az, $page->body_az, $page->body_en]);
        $this->get(route('front.page.about'))->assertSee('Biz kimik');
    }

    public function test_delivery_fee_comes_from_settings_and_zero_means_free(): void
    {
        DB::table('settings')->insert([['key' => 'delivery_mode', 'value' => 'paid'], ['key' => 'delivery_fee', 'value' => '5']]);
        $this->get(route('front.page.delivery'))->assertSee('5 ₼')->assertDontSee('Pulsuz');

        DB::table('settings')->where('key', 'delivery_fee')->update(['value' => '0']);
        $this->get(route('front.page.delivery'))->assertSee('Pulsuz');
    }

    public function test_bonus_and_installment_pages_use_admin_settings(): void
    {
        DB::table('settings')->insert([['key' => 'order_bonus_percent', 'value' => '7'], ['key' => 'registration_bonus_enabled', 'value' => '0']]);
        $bonus = $this->get(route('front.bonus'))->assertOk()->getContent();
        $this->assertStringContainsString('7%', $bonus);
        $this->assertStringContainsString('<h2>Bonus necə qazanılır?</h2>', $bonus);
        $this->assertStringContainsString('<li>Hər sifarişdən məhsulların dəyərinin 7%-i', $bonus);
        $this->assertStringNotContainsString('Qeydiyyat bonusu', $bonus);   // söndürülüb — fakt kartı yoxdur
        $this->assertStringContainsString('Bonusların istifadə müddəti məhdud deyil.', $bonus);   // :expiry, müddət söndürülüb

        DB::table('settings')->insert([['key' => 'bonus_expiry_enabled', 'value' => '1'], ['key' => 'bonus_order_expiry_days', 'value' => '120']]);
        $this->get(route('front.bonus'))->assertSee('120 gün ərzində istifadə olunmalıdır')->assertDontSee('qeydiyyat bonusu —');

        DB::table('credit_periods')->insert([
            ['month' => 3, 'interest_rate' => 0, 'min_amount' => null, 'active' => true],
            ['month' => 12, 'interest_rate' => 33.3, 'min_amount' => 200, 'active' => true],
            ['month' => 18, 'interest_rate' => 44.9, 'min_amount' => 200, 'active' => false],
        ]);
        DB::table('credit_term_items')->insert([['content_az' => 'Şəxsiyyət vəsiqəsi <b>lazımdır</b>', 'sort_order' => 1]]);
        $html = $this->get(route('front.installment'))->assertOk()->getContent();
        foreach (['3 ay', 'Faizsiz', '12 ay', '33.3%', '200 ₼-dan yuxarı', 'Şəxsiyyət vəsiqəsi &lt;b&gt;lazımdır&lt;/b&gt;'] as $text) {
            $this->assertStringContainsString($text, $html);
        }
        $this->assertStringNotContainsString('18 ay', $html);
    }

    public function test_text_to_html_escapes_and_builds_lists(): void
    {
        $html = \App\Http\Controllers\Frontend\PageController::textToHtml("Başlıq\nMətn <script>\n• bir\n• iki\n\nTək sətir");
        $this->assertSame('<h2>Başlıq</h2><p>Mətn &lt;script&gt;</p><ul><li>bir</li><li>iki</li></ul><p>Tək sətir</p>', $html);
    }

    public function test_public_referral_page_shows_terms_from_settings(): void
    {
        $this->get(route('front.referral'))->assertOk()->assertSee(__('referral_disabled_title'))->assertDontSee(__('referral_terms_title'));

        DB::table('settings')->insert([['key' => 'referral_enabled', 'value' => '1'], ['key' => 'referral_min_order_enabled', 'value' => '1'], ['key' => 'referral_min_order_amount', 'value' => '80']]);
        $this->assertStringContainsString(__('referral_term_min_order', ['amount' => '80']), $this->get(route('front.referral'))->getContent());
        $html = $this->get(route('front.referral'))->assertOk()->getContent();
        $this->assertStringContainsString(__('referral_terms_title'), $html);
        $this->assertStringContainsString(__('referral_term_self'), $html);
        $this->assertStringContainsString(route('front.login'), $html);
        $this->get(route('front.page.about'))->assertSee(route('front.referral'));   // yan menyu və footer
    }

    public function test_contact_details_come_from_settings_in_footer_and_contact_page(): void
    {
        $html = $this->get(route('front.page.contact'))->assertOk()->getContent();
        $this->assertStringContainsString('tel:+994123102255', $html);                       // standart
        $this->assertStringContainsString('https://www.instagram.com/parfumshop.az/', $html); // sosial ikon

        DB::table('settings')->insert([
            ['key' => 'contact_phone', 'value' => '012 555 66 77'],
            ['key' => 'contact_whatsapp', 'value' => '+994 50 111 22 33'],
            ['key' => 'contact_instagram', 'value' => ''],
            ['key' => 'contact_address_az', 'value' => 'Bakı, Nizami küç. 1'],
        ]);
        app()->forgetScopedInstances();   // real saytda hər sorğu ayrıca prosesdir
        $html = $this->get(route('front.page.contact'))->getContent();
        foreach (['tel:+994125556677', 'https://wa.me/994501112233', 'Bakı, Nizami küç. 1'] as $text) {
            $this->assertStringContainsString($text, $html);
        }
        $this->assertStringNotContainsString('instagram.com', $html);   // boş — ikon yoxdur
        $this->assertStringContainsString('facebook.com/ParfumShopAZ', $html);
    }
}
