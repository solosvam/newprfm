<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Mail\OrderCreatedMail;
use App\Models\Order\Order;
use App\Models\Payment\Payment;
use App\Models\PromoCode;
use App\Services\BonusService;
use App\Services\Payment\Birbank;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class BirbankPaymentController extends Controller
{
    public function start(Request $request, Order $order, Birbank $birbank): RedirectResponse
    {
        abort_unless((int) $order->customer_id === (int) $request->user()->id, 403);

        // Reuse a successful payment instead of creating another charge.
        if ($order->payments()->where('status', Payment::PAID)->exists()) {
            return redirect()->route('checkout.success', $order);
        }

        // Qayda Order::canStartOnlinePayment()-dədir (SMS ödəniş linki də onu istifadə edir)
        if ($order->hasPendingPayment()) {
            return redirect()->route('order.details', $order)
                ->with('error', 'Əvvəlki ödənişin nəticəsi hələ dəqiqləşməyib.');
        }
        if (!$order->canStartOnlinePayment()) {
            return redirect()->route('order.details', $order)
                ->with('error', 'Bu sifariş üçün təkrar ödəniş hazırda mümkün deyil.');
        }

        $months = $order->paymentMethod?->code === 'birbank_installment'
            ? (int) $order->birbank_installment_months : null;
        $result = $birbank->createOrder($order->load('paymentMethod'), app()->getLocale(), $months);

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
                if ($order->promo_code_id) PromoCode::whereKey($order->promo_code_id)->increment('used_count');
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

        // SMS linkindən ödəyib: login istəmədən həmin səhifəyə qayıdır, nəticəni orada görür
        if ((int) $request->session()->pull(PayLinkController::SESSION_KEY) === (int) $payment->order_id) {
            $token = Order::whereKey($payment->order_id)->value('pay_token');
            if ($token) {
                return redirect()->route('pay.link', $token);
            }
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
            ->with('error', 'Ödəniş təsdiqlənmədi. Yenidən cəhd edin.');
    }
}
