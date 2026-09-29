<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Order\Order;
use App\Models\Payment\Payment;
use App\Services\Payment\Birbank;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * SMS ödəniş linki — /p/{token}. Login tələb olunmur: linki bilən sifarişi görür və ödəyir.
 * Ona görə səhifədə yalnız ödəniş üçün lazım olan məlumat var (telefon, tam ünvan yoxdur).
 */
class PayLinkController extends Controller
{
    /** Callback-dən sonra müştəri bu səhifəyə qayıtsın deyə (BirbankPaymentController::callback) */
    public const SESSION_KEY = 'pay_link_order';

    public function show(string $token): Response
    {
        $order = $this->find($token);
        $order->load([
            'items.product.brand',
            'items.product.images',
            'items.variant.size',
            'paymentMethod',
            'status',
            'address',
            'customer',
            'payments' => fn ($q) => $q->latest(),
        ]);

        $lastPayment = $order->payments->first();
        $state = match (true) {
            $order->payment_status === 'paid' => 'paid',
            $order->isCancelled() => 'cancelled',
            $order->hasPendingPayment() => 'checking',
            $order->canStartOnlinePayment() => in_array($lastPayment?->status, [Payment::FAILED, Payment::CANCELLED], true) ? 'failed' : 'pay',
            default => 'unavailable',
        };

        return response()
            ->view('frontend.pay-link', compact('order', 'state', 'token'))
            ->header('X-Robots-Tag', 'noindex, nofollow')
            ->header('Referrer-Policy', 'no-referrer')
            ->header('Cache-Control', 'no-store, private');
    }

    public function start(Request $request, string $token, Birbank $birbank): RedirectResponse
    {
        $order = $this->find($token);

        if (!$order->canStartOnlinePayment()) {
            return redirect()->route('pay.link', $token);
        }

        $request->session()->put(self::SESSION_KEY, $order->id);

        $months = $order->paymentMethod?->code === 'birbank_installment'
            ? (int) $order->birbank_installment_months : null;
        $result = $birbank->createOrder($order->load('paymentMethod'), app()->getLocale(), $months);

        return redirect()->away($result['url']);
    }

    /** Yalnız onlayn ödənişli sifarişlər; qalanları üçün link yoxdur (404) */
    private function find(string $token): Order
    {
        $order = Order::with('paymentMethod', 'status')->where('pay_token', $token)->firstOrFail();
        abort_unless($order->isOnlinePayment(), 404);

        return $order;
    }
}
