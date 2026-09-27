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
        DB::transaction(function () use ($payment) {
            $order = Order::whereKey($payment->order_id)->lockForUpdate()->firstOrFail();
            if ($payment->status === Payment::PAID && $order->payment_status !== 'paid') {
                $order->update(['payment_status' => 'paid']);
                app(BonusService::class)->earnForOrder($order->customer, $order, (float) $order->total);
                if (filter_var($order->customer->email, FILTER_VALIDATE_EMAIL)) {
                    Mail::to($order->customer->email)->queue(
                        (new OrderCreatedMail($order, app()->getLocale()))->afterCommit()
                    );
                }
            } elseif ($payment->status === Payment::FAILED && $order->payment_status !== 'paid') {
                $order->update(['payment_status' => 'failed']);
            } elseif ($payment->status === Payment::CANCELLED && $order->payment_status !== 'paid') {
                $order->update(['payment_status' => 'cancelled']);
            }
        });

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
            ->with('error', 'Ödəniş təsdiqlənmədi. Yenidən cəhd edin.');
    }
}
