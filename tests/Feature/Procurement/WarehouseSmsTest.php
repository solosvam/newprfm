<?php

namespace Tests\Feature\Procurement;

use App\Models\Order\Order;
use App\Models\Procurement\OrderItemAllocation;
use App\Models\Procurement\Warehouse;
use App\Models\Procurement\WarehouseRequestItem;
use App\Models\SmsLog;
use App\Services\ProcurementService;
use App\Services\SmsService;
use App\Services\WarehouseNotifier;
use App\Services\WarehousePortalService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\OrderStatusFixtures;
use Tests\Concerns\ProcurementSchema;
use Tests\TestCase;

class WarehouseSmsTest extends TestCase
{
    use OrderStatusFixtures;
    use ProcurementSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->procurementSchema();
        $this->orderStatusFixtures();
        config(['services.parfumshop_sms' => [
            'url' => 'https://apps.lsim.az/quicksms/v1/send', 'login' => 'test', 'password' => 'secret', 'sender' => 'ParfumShop',
        ]]);
    }

    private function smsOk(): void
    {
        Http::fake(['apps.lsim.az/*' => Http::response(['successMessage' => 'OK', 'errorMessage' => null, 'obj' => 555, 'errorCode' => 0])]);
    }

    private function allocation(?string $phone = '0501234567'): array
    {
        $order = Order::create(['customer_id' => 1, 'order_status_id' => 1]);
        $item = $order->items()->create(['quantity' => 1]);
        $warehouse = Warehouse::create(['name_az' => 'Anbar A', 'phone' => $phone, 'active' => true]);
        $service = app(ProcurementService::class);
        $service->createRequests($order, [$warehouse->id], [$item->id], 7);
        $offer = $service->recordOffer($order, WarehouseRequestItem::first()->id, ['available_quantity' => 1, 'unit_cost' => '50', 'source' => 'phone'], 7);

        return [$order, $service->allocate($order, $offer->id, 1, 7), $warehouse];
    }

    public function test_sms_service_detects_provider_error_with_http_200(): void
    {
        Http::fake(['apps.lsim.az/*' => Http::response(['successMessage' => null, 'errorMessage' => 'balance', 'obj' => null, 'errorCode' => -104])]);
        $this->expectExceptionMessage('SMS balansı bitib');
        app(SmsService::class)->send('0501234567', 'test');
    }

    public function test_sms_service_returns_transaction_and_sets_unicode_only_when_needed(): void
    {
        $this->smsOk();
        $this->assertSame('555', app(SmsService::class)->send('050 123 45 67', 'Parfumshop test'));
        app(SmsService::class)->send('994501234567', 'Şəkil');
        $requests = Http::recorded()->map(fn ($pair) => $pair[0]);
        $this->assertSame('994501234567', $requests[0]['msisdn']);
        $this->assertArrayNotHasKey('unicode', $requests[0]->data());
        $this->assertSame('true', $requests[1]['unicode']);
        // key = md5(md5(password) + login + text + msisdn + sender)
        $this->assertSame(md5(md5('secret').'testParfumshop test994501234567ParfumShop'), $requests[0]['key']);
    }

    public function test_request_sms_contains_portal_link_and_is_logged(): void
    {
        $this->smsOk();
        $order = Order::create(['customer_id' => 1, 'order_status_id' => 1]);
        $item = $order->items()->create(['quantity' => 1]);
        $a = Warehouse::create(['name_az' => 'A', 'phone' => '0501234567', 'active' => true]);
        $b = Warehouse::create(['name_az' => 'B', 'active' => true]); // telefon yoxdur
        $created = app(ProcurementService::class)->createRequests($order, [$a->id, $b->id], [$item->id], 7);
        $notifier = app(WarehouseNotifier::class);
        $results = $created->mapWithKeys(fn ($r) => [$r->warehouse->name_az => $notifier->notifyRequest($r, 7)]);

        $this->assertNull($results['B']);
        $log = SmsLog::sole();
        $this->assertTrue($log->isSent());
        $this->assertSame('555', $log->provider_id);
        $this->assertMatchesRegularExpression('#1 mehsul ucun sorgu var.*/w/[A-Za-z0-9]{12}$#', $log->message);
        $this->assertSame(1, DB::table('warehouse_access_links')->where('warehouse_id', $a->id)->count());
        $this->assertStringContainsString('SMS 1 anbara göndərildi', WarehouseNotifier::summary($results));
        $this->assertStringContainsString('B — telefon yoxdur', WarehouseNotifier::summary($results));
        Http::assertSentCount(1);
    }

    public function test_selected_sms_marks_allocation_notified(): void
    {
        $this->smsOk();
        [$order, $allocation] = $this->allocation();
        $log = app(WarehouseNotifier::class)->notifySelected($order, $allocation, 7);
        $this->assertTrue($log->isSent());
        $this->assertStringContainsString('?tab=selected', $log->message);
        $this->assertSame(OrderItemAllocation::NOTIFIED, $allocation->fresh()->status);
    }

    public function test_failed_sms_keeps_selected_and_logs_error(): void
    {
        Http::fake(['apps.lsim.az/*' => Http::response(['errorCode' => -104])]);
        [$order, $allocation] = $this->allocation();
        $log = app(WarehouseNotifier::class)->notifySelected($order, $allocation, 7);
        $this->assertFalse($log->isSent());
        $this->assertStringContainsString('balansı bitib', $log->error);
        $this->assertSame(OrderItemAllocation::SELECTED, $allocation->fresh()->status);
    }

    public function test_warehouse_confirms_reservation_in_portal(): void
    {
        $this->smsOk();
        [$order, $allocation, $warehouse] = $this->allocation();
        $url = app(WarehousePortalService::class)->issue($warehouse, 7);
        $this->get($url.'?tab=selected')->assertOk()->assertSee('Rezerv etdim')->assertSee('<span class="wp-count">1</span>', false);
        $this->post($url.'/allocations/'.$allocation->id, ['action' => 'reserve'])->assertRedirect($url.'?tab=selected');
        $this->post($url.'/allocations/'.$allocation->id, ['action' => 'reserve'])->assertRedirect(); // təkrar — heç nə
        $this->assertSame(OrderItemAllocation::RESERVED, $allocation->fresh()->status);
        $log = DB::table('allocation_status_logs')->where('to_status', 'reserved')->sole();
        $this->assertSame(0, (int) $log->user_id);
        $this->get($url.'?tab=selected')->assertSee('Rezervdədir')->assertDontSee('>Rezerv etdim</button>', false);
    }

    public function test_portal_problem_requires_new_price_and_is_scoped_to_warehouse(): void
    {
        $this->smsOk();
        [$order, $allocation, $warehouse] = $this->allocation();
        $other = Warehouse::create(['name_az' => 'Başqa', 'active' => true]);
        $otherUrl = app(WarehousePortalService::class)->issue($other, 7);
        $this->post($otherUrl.'/allocations/'.$allocation->id, ['action' => 'reserve'])->assertNotFound();

        $url = app(WarehousePortalService::class)->issue($warehouse, 7);
        $this->from($url.'?tab=selected')->post($url.'/allocations/'.$allocation->id, ['action' => 'problem', 'problem_type' => 'price_changed'])
            ->assertSessionHasErrors('unit_cost');
        $this->post($url.'/allocations/'.$allocation->id, ['action' => 'problem', 'problem_type' => 'price_changed', 'unit_cost' => '55'])->assertRedirect();
        $fresh = $allocation->fresh();
        $this->assertSame(OrderItemAllocation::PROBLEM, $fresh->status);
        $this->assertSame('price_changed', $fresh->problem_type);
        $this->assertStringContainsString('yeni qiymət 55.00 AZN', DB::table('allocation_status_logs')->where('to_status', 'problem')->value('note'));
    }

    public function test_cancel_sms_only_when_warehouse_was_informed(): void
    {
        $this->smsOk();
        [$order, $allocation] = $this->allocation();
        $from = app(ProcurementService::class)->cancelAllocation($order, $allocation->id, 'test', 7);
        $this->assertSame(OrderItemAllocation::SELECTED, $from);
        $this->assertNull(app(ProcurementService::class)->cancelAllocation($order, $allocation->id, 'test', 7));
    }

    public function test_admin_routes_send_sms_after_request_and_selection(): void
    {
        $this->smsOk();
        $user = new \App\Models\User(['name' => 'Operator']);
        $user->id = 7;
        \Illuminate\Support\Facades\Gate::before(fn () => true);
        $this->actingAs($user, 'admin');
        $order = Order::create(['customer_id' => 1, 'order_status_id' => 1]);
        $item = $order->items()->create(['quantity' => 1]);
        $warehouse = Warehouse::create(['name_az' => 'Anbar A', 'phone' => '0501234567', 'active' => true]);

        $this->post(route('admin.procurement.requests.store', $order), ['warehouse_ids' => [$warehouse->id], 'item_ids' => [$item->id]])
            ->assertSessionHas('success', fn ($m) => str_contains($m, 'SMS 1 anbara göndərildi'));
        $offer = app(ProcurementService::class)->recordOffer($order, WarehouseRequestItem::first()->id, ['available_quantity' => 1, 'unit_cost' => '50', 'source' => 'phone'], 7);
        $key = (string) \Illuminate\Support\Str::uuid();
        $this->post(route('admin.procurement.allocations.store', $order), ['offer_id' => $offer->id, 'quantity' => 1, 'idempotency_key' => $key])->assertSessionHas('success');
        $this->post(route('admin.procurement.allocations.store', $order), ['offer_id' => $offer->id, 'quantity' => 1, 'idempotency_key' => $key]);
        Http::assertSentCount(2); // təkrar forma ikinci SMS göndərmir
        $allocation = OrderItemAllocation::sole();
        $this->assertSame(OrderItemAllocation::NOTIFIED, $allocation->status);

        $this->post(route('admin.procurement.allocations.sms', [$order, $allocation]))->assertSessionHas('success', 'Anbar A: SMS göndərildi.');
        $this->get(route('admin.procurement.show', $order))->assertOk()->assertSee('Sorğular və anbar SMS-ləri')->assertSee('✓ SMS');
        $this->post(route('admin.procurement.allocations.cancel', [$order, $allocation]), ['note' => 'müştəri imtina etdi'])->assertSessionHasNoErrors()->assertSessionHas('success', fn ($m) => str_contains($m, 'SMS 1'));
        $this->assertSame('warehouse_cancelled', SmsLog::latest('id')->value('context'));
    }

    public function test_validation_messages_are_azerbaijani(): void
    {
        app()->setLocale('az');
        $v = \Illuminate\Support\Facades\Validator::make(['amount' => ''], ['amount' => 'required']);
        $this->assertSame('Məbləğ mütləq doldurulmalıdır.', $v->errors()->first('amount'));
        // az.json-da olmayan qayda — lang/az/validation.php-dən (əvvəl "validation.before_or_equal" görünürdü)
        $v = \Illuminate\Support\Facades\Validator::make(['occurred_at' => '2030-01-01'], ['occurred_at' => 'before_or_equal:2029-01-01']);
        $this->assertSame('Əməliyyat tarixi 2029-01-01 tarixi və ya ondan əvvəl olmalıdır.', $v->errors()->first('occurred_at'));
    }
}
