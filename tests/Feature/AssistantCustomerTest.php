<?php

namespace Tests\Feature;

use App\Http\Controllers\Backend\AssistantController;
use App\Services\SmsService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

/** Operator yan paneli: WhatsApp nömrəsi ilə müştəri */
class AssistantCustomerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        Schema::create('permissions', fn (Blueprint $t) => [$t->id(), $t->string('name'), $t->string('guard_name'), $t->timestamps()]);
        Schema::create('customers', fn (Blueprint $t) => [$t->id(), $t->string('name'), $t->string('surname')->nullable(), $t->string('mobile'),
            $t->string('email')->nullable(), $t->integer('gender')->nullable(), $t->string('password')->nullable(), $t->boolean('active')->default(false),
            $t->decimal('bonus_balance', 10, 2)->default(0), $t->timestamps()]);
        Schema::create('sms_templates', fn (Blueprint $t) => [$t->increments('id'), $t->string('code'), $t->string('name'), $t->text('template'), $t->boolean('active')->default(true)]);
        Schema::create('customer_credit_profiles', fn (Blueprint $t) => [$t->id(), $t->integer('customer_id'), $t->string('fin')->nullable(),
            $t->string('father_name')->nullable(), $t->string('id_card_series')->nullable(), $t->string('id_card_number')->nullable(),
            $t->string('relative_1_name')->nullable(), $t->string('relative_1_phone')->nullable(), $t->string('relative_2_name')->nullable(),
            $t->string('relative_2_phone')->nullable(), $t->string('workplace_name')->nullable(), $t->decimal('salary', 10, 2)->nullable(),
            $t->string('id_card_front')->nullable(), $t->string('id_card_back')->nullable(), $t->timestamps()]);
        Schema::create('order_statuses', fn (Blueprint $t) => [$t->id(), $t->string('code'), $t->string('name_az')]);
        Schema::create('orders', fn (Blueprint $t) => [$t->id(), $t->integer('customer_id'), $t->string('order_no'), $t->decimal('total', 10, 2),
            $t->integer('order_status_id')->nullable(), $t->timestamps()]);

        DB::table('customers')->insert(['id' => 5, 'name' => 'Aysel', 'surname' => 'Məmmədova', 'mobile' => '994603831010', 'bonus_balance' => 12.5]);
        DB::table('order_statuses')->insert(['id' => 1, 'code' => 'sent', 'name_az' => 'Yolda']);
        foreach ([1, 2, 3, 4] as $i) {
            DB::table('orders')->insert(['customer_id' => 5, 'order_no' => "PS-{$i}", 'total' => 100 + $i, 'order_status_id' => 1,
                'created_at' => now()->subDays(10 - $i), 'updated_at' => now()]);
        }

        $user = new \App\Models\User(['name' => 'Operator']);
        $user->id = 7;
        Gate::before(fn () => true);
        $this->actingAs($user, 'admin');
    }

    public function test_found_by_whatsapp_number(): void
    {
        foreach (['994603831010', '+994 60 383 10 10', '0603831010'] as $phone) {
            $r = $this->getJson('/admin/assistant/customer?phone='.urlencode($phone))->assertOk()->json();
            $this->assertTrue($r['valid'], $phone);
            $this->assertSame(5, $r['customer']['id'], $phone);
        }
        $this->assertSame('12.50', $r['customer']['bonus']);
        $this->assertSame(4, $r['customer']['orders_count']);
        $this->assertFalse($r['customer']['credit_ready']);
        $this->assertSame(['PS-4', 'PS-3', 'PS-2'], array_column($r['orders'], 'no')); // son 3, yenidən köhnəyə
        $this->assertSame('Yolda', $r['orders'][0]['status']);
    }

    public function test_not_found_and_foreign_number(): void
    {
        $r = $this->getJson('/admin/assistant/customer?phone=994551112233')->assertOk()->json();
        $this->assertSame(['valid' => true, 'mobile' => '994551112233', 'customer' => null], array_diff_key($r, ['crm_url' => 1]));

        $this->getJson('/admin/assistant/customer?phone=79161234567')->assertOk()->assertExactJson(['valid' => false]);
    }

    public function test_guest_gets_401(): void
    {
        auth('admin')->logout();
        $this->getJson('/admin/assistant/customer?phone=994603831010')->assertUnauthorized();
    }

    /** Burada yalnız ölçü ayrılır; artıq sözləri (salam, göndər…) axtarış lüğətdən atır — ProductSearchServiceTest */
    public function test_query_parser_extracts_size(): void
    {
        $cases = [
            'Salam, Dior Savaj 100 lük neçəyədi?' => ['salam dior savaj 100 luk neceyedi', '100'],
            'chanel bleu 100ml' => ['chanel bleu', '100'],
            'Lost Cherry 50mllik' => ['lost cherry', '50'],
            'tom ford 100 mlsi' => ['tom ford 100 mlsi', '100'],
            '212 VIP' => ['212 vip', '212'],
            'Aventus var?' => ['aventus var', null],
        ];
        foreach ($cases as $text => [$query, $size]) {
            $this->assertSame(['query' => $query, 'size' => $size], AssistantController::parseQuery($text), $text);
        }
    }


    public function test_panel_creates_customer_without_leaving_whatsapp(): void
    {
        $sms = Mockery::mock(SmsService::class);
        $sms->shouldReceive('send')->once()->withArgs(fn ($to, $text) => $to === '994503573030' && str_contains($text, 'Sifreniz'));
        $this->app->instance(SmsService::class, $sms);

        $r = $this->postJson('/admin/assistant/customer', [
            'mobile' => '994503573030', 'name' => 'Leyla', 'surname' => 'Həsənova', 'email' => null, 'gender' => '0', 'send_password' => true,
        ])->assertCreated()->json();

        $this->assertSame('994503573030', $r['mobile']);
        $this->assertTrue($r['sms']);
        $this->assertStringContainsString('Şifrə SMS ilə göndərildi', $r['message']);
        $customer = DB::table('customers')->where('mobile', '994503573030')->first();
        $this->assertSame(['Leyla', 1, 0], [$customer->name, (int) $customer->active, (int) $customer->gender]);

        // artıq var → kart açılır
        $this->getJson('/admin/assistant/customer?phone=994503573030')->assertOk()->assertJsonPath('customer.id', $r['id']);
    }

    public function test_panel_create_validation_errors_are_json(): void
    {
        $this->app->instance(SmsService::class, Mockery::mock(SmsService::class)); // SMS getməməlidir
        $this->postJson('/admin/assistant/customer', ['mobile' => '994603831010', 'name' => '', 'surname' => 'X'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['mobile', 'name', 'gender']);
    }

    private function creditFields(array $override = []): array
    {
        return $override + [
            'father_name' => 'Rauf', 'fin' => '5xyz12a', 'id_card_series' => 'AA', 'id_card_number' => '5267450',
            'relative_1_name' => 'Nigar', 'relative_1_phone' => '994501112233', 'relative_2_name' => 'Elçin', 'relative_2_phone' => '994552223344',
            'workplace_name' => 'Parfumshop MMC', 'salary' => '850',
        ];
    }

    public function test_panel_saves_credit_profile_with_id_card(): void
    {
        $uploads = public_path('frontend/uploads/customers');
        $before = is_dir($uploads) ? scandir($uploads) : [];

        $r = $this->post('/admin/assistant/customer/5/credit-profile', $this->creditFields([
            'id_card_front' => \Illuminate\Http\UploadedFile::fake()->image('front.jpg', 856, 540),
        ]), ['Accept' => 'application/json'])->assertOk()->json();

        $this->assertTrue($r['complete']);
        $this->assertSame('5XYZ12A', $r['credit']['fin']);
        $this->assertStringContainsString('/frontend/uploads/customers/', $r['credit']['id_card_front']);
        $this->getJson('/admin/assistant/customer?phone=994603831010')->assertJsonPath('customer.credit_ready', true)
            ->assertJsonPath('customer.credit.relative_1_name', 'Nigar');

        // test faylını təmizlə
        foreach (array_diff(scandir($uploads), $before) as $file) {
            @unlink($uploads.'/'.$file);
        }
    }

    public function test_panel_credit_profile_validation_is_json(): void
    {
        $this->postJson('/admin/assistant/customer/5/credit-profile', $this->creditFields(['fin' => '12', 'salary' => '']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['fin', 'salary', 'id_card_front']);
        $this->assertSame(0, DB::table('customer_credit_profiles')->count());
    }

    public function test_panel_ocr_without_vision_key_returns_message(): void
    {
        config(['services.google_vision.key_path' => null]);
        $this->post('/admin/assistant/customer/5/credit-profile/ocr', [
            'image' => \Illuminate\Http\UploadedFile::fake()->image('card.jpg', 856, 540),
        ], ['Accept' => 'application/json'])->assertStatus(422)->assertJsonStructure(['message']);
    }
}
