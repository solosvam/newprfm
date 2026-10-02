<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Order\Order;
use App\Models\Order\OrderStatus;
use App\Models\Payment\PaymentMethod;
use App\Services\Dashboard\AdminDashboard;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Bütün sifarişlərin ümumi siyahısı (Satış → Sifarişlər). Sifariş detalı CRM-də açılır.
 * Süzgəclər: status (kod, "active" — bitməmiş, "courier_late" — kuryerdə 1 gündən çox), mənbə, ödəniş üsulu, axtarış.
 */
class OrdersController extends Controller
{
    public const PER_PAGE = 30;

    public function index(Request $request): View
    {
        $statuses = OrderStatus::where('active', 1)->orderBy('sort_order')->get();
        $status = (string) $request->query('status', '');
        $source = (string) $request->query('source', '');
        $payment = (string) $request->query('payment', '');
        $search = trim((string) $request->query('q', ''));

        $orders = Order::with(['status', 'customer', 'paymentMethod'])
            ->when($status === 'active', fn ($q) => $q->whereIn('order_status_id',
                $statuses->whereIn('code', array_keys(AdminDashboard::ACTIVE_STATUSES))->pluck('id')))
            ->when($status === 'courier_late', fn ($q) => $q
                ->whereIn('order_status_id', $statuses->whereIn('code', AdminDashboard::COURIER_STATUSES)->pluck('id'))
                ->where('updated_at', '<', now()->subDay()))
            ->when($status !== '' && !in_array($status, ['active', 'courier_late'], true), fn ($q) => $q
                ->whereIn('order_status_id', $statuses->where('code', $status)->pluck('id')))
            ->when($source === 'one_click', fn ($q) => $q->where('one_click', true))
            ->when(in_array($source, ['customer', 'operator'], true), fn ($q) => $q->where('one_click', false)->where('source', $source))
            ->when($payment !== '', fn ($q) => $q->whereHas('paymentMethod', fn ($m) => $m->where('code', $payment)))
            ->when($search !== '', function ($q) use ($search) {
                $like = '%'.addcslashes($search, '%_\\').'%';
                $q->where(fn ($w) => $w->where('order_no', 'like', $like)
                    ->orWhere('guest_mobile', 'like', $like)
                    ->orWhereHas('customer', fn ($c) => $c->where('mobile', 'like', $like)
                        ->orWhere('name', 'like', $like)->orWhere('surname', 'like', $like)));
            })
            ->latest('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('backend.orders.index', [
            'orders' => $orders,
            'statuses' => $statuses,
            'paymentMethods' => PaymentMethod::orderBy('sort_order')->get(),
            'filters' => compact('status', 'source', 'payment', 'search'),
        ]);
    }
}
