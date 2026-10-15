<?php

namespace Tests\Feature\Crm;

use App\Models\Customer\Customer;
use App\Models\Customer\CustomerAddress;
use App\Models\Order\Order;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** "Müştəri ilə əlaqə saxlayın və yeri dəqiqləşdirin": müştəriyə bir xüsusi ünvan, sifarişdə görüş yeri */
class OnCallAddressTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        Schema::create('customers', fn (Blueprint $t) => [$t->id(), $t->string('name')->nullable(), $t->string('surname')->nullable(), $t->string('mobile')->nullable(), $t->timestamps()]);
        Schema::create('customer_addresses', fn (Blueprint $t) => [$t->id(), $t->integer('customer_id'), $t->boolean('on_call')->default(false), $t->string('title')->nullable(),
            $t->integer('city_id')->nullable(), $t->string('city')->nullable(), $t->string('address'), $t->boolean('is_default')->default(false), $t->timestamps()]);
        Schema::create('orders', fn (Blueprint $t) => [$t->id(), $t->integer('customer_id'), $t->unsignedBigInteger('customer_address_id')->nullable(),
            $t->string('meeting_place', 500)->nullable(), $t->unsignedBigInteger('order_status_id')->default(1), $t->string('order_no')->default('PS1'), $t->timestamps()]);
        // Admin panel middleware icazələri bazadan oxuyur
        (require base_path('vendor/spatie/laravel-permission/database/migrations/create_permission_tables.php.stub'))->up();
        DB::table('customers')->insert(['id' => 1, 'name' => 'Aysel']);
        DB::table('customer_addresses')->insert(['customer_id' => 1, 'title' => 'Ev', 'city' => 'Bakı', 'address' => 'Nizami 1']);
    }

    public function test_on_call_address_is_created_once_and_hidden_from_the_address_list(): void
    {
        $customer = Customer::findOrFail(1);

        $first = CustomerAddress::onCallFor($customer);
        $second = CustomerAddress::onCallFor($customer);

        $this->assertSame($first->id, $second->id, 'ikinci dəfə yeni ünvan yaranmır');
        $this->assertSame('📞 Müştəri ilə əlaqə saxlayın və yeri dəqiqləşdirin', $first->label);
        $this->assertSame(['Ev'], $customer->addresses()->pluck('title')->all(), 'saytda və seçimlərdə görünmür');
        $this->assertSame(2, DB::table('customer_addresses')->count());
    }

    public function test_operator_writes_and_clears_the_meeting_place(): void
    {
        Gate::before(fn () => true);
        $user = new \App\Models\User(['name' => 'Operator']);
        $user->id = 7;
        $this->actingAs($user, 'admin');
        $address = CustomerAddress::onCallFor(Customer::findOrFail(1));
        $order = Order::create(['customer_id' => 1, 'customer_address_id' => $address->id]);

        $this->post(route('admin.crm.order.meeting-place', [1, $order]), ['meeting_place' => ' 28 May metrosu, 15:00 '])->assertSessionHas('success');
        $this->assertSame('28 May metrosu, 15:00', $order->fresh()->meeting_place);

        $this->post(route('admin.crm.order.meeting-place', [1, $order]), ['meeting_place' => ''])->assertSessionHas('success');
        $this->assertNull($order->fresh()->meeting_place);

        // Başqa müştərinin sifarişi — 404
        DB::table('customers')->insert(['id' => 2]);
        $this->post(route('admin.crm.order.meeting-place', [2, $order]), ['meeting_place' => 'x'])->assertNotFound();
    }
}
