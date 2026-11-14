<?php

namespace Tests\Unit;

use App\Support\GoogleMapsLink;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GoogleMapsLinkTest extends TestCase
{
    public function test_coordinates_from_whatsapp_and_google_links(): void
    {
        $point = ['lat' => 40.3712, 'lng' => 49.8345];
        foreach ([
            'https://maps.google.com/maps?q=40.3712%2C49.8345&z=17&hl=en',                 // WhatsApp Web / Desktop
            'https://www.google.com/maps/place/Nizami/@40.3700,49.8300,17z/data=!3m1!4b1!4m6!3m5!1s0x0:0x0!8m2!3d40.3712!4d49.8345',
            'https://www.google.com/maps/search/40.3712,+49.8345?entry=tts',
            'https://www.google.com/maps/dir/?api=1&destination=40.3712,49.8345',
            'https://www.google.com/maps/@40.3712,49.8345,18z',
            '40.3712, 49.8345',
        ] as $link) {
            $this->assertSame($point, app(GoogleMapsLink::class)->resolve($link), $link);
        }
        // Azərbaycandan kənar — qəbul edilmir
        $this->assertNull(GoogleMapsLink::coordinates('https://maps.google.com/maps?q=51.5,-0.12'));
    }

    public function test_short_link_is_followed_and_place_name_falls_back_to_query(): void
    {
        Http::fake([
            'maps.app.goo.gl/abc' => Http::response('', 302, ['Location' => 'https://www.google.com/maps/search/40.3712,+49.8345?entry=tts']),
            'maps.app.goo.gl/name' => Http::response('', 302, ['Location' => 'https://maps.google.com/?q=Port+Baku+Mall,+Baku&ftid=0x1:0x2']),
        ]);
        $links = app(GoogleMapsLink::class);
        $this->assertSame(['lat' => 40.3712, 'lng' => 49.8345], $links->resolve('https://maps.app.goo.gl/abc'));
        $this->assertSame(['query' => 'Port Baku Mall, Baku'], $links->resolve('https://maps.app.goo.gl/name'));
    }

    public function test_only_google_hosts_are_requested(): void
    {
        Http::fake();
        $links = app(GoogleMapsLink::class);
        $this->assertNull($links->resolve('http://169.254.169.254/latest/meta-data'));
        $this->assertNull($links->resolve('https://evil.example/maps?q=Baku'));
        $this->assertNull($links->resolve('salam'));
        Http::assertNothingSent();

        // Qısa link Google olmayan ünvana yönləndirirsə — getmirik
        Http::fake(['goo.gl/*' => Http::response('', 302, ['Location' => 'http://127.0.0.1/admin'])]);
        $this->assertNull($links->resolve('https://goo.gl/maps/x'));
        Http::assertSentCount(1);
    }
}
