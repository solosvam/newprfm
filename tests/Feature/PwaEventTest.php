<?php

namespace Tests\Feature;

use App\Support\PwaDevice;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PwaEventTest extends TestCase
{
    private const IPHONE = 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148';
    private const ANDROID = 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Mobile Safari/537.36';

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        (require database_path('migrations/2026_11_01_120000_create_pwa_events.php'))->up();
    }

    public function test_install_once_per_device_and_opens(): void
    {
        $send = fn (array $body, string $ua) => $this->withHeader('User-Agent', $ua)->postJson('/pwa/event', $body)->assertNoContent();

        $send(['event' => 'install', 'method' => 'first_open', 'device' => 'ios-device-1', 'screen' => '393x852', 'dpr' => 3, 'lang' => 'az-AZ', 'dark' => true], self::IPHONE);
        $send(['event' => 'install', 'method' => 'first_open', 'device' => 'ios-device-1', 'screen' => '393x852'], self::IPHONE); // təkrar — yazılmır
        $send(['event' => 'open', 'device' => 'ios-device-1', 'screen' => '393x852'], self::IPHONE);
        // Android: model və versiya Client Hints-dən, ekran üfüqi gəlsə də portret yazılır
        $send(['event' => 'install', 'method' => 'prompt', 'device' => 'android-device-1', 'model' => 'SM-S918B', 'os' => '14.0.0', 'screen' => '915x412'], self::ANDROID);

        $this->assertSame(2, DB::table('pwa_events')->where('event', 'install')->count());
        $ios = DB::table('pwa_events')->where('device_id', 'ios-device-1')->where('event', 'install')->first();
        $this->assertSame(['ios', '18.6', 'Safari', null, '393x852', 1], [$ios->platform, $ios->os_version, $ios->browser, $ios->model, $ios->screen, (int) $ios->dark]);
        $android = DB::table('pwa_events')->where('device_id', 'android-device-1')->first();
        $this->assertSame(['android', '14.0', 'Chrome', '141.0', 'SM-S918B', '412x915', 'prompt'],
            [$android->platform, $android->os_version, $android->browser, $android->browser_version, $android->model, $android->screen, $android->method]);
    }

    public function test_validation(): void
    {
        $this->postJson('/pwa/event', ['event' => 'hack', 'device' => 'x'])->assertUnprocessable();
        $this->postJson('/pwa/event', ['event' => 'open', 'device' => 'abc"><script>'])->assertUnprocessable();
        $this->assertSame(0, DB::table('pwa_events')->count());
    }

    public function test_device_labels(): void
    {
        $this->assertSame('iPhone 14 Pro, 15, 15 Pro, 16', PwaDevice::label('ios', null, '393x852'));
        $this->assertSame('iPad', PwaDevice::label('ios', null, '820x1180'));
        $this->assertSame('SM-S918B', PwaDevice::label('android', 'SM-S918B', '412x915'));
        // Safari 26: sistem versiyası Safari-nin versiyasından
        $ua = 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.1 Mobile/15E148 Safari/604.1';
        $this->assertSame(['ios', '26.1', 'Safari', '26.1'], array_values(PwaDevice::parse($ua)));
    }
}
