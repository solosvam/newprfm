<?php

namespace Tests\Feature\Crm;

use App\Services\SmsService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class CrmCustomerSearchTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        Schema::create('permissions', fn (Blueprint $t) => [$t->id(), $t->string('name'), $t->string('guard_name'), $t->timestamps()]);
        Schema::create('customers', fn (Blueprint $t) => [$t->id(), $t->string('name'), $t->string('surname')->nullable(), $t->string('mobile'), $t->string('mobile_2', 12)->nullable(),
            $t->string('email')->nullable(), $t->integer('gender')->nullable(), $t->string('password')->nullable(), $t->boolean('active')->default(false), $t->string('source', 20)->nullable(), $t->timestamps()]);
        Schema::create('sms_templates', fn (Blueprint $t) => [$t->increments('id'), $t->string('code'), $t->string('name'), $t->text('template'), $t->boolean('active')->default(true)]);
        Schema::create('customer_credit_profiles', fn (Blueprint $t) => [$t->id(), $t->integer('customer_id'), $t->string('fin')->nullable(), $t->timestamps()]);
        DB::table('customers')->insert(['id' => 5, 'name' => 'Aysel', 'surname' => 'Məmmədova', 'mobile' => '994501234567']);

        $user = new \App\Models\User(['name' => 'Operator']);
        $user->id = 7;
        Gate::before(fn () => true);
        $this->actingAs($user, 'admin');
    }

    private function search(string $q): array
    {
        return $this->getJson('/admin/ajax/search-customer/crm?q='.urlencode($q))->assertOk()->json();
    }

    public function test_phone_formats_are_recognized(): void
    {
        foreach (['0501234567', '994501234567', '+994 50 123 45 67', '501234567'] as $q) {
            $r = $this->search($q);
            $this->assertSame('phone', $r['kind'], $q);
            $this->assertSame([5], array_column($r['results'], 'id'), $q);
        }
    }

    public function test_backup_phone_finds_customer(): void
    {
        DB::table('customers')->where('id', 5)->update(['mobile_2' => '994771112233']);
        $this->assertSame([5], array_column($this->search('077 111 22 33')['results'], 'id'));
    }

    public function test_not_found_tells_kind(): void
    {
        $this->assertSame(['kind' => 'phone', 'mobile' => '994551112233', 'results' => []], $this->search('0551112233'));
        $this->assertSame(['kind' => 'name', 'mobile' => null, 'results' => []], $this->search('Rəşad Əliyev'));
        $this->assertSame('fin', $this->search('.ABC1234')['kind']);
        $this->assertNull($this->search('abcdef')['kind']);              // format tanınmadı
        $this->assertNull($this->search('0051234567')['kind']);          // 994-dən sonra 0 olmur
    }

    public function test_create_customer_from_phone(): void
    {
        $sms = Mockery::mock(SmsService::class);
        $sms->shouldReceive('send')->once()->withArgs(fn ($to, $text) => $to === '994551112233' && str_contains($text, 'Sifreniz'));
        $this->app->instance(SmsService::class, $sms);

        $response = $this->post('/admin/crm/customer', ['mobile' => '055 111 22 33', 'name' => 'Rəşad', 'surname' => 'Əliyev', 'gender' => '1', 'send_password' => '1']);

        $customer = DB::table('customers')->where('mobile', '994551112233')->first();
        $this->assertNotNull($customer);
        $response->assertRedirect(route('admin.crm.customer', $customer->id));
        $this->assertSame(1, (int) $customer->active);
        $this->assertNull($customer->email);
        $this->assertTrue(Hash::check('x', Hash::make('x')) && strlen((string) $customer->password) > 20); // hash, açıq şifrə yox
    }

    public function test_create_customer_sms_uses_template(): void
    {
        DB::table('sms_templates')->insert(['code' => 'crm_customer_created', 'name' => 'x', 'template' => 'Salam {fullname}, kod: {password}', 'active' => 1]);
        $sms = Mockery::mock(SmsService::class);
        $sms->shouldReceive('send')->once()->withArgs(fn ($to, $text) => (bool) preg_match('/^Salam Rəşad Əliyev, kod: \d{6}$/u', $text));
        $this->app->instance(SmsService::class, $sms);

        $this->post('/admin/crm/customer', ['mobile' => '055 111 22 33', 'name' => 'Rəşad', 'surname' => 'Əliyev', 'gender' => '1', 'send_password' => '1']);
    }

    public function test_create_customer_sms_falls_back_when_password_removed_from_template(): void
    {
        DB::table('sms_templates')->insert(['code' => 'crm_customer_created', 'name' => 'x', 'template' => 'Salam {fullname}', 'active' => 1]);
        $sms = Mockery::mock(SmsService::class);
        $sms->shouldReceive('send')->once()->withArgs(fn ($to, $text) => str_contains($text, 'Sifreniz: '));
        $this->app->instance(SmsService::class, $sms);

        $this->post('/admin/crm/customer', ['mobile' => '055 111 22 33', 'name' => 'Rəşad', 'surname' => 'Əliyev', 'gender' => '1', 'send_password' => '1']);
    }

    public function test_create_customer_validation(): void
    {
        $this->app->instance(SmsService::class, Mockery::mock(SmsService::class)); // SMS getməməlidir
        $this->from('/admin/crm')->post('/admin/crm/customer', ['mobile' => '0501234567', 'name' => 'A', 'surname' => 'B', 'gender' => '0'])
            ->assertRedirect('/admin/crm')->assertSessionHasErrorsIn('createCustomer', ['mobile']);   // artıq var
        $this->from('/admin/crm')->post('/admin/crm/customer', ['mobile' => '994051234567', 'name' => 'A', 'surname' => 'B', 'gender' => '0'])
            ->assertSessionHasErrorsIn('createCustomer', ['mobile']);                                 // 994-dən sonra 0
        $this->assertSame(1, DB::table('customers')->count());
    }

    public function test_address_is_edited_and_becomes_default(): void
    {
        Schema::create('cities', fn (Blueprint $t) => [$t->id(), $t->string('name'), $t->boolean('active')->default(true)]);
        Schema::create('customer_addresses', fn (Blueprint $t) => [$t->id(), $t->integer('customer_id'), $t->string('title')->nullable(),
            $t->integer('city_id')->nullable(), $t->string('city')->nullable(), $t->string('address')->nullable(), $t->string('building')->nullable(),
            $t->string('entrance')->nullable(), $t->string('floor')->nullable(), $t->string('apartment')->nullable(), $t->text('note')->nullable(),
            $t->boolean('is_default')->default(false), $t->timestamps()]);
        DB::table('cities')->insert([['id' => 1, 'name' => 'Bakı'], ['id' => 2, 'name' => 'Sumqayıt']]);
        DB::table('customer_addresses')->insert([
            ['id' => 10, 'customer_id' => 5, 'title' => 'Ev', 'city_id' => 1, 'city' => 'Bakı', 'address' => 'Nizami 1', 'is_default' => 1],
            ['id' => 11, 'customer_id' => 5, 'title' => 'İş', 'city_id' => 1, 'city' => 'Bakı', 'address' => 'Füzuli 2', 'is_default' => 0],
            ['id' => 12, 'customer_id' => 6, 'title' => 'Başqası', 'city_id' => 1, 'city' => 'Bakı', 'address' => 'X', 'is_default' => 1],
        ]);

        $this->postJson('/admin/crm/customer/5/address/11', ['title' => '', 'city_id' => 2, 'address' => 'Sülh 5', 'floor' => '3', 'is_default' => 1])
            ->assertOk()->assertJsonPath('ok', true);
        $edited = DB::table('customer_addresses')->find(11);
        $this->assertSame(['Sumqayıt', 'Sülh 5', '3', 1], [$edited->city, $edited->address, $edited->floor, (int) $edited->is_default]);
        $this->assertSame(0, (int) DB::table('customer_addresses')->where('id', 10)->value('is_default'), 'əvvəlki əsas ünvan');
        $this->assertSame(1, (int) DB::table('customer_addresses')->where('id', 12)->value('is_default'), 'başqa müştəriyə toxunulmur');

        $this->postJson('/admin/crm/customer/5/address/11', ['city_id' => 2, 'address' => ''])->assertStatus(422)->assertJsonValidationErrors('address');
        $this->postJson('/admin/crm/customer/5/address/12', ['city_id' => 1, 'address' => 'Y'])->assertNotFound();
    }
}

