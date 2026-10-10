<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Order\Order;
use App\Models\Payment\Payment;
use App\Services\BonusService;
use App\Services\ContactInfo;
use App\Services\OrderPayLinkService;
use App\Services\Payment\Birbank;
use App\Services\Payment\BirbankPaymentSync;
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

    /** Bankdan qayıdan müştəri: link bu arada bitsə də nəticə səhifəsi bir dəfə açılır (+ sifariş ID) */
    public const SESSION_RETURN_KEY = 'pay_link_return_';

    public function show(Request $request, string $token, OrderPayLinkService $payLink): Response
    {
        $order = $this->find($token);

        // Vaxtı bitmiş link sifarişin heç bir məlumatını göstərmir. İstisna: müştəri bu linkdən ödənişə başlayıb və
        // bankdan indi qayıdır (callback sessiyanı artıq götürüb yönləndirib) — nəticəni görməlidir.
        if ($payLink->isExpired($order) && !$request->session()->pull(self::SESSION_RETURN_KEY.$order->id)) {
            return response()
                ->view('frontend.pay-link-expired', ['contact' => app(ContactInfo::class)], 410)
                ->header('X-Robots-Tag', 'noindex, nofollow')
                ->header('Cache-Control', 'no-store, private');
        }

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

        // Bonus təhvildə yazılır: yazılıbsa — yazılan, yoxsa — təhvil veriləndə qazanacağı
        $bonus = match ($state) {
            'paid', 'pay', 'failed', 'checking' => (float) $order->bonus_earned > 0
                ? (float) $order->bonus_earned
                : app(BonusService::class)->pendingFor($order),
            default => 0.0,
        };

        return response()
            ->view('frontend.pay-link', compact('order', 'state', 'token', 'bonus'))
            ->header('X-Robots-Tag', 'noindex, nofollow')
            ->header('Referrer-Policy', 'no-referrer')
            ->header('Cache-Control', 'no-store, private');
    }

    public function start(Request $request, string $token, Birbank $birbank, OrderPayLinkService $payLink, BirbankPaymentSync $sync): RedirectResponse
    {
        $order = $this->find($token);

        if ($payLink->isExpired($order)) {
            return redirect()->route('pay.link', $token);
        }

        // Yarımçıq qalmış cəhd: bankda hələ açıqdırsa eyni bank səhifəsinə qayıdır (yeni ödəniş yaranmır)
        $pending = $sync->resumePending($order, app()->getLocale());
        if ($pending['state'] === 'resume') {
            $request->session()->put(self::SESSION_KEY, $order->id);

            return redirect()->away($pending['url']);
        }
        if (in_array($pending['state'], ['paid', 'blocked'], true)) {
            return redirect()->route('pay.link', $token);
        }
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
