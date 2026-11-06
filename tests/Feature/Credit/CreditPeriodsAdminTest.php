<?php

namespace Tests\Feature\Credit;

use App\Models\Credit\CreditPeriod;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/** Admin → Kredit faizləri: çıxılacaq faiz (Ferrum-un ümumi məbləğdən çıxdığı) yadda qalır, boş — null */
class CreditPeriodsAdminTest extends TestCase
{
    public function test_bank_fee_rate_is_saved(): void
    {
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'cache.default' => 'array']);
        DB::purge('sqlite');
        (require base_path('vendor/spatie/laravel-permission/database/migrations/create_permission_tables.php.stub'))->up();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::create(['name' => 'credit.menu', 'guard_name' => 'admin']);
        Schema::create('credit_periods', fn (Blueprint $t) => [$t->id(), $t->integer('month'), $t->decimal('interest_rate', 5, 2), $t->decimal('min_amount', 10, 2)->nullable(),
            $t->boolean('active')->default(true), $t->integer('sort_order')->default(0), $t->timestamps()]);
        (require database_path('migrations/2026_11_06_160000_add_bank_fee_rate_to_credit_periods.php'))->up();

        $admin = (new User)->forceFill(['id' => 10, 'name' => 'Admin']);
        $admin->setRelation('permissions', Permission::all());
        $admin->setRelation('roles', collect());

        $this->actingAs($admin, 'admin')->post(route('admin.credit.periods.update'), ['periods' => [
            ['month' => 3, 'interest_rate' => 0, 'bank_fee_rate' => '', 'min_amount' => '', 'active' => 1],
            ['month' => 6, 'interest_rate' => 17.6, 'bank_fee_rate' => 16, 'min_amount' => '', 'active' => 1],
        ]])->assertSessionHas('success');

        $this->assertSame([3 => null, 6 => '16.00'], CreditPeriod::orderBy('month')->pluck('bank_fee_rate', 'month')->all());
        $this->post(route('admin.credit.periods.update'), ['periods' => [['month' => 6, 'interest_rate' => 17.6, 'bank_fee_rate' => 120]]])
            ->assertSessionHasErrors('periods.0.bank_fee_rate');
        $this->get(route('admin.credit.periods'))->assertOk()->assertSee('Çıxılacaq faiz');
    }
}
