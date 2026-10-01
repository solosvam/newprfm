<?php

namespace Tests\Feature\Crm;

use App\Models\Customer\Customer;
use App\Services\LegacyCustomerImporter;
use App\Services\SmsService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class LegacyCustomerImportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:',
            'cache.default' => 'array', 'session.driver' => 'array',
            'services.legacy_customers.url' => 'https://legacy.example/index.php?route=api/customer_export',
            'services.legacy_customers.token' => str_repeat('t', 32)]);
        DB::purge('sqlite');
        $this->app->useStoragePath(sys_get_temp_dir().'/parfumshop-import-test-'.bin2hex(random_bytes(8)));
        Http::preventStrayRequests();
        Mail::fake();
        Schema::create('customers', function (Blueprint $t) {
            $t->id(); $t->string('name', 30); $t->string('surname', 30); $t->string('email', 50)->nullable()->unique();
            $t->string('mobile')->unique(); $t->integer('gender'); $t->string('password')->nullable();
            $t->boolean('active')->default(false); $t->decimal('bonus_balance', 12, 2)->default(0);
            $t->rememberToken(); $t->timestamps();
        });
        (require database_path('migrations/2026_10_01_180000_add_old_customer_id_to_customers.php'))->up();
        Schema::create('customer_bonus_transactions', function (Blueprint $t) {
            $t->id(); $t->integer('customer_id'); $t->string('type'); $t->decimal('amount', 12, 2);
            $t->string('note')->nullable(); $t->timestamps();
        });
    }

    private function row(int $id = 1, array $changes = []): array
    {
        return array_replace(['customer_id' => $id, 'firstname' => 'Ad', 'lastname' => 'Soyad',
            'email' => 'test'.$id.'@example.com', 'telephone' => '99410'.str_pad((string) $id, 7, '0', STR_PAD_LEFT),
            'sex' => 1, 'bonus' => '12.50'], $changes);
    }

    private function page(array $rows, int $after = 0, int $snapshot = 100, bool $more = false, int $limit = 200): array
    {
        $last = $rows ? end($rows)['customer_id'] : $after;
        return ['version' => 1, 'customers' => $rows, 'pagination' => [
            'after_id' => $after, 'limit' => $limit, 'count' => count($rows), 'snapshot_max_id' => $snapshot,
            'last_customer_id' => $last, 'has_more' => $more, 'next_after_id' => $more ? $last : null,
        ]];
    }

    public function test_import_mapping_bonus_history_and_no_messages(): void
    {
        $this->mock(SmsService::class)->shouldNotReceive('send');
        $importer = new LegacyCustomerImporter();
        $this->assertSame('created', $importer->import($this->row(1, ['sex' => 2]), true)['status']);
        $customer = Customer::first();
        $this->assertSame(1, $customer->old_customer_id);
        $this->assertSame(0, $customer->gender);
        $this->assertTrue($customer->active);
        $this->assertNull($customer->password);
        $this->assertEquals(12.50, $customer->bonus_balance);
        $this->assertDatabaseHas('customer_bonus_transactions', ['customer_id' => $customer->id, 'type' => 'adjustment', 'amount' => 12.50]);
        $this->assertSame('created', $importer->import($this->row(2), true)['status']);
        $this->assertSame(1, Customer::where('old_customer_id', 2)->first()->gender);
        Mail::assertNothingSent();
        Mail::assertNothingQueued();
    }

    public function test_bonus_under_one_is_zero_and_other_amounts_round_without_float(): void
    {
        $importer = new LegacyCustomerImporter();
        foreach (['-12.5', '0', '0.999999', '1', '1.005', '9999999999.99'] as $i => $bonus) {
            $result = $importer->import($this->row($i + 1, compact('bonus')), true);
            $this->assertSame([0, 0, 0, 100, 101, 999999999999][$i], $result['bonus_cents']);
        }
        $this->assertSame(3, DB::table('customer_bonus_transactions')->count());
        $this->assertSame('invalid', $importer->import($this->row(7, ['bonus' => '9999999999.999']), true)['status']);
    }

    public function test_repeated_import_keeps_existing_balance_and_password(): void
    {
        (new LegacyCustomerImporter())->import($this->row(), true);
        $customer = Customer::first();
        $customer->update(['password' => 'my-password', 'bonus_balance' => '2.00']);
        $password = $customer->fresh()->password;
        $result = (new LegacyCustomerImporter())->import($this->row(1, ['bonus' => '50']), true);
        $this->assertSame('already_imported', $result['status']);
        $this->assertEquals(2, $customer->fresh()->bonus_balance);
        $this->assertSame($password, $customer->fresh()->password);
        $this->assertSame(1, Customer::count());
        $this->assertSame(1, DB::table('customer_bonus_transactions')->count());
    }

    public function test_dry_run_detects_duplicates_across_rows_without_writing(): void
    {
        $importer = new LegacyCustomerImporter();
        $this->assertSame('would_create', $importer->import($this->row(), false)['status']);
        $this->assertSame('conflict', $importer->import($this->row(2, ['telephone' => $this->row()['telephone']]), false)['status']);
        $this->assertSame('conflict', $importer->import($this->row(3, ['email' => 'TEST1@EXAMPLE.COM']), false)['status']);
        $this->assertSame('already_imported', $importer->import($this->row(), false)['status']);
        $this->assertSame(0, Customer::count());
        $this->assertSame(0, DB::table('customer_bonus_transactions')->count());
    }

    public function test_existing_customer_conflicts_are_not_merged(): void
    {
        Customer::create(['name' => 'Existing', 'surname' => 'Customer', 'mobile' => $this->row()['telephone'],
            'email' => 'TEST1@EXAMPLE.COM', 'gender' => 1, 'bonus_balance' => 7]);
        $importer = new LegacyCustomerImporter();
        $this->assertSame('conflict', $importer->import($this->row(), true)['status']);
        $this->assertSame('conflict', $importer->import($this->row(2, ['email' => 'test1@example.com']), true)['status']);
        $this->assertSame(1, Customer::count());
        $this->assertNull(Customer::first()->old_customer_id);
        $this->assertEquals(7, Customer::first()->bonus_balance);
    }

    public function test_invalid_phone_sex_email_and_names_are_reported(): void
    {
        $importer = new LegacyCustomerImporter();
        foreach (['+994103227575', '0103227575', '9941032275750', "994103227575\n", '994 10 3227575'] as $phone) {
            $this->assertSame('invalid', $importer->import($this->row(1, ['telephone' => $phone]), true)['status']);
        }
        foreach ([['sex' => 0], ['sex' => null], ['email' => 'bad-email'], ['firstname' => str_repeat('a', 31)], ['lastname' => '']] as $changes) {
            $this->assertSame('invalid', $importer->import($this->row(1, $changes), true)['status']);
        }
        $this->assertSame('created', $importer->import($this->row(1, ['email' => ' ']), true)['status']);
        $this->assertNull(Customer::first()->email);
    }

    public function test_bonus_history_failure_rolls_back_customer(): void
    {
        Schema::drop('customer_bonus_transactions');
        try {
            (new LegacyCustomerImporter())->import($this->row(), true);
            $this->fail('Missing history table should fail.');
        } catch (\Illuminate\Database\QueryException $e) {
            $this->assertSame(0, Customer::count());
        }
    }

    public function test_command_defaults_to_dry_run_and_follows_snapshot_cursor(): void
    {
        Http::fakeSequence()->push($this->page([$this->row()], 0, 2, true))
            ->push($this->page([$this->row(2)], 1, 2));
        $this->artisan('parfumshop:import-customers')->assertExitCode(0);
        $this->assertSame(0, Customer::count());
        Http::assertSent(fn ($request) => $request->hasHeader('X-Customer-Export-Token', str_repeat('t', 32))
            && $request['after_id'] === 1 && $request['snapshot_max_id'] === 2);
        $report = file_get_contents(glob(storage_path('app/private/imports/*.jsonl'))[0]);
        $this->assertStringContainsString('"would_create":2', $report);
        $this->assertStringNotContainsString('test1@example.com', $report);
        $this->assertStringNotContainsString($this->row()['telephone'], $report);
    }

    public function test_command_applies_valid_rows_and_reports_invalid_rows(): void
    {
        Http::fake(['*' => Http::response($this->page([$this->row(), $this->row(2, ['sex' => 7])]))]);
        $this->artisan('parfumshop:import-customers --apply')->assertExitCode(1);
        $this->assertSame(1, Customer::count());
        $report = file_get_contents(glob(storage_path('app/private/imports/*.jsonl'))[0]);
        $this->assertStringContainsString('"status":"invalid"', $report);
        $this->assertStringContainsString('"old_customer_id":2', $report);
    }

    public function test_invalid_pagination_rejects_entire_page_before_creating(): void
    {
        $page = $this->page([$this->row()], 0, 100, true);
        $page['pagination']['next_after_id'] = 0;
        Http::fake(['*' => Http::response($page)]);
        $this->artisan('parfumshop:import-customers --apply')->assertExitCode(1);
        $this->assertSame(0, Customer::count());
    }

    public function test_http_failure_and_resume_without_snapshot_fail(): void
    {
        $this->artisan('parfumshop:import-customers --after-id=1')->assertExitCode(1);
        Http::assertNothingSent();
        Http::fake(['*' => Http::response(['error' => 'Unauthorized'], 401)]);
        $this->artisan('parfumshop:import-customers --apply')->assertExitCode(1);
        $this->assertSame(0, Customer::count());
    }

    public function test_imported_customer_uses_otp_and_sets_own_password(): void
    {
        (new LegacyCustomerImporter())->import($this->row(), true);
        $code = null;
        $this->mock(SmsService::class)->shouldReceive('send')->once()->andReturnUsing(function ($number, $message) use (&$code) {
            $this->assertSame($this->row()['telephone'], $number);
            preg_match('/([0-9]{6})$/', $message, $matches);
            $code = $matches[1];
            return 'test-sms';
        });
        $this->postJson('/login/check', ['mobile' => $this->row()['telephone']])->assertOk()->assertJson(['status' => 'otp']);
        $this->postJson('/login/otp', ['mobile' => $this->row()['telephone'], 'otp' => $code])->assertOk()->assertJson(['status' => 'verified']);
        $this->postJson('/login/set-password', ['mobile' => $this->row()['telephone'], 'password' => 'new-password', 'password_confirmation' => 'new-password'])
            ->assertOk()->assertJson(['status' => 'success']);
        $this->assertAuthenticatedAs(Customer::first());
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('new-password', Customer::first()->password));
        $this->assertEquals(12.50, Customer::first()->bonus_balance);
    }
}
