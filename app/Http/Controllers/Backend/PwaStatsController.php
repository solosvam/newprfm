<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Support\PwaDevice;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Admin → Satışlar → Ana ekran (PWA): neçə nəfər saytı ana ekrana əlavə edib, nə qədər açır, hansı cihazlarla.
 * Cihazın məlumatı onun dövrdəki son hadisəsindən götürülür (versiya yenilənə bilər).
 */
class PwaStatsController extends Controller
{
    public const PERIODS = ['7' => '7 gün', '30' => '30 gün', '90' => '90 gün', 'all' => 'Hamısı'];

    public function index(Request $request): View
    {
        $period = array_key_exists((string) $request->query('period'), self::PERIODS) ? (string) $request->query('period') : '30';
        $from = $period === 'all' ? null : Carbon::today()->subDays((int) $period - 1);
        $inPeriod = fn ($query) => $from ? $query->where('created_at', '>=', $from) : $query;

        $installs = $inPeriod(DB::table('pwa_events')->where('event', 'install'))
            ->selectRaw('platform, count(*) as total')->groupBy('platform')->pluck('total', 'platform');
        $opens = $inPeriod(DB::table('pwa_events')->where('event', 'open'));

        // Dövrdə aktiv cihazlar — hər cihazın son hadisəsi
        $lastIds = $inPeriod(DB::table('pwa_events'))->selectRaw('max(id)')->groupBy('device_id');
        $devices = DB::table('pwa_events')->whereIn('id', $lastIds)->get();

        return view('backend.pwa.index', [
            'period' => $period,
            'installs' => [
                'total' => (int) $installs->sum(),
                'android' => (int) ($installs['android'] ?? 0),
                'ios' => (int) ($installs['ios'] ?? 0),
                'allTime' => DB::table('pwa_events')->where('event', 'install')->count(),
            ],
            'opens' => (clone $opens)->count(),
            'activeDevices' => (clone $opens)->distinct()->count('device_id'),
            'customers' => $devices->whereNotNull('customer_id')->unique('customer_id')->count(),
            'daily' => $this->daily($from),
            'models' => $this->group($devices, fn ($d) => PwaDevice::label($d->platform, $d->model, $d->screen)),
            'systems' => $this->group($devices, fn ($d) => match ($d->platform) {
                'ios' => 'iOS '.($d->os_version ?? '?'),
                'android' => 'Android '.($d->os_version ?? '?'),
                default => 'Digər',
            }),
            'browsers' => $this->group($devices, fn ($d) => trim(($d->browser ?? 'Bilinmir').' '.($d->browser_version ? strtok($d->browser_version, '.') : ''))),
            'themes' => $this->group($devices, fn ($d) => $d->dark === null ? 'Bilinmir' : ($d->dark ? 'Qaranlıq' : 'İşıqlı')),
        ]);
    }

    /** @return Collection<int, array{name: string, platform: string, count: int}> */
    private function group(Collection $devices, callable $label): Collection
    {
        return $devices->groupBy(fn ($d) => $d->platform.'|'.$label($d))
            ->map(fn (Collection $rows, string $key) => [
                'platform' => explode('|', $key, 2)[0],
                'name' => explode('|', $key, 2)[1],
                'count' => $rows->count(),
            ])
            ->sortByDesc('count')->values();
    }

    /** Günlər üzrə (ən çox 90 gün): quraşdırma, açılış, açan cihaz */
    private function daily(?Carbon $from): array
    {
        $from = $from ?? Carbon::today()->subDays(89);
        $rows = DB::table('pwa_events')->where('created_at', '>=', $from)
            ->selectRaw("date(created_at) as day,
                sum(case when event = 'install' then 1 else 0 end) as installs,
                sum(case when event = 'open' then 1 else 0 end) as opens,
                count(distinct case when event = 'open' then device_id end) as devices")
            ->groupBy('day')->get()->keyBy('day');

        $days = [];
        for ($day = Carbon::today(); $day->gte($from); $day = $day->copy()->subDay()) {
            $row = $rows[$day->toDateString()] ?? null;
            $days[] = [
                'date' => $day->copy(),
                'installs' => (int) ($row->installs ?? 0),
                'opens' => (int) ($row->opens ?? 0),
                'devices' => (int) ($row->devices ?? 0),
            ];
        }

        return $days;
    }
}
