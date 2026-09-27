<?php

namespace App\Services\Payment;

use App\Models\Order\Order;
use App\Models\Payment;
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

        return rtrim($url, '/');
    }

    private function http()
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
     * Create a payment session for an existing order. Never accept an amount
     * or customer ID from the browser.
     */
    public function createOrder(Order $order, string $language = 'az'): array
    {
        if ($order->paymentMethod?->code !== 'card_online') {
            throw ValidationException::withMessages(['payment' => 'Invalid payment method.']);
        }

        $amount = number_format((float) $order->total, 2, '.', '');
        if ((float) $amount <= 0) {
            throw ValidationException::withMessages(['payment' => 'Invalid payment amount.']);
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
                    'description' => $order->order_no,
                    'hppRedirectUrl' => route('payment.birbank.return', ['payment' => $payment->id]),
                ],
            ]);

            if (!$response->successful()) {
                throw new RuntimeException('Birbank order creation failed (HTTP '.$response->status().').');
            }

            $data = $response->json('order');
            if (!is_array($data) || empty($data['id']) || empty($data['password']) || empty($data['hppUrl'])) {
                throw new RuntimeException('Birbank returned an incomplete order.');
            }

            $payment->update([
                'provider_order_id' => (string) $data['id'],
                'session_id' => (string) $data['password'],
            ]);

            return [
                'payment_id' => $payment->id,
                'url' => $data['hppUrl'].'?'.http_build_query([
                    'id' => $data['id'],
                    'password' => $data['password'],
                ]),
            ];
        } catch (\Throwable $e) {
            $payment->update(['status' => Payment::FAILED]);
            throw $e;
        }
    }

    /**
     * The browser redirect is not proof of payment: query the bank directly.
     * Lock the payment so repeated redirects cannot apply it twice.
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
            || (string) ($bankOrder['id'] ?? '') !== $payment->provider_order_id) {
            throw new RuntimeException('Birbank order ID mismatch.');
        }

        $bankStatus = (string) ($bankOrder['status'] ?? '');
        $bankAmount = $bankOrder['amount'] ?? null;
        // Some API responses omit amount on status lookup; compare when supplied.
        if ($bankAmount !== null && round((float) $bankAmount, 2) !== round((float) $payment->amount, 2)) {
            throw new RuntimeException('Birbank payment amount mismatch.');
        }

        return DB::transaction(function () use ($payment, $bankOrder, $bankStatus) {
            $locked = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === Payment::PAID) {
                return $locked;
            }

            $status = match ($bankStatus) {
                'FullyPaid' => Payment::PAID,
                'Cancelled' => Payment::CANCELLED,
                'Declined', 'Refused' => Payment::FAILED,
                default => Payment::PENDING,
            };

            $locked->update([
                'status' => $status,
                'response_text' => $bankStatus,
                'card_pan' => data_get($bankOrder, 'srcToken.displayName'),
            ]);

            return $locked->refresh();
        });
    }
}
