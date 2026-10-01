<?php

namespace Tests\Feature\Crm;

use App\Models\Customer\Customer;
use App\Models\Customer\CustomerCreditProfile;
use App\Services\LegacyCreditProfileImporter;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LegacyCreditProfileImportTest extends TestCase
{
    private string $phone = '994103227575';
    private string $png;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'cache.default' => 'array',
            'services.legacy_customers.token' => str_repeat('t', 32),
            'services.legacy_credit_profiles.url' => 'https://legacy.example/credit-profile-export.php',
            'services.legacy_credit_profiles.images_url' => 'https://legacy.example/credit_images/']);
        DB::purge('sqlite');
        $temporary = sys_get_temp_dir().'/parfumshop-credit-test-'.bin2hex(random_bytes(8));
        $this->app->useStoragePath($temporary.'/storage');
        $this->app->usePublicPath($temporary.'/public');
        $this->png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jD1sAAAAASUVORK5CYII=');
        Http::preventStrayRequests();
        Schema::create('customers', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->string('surname'); $t->string('mobile'); $t->integer('gender')->nullable();
            $t->string('email')->nullable(); $t->string('password')->nullable(); $t->boolean('active')->default(true);
            $t->decimal('bonus_balance', 12, 2)->default(0); $t->timestamps();
        });
        Schema::create('customer_credit_profiles', function (Blueprint $t) {
            $t->id(); $t->integer('customer_id')->unique();
            foreach (['father_name', 'fin', 'id_card_series', 'id_card_number', 'relative_1_name', 'relative_1_phone',
                'relative_2_name', 'relative_2_phone', 'id_card_front', 'id_card_back', 'workplace_name', 'position'] as $field) $t->string($field)->nullable();
            $t->unique('fin'); $t->decimal('salary', 10, 2)->nullable(); $t->timestamps();
        });
    }

    private function customer(array $data = []): Customer
    {
        return Customer::create(array_replace(['name' => 'Current', 'surname' => 'Customer', 'mobile' => $this->phone,
            'gender' => 1, 'bonus_balance' => '17.00'], $data));
    }

    private function record(int $id = 10, array $changes = []): array
    {
        return array_replace(['id' => $id, 'name' => 'Legacy', 'surname' => 'Person', 'fathername' => 'Əli', 'gender' => 'female',
            'card_id' => 'AZE №09217804', 'card_fin' => 'abc1234', 'mobile' => $this->phone,
            'job_type' => 'Legacy workplace', 'job_salary' => '700,50', 'relation_number1' => '050 111 22 33',
            'relation_number2' => '+994 50 222 33 44', 'relation_number1_who' => 'Ana', 'relation_number2_who' => 'Ata',
            'id_front' => 'front.png', 'id_back' => 'back.png'], $changes);
    }

    private function group(array $records = []): array
    {
        return ['mobile' => $this->phone, 'records' => $records ?: [$this->record()]];
    }

    private function fakeImages(): void
    {
        Http::swap(new \Illuminate\Http\Client\Factory());
        Http::preventStrayRequests();
        Http::fake(['https://legacy.example/credit_images/*' => Http::response($this->png)]);
    }

    private function profileData(): array
    {
        return ['father_name' => 'Existing father', 'fin' => 'ABC1234', 'id_card_series' => 'AZE', 'id_card_number' => '09217804',
            'relative_1_name' => 'Mother', 'relative_1_phone' => '0501112233', 'relative_2_name' => 'Father', 'relative_2_phone' => '0502223344',
            'id_card_front' => 'existing-front.png', 'id_card_back' => 'existing-back.png', 'workplace_name' => 'Existing company', 'salary' => '800.00'];
    }

    public function test_card_normalization_keeps_leading_zero_and_splits_series(): void
    {
        foreach ([['AZE 09217804', 'AZE', '09217804'], ['AA1253395', 'AA', '1253395'],
            ['aa1559649', 'AA', '1559649'], ['AZE №15976567', 'AZE', '15976567']] as [$raw, $series, $number]) {
            $this->assertSame(['id_card_series' => $series, 'id_card_number' => $number], LegacyCreditProfileImporter::card($raw));
        }
        $this->assertNull(LegacyCreditProfileImporter::card('unknown 123'));
    }

    public function test_valid_profile_copies_both_images_and_keeps_customer_fields(): void
    {
        $customer = $this->customer();
        $before = $customer->fresh()->getAttributes();
        $this->fakeImages();
        $result = (new LegacyCreditProfileImporter())->import($this->group(), true);
        $this->assertSame('updated', $result['status']);
        $this->assertSame([], $result['missing']);
        $profile = $customer->fresh()->creditProfile;
        $this->assertSame('ABC1234', $profile->fin);
        $this->assertSame('09217804', $profile->id_card_number);
        $this->assertSame('Əli', $profile->father_name);
        $this->assertSame('700.50', $profile->salary);
        $this->assertSame('0501112233', $profile->relative_1_phone);
        $this->assertSame($before, $customer->fresh()->getAttributes());
        foreach (['id_card_front', 'id_card_back'] as $field) {
            $this->assertSame($this->png, file_get_contents(public_path('frontend/uploads/customers/'.$profile->{$field})));
        }
        $this->assertSame('complete', (new LegacyCreditProfileImporter())->import($this->group(), true)['status']);
        Http::assertSentCount(2);
    }

    public function test_only_blank_fields_are_filled_and_zero_is_preserved(): void
    {
        $customer = $this->customer();
        $profile = $customer->creditProfile()->create(['fin' => 'ABC1234', 'father_name' => 'Current father',
            'workplace_name' => 'Current company', 'salary' => '0.00', 'id_card_front' => 'current-front.png',
            'id_card_series' => 'AZE', 'id_card_number' => '09217804']);
        $this->fakeImages();
        $result = (new LegacyCreditProfileImporter())->import($this->group(), true);
        $this->assertSame('updated', $result['status']);
        $this->assertSame('Current father', $profile->fresh()->father_name);
        $this->assertSame('Current company', $profile->fresh()->workplace_name);
        $this->assertSame('0.00', $profile->fresh()->salary);
        $this->assertSame('current-front.png', $profile->fresh()->id_card_front);
        Http::assertSentCount(1);
    }

    public function test_complete_profiles_and_nonexistent_customers_are_skipped_without_downloads(): void
    {
        $importer = new LegacyCreditProfileImporter();
        $this->assertSame('not_found', $importer->import($this->group(), true)['status']);
        $customer = $this->customer();
        $profile = $customer->creditProfile()->create($this->profileData());
        $before = $profile->fresh()->getAttributes();
        $this->assertSame('complete', $importer->import($this->group(), true)['status']);
        $this->assertSame($before, $profile->fresh()->getAttributes());
        Http::assertNothingSent();
    }

    public function test_multiple_fins_or_different_cards_never_merge(): void
    {
        $this->customer();
        $importer = new LegacyCreditProfileImporter();
        $this->assertSame('conflict', $importer->import($this->group([$this->record(11), $this->record(10, ['card_fin' => 'XYZ1234'])]), true)['status']);
        $this->assertSame('conflict', $importer->import($this->group([$this->record(11), $this->record(10, ['card_id' => 'AA1253395'])]), true)['status']);
        $this->assertSame(0, CustomerCreditProfile::count());
        Http::assertNothingSent();
    }

    public function test_existing_fin_or_partial_card_conflicts_are_preserved(): void
    {
        $customer = $this->customer();
        $profile = $customer->creditProfile()->create(['fin' => 'XYZ1234']);
        $importer = new LegacyCreditProfileImporter();
        $this->assertSame('conflict', $importer->import($this->group(), true)['status']);
        $profile->update(['fin' => 'ABC1234', 'id_card_series' => 'AA']);
        $this->assertSame('conflict', $importer->import($this->group(), true)['status']);
        $this->assertSame('AA', $profile->fresh()->id_card_series);
        Http::assertNothingSent();
    }

    public function test_same_fin_older_rows_fill_missing_fields_and_latest_valid_value_wins(): void
    {
        $customer = $this->customer(['gender' => null]);
        $this->fakeImages();
        $result = (new LegacyCreditProfileImporter())->import($this->group([
            $this->record(11, ['fathername' => ' ', 'job_type' => 'Newest company']),
            $this->record(10, ['fathername' => 'Older father', 'job_type' => 'Old company']),
        ]), true);
        $this->assertSame('updated', $result['status']);
        $this->assertSame('Older father', $customer->fresh()->creditProfile->father_name);
        $this->assertSame('Newest company', $customer->fresh()->creditProfile->workplace_name);
        $this->assertSame(0, $customer->fresh()->gender);
    }

    public function test_invalid_phone_and_missing_fin_do_not_create_profiles(): void
    {
        $this->customer();
        $importer = new LegacyCreditProfileImporter();
        foreach (['0503227575', '+994103227575', "994103227575\n"] as $phone) {
            $group = $this->group(); $group['mobile'] = $phone;
            $this->assertSame('invalid', $importer->import($group, true)['status']);
        }
        $this->assertSame('invalid', $importer->import($this->group([$this->record(10, ['card_fin' => ''])]), true)['status']);
        $this->assertSame(0, CustomerCreditProfile::count());
        Http::assertNothingSent();
    }

    public function test_other_customer_fin_is_conflict(): void
    {
        $this->customer();
        $other = $this->customer(['mobile' => '994503227575']);
        $other->creditProfile()->create(['fin' => 'ABC1234']);
        $this->assertSame('conflict', (new LegacyCreditProfileImporter())->import($this->group(), true)['status']);
        Http::assertNothingSent();
    }

    public function test_dry_run_checks_images_but_writes_no_profile_or_files(): void
    {
        $customer = $this->customer();
        $before = $customer->fresh()->getAttributes();
        $this->fakeImages();
        $result = (new LegacyCreditProfileImporter())->import($this->group(), false);
        $this->assertSame('would_update', $result['status']);
        $this->assertSame([], $result['missing']);
        $this->assertSame(0, CustomerCreditProfile::count());
        $this->assertDirectoryDoesNotExist(public_path('frontend/uploads/customers'));
        $this->assertSame($before, $customer->fresh()->getAttributes());
    }

    public function test_failed_back_image_is_reported_and_completed_on_retry(): void
    {
        $customer = $this->customer();
        Http::fake(['*/front.png' => Http::response($this->png), '*/back.png' => Http::response('Missing', 404)]);
        $result = (new LegacyCreditProfileImporter())->import($this->group(), true);
        $this->assertContains('id_card_back', $result['missing']);
        $this->assertContains('id_card_back', $result['warnings']);
        $front = $customer->fresh()->creditProfile->id_card_front;
        $this->fakeImages();
        $result = (new LegacyCreditProfileImporter())->import($this->group(), true);
        $this->assertSame([], $result['missing']);
        $this->assertSame($front, $customer->fresh()->creditProfile->id_card_front);
        $this->assertNotNull($customer->fresh()->creditProfile->id_card_back);
    }

    public function test_path_traversal_and_non_image_files_are_rejected(): void
    {
        $customer = $this->customer();
        Http::fake(['*' => Http::response('<html>not an image</html>')]);
        $result = (new LegacyCreditProfileImporter())->import($this->group([$this->record(10, ['id_front' => '../config.php'])]), true);
        $this->assertContains('id_card_front', $result['warnings']);
        $this->assertContains('id_card_back', $result['warnings']);
        $this->assertNull($customer->fresh()->creditProfile->id_card_front);
        Http::assertSentCount(1);
    }

    private function page(array $groups, string $after = '', int $snapshot = 100, bool $more = false): array
    {
        $last = $groups ? end($groups)['mobile'] : $after;
        return ['version' => 1, 'groups' => $groups, 'pagination' => ['after_mobile' => $after, 'limit' => 50,
            'count' => count($groups), 'snapshot_max_id' => $snapshot, 'last_mobile' => $last,
            'has_more' => $more, 'next_after_mobile' => $more ? $last : null]];
    }

    public function test_command_dry_run_and_snapshot_pagination(): void
    {
        $this->customer();
        Http::fake(['https://legacy.example/credit-profile-export.php*' => Http::sequence()
            ->push($this->page([$this->group()], '', 100, true))->push($this->page([], $this->phone)),
            'https://legacy.example/credit_images/*' => Http::response($this->png)]);
        $this->artisan('parfumshop:import-credit-profiles')->assertExitCode(0);
        $this->assertSame(0, CustomerCreditProfile::count());
        Http::assertSent(fn ($r) => str_contains($r->url(), 'credit-profile-export.php') && $r['after_mobile'] === $this->phone && $r['snapshot_max_id'] === 100);
        $report = file_get_contents(glob(storage_path('app/private/imports/*.jsonl'))[0]);
        $this->assertStringNotContainsString('ABC1234', $report);
        $this->assertStringNotContainsString('09217804', $report);
    }

    public function test_bad_group_or_snapshot_fails_before_writing(): void
    {
        $this->customer();
        $page = $this->page([$this->group()]);
        $page['groups'][0]['records'][0]['mobile'] = '994503227575';
        Http::fake(['*' => Http::response($page)]);
        $this->artisan('parfumshop:import-credit-profiles --apply')->assertExitCode(1);
        $this->assertSame(0, CustomerCreditProfile::count());
    }

    public function test_aa_front_and_back_are_both_copied_for_new_profile(): void
    {
        $customer = $this->customer();
        $this->fakeImages();
        $result = (new LegacyCreditProfileImporter())->import($this->group([$this->record(10, ['card_id' => 'aa1559649'])]), true);
        $this->assertSame('updated', $result['status']);
        $this->assertSame('AA', $customer->fresh()->creditProfile->id_card_series);
        $this->assertNotNull($customer->fresh()->creditProfile->id_card_back);
        Http::assertSentCount(2);
    }

    public function test_database_failure_rolls_back_profile_and_removes_new_images(): void
    {
        $this->customer();
        $this->fakeImages();
        CustomerCreditProfile::creating(function () { throw new \RuntimeException('Simulated database failure.'); });
        try {
            (new LegacyCreditProfileImporter())->import($this->group(), true);
            $this->fail('Expected profile save failure.');
        } catch (\RuntimeException $e) {
            $this->assertSame(0, CustomerCreditProfile::count());
            $this->assertSame([], glob(public_path('frontend/uploads/customers/*')));
        }
    }

    public function test_parallel_customer_change_is_preserved_and_import_rejected(): void
    {
        $customer = $this->customer();
        Http::fake(['*' => function () use ($customer) {
            DB::table('customers')->where('id', $customer->id)->update(['name' => 'Operator edit']);
            return Http::response($this->png);
        }]);
        try {
            (new LegacyCreditProfileImporter())->import($this->group(), true);
            $this->fail('Concurrent edit should be detected.');
        } catch (\RuntimeException $e) {
            $this->assertSame('Operator edit', $customer->fresh()->name);
            $this->assertSame(0, CustomerCreditProfile::count());
            $this->assertDirectoryDoesNotExist(public_path('frontend/uploads/customers'));
        }
    }
}
