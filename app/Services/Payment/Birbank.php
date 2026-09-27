<?php

namespace App\Services\Payment;

use App\Models\Order\Order;
use App\Models\Payment;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class Birbank
{
    private function endpoint(): string
    {
        $config = config('services.birbank');
        $url = $config['test_mode'] ? $config['test_url'] : $config['live_url'];

        if (!is_string($url) || !str_starts_with($url, 'https://')) {
            throw new RuntimeException('Birbank API URL must use HTTPS.');
        }

        return rtrim($url, '/');
    }

    private function http(): PendingRequest
    {
        $config = config('services.birbank');
        $username = $config['test_mode'] ? $config['test_username'] : $config['username'];
        $password = $config['test_mode'] ? $config['test_password'] : $config['password'];

        if (!$username || !$password) {
            throw new RuntimeException('Birbank credentials are not configured.');
        }

        return Http::withBasicAuth($username, $password)
            ->acceptJson()
            ->asJson()
            ->connectTimeout(5)
            ->timeout(20);
    }

    /**
     * Standard hosted payment page (Order_SMS).
     * The bank's hppUrl may already end in /flex; never append it twice.
     */
    public function createOrder(Order $order, string $language = 'az'): array
    {
        $order->loadMissing('paymentMethod');

        // The project's payment method code is online_card.
        if ($order->paymentMethod?->code !== 'online_card') {
            throw ValidationException::withMessages([
                'payment' => 'This order is not configured for online card payment.',
            ]);
        }

        if (!$order->customer_id) {
            throw ValidationException::withMessages(['payment' => 'Customer is required.']);
        }

        $amount = number_format((float) $order->total, 2, '.', '');
        if ((float) $amount <= 0) {
            throw ValidationException::withMessages(['payment' => 'Invalid payment amount.']);
        }

        if ($order->payments()->where('status', Payment::PAID)->exists()) {
            throw ValidationException::withMessages(['payment' => 'This order has already been paid.']);
        }

        $payment = Payment::create([
            'customer_id' => $order->customer_id,
            'order_id' => $order->id,
            'provider' => 'birbank',
            'amount' => $amount,
            'status' => Payment::PENDING,
        ]);

        try {
            $response = $this->http()->post($this->endpoint().'/order', [
                'order' => [
                    'typeRid' => 'Order_SMS',
                    'amount' => $amount,
                    'currency' => 'AZN',
                    'language' => in_array($language, ['az', 'en', 'ru'], true) ? $language : 'az',
                    'title' => 'Parfumshop',
                    'description' => (string) $order->order_no,
                    'hppRedirectUrl' => route('payment.birbank.return', ['payment' => $payment->id]),
                ],
            ]);

            if (!$response->successful()) {
                throw new RuntimeException('Birbank order creation failed (HTTP '.$response->status().').');
            }

            $bankOrder = $response->json('order');
            if (!is_array($bankOrder)
                || empty($bankOrder['id'])
                || empty($bankOrder['password'])
                || empty($bankOrder['hppUrl'])) {
                throw new RuntimeException('Birbank returned an incomplete order.');
            }

            $hppUrl = rtrim((string) $bankOrder['hppUrl'], '/');
            if (!str_ends_with(parse_url($hppUrl, PHP_URL_PATH) ?: '', '/flex')) {
                $hppUrl .= '/flex';
            }

            // Only accept the HPP URL supplied by the configured bank host.
            $apiHost = parse_url($this->endpoint(), PHP_URL_HOST);
            $hppHost = parse_url($hppUrl, PHP_URL_HOST);
            if (!str_starts_with($hppUrl, 'https://') || !$hppHost
                || !($hppHost === $apiHost || str_ends_with($hppHost, '.kapitalbank.az'))) {
                throw new RuntimeException('Birbank returned an unexpected payment page URL.');
            }

            $payment->update([
                'provider_order_id' => (string) $bankOrder['id'],
                'session_id' => (string) $bankOrder['password'],
            ]);

            return [
                'payment_id' => $payment->id,
                'url' => $hppUrl.'?'.http_build_query([
                    'id' => $bankOrder['id'],
                    'password' => $bankOrder['password'],
                ], '', '&', PHP_QUERY_RFC3986),
            ];
        } catch (\Throwable $exception) {
            // An HTTP timeout is ambiguous: the bank might have created an order.
            // Do not mark it as failed if its outcome cannot be established.
            $payment->update(['response_text' => 'Birbank order creation requires reconciliation.']);
            throw $exception;
        }
    }

    /**
     * Callback STATUS is provisional. The only source of truth is GET /order/{ID}.
     * Repeated callbacks are idempotent and cannot downgrade an already paid row.
     */
    public function verify(Payment $payment): Payment
    {
        if ($payment->provider !== 'birbank' || !$payment->provider_order_id) {
            throw new RuntimeException('Invalid Birbank payment.');
        }

        $response = $this->http()->get(
            $this->endpoint().'/order/'.rawurlencode($payment->provider_order_id),
            ['tranDetailLevel' => 2, 'tokenDetailLevel' => 2, 'orderDetailLevel' => 2]
        );

        if (!$response->successful()) {
            throw new RuntimeException('Birbank verification failed (HTTP '.$response->status().').');
        }

        $bankOrder = $response->json('order');
        if (!is_array($bankOrder)
            || (string) ($bankOrder['id'] ?? '') !== (string) $payment->provider_order_id) {
            throw new RuntimeException('Birbank order ID mismatch.');
        }

        // The documented details response includes amount and currency.
        if (!isset($bankOrder['amount'], $bankOrder['currency'])
            || strtoupper((string) $bankOrder['currency']) !== 'AZN'
            || (int) round((float) $bankOrder['amount'] * 100) !== (int) round((float) $payment->amount * 100)) {
            throw new RuntimeException('Birbank payment amount or currency mismatch.');
        }

        $bankStatus = (string) ($bankOrder['status'] ?? '');
        if ($bankStatus === '') {
            throw new RuntimeException('Birbank order status is missing.');
        }

        return DB::transaction(function () use ($payment, $bankOrder, $bankStatus) {
            $locked = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === Payment::PAID) {
                return $locked;
            }

            // FullyPaid is the documented successful status. Unknown or
            // intermediate statuses remain pending until another verification.
            $status = match ($bankStatus) {
                'FullyPaid' => Payment::PAID,
                'Cancelled' => Payment::CANCELLED,
                'Declined', 'Refused' => Payment::FAILED,
                default => Payment::PENDING,
            };

            $maskedPan = data_get($bankOrder, 'srcToken.displayName');
            $locked->update([
                'status' => $status,
                'response_text' => $bankStatus,
                'card_pan' => is_string($maskedPan) && str_contains($maskedPan, '*')
                    ? substr($maskedPan, 0, 32)
                    : null,
            ]);

            return $locked->refresh();
        });
    }
}
