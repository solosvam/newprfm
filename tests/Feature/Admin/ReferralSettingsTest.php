<?php

namespace Tests\Feature\Admin;

use App\Models\Setting;
use App\Models\User;
use App\Services\Referral\ReferralSettings;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ReferralSettingsTest extends TestCase
{
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'cache.default' => 'array']);
        DB::purge('sqlite');
        Schema::create('users', fn (Blueprint $t) => [$t->id(), $t->string('name'), $t->string('surname')->nullable(), $t->boolean('active')->default(true), $t->timestamps()]);
        Schema::create('settings', fn (Blueprint $t) => [$t->id(), $t->string('key')->unique(), $t->text('value')->nullable(), $t->timestamps()]);
        (require base_path('vendor/spatie/laravel-permission/database/migrations/create_permission_tables.php.stub'))->up();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::create(['name' => 'system.settings', 'guard_name' => 'admin']);
        Role::create(['name' => 'Admin', 'guard_name' => 'admin'])->givePermissionTo('system.settings');
        $this->admin = User::forceCreate(['name' => 'Rufat']);
        $this->admin->assignRole('Admin');
    }

    /** Referal bölməsinin formu (açarların standart dəyərləri ilə) */
    private function base(array $referral = []): array
    {
        return $referral + [
            'referral_enabled' => 0, 'referral_invitee_mode' => 'discount', 'referral_discount_with_promo' => 0,
            'referral_min_order_enabled' => 0, 'referral_installment_allowed' => 0, 'referral_expiry_enabled' => 0,
            'referral_limit_enabled' => 0, 'referral_limit_mode' => 'no_reward', 'referral_inviter_requires_order' => 0,
            'referral_cookie_days' => 30,
        ];
    }

    public function test_defaults_when_nothing_saved(): void
    {
        $s = app(ReferralSettings::class);
        $this->assertFalse($s->enabled());
        $this->assertSame(10.0, $s->referrerAmount());
        $this->assertSame(17.0, $s->inviteeAmount());
        $this->assertSame(ReferralSettings::MODE_DISCOUNT, $s->inviteeMode());
        $this->assertNull($s->minOrderAmount());
        $this->assertNull($s->inviteLimit());
        $this->assertNull($s->referrerExpiryDays());
        $this->assertStringContainsString('17 ₼', $s->shareText('az'));
    }

    public function test_page_shows_referral_section(): void
    {
        $this->actingAs($this->admin, 'admin')->get(route('admin.settings.referral'))
            ->assertOk()
            ->assertSee('Dostunu dəvət et (referal)')
            ->assertSee('Limit dolanda: link bağlanır');
    }

    public function test_saves_all_referral_settings(): void
    {
        $this->actingAs($this->admin, 'admin')->post(route('admin.settings.referral.update'), $this->base([
            'referral_enabled' => 1, 'referral_referrer_amount' => 12, 'referral_invitee_amount' => 20,
            'referral_invitee_mode' => 'balance', 'referral_discount_with_promo' => 1,
            'referral_min_order_enabled' => 1, 'referral_min_order_amount' => 60,
            'referral_installment_allowed' => 1,
            'referral_expiry_enabled' => 1, 'referral_referrer_expiry_days' => 90, 'referral_invitee_expiry_days' => 120,
            'referral_limit_enabled' => 1, 'referral_limit_count' => 50, 'referral_limit_mode' => 'block',
            'referral_inviter_requires_order' => 1,
            'referral_cookie_days' => 14, 'referral_share_text_en' => 'Take :amount ₼',
        ]))->assertRedirect()->assertSessionHasNoErrors();

        $s = new ReferralSettings();
        $this->assertTrue($s->enabled());
        $this->assertSame(12.0, $s->referrerAmount());
        $this->assertSame(ReferralSettings::MODE_BALANCE, $s->inviteeMode());
        $this->assertFalse($s->discountCombinesWithPromo()); // balans rejimində mənasızdır
        $this->assertSame(60.0, $s->minOrderAmount());
        $this->assertTrue($s->installmentAllowed());
        $this->assertSame(90, $s->referrerExpiryDays());
        $this->assertSame(120, $s->inviteeExpiryDays());
        $this->assertSame(50, $s->inviteLimit());
        $this->assertSame(ReferralSettings::LIMIT_BLOCK, $s->limitMode());
        $this->assertTrue($s->inviterRequiresOrder());
        $this->assertSame(14, $s->cookieDays());
        $this->assertSame('Take 20 ₼', $s->shareText('en'));
    }

    public function test_enabled_requires_amounts_and_toggles_require_values(): void
    {
        $this->actingAs($this->admin, 'admin')->post(route('admin.settings.referral.update'), $this->base([
            'referral_enabled' => 1, 'referral_referrer_amount' => '', 'referral_invitee_amount' => 17,
            'referral_limit_enabled' => 1, 'referral_limit_count' => '',
        ]))->assertSessionHasErrors(['referral_referrer_amount', 'referral_limit_count']);
    }

    public function test_og_image_upload_and_remove(): void
    {
        $dir = public_path(ReferralSettings::OG_IMAGE_DIR);
        $this->actingAs($this->admin, 'admin')->post(route('admin.settings.referral.update'), $this->base([
            'referral_og_image_file' => UploadedFile::fake()->image('og.jpg', 1200, 630),
        ]))->assertSessionHasNoErrors();
        $file = Setting::valueOf('referral_og_image');
        $this->assertNotEmpty($file);
        $this->assertFileExists($dir.'/'.$file);
        $this->assertStringContainsString(ReferralSettings::OG_IMAGE_DIR.'/'.$file, (new ReferralSettings())->ogImageUrl());

        $this->post(route('admin.settings.referral.update'), $this->base(['referral_og_image_remove' => 1]))->assertSessionHasNoErrors();
        $this->assertNull((new ReferralSettings())->ogImageUrl());
        File::delete($dir.'/'.$file);
    }

    public function test_settings_root_redirects_and_each_section_renders(): void
    {
        $this->actingAs($this->admin, 'admin')->get(route('admin.settings.index'))->assertRedirect(route('admin.settings.bonuses'));
        foreach (['bonuses' => 'Bonusun istifadə müddəti', 'referral' => 'Dostunu dəvət et', 'orders' => 'Çatdırılma qaydası', 'banners' => 'Nisbət önizləməsi'] as $section => $text) {
            $this->get(route('admin.settings.'.$section))->assertOk()->assertSee($text);
        }
        $this->get('/admin/settings/unknown')->assertNotFound();
    }

    public function test_sections_save_only_their_own_fields(): void
    {
        Setting::set('delivery_mode', 'paid');
        $this->actingAs($this->admin, 'admin')->post(route('admin.settings.bonuses.update'), [
            'order_bonus_percent' => 4, 'registration_bonus_enabled' => 0,
            'bonus_pay_limit_enabled' => 1, 'bonus_pay_percent' => 30,
            'bonus_expiry_enabled' => 1, 'bonus_order_expiry_days' => 180, 'bonus_registration_expiry_days' => 60,
            'bonus_terms_az' => 'Hər sifarişdən :percent% bonus, qeydiyyatda :registration ₼.',
        ])->assertRedirect(route('admin.settings.bonuses'))->assertSessionHasNoErrors();

        $this->assertSame('4', (string) Setting::valueOf('order_bonus_percent'));
        $this->assertSame('paid', Setting::valueOf('delivery_mode')); // başqa bölmə toxunulmadı
        $this->assertSame(30.0, app(\App\Services\BonusService::class)->payLimitPercent());
        $this->assertSame(180, app(\App\Services\BonusService::class)->expiryDays('earn'));
        $this->assertSame(60, app(\App\Services\BonusService::class)->expiryDays('register'));
        $this->assertSame('Hər sifarişdən 4% bonus, qeydiyyatda 10 ₼.', app(\App\Services\BonusService::class)->terms('az'));
        $this->assertStringContainsString('4% of the product value', app(\App\Services\BonusService::class)->terms('en')); // boş — standart mətn

        $this->post(route('admin.settings.bonuses.update'), [
            'order_bonus_percent' => 4, 'registration_bonus_enabled' => 0, 'bonus_pay_limit_enabled' => 1, 'bonus_pay_percent' => 150,
            'bonus_expiry_enabled' => 1, 'bonus_order_expiry_days' => '',
        ])->assertSessionHasErrors(['bonus_pay_percent', 'bonus_terms_az', 'bonus_order_expiry_days']);

        $this->post(route('admin.settings.bonuses.update'), [
            'order_bonus_percent' => 4, 'registration_bonus_enabled' => 0, 'bonus_pay_limit_enabled' => 0, 'bonus_terms_az' => 'x',
            'bonus_expiry_enabled' => 0,
        ])->assertSessionHasNoErrors();
        $this->assertNull(app(\App\Services\BonusService::class)->expiryDays('earn'));
        $this->assertNull(app(\App\Services\BonusService::class)->payLimitPercent());
    }
}
