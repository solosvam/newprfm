<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Support\PwaDevice;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * pwa.js: sayt ana ekrana əlavə olundu (install) və ya ana ekrandan açıldı (open) → pwa_events.
 * Platforma, sistem və brauzer serverdə User-Agent-dən; model və Android versiyası brauzerin
 * Client Hints-indən (yalnız Chrome / Android verir). Eyni cihazın ikinci quraşdırması yazılmır.
 */
class PwaController extends Controller
{
    public function event(Request $request): Response
    {
        $data = $request->validate([
            'event' => ['required', Rule::in(['install', 'open'])],
            'method' => ['nullable', Rule::in(['prompt', 'first_open'])],
            'device' => ['required', 'string', 'regex:/^[A-Za-z0-9-]{8,40}$/'],
            'model' => ['nullable', 'string', 'max:60'],
            'os' => ['nullable', 'string', 'max:20'],
            'screen' => ['nullable', 'string', 'regex:/^\d{2,4}x\d{2,4}$/'],
            'dpr' => ['nullable', 'numeric', 'between:0.5,9'],
            'lang' => ['nullable', 'string', 'max:10'],
            'dark' => ['nullable', 'boolean'],
        ]);

        if ($data['event'] === 'install'
            && DB::table('pwa_events')->where('event', 'install')->where('device_id', $data['device'])->exists()) {
            return response()->noContent();
        }

        $ua = (string) $request->userAgent();
        $device = PwaDevice::parse($ua);
        // Ekran həmişə portret yazılır: kiçik x böyük (Android-də dönəndə yerləri dəyişir)
        $screen = null;
        if (!empty($data['screen'])) {
            $sides = array_map('intval', explode('x', $data['screen']));
            sort($sides);
            $screen = implode('x', $sides);
        }

        DB::table('pwa_events')->insert([
            'event' => $data['event'],
            'method' => $data['event'] === 'install' ? ($data['method'] ?? null) : null,
            'device_id' => $data['device'],
            'platform' => $device['platform'],
            'os_version' => $device['platform'] === 'android' && !empty($data['os'])
                ? implode('.', array_slice(explode('.', $data['os']), 0, 2))
                : $device['os_version'],
            'browser' => $device['browser'],
            'browser_version' => $device['browser_version'],
            'model' => $device['platform'] === 'android' ? (trim((string) ($data['model'] ?? '')) ?: null) : null,
            'screen' => $screen,
            'dpr' => $data['dpr'] ?? null,
            'lang' => $data['lang'] ?? null,
            'dark' => $data['dark'] ?? null,
            'customer_id' => auth()->id(),
            'user_agent' => mb_substr($ua, 0, 500),
            'created_at' => now(),
        ]);

        return response()->noContent();
    }
}
