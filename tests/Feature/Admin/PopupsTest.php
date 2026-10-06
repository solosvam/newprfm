<?php

namespace Tests\Feature\Admin;

use App\Models\Customer\Customer;
use App\Models\Popup;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PopupsTest extends TestCase
{
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'cache.default' => 'array']);
        DB::purge('sqlite');
        Carbon::setTestNow('2026-10-08 12:00:00');

        Schema::create('users', fn (Blueprint $t) => [$t->id(), $t->string('name'), $t->string('surname')->nullable(), $t->string('email')->nullable(), $t->string('password')->nullable(), $t->boolean('active')->default(true), $t->timestamps()]);
        Schema::create('customers', fn (Blueprint $t) => [$t->id(), $t->string('name')->nullable(), $t->string('surname')->nullable(), $t->string('mobile')->nullable(), $t->boolean('active')->default(true), $t->timestamps()]);
        (require base_path('vendor/spatie/laravel-permission/database/migrations/create_permission_tables.php.stub'))->up();
        Schema::table('permissions', fn (Blueprint $t) => $t->string('description')->nullable());
        (require base_path('database/migrations/2026_10_08_100000_create_popups_tables.php'))->up();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Role::create(['name' => 'Admin', 'guard_name' => 'admin'])->givePermissionTo(Permission::findByName('site.popups', 'admin'));
        $this->admin = User::forceCreate(['name' => 'Rufat']);
        $this->admin->assignRole('Admin');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function popup(array $attributes = []): Popup
    {
        return Popup::forceCreate($attributes + ['name' => 'Test', 'title_az' => 'Salam', 'active' => true]);
    }

    public function test_visitor_filter_by_audience_page_period_and_dismissal(): void
    {
        $all = $this->popup(['name' => 'all']);
        $guests = $this->popup(['name' => 'guests', 'audience' => 'guests']);
        $customers = $this->popup(['name' => 'customers', 'audience' => 'customers']);
        $home = $this->popup(['name' => 'home', 'pages' => 'home']);
        $this->popup(['name' => 'checkout', 'pages' => 'cart,checkout,success']);
        $this->popup(['name' => 'future', 'starts_at' => '2026-10-09 00:00']);
        $this->popup(['name' => 'ended', 'ends_at' => '2026-10-08 11:00']);
        $this->popup(['name' => 'off', 'active' => false]);

        $names = fn (?int $customer, ?string $page) => Popup::forVisitor($customer, $page)->pluck('name')->sort()->values()->all();

        $this->assertSame(['all', 'guests'], $names(null, null));
        $this->assertSame(['all', 'guests', 'home'], $names(null, 'home'));
        $this->assertSame(['all', 'checkout', 'guests'], $names(null, 'success'));
        $this->assertSame(['all', 'customers'], $names(7, null));

        DB::table('popup_dismissals')->insert(['popup_id' => $all->id, 'customer_id' => 7]);
        $this->assertSame(['customers', 'home'], $names(7, 'home'));
    }

    public function test_events_count_per_day_and_dismiss_is_remembered_for_customer(): void
    {
        $popup = $this->popup();
        $this->post(route('popup.event', $popup), ['type' => 'shown'])->assertNoContent();
        $this->post(route('popup.event', $popup), ['type' => 'shown'])->assertNoContent();
        $this->post(route('popup.event', $popup), ['type' => 'click'])->assertNoContent();
        $this->post(route('popup.event', $popup), ['type' => 'dismiss'])->assertNoContent();   // qonaq — bazaya yazılmır
        $this->post(route('popup.event', $popup), ['type' => 'hack'])->assertSessionHasErrors('type');

        $row = DB::table('popup_stats')->where('popup_id', $popup->id)->first();
        $this->assertSame([2, 1, 0, 1], [(int) $row->shown, (int) $row->clicked, (int) $row->closed, (int) $row->dismissed]);
        $this->assertSame(0, DB::table('popup_dismissals')->count());

        $customer = Customer::forceCreate(['name' => 'Aysel', 'mobile' => '0501112233']);
        $this->actingAs($customer)->post(route('popup.event', $popup), ['type' => 'dismiss'])->assertNoContent();
        $this->actingAs($customer)->post(route('popup.event', $popup), ['type' => 'dismiss'])->assertNoContent();
        $this->assertSame(1, DB::table('popup_dismissals')->where('customer_id', $customer->id)->count());
        $this->assertTrue(Popup::forVisitor($customer->id, true)->isEmpty());
    }

    public function test_admin_create_validates_and_lists_with_stats(): void
    {
        $this->actingAs($this->admin, 'admin');
        $base = ['name' => 'Novruz', 'position_desktop' => 'center', 'position_mobile' => 'bar', 'audience' => 'all',
            'pages_all' => 0, 'pages' => ['home', 'success'], 'frequency' => 'daily', 'delay_seconds' => 5, 'active' => 1];

        $this->post(route('admin.popups.store'), $base)->assertSessionHasErrors('title_az');
        $this->post(route('admin.popups.store'), array_merge($base, ['title_az' => 'Endirim', 'pages' => []]))->assertSessionHasErrors('pages');
        $this->post(route('admin.popups.store'), $base + ['title_az' => 'Endirim', 'button_az' => 'Bax'])->assertSessionHasErrors('link_url');
        $this->post(route('admin.popups.store'), $base + ['title_az' => 'Endirim', 'link_url' => 'javascript:alert(1)'])->assertSessionHasErrors('link_url');
        $this->post(route('admin.popups.store'), $base + ['title_az' => 'Endirim', 'button_az' => 'Bax', 'link_url' => '/brands',
            'starts_at' => '2026-10-08T10:00', 'ends_at' => '2026-10-20T23:59'])->assertRedirect(route('admin.popups.index'));

        $popup = Popup::sole();
        $this->assertSame(['home,success', 'daily', 5, true], [$popup->pages, $popup->frequency, $popup->delay_seconds, $popup->active]);
        $this->assertSame('2026-10-20 23:59', $popup->ends_at->format('Y-m-d H:i'));
        DB::table('popup_stats')->insert(['popup_id' => $popup->id, 'day' => '2026-10-08', 'shown' => 40, 'clicked' => 4]);

        $html = $this->get(route('admin.popups.index'))->assertOk()->getContent();
        $this->assertStringContainsString('Novruz', $html);
        $this->assertStringContainsString('10%', $html);
        $this->assertStringContainsString('Ana səhifə, Uğurlu sifariş', $html);
        $this->get(route('admin.popups.edit', $popup))->assertOk()->assertSee('Son 14 gün');
        $this->get(route('admin.popups.create'))->assertOk();

        $this->post(route('admin.popups.update', $popup), array_merge($base, ['title_az' => 'Yeni', 'active' => 0, 'pages_all' => 1]))->assertRedirect();
        $this->assertFalse($popup->fresh()->active);
        $this->assertSame('all', $popup->fresh()->pages);
        $this->delete(route('admin.popups.destroy', $popup))->assertRedirect(route('admin.popups.index'));
        $this->assertSame(0, DB::table('popup_stats')->count());
    }

    public function test_create_form_survives_old_string_pages_input(): void
    {
        // köhnə formanın (select) flash olunmuş old('pages') = 'home' dəyəri səhifəni sındırmamalıdır
        $this->actingAs($this->admin, 'admin')->withSession(['_old_input' => ['pages' => 'home']])
            ->get(route('admin.popups.create'))->assertOk();
    }

    public function test_admin_without_permission_is_forbidden(): void
    {
        $user = User::forceCreate(['name' => 'Operator']);
        $this->actingAs($user, 'admin')->get(route('admin.popups.index'))->assertForbidden();
    }
}
