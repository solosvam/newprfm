<?php

namespace App\Http\Controllers\Frontend;

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
            ->where('customer_id', auth()->id())
            ->latest()
            ->paginate(10);

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

        return view('frontend.order-detail', compact('order'));
    }
}
