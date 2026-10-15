<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/** Müvəqqəti sıfırlama səhifəsi: yalnız Super admin, bölmə və "SIFIRLA" təsdiqi olmadan heç nə silinmir */
class ProductionResetTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'cache.default' => 'array']);
        DB::purge('sqlite');
        Schema::create('users', fn (Blueprint $t) => [$t->id(), $t->string('name'), $t->string('surname')->nullable(), $t->boolean('active')->default(true), $t->timestamps()]);
        Schema::create('money_movements', fn (Blueprint $t) => [$t->id(), $t->unsignedBigInteger('reversal_of_id')->nullable(), $t->decimal('amount', 12, 2)]);
        (require base_path('vendor/spatie/laravel-permission/database/migrations/create_permission_tables.php.stub'))->up();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Role::create(['name' => 'Super admin', 'guard_name' => 'admin']);
        Role::create(['name' => 'Admin', 'guard_name' => 'admin']);
        DB::table('money_movements')->insert([['id' => 1, 'reversal_of_id' => null, 'amount' => 10], ['id' => 2, 'reversal_of_id' => 1, 'amount' => -10]]);
    }

    private function user(string $role): User
    {
        $user = User::forceCreate(['name' => $role]);
        $user->assignRole($role);

        return $user;
    }

    public function test_only_super_admin_can_open_or_run_it(): void
    {
        $this->actingAs($this->user('Admin'), 'admin')->get(route('admin.reset'))->assertForbidden();
        $this->post(route('admin.reset.run'), ['sections' => ['finance'], 'confirm' => 'SIFIRLA'])->assertForbidden();
        $this->assertSame(2, DB::table('money_movements')->count());
    }

    public function test_nothing_is_deleted_without_the_confirmation_word_and_finance_reset_handles_reversals(): void
    {
        $this->actingAs($this->user('Super admin'), 'admin');

        $this->post(route('admin.reset.run'), ['sections' => ['finance'], 'confirm' => 'sifirla'])->assertSessionHasErrors('confirm');
        $this->post(route('admin.reset.run'), ['confirm' => 'SIFIRLA'])->assertSessionHasErrors('sections');
        $this->assertSame(2, DB::table('money_movements')->count());

        // Geri alma eyni cədvələ istinad edir — silmə ona görə dayanmamalıdır
        $this->post(route('admin.reset.run'), ['sections' => ['finance'], 'confirm' => 'SIFIRLA'])->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->assertSame(0, DB::table('money_movements')->count());
    }
}
