<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Finance\FinanceAccount;
use App\Models\Finance\MoneyMovement;
use App\Models\Order\Order;
use App\Models\Procurement\OrderItemAllocation;
use App\Models\User;
use App\Services\Courier\CourierExpenses;
use App\Services\Courier\PickPhotoStorage;
use App\Services\FinanceService;
use App\Services\OrderStatusService;
use App\Services\Telegram\CourierTelegram;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Baş kuryer (courier.team): bütün kuryerlərin balansı, haqq-hesabı və sifarişləri (aktivlər və arxiv).
 * courier.collect — kuryerin balansındakı pulu öz hesabına köçürmək ("Baş kuryerə təhvil") və öz balansından kuryerə pul vermək;
 * kuryerə Telegram bildirişi gedir.
 * Xərclər — kuryerin yazdığı xərclər (qəbz şəkli, ləğv — CourierExpenses).
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

        return view($this->view('team'), ['couriers' => $couriers, 'me' => auth('admin')->id()]);
    }

    /**
     * Arxiv filtri: a_from / a_to (son dəyişiklik tarixi), a_status, a_q (sifariş nömrəsi və ya müştəri);
     * tarixçə filtri: h_from / h_to, h_kind. Səhifələr: page (arxiv), hpage (tarixçə).
     * Baxan kuryerdirsə (baş kuryer): filtrsiz — yalnız bu gün; filtrdə ən çox COURIER_DAYS_BACK gün geriyə.
     * Yer tarixçəsi (l_date — gün, standart bu gün) yalnız admin paneldə.
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
            'l_date' => ['nullable', 'date'],
        ]);
        // Yer tarixçəsi (admin panel): seçilən günün nöqtələri — səhifədəki xəritədə marşrut kimi (courier-route.js)
        $locationDate = \Illuminate\Support\Carbon::parse($f['l_date'] ?? today())->startOfDay();
        $locations = $request->routeIs('kuryer.*') ? collect() : \App\Models\Courier\CourierLocation::where('user_id', $courier->id)
            ->whereBetween('recorded_at', [$locationDate, $locationDate->copy()->endOfDay()])->orderBy('recorded_at')->get();

        $minDate = FinanceService::isCourier($me) ? today()->subDays(self::COURIER_DAYS_BACK)->toDateString() : null;
        // Sorğuda istifadə olunan "tarixdən": kuryerə tarix yazılmayıbsa — bu gün, yazılıbsa — həddən əvvəl deyil
        $since = function (?string $from) use ($minDate) {
            if (!$minDate) {
                return $from;
            }
            if (!$from) {
                return today()->toDateString();
            }

            return max(\Illuminate\Support\Carbon::parse($from)->toDateString(), $minDate);
        };

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

        return view($this->view('team-show'), [
            'courier' => $courier,
            'balance' => $balance,
            'filters' => $f,
            'minDate' => $minDate,
            'locations' => $locations,
            // xəritə üçün: [lat, lng, "HH:MM", dəqiqlik]
            'routePoints' => $locations->map(fn ($p) => [$p->lat, $p->lng, $p->recorded_at->format('H:i'), $p->accuracy])->values(),
            'locationDate' => $locationDate,

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
            // Kuryerə pul vermək — baş kuryerin öz balansından (əlində olan şirkət pulu)
            'myBalance' => $myBalance = FinanceService::isCourier($me) ? (int) ($finance->balances()[$finance->courierAccount($me)->id] ?? 0) : 0,
            'canGive' => $me->can('courier.collect') && FinanceService::isCourier($me) && $me->id !== $courier->id && $myBalance > 0,
            // Xərclər: baş kuryerə son COURIER_DAYS_BACK gün, adminə son 100
            'expenses' => $expenses = app(CourierExpenses::class)->of($courier, $minDate),
            'cancellable' => $expenses->filter(fn ($e) => app(CourierExpenses::class)->canCancel($e, $me))->pluck('id')->all(),
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
        rescue(fn () => app(\App\Services\Courier\CourierNotifier::class)->moneyCollected($movement, $courier, $me, $after), null, !app()->runningUnitTests());

        return back()->with('success', number_format((float) $movement->amount, 2).' AZN '.trim($courier->full_name).' adlı kuryerdən hesabınıza köçürüldü.');
    }

    /** Baş kuryerin balansındakı pul kuryerə (məbləğ baş kuryerin qalığından çox ola bilməz) */
    public function give(Request $request, User $courier, FinanceService $finance, CourierTelegram $telegram): RedirectResponse
    {
        $me = auth('admin')->user();
        abort_unless(FinanceService::isCourier($courier), 404);
        abort_unless(FinanceService::isCourier($me), 403, 'Pulu yalnız kuryer hesabı olan baş kuryer verə bilər.');
        $data = $request->validateWithBag('give', [
            'amount' => ['required', 'numeric', 'min:0.01', 'max:1000000'],
            'note' => ['nullable', 'string', 'max:200'],
        ], ['amount.required' => 'Məbləği yazın.']);
        if ($me->id === $courier->id) {
            throw ValidationException::withMessages(['amount' => 'Özünüzə pul vermək olmaz.'])->errorBag('give');
        }

        $from = $finance->courierAccount($me);
        $to = $finance->courierAccount($courier);
        [$movement, $after] = DB::transaction(function () use ($finance, $from, $to, $data, $me) {
            FinanceAccount::whereKey([$from->id, $to->id])->lockForUpdate()->get();
            $balances = $finance->balances();
            $mine = (int) ($balances[$from->id] ?? 0);
            $cents = (int) round((float) $data['amount'] * 100);
            if ($cents > $mine) {
                throw ValidationException::withMessages(['amount' => 'Balansınızda '.number_format(max(0, $mine) / 100, 2).' AZN var.'])->errorBag('give');
            }
            $movement = $finance->record($from, $to, $cents / 100, 'courier_give', ['note' => $data['note'] ?? null], $me->id);

            return [$movement, (int) ($balances[$to->id] ?? 0) + $cents];
        });
        rescue(fn () => $telegram->moneyGiven($movement, $courier, $me, $after), null, !app()->runningUnitTests());
        rescue(fn () => app(\App\Services\Courier\CourierNotifier::class)->moneyGiven($movement, $courier, $me, $after), null, !app()->runningUnitTests());

        return back()->with('success', number_format((float) $movement->amount, 2).' AZN hesabınızdan '.trim($courier->full_name).' adlı kuryerə verildi.');
    }

    /** Admin kuryerin balansından birbaşa xərc yazır (kuryer tətbiqindəki "Xərc yaz" ilə eyni; kuryerə bildiriş gedir) */
    public function storeExpense(Request $request, User $courier, CourierExpenses $expenses): RedirectResponse
    {
        abort_unless(FinanceService::isCourier($courier), 404);
        $data = $request->validateWithBag('expense', [
            'amount' => ['required', 'numeric', 'min:0.01', 'max:10000'],
            'note' => ['required', 'string', 'max:200'],
            'photo' => ['nullable', 'file', 'image', 'max:10240'],
        ], [
            'amount.required' => 'Məbləği yazın.', 'amount.min' => 'Məbləğ sıfırdan böyük olmalıdır.',
            'note.required' => 'Xərcin açıqlamasını yazın.', 'photo.image' => 'Şəkil oxunmadı.', 'photo.max' => 'Şəkil çox böyükdür.',
        ]);
        $expense = $expenses->record($courier, (float) $data['amount'], $data['note'], $request->file('photo'), auth('admin')->user());

        return redirect()->to(route('admin.courier.team.show', $courier).'#expenses')
            ->with('success', trim($courier->full_name).' adlı kuryerin balansından '.number_format((float) $expense->amount, 2).' AZN xərc yazıldı.');
    }

    /** Kuryerin xərcini ləğv etmək — əks əməliyyat, pul kuryerin balansına qayıdır (kim edə bilər — CourierExpenses::canCancel) */
    public function cancelExpense(User $courier, MoneyMovement $expense, CourierExpenses $expenses): RedirectResponse
    {
        $this->ensureExpenseOf($courier, $expense);
        $expenses->cancel($expense, auth('admin')->user());

        return back()->with('success', number_format((float) $expense->amount, 2).' AZN xərc ləğv edildi — məbləğ '.trim($courier->full_name).' adlı kuryerin balansına qayıtdı.');
    }

    /** Xərcin qəbz şəkli (baş kuryer, admin) */
    public function expensePhoto(User $courier, MoneyMovement $expense): BinaryFileResponse
    {
        $this->ensureExpenseOf($courier, $expense);
        $path = PickPhotoStorage::path($expense->photo, PickPhotoStorage::EXPENSES);
        abort_unless($path, 404);

        return response()->file($path, ['Cache-Control' => 'private, max-age=86400']);
    }

    private function ensureExpenseOf(User $courier, MoneyMovement $expense): void
    {
        abort_unless(FinanceService::isCourier($courier) && in_array($expense->kind, CourierExpenses::KINDS, true)
            && (int) $expense->from_account_id === (int) app(FinanceService::class)->courierAccount($courier)->id, 404);
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

    /** Kuryer tətbiqindən (/kuryer/kuryerler) — tətbiqin səhifəsi, admin paneldən — panelin səhifəsi */
    private function view(string $name): string
    {
        return request()->routeIs('kuryer.*') ? 'courier-app.'.$name : 'backend.courier.'.$name;
    }
}
