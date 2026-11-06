<?php

namespace Tests\Feature\Http;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/** throttle:N,M — route-lar sayğacı bölüşmür (əvvəl bir route-un sorğuları digərinin limitini doldururdu → 429) */
class ThrottlePerRouteTest extends TestCase
{
    public function test_each_route_has_its_own_counter(): void
    {
        config(['cache.default' => 'array']);
        Route::middleware('throttle:60,1')->post('/__busy', fn () => 'ok')->name('test.busy');
        Route::middleware('throttle:3,1')->post('/__strict', fn () => 'ok')->name('test.strict');

        foreach (range(1, 10) as $i) {
            $this->post('/__busy')->assertOk();
        }
        foreach (range(1, 3) as $i) {
            $this->post('/__strict')->assertOk();
        }
        $this->post('/__strict')->assertStatus(429);
        $this->post('/__busy')->assertOk();
    }
}
