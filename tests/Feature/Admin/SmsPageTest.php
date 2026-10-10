<?php

namespace Tests\Feature\Admin;

use App\Http\Controllers\Backend\SmsTemplateController;
use App\Models\SmsLog;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class SmsPageTest extends TestCase
{
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'cache.default' => 'array',
            'services.parfumshop_sms.login' => 'login', 'services.parfumshop_sms.password' => 'secret']);
        DB::purge('sqlite');
        Schema::create('users', fn (Blueprint $t) => [$t->id(), $t->string('name'), $t->string('surname')->nullable(), $t->boolean('active')->default(true), $t->timestamps()]);
        Schema::create('settings', fn (Blueprint $t) => [$t->id(), $t->string('key')->unique(), $t->text('value')->nullable(), $t->timestamps()]);
        (require base_path('vendor/spatie/laravel-permission/database/migrations/create_permission_tables.php.stub'))->up();
        foreach (['2026_09_21_150000_create_sms_templates_table', '2026_09_30_100000_create_sms_logs_and_warehouse_sms', '2026_10_10_180000_add_delivery_status_to_sms_logs'] as $file) {
            (require database_path('migrations/'.$file.'.php'))->up();
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::create(['name' => 'system.sms', 'guard_name' => 'admin']);
        Role::create(['name' => 'Admin', 'guard_name' => 'admin'])->givePermissionTo('system.sms');
        $this->admin = User::forceCreate(['name' => 'Rufat']);
        $this->admin->assignRole('Admin');
        Http::fake(['apps.lsim.az/quicksms/v1/balance*' => Http::response(['obj' => 1543, 'errorCode' => 0])]);
    }

    public function test_templates_tab_shows_balance_and_which_templates_are_not_wired(): void
    {
        $this->actingAs($this->admin, 'admin')->get(route('admin.sms-template.index'))
            ->assertOk()
            ->assertSee('1 543')
            ->assertSee('Kuryer "Yola çıxdım" basanda')
            ->assertSee('Qoşulmayıb');
    }

    public function test_every_seeded_template_has_a_usage_entry(): void
    {
        // Şablon kodları migrasiyalardan oxunur (bəzi migrasiyalar yalnız MySQL-də işləyir — burada işə salınmır)
        $codes = [];
        foreach (glob(database_path('migrations/*.php')) as $file) {
            $source = file_get_contents($file);
            if (!str_contains($source, 'sms_templates')) {
                continue;
            }
            preg_match_all("/'code'\\s*=>\\s*'([a-z_]+)'|'(warehouse_[a-z]+)'\\s*=>\\s*\\[/", $source, $m);
            $codes = array_merge($codes, array_filter($m[1]), array_filter($m[2]));
        }
        // Sonradan silinən şablon (2026_10_10_200000_sms_templates_name_variable)
        $codes = array_diff(array_unique($codes), ['website_order_accepted']);

        $this->assertGreaterThanOrEqual(11, count($codes));
        $this->assertSame([], array_values(array_diff($codes, array_keys(SmsTemplateController::USAGE))), 'Yeni şablon əlavə olunub — SmsTemplateController::USAGE-ə yazın');
    }

    public function test_name_migration_drops_the_unused_template_and_switches_to_the_name_variable(): void
    {
        (require database_path('migrations/2026_10_10_200000_sms_templates_name_variable.php'))->up();

        $this->assertFalse(DB::table('sms_templates')->where('code', 'website_order_accepted')->exists());
        $this->assertSame(0, DB::table('sms_templates')->where('template', 'like', '%{fullname}%')->count());
        $this->assertStringStartsWith('Hormetli {name},', DB::table('sms_templates')->where('code', 'order_sent')->value('template'));
    }

    public function test_problems_tab_lists_only_failed_and_undelivered_messages(): void
    {
        SmsLog::create(['context' => 'otp', 'msisdn' => '994500000001', 'message' => 'catdi', 'status' => SmsLog::SENT, 'delivery_status' => 101]);
        SmsLog::create(['context' => 'otp', 'msisdn' => '994500000002', 'message' => 'catmadi', 'status' => SmsLog::SENT, 'delivery_status' => 102]);
        SmsLog::create(['context' => 'order_sent', 'msisdn' => '994500000003', 'message' => 'getmedi', 'status' => SmsLog::FAILED, 'error' => 'SMS balansı bitib']);

        $this->actingAs($this->admin, 'admin')->get(route('admin.sms-template.index', ['tab' => 'problems']))
            ->assertOk()->assertSee('994500000002')->assertSee('994500000003')->assertSee('SMS balansı bitib')->assertDontSee('994500000001');

        $this->actingAs($this->admin, 'admin')->get(route('admin.sms-template.index', ['tab' => 'log', 'q' => '0000001']))
            ->assertOk()->assertSee('994500000001')->assertDontSee('994500000002');
    }
}
