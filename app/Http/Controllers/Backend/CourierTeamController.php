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
    /** Kuryerin işdə olan sifarişləri: toplanır / yoldadır / ünvandadır və ya anbara qaytarılacaq malı var */
    public const ACTIVE = ['courier_assigned', 'sent', 'at_address'];

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

    public function show(User $courier, FinanceService $finance, OrderStatusService $statuses): View
    {
        abort_unless(FinanceService::isCourier($courier), 404);
        $account = $finance->courierAccount($courier);
        $balance = (int) ($finance->balances()[$account->id] ?? 0);
        $me = auth('admin')->user();

        return view('backend.courier.team-show', [
            'courier' => $courier,
            'balance' => $balance,
            'history' => $finance->courierHistory($account, 100),
            'active' => $this->activeOrders($courier)->with(['status', 'customer', 'address', 'items.product', 'items.allocations.warehouse'])->orderBy('id')->get(),
            'archive' => Order::with(['status', 'customer'])->where('courier_id', $courier->id)
                ->whereDoesntHave('status', fn ($q) => $q->whereIn('code', self::ACTIVE))
                ->orderByDesc('id')->paginate(20)->withQueryString(),
            'statuses' => $statuses,
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

    private function activeOrders(User $courier)
    {
        return Order::where('courier_id', $courier->id)
            ->where(fn ($q) => $q->whereHas('status', fn ($s) => $s->whereIn('code', self::ACTIVE))
                ->orWhereHas('items.allocations', fn ($a) => $a->where('status', OrderItemAllocation::RETURNING)));
    }
}
