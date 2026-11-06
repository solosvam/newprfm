<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Finance\FinanceAccount;
use App\Models\Order\Order;
use App\Models\Procurement\OrderItemAllocation;
use App\Models\User;
use App\Services\FinanceService;
use App\Services\OrderStatusService;
use App\Services\Telegram\CourierTelegram;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Baş kuryer (courier.team): bütün kuryerlərin balansı, haqq-hesabı və sifarişləri (aktivlər və arxiv).
 * courier.collect — kuryerin balansındakı pulu öz hesabına köçürmək ("Baş kuryerə təhvil"); kuryerə Telegram bildirişi gedir.
 */
class CourierTeamController extends Controller
{
    /** Kuryerin işdə olan sifarişləri: toplanır / yoldadır / ünvandadır və ya depoya qaytarılacaq malı var */
    public const ACTIVE = ['courier_assigned', 'sent'];

    /** Baş kuryer (kuryer) arxivdə və tarixçədə ən çox neçə gün geriyə baxa bilər; adminə məhdudiyyət yoxdur */
    public const COURIER_DAYS_BACK = 3;

    public function index(FinanceService $finance): View
    {
        $balances = $finance->balances();
        $couriers = FinanceService::courierUsers()->orderByDesc('active')->orderBy('name')->get()
            ->map(function (User $user) use ($finance, $balances) {
                $account = $finance->courierAccount($user);

                return [
                    'user' => $user,
                    'balance' => (int) ($balances[$account->id] ?? 0),
                    'active' => $this->activeOrders($user)->count(),
                    'deliveredToday' => Order::where('courier_id', $user->id)->whereHas('status', fn ($q) => $q->where('code', 'delivered'))
                        ->whereDate('updated_at', today())->count(),
                ];
            })
            // Deaktiv kuryer yalnız balansı varsa görünür
            ->filter(fn ($row) => $row['user']->active || $row['balance'] !== 0)->values();

        return view('backend.courier.team', ['couriers' => $couriers, 'me' => auth('admin')->id()]);
    }

    /**
     * Arxiv filtri: a_from / a_to (son dəyişiklik tarixi), a_status, a_q (sifariş nömrəsi və ya müştəri);
     * tarixçə filtri: h_from / h_to, h_kind. Səhifələr: page (arxiv), hpage (tarixçə).
     * Baxan kuryerdirsə (baş kuryer) — hər iki siyahı son COURIER_DAYS_BACK gündən o yana getmir (filtrsiz də).
     */
    public function show(Request $request, User $courier, FinanceService $finance, OrderStatusService $statuses): View
    {
        abort_unless(FinanceService::isCourier($courier), 404);
        $account = $finance->courierAccount($courier);
        $balance = (int) ($finance->balances()[$account->id] ?? 0);
        $me = auth('admin')->user();
        $f = $request->validate([
            'a_from' => ['nullable', 'date'], 'a_to' => ['nullable', 'date'], 'a_status' => ['nullable', 'string', 'max:30'], 'a_q' => ['nullable', 'string', 'max:60'],
            'h_from' => ['nullable', 'date'], 'h_to' => ['nullable', 'date'], 'h_kind' => ['nullable', 'string', 'max:30'],
        ]);
        $minDate = FinanceService::isCourier($me) ? today()->subDays(self::COURIER_DAYS_BACK)->toDateString() : null;
        // Sorğuda istifadə olunan "tarixdən": yazılan tarix, amma həddən əvvəl deyil (filtr formasında yazılan qalır)
        $since = fn (?string $from) => $minDate && (!$from || \Illuminate\Support\Carbon::parse($from)->toDateString() < $minDate) ? $minDate : $from;

        // Arxiv: aktiv olmayan sifarişlər
        $archive = Order::where('courier_id', $courier->id)
            ->whereDoesntHave('status', fn ($q) => $q->whereIn('code', self::ACTIVE))
            ->when($since($f['a_from'] ?? null), fn ($q, $d) => $q->whereDate('updated_at', '>=', $d))
            ->when($f['a_to'] ?? null, fn ($q, $d) => $q->whereDate('updated_at', '<=', $d))
            ->when($f['a_status'] ?? null, fn ($q, $code) => $q->whereHas('status', fn ($s) => $s->where('code', $code)))
            ->when(trim((string) ($f['a_q'] ?? '')), function ($q, $text) {
                $like = '%'.addcslashes($text, '%_\\').'%';
                $q->where(fn ($w) => $w->where('order_no', 'like', $like)
                    ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', $like)->orWhere('surname', 'like', $like)));
            });
        $archiveStatuses = \App\Models\Order\OrderStatus::whereIn('id', Order::where('courier_id', $courier->id)->select('order_status_id'))
            ->whereNotIn('code', self::ACTIVE)->orderBy('id')->pluck('name_az', 'code');

        // Tarixçə: qalıq bütün tarixçə üzrə, sonra filtr
        $ledger = $finance->courierLedger($account);
        $kinds = $ledger->pluck('m.kind')->unique()->mapWithKeys(fn ($k) => [$k => \App\Models\Finance\MoneyMovement::KINDS[$k][0] ?? $k])->sort();
        $history = $ledger
            ->when($since($f['h_from'] ?? null), fn ($rows, $d) => $rows->filter(fn ($r) => $r['m']->occurred_at->toDateString() >= $d))
            ->when($f['h_to'] ?? null, fn ($rows, $d) => $rows->filter(fn ($r) => $r['m']->occurred_at->toDateString() <= $d))
            ->when($f['h_kind'] ?? null, fn ($rows, $k) => $rows->filter(fn ($r) => $r['m']->kind === $k))
            ->values();
        $hPage = max(1, (int) $request->query('hpage', 1));
        $historyPage = new \Illuminate\Pagination\LengthAwarePaginator($history->forPage($hPage, 30)->values(), $history->count(), 30, $hPage,
            ['path' => $request->url(), 'pageName' => 'hpage', 'query' => $request->except('hpage'), 'fragment' => 'history']);

        return view('backend.courier.team-show', [
            'courier' => $courier,
            'balance' => $balance,
            'filters' => $f,
            'minDate' => $minDate,
            'history' => $historyPage,
            'historyTotals' => ['in' => $history->where('delta', '>', 0)->sum('delta'), 'out' => -$history->where('delta', '<', 0)->sum('delta')],
            'kinds' => $kinds,
            'active' => $this->activeOrders($courier)->with(['status', 'customer', 'address', 'items.product', 'items.allocations.warehouse'])->orderBy('id')->get(),
            'archive' => (clone $archive)->with(['status', 'customer'])->orderByDesc('updated_at')->orderByDesc('id')->paginate(20)->withQueryString()->fragment('archive'),
            'archiveTotals' => ['count' => (clone $archive)->count(), 'sum' => (float) (clone $archive)->sum('total')],
            'archiveStatuses' => $archiveStatuses,
            'statuses' => $statuses,
            'couriers' => $statuses->couriers()->reject(fn ($u) => $u->id === $courier->id)->values(),
            'canCollect' => $me->can('courier.collect') && FinanceService::isCourier($me) && $me->id !== $courier->id && $balance > 0,
        ]);
    }

    /** Kuryerin balansındakı pul baş kuryerin hesabına (məbləğ qalıqdan çox ola bilməz) */
    public function collect(Request $request, User $courier, FinanceService $finance, CourierTelegram $telegram): RedirectResponse
    {
        $me = auth('admin')->user();
        abort_unless(FinanceService::isCourier($courier), 404);
        abort_unless(FinanceService::isCourier($me), 403, 'Pulu yalnız kuryer hesabı olan baş kuryer götürə bilər.');
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', 'max:1000000'],
            'note' => ['nullable', 'string', 'max:200'],
        ], ['amount.required' => 'Məbləği yazın.']);
        if ($me->id === $courier->id) {
            throw ValidationException::withMessages(['amount' => 'Öz balansınızdan köçürmək olmaz.']);
        }

        $from = $finance->courierAccount($courier);
        $to = $finance->courierAccount($me);
        [$movement, $after] = DB::transaction(function () use ($finance, $from, $to, $data, $me) {
            FinanceAccount::whereKey([$from->id, $to->id])->lockForUpdate()->get(); // eyni anda iki köçürmə qalığı aşmasın
            $balance = (int) ($finance->balances()[$from->id] ?? 0);
            $cents = (int) round((float) $data['amount'] * 100);
            if ($cents > $balance) {
                throw ValidationException::withMessages(['amount' => 'Kuryerin balansında '.number_format(max(0, $balance) / 100, 2).' AZN var.']);
            }
            $movement = $finance->record($from, $to, $cents / 100, 'courier_collect', ['note' => $data['note'] ?? null], $me->id);

            return [$movement, $balance - $cents];
        });
        rescue(fn () => $telegram->moneyCollected($movement, $courier, $me, $after), null, !app()->runningUnitTests());

        return back()->with('success', number_format((float) $movement->amount, 2).' AZN '.trim($courier->full_name).' adlı kuryerdən hesabınıza köçürüldü.');
    }

    /** Baş kuryer kuryerin sifarişini başqa kuryerə ötürür (toplamadan əvvəl — OrderStatusService::transferBlock) */
    public function transfer(Request $request, User $courier, Order $order, OrderStatusService $statuses): RedirectResponse
    {
        abort_unless(FinanceService::isCourier($courier) && (int) $order->courier_id === (int) $courier->id, 404);
        $data = $request->validate(['courier_id' => ['required', 'integer']], ['courier_id.required' => 'Kuryeri seçin.']);
        $to = $statuses->couriers()->firstWhere('id', (int) $data['courier_id']);
        abort_if(!$to, 422, 'Kuryer tapılmadı.');
        $statuses->transferCourier($order, $to, (int) auth('admin')->id(), byHead: true);

        return back()->with('success', 'Sifariş '.$order->order_no.' '.trim($to->full_name).' adlı kuryerə ötürüldü.');
    }

    private function activeOrders(User $courier)
    {
        return Order::where('courier_id', $courier->id)
            ->where(fn ($q) => $q->whereHas('status', fn ($s) => $s->whereIn('code', self::ACTIVE))
                ->orWhereHas('items.allocations', fn ($a) => $a->where('status', OrderItemAllocation::RETURNING)));
    }
}
