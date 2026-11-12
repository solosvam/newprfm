<?php

namespace App\Http\Controllers\Courier;

use App\Http\Controllers\Controller;
use App\Models\Courier\CourierNotification;
use App\Models\Finance\MoneyMovement;
use App\Models\Order\Order;
use App\Models\Procurement\OrderItemAllocation;
use App\Services\Courier\CourierExpenses;
use App\Services\Courier\PickPhotoStorage;
use App\Services\FinanceService;
use App\Services\OrderStatusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Kuryer tətbiqi (/kuryer): ana səhifə (aktiv sifarişlər) və hesab (haqq-hesab tarixçəsi, xərclər).
 * Sifarişin səhifəsi və əməliyyatlar — Backend\CourierController, baş kuryer — Backend\CourierTeamController.
 */
class CourierAppController extends Controller
{
    public function home(FinanceService $finance, OrderStatusService $statuses): View
    {
        $user = auth('admin')->user();
        $orders = Order::with(['status', 'address', 'customer', 'items.allocations', 'items.product'])
            ->where('courier_id', $user->id)
            ->where(fn ($q) => $q->whereHas('status', fn ($s) => $s->whereIn('code', ['courier_assigned', 'sent']))
                // təhvil verilib, amma qapıda imtina edilən məhsul hələ depoya qaytarılmayıb
                ->orWhereHas('items.allocations', fn ($a) => $a->where('status', OrderItemAllocation::RETURNING)))
            ->orderBy('id')->get();

        return view('courier-app.home', [
            'orders' => $orders,
            'activeCount' => $orders->filter(fn ($o) => in_array($o->status?->code, ['courier_assigned', 'sent'], true))->count(),
            'balance' => $finance->balances()[$finance->courierAccount($user)->id] ?? 0,
            'deliveredToday' => Order::where('courier_id', $user->id)
                ->whereHas('status', fn ($q) => $q->where('code', 'delivered'))->whereDate('updated_at', today())->count(),
            'statuses' => $statuses,
        ]);
    }

    public function account(FinanceService $finance, CourierExpenses $expenses): View
    {
        $user = auth('admin')->user();
        $account = $finance->courierAccount($user);

        return view('courier-app.account', [
            'user' => $user,
            'balance' => $finance->balances()[$account->id] ?? 0,
            'history' => $finance->courierHistory($account),
            'expenses' => $expenses->of($user, null, 30),
        ]);
    }

    /** "Xərc yaz": məbləğ və açıqlama (Taxi...), qəbzin şəkli istəyə görə — balansdan dərhal çıxır (CourierExpenses) */
    public function storeExpense(Request $request, CourierExpenses $expenses): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', 'max:10000'],
            'note' => ['required', 'string', 'max:200'],
            'photo' => ['nullable', 'file', 'image', 'max:10240'],
        ], [
            'amount.required' => 'Məbləği yazın.', 'amount.min' => 'Məbləğ sıfırdan böyük olmalıdır.',
            'note.required' => 'Xərcin açıqlamasını yazın.', 'photo.image' => 'Şəkil oxunmadı — yenidən çəkin.', 'photo.max' => 'Şəkil çox böyükdür.',
        ]);
        $expense = $expenses->record(auth('admin')->user(), (float) $data['amount'], $data['note'], $request->file('photo'));

        return redirect()->to(route('kuryer.account').'#expenses')->with('success', number_format((float) $expense->amount, 2).' AZN xərc yazıldı.');
    }

    /** Öz xərcinin qəbz şəkli */
    public function expensePhoto(MoneyMovement $expense, FinanceService $finance): BinaryFileResponse
    {
        abort_unless(in_array($expense->kind, \App\Services\Courier\CourierExpenses::KINDS, true)
            && (int) $expense->from_account_id === (int) $finance->courierAccount(auth('admin')->user())->id, 404);
        $path = PickPhotoStorage::path($expense->photo, PickPhotoStorage::EXPENSES);
        abort_unless($path, 404);

        return response()->file($path, ['Cache-Control' => 'private, max-age=86400']);
    }

    /** Bildirişlər: yenilər yuxarıda (son 30 gün — köhnələr silinir) */
    public function notifications(): View
    {
        return view('courier-app.notifications', [
            'notifications' => CourierNotification::where('user_id', auth('admin')->id())->latest('created_at')->latest('id')->limit(100)->get(),
        ]);
    }

    /** Bildirişi açmaq: oxunmuş olur və aid olduğu yerə (sifariş, hesab) keçir — push-un linki də budur */
    public function openNotification(CourierNotification $notification): RedirectResponse
    {
        abort_unless((int) $notification->user_id === (int) auth('admin')->id(), 404);
        if (!$notification->read_at) {
            $notification->update(['read_at' => now()]);
        }

        return redirect($notification->url ?: route('kuryer.notifications'));
    }

    public function readAll(): RedirectResponse
    {
        CourierNotification::where('user_id', auth('admin')->id())->whereNull('read_at')->update(['read_at' => now()]);

        return redirect()->route('kuryer.notifications');
    }

    /** Oxunmamış bildirişlərin sayı — tətbiqin ikonundakı rəqəm (service worker push gələndə soruşur) */
    public function badge(): JsonResponse
    {
        return response()->json(['unread' => CourierNotification::unreadCount((int) auth('admin')->id())])->header('Cache-Control', 'no-store');
    }

    /** Kuryerin yeri (tətbiq açılanda / qayıdanda — app.js): tarixçəyə yazılır, son yer istifadəçidə; admin Kuryerlər səhifəsində görür */
    public function location(Request $request): JsonResponse
    {
        $data = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'accuracy' => ['nullable', 'numeric', 'min:0'],
        ]);
        $point = [
            'lat' => round((float) $data['lat'], 7),
            'lng' => round((float) $data['lng'], 7),
            'accuracy' => isset($data['accuracy']) ? (int) min(round((float) $data['accuracy']), 4294967295) : null,
        ];
        $user = auth('admin')->user();
        // Tarixçə (hər göndəriş) və son yer (siyahı üçün)
        \App\Models\Courier\CourierLocation::create($point + ['user_id' => $user->id, 'recorded_at' => now()]);
        $user->forceFill([
            'location_lat' => $point['lat'], 'location_lng' => $point['lng'],
            'location_accuracy' => $point['accuracy'], 'location_at' => now(),
        ])->save();

        return response()->json(['ok' => true]);
    }

    /** public/courier-app/sw.js — əhatəsi /kuryer (ana səhifə /kuryer sonda "/" olmadan açılır, ona görə Service-Worker-Allowed) */
    public function serviceWorker(): Response
    {
        return response((string) file_get_contents(public_path('courier-app/sw.js')), 200, [
            'Content-Type' => 'application/javascript; charset=utf-8',
            'Cache-Control' => 'no-cache',
            'Service-Worker-Allowed' => '/kuryer',
        ]);
    }
}
