<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Order\Order;
use App\Models\Payment;
use App\Services\Payment\Birbank;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BirbankPaymentController extends Controller
{
    public function start(Request $request, Order $order, Birbank $birbank): RedirectResponse
    {
        abort_unless((int) $order->customer_id === (int) $request->user()->id, 403);

        // Reuse a successful payment instead of creating another charge.
        if ($order->payments()->where('status', Payment::PAID)->exists()) {
            return redirect()->route('checkout.success', $order);
        }

        $result = $birbank->createOrder($order->load('paymentMethod'), app()->getLocale());

        return redirect()->away($result['url']);
    }

    public function callback(Request $request, Payment $payment, Birbank $birbank): RedirectResponse
    {
        // The gateway return is not proof of payment.
        $payment = $birbank->verify($payment);

        if (!$request->user()) {
            return redirect()->route('front.login', [
                'redirect' => route('checkout.success', $payment->order_id),
            ]);
        }

        abort_unless((int) $payment->customer_id === (int) $request->user()->id, 403);

        if ($payment->status === Payment::PAID) {
            return redirect()->route('checkout.success', $payment->order_id);
        }

        return redirect()->route('order.details', $payment->order_id)
            ->with('error', __('credit_modal_error'));
    }
}
