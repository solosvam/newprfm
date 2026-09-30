<?php

namespace Tests\Feature\Credit;

use App\Models\Customer\Customer;
use App\Models\User;
use App\Services\IdCard\GoogleVisionOcr;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Illuminate\Support\Facades\Route;
use Mockery;
use Tests\TestCase;

class CrmCreditProfileOcrTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'cache.default' => 'array']);
        DB::purge('sqlite');
        (require base_path('vendor/spatie/laravel-permission/database/migrations/create_permission_tables.php.stub'))->up();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::create(['name' => 'crm', 'guard_name' => 'admin']);
        Route::bind('customer', fn () => (new Customer())->forceFill([
            'id' => 5, 'name' => 'Gulnar', 'surname' => 'Testova',
        ]));
    }

    private function sendCard(): \Illuminate\Testing\TestResponse
    {
        return $this->postJson(route('admin.crm.credit-profile.ocr', 5), [
            'image' => UploadedFile::fake()->image('card.jpg', 900, 570),
        ]);
    }

    private function admin(): User
    {
        $user = (new User())->forceFill(['id' => 8, 'name' => 'Operator', 'surname' => 'Admin']);
        $user->setRelation('permissions', Permission::all());
        $user->setRelation('roles', collect());

        return $user;
    }

    public function test_guest_cannot_read_cards(): void
    {
        $this->sendCard()->assertUnauthorized();
    }

    public function test_crm_permission_is_required(): void
    {
        $admin = $this->admin();
        $admin->setRelation('permissions', collect());
        $this->actingAs($admin, 'admin')->sendCard()->assertForbidden();
    }

    public function test_card_is_matched_against_customer_instead_of_operator(): void
    {
        $vision = Mockery::mock(GoogleVisionOcr::class);
        $vision->shouldReceive('isConfigured')->andReturn(true);
        $vision->shouldReceive('text')->once()->andReturn(
            "IDENTITY CARD\nSOYADI/SURNAME\nTESTOVA\nADI/GIVEN NAME\nGÜLNAR\nATASININ ADI/PATRONYMIC\nİLHAM QIZI\n"
            ."VƏSİQƏNİN NÖMRƏSİ/CARD NO FƏRDİ İDENTİFİKASİYA NÖMRƏSİ/PERSONAL NO\nAA1234567 5XYZ12A"
        );
        $this->app->instance(GoogleVisionOcr::class, $vision);
        $this->actingAs($this->admin(), 'admin')->sendCard()->assertOk()
            ->assertJsonPath('fields.father_name', 'İlham')
            ->assertJsonPath('fields.fin', '5XYZ12A')
            ->assertJsonPath('fields.id_card_series', 'AA')
            ->assertJsonPath('fields.id_card_number', '1234567')
            ->assertJsonPath('name_match', true);
    }

    public function test_image_is_required(): void
    {
        $this->actingAs($this->admin(), 'admin')
            ->postJson(route('admin.crm.credit-profile.ocr', 5), [])->assertUnprocessable();
    }
}
