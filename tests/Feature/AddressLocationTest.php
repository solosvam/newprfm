<?php

namespace Tests\Feature;

use App\Models\Customer\CustomerAddress;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/** Ünvanın xəritədəki nöqtəsi: yoxlama, saxlama (göndərilməyəndə silinmir), naviqasiya linkləri */
class AddressLocationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        Schema::create('cities', fn (Blueprint $t) => [$t->id(), $t->string('name'), $t->boolean('active')->default(true)]);
        DB::table('cities')->insert(['id' => 1, 'name' => 'Bakı']);
    }

    public function test_coordinates_are_validated_inside_azerbaijan_and_in_pairs(): void
    {
        $rules = CustomerAddress::formRules();
        $base = ['title' => 'Ev', 'city_id' => 1, 'address' => 'Nizami 1'];
        $passes = fn (array $extra) => Validator::make($base + $extra, $rules)->passes();

        $this->assertTrue($passes([]));
        $this->assertTrue($passes(['latitude' => 40.4093, 'longitude' => 49.8671]));
        $this->assertFalse($passes(['latitude' => 51.5, 'longitude' => -0.12]));   // London
        $this->assertFalse($passes(['latitude' => 40.4093]));                       // cüt olmalıdır
    }

    public function test_attributes_keep_existing_point_when_form_does_not_send_it(): void
    {
        $base = ['city_id' => 1, 'address' => 'Nizami 1'];
        $this->assertArrayNotHasKey('latitude', CustomerAddress::attributesFromForm($base));

        $with = CustomerAddress::attributesFromForm($base + ['latitude' => '40.40930001', 'longitude' => '49.8671']);
        $this->assertSame([40.4093, 49.8671], [$with['latitude'], $with['longitude']]);

        $cleared = CustomerAddress::attributesFromForm($base + ['latitude' => null, 'longitude' => null]);
        $this->assertNull($cleared['latitude']);
    }

    public function test_navigation_links_use_point_or_fall_back_to_text(): void
    {
        $address = new CustomerAddress(['city' => 'Bakı', 'address' => 'Nizami 1']);
        $this->assertFalse($address->hasLocation());
        $this->assertStringContainsString('query='.rawurlencode('Bakı, Nizami 1'), $address->mapsUrl());
        $this->assertStringContainsString('waze.com/ul?q=', $address->wazeUrl());

        $address->forceFill(['latitude' => 40.4093, 'longitude' => 49.8671]);
        $this->assertTrue($address->hasLocation());
        $this->assertStringEndsWith('destination=40.4093,49.8671', $address->directionsUrl());
        $this->assertStringContainsString('ll=40.4093,49.8671', $address->wazeUrl());
    }
}
