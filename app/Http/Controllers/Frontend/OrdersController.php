<?php

namespace App\Http\Controllers\Frontend;

use App\Models\Order\OrderItemCancellation;
use App\Services\OrderItemCancellationService;
use App\Services\Payment\BirbankPaymentSync;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use App\Http\Controllers\Controller;
use App\Models\Order\Order;

class OrdersController extends Controller
{
    public function index()
    {
        $orders = Order::with([
            'items.product.brand',
            'items.product.images',
            'items.variant.size',
            'paymentMethod',
            'status',
        ])
            ->withCount('payments')
            ->withSum('itemCancellations as cancelled_amount', 'amount')
            ->where('customer_id', auth()->id())
            ->latest()
            ->paginate(10);

        // sonsuz scroll: növbəti səhifənin kartları (frontend/js/infinite-list.js)
        if (request()->ajax()) {
            return response()->json([
                'html' => view('frontend.partials.order-cards', compact('orders'))->render(),
                'next' => $orders->nextPageUrl(),
            ]);
        }

        return view('frontend.orders', compact('orders'));
    }

    public function details(Order $order)
    {
        abort_unless($order->customer_id === auth()->id(), 403);

        $order->load([
            'items.product.brand',
            'items.product.images',
            'items.variant.size',
            'paymentMethod',
            'payments' => fn ($query) => $query->latest(),
            'statusLogs.status',
            'address',
        ]);

        // Müştəri özü imtina edə bilərmi: allowed / contact / none (OrderItemCancellationService::customerCancelState)
        $cancelState = app(OrderItemCancellationService::class)->customerCancelState($order);

        return view('frontend.order-detail', compact('order', 'cancelState'));
    }

    /** "Sifarişdən imtina et": müştəri ilk mərhələlərdə sifarişini özü ləğv edir */
    public function cancel(Order $order, OrderItemCancellationService $service, BirbankPaymentSync $sync): RedirectResponse
    {
        abort_unless($order->customer_id === auth()->id(), 403);

        // Yarımçıq ödəniş: əvvəl bankdan soruşulur — bankda hələ açıqdırsa ləğv etmirik (müştəri ödəyərsə pul çıxar)
        $pending = $sync->resumePending($order, app()->getLocale());
        if (in_array($pending['state'], ['resume', 'blocked'], true)) {
            return redirect()->route('order.details', $order)->with('error', __('orders_cancel_payment_open'));
        }

        try {
            $result = $service->cancelByCustomer($order);
        } catch (ValidationException $e) {
            return redirect()->route('order.details', $order)->with('error', __('orders_cancel_contact'));
        }

        $cancellations = collect($result['cancellations']);
        $toCard = (float) $cancellations->where('refund_status', OrderItemCancellation::REFUND_PENDING)->sum('amount');
        $toBonus = (float) $cancellations->where('refund_status', OrderItemCancellation::REFUND_BONUS)->sum('amount');

        $message = __('orders_cancel_done');
        if ($toCard > 0) {
            $message .= ' '.__('orders_cancel_refund_card', ['amount' => number_format($toCard, 2)]);
        }
        if ($toBonus > 0) {
            $message .= ' '.__('orders_cancel_refund_bonus', ['amount' => number_format($toBonus, 2)]);
        }

        return redirect()->route('order.details', $order)->with('success', $message);
    }
}
