<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Order\Order;
use App\Models\Payment;
use App\Services\Payment\Birbank;
use App\Services\BonusService;
use App\Mail\OrderCreatedMail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
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
        // Bank verification is authoritative; never trust the redirect STATUS.
        $payment = $birbank->verify($payment);
        if ($payment->status === Payment::PAID) {
            DB::transaction(function () use ($payment) {
                $order = Order::whereKey($payment->order_id)->lockForUpdate()->firstOrFail();
                if ((float) $order->bonus_earned > 0) return;
                $customer = $order->customer;
                $bonus = app(BonusService::class)->earnForOrder($customer, $order, (float) $order->total);
                // Zero-bonus settings must also be idempotent.
                if ($bonus <= 0) $order->update(['bonus_earned' => 0]);
            });
        }

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
