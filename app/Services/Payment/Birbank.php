<?php

namespace App\Services\Payment;

use App\Models\Order\Order;
use App\Models\Payment;
use App\Models\PaymentOperation;
use App\Models\PaymentSavedCard;
use Illuminate\Support\Str;
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
    /**
     * Bank details are authoritative. Do not trust callback STATUS.
     */
    public function details(Payment $payment): array
    {
        $this->assertBirbankPayment($payment);
        $response = $this->http()->get(
            $this->endpoint().'/order/'.rawurlencode($payment->provider_order_id),
            ['tranDetailLevel' => 2, 'tokenDetailLevel' => 2, 'orderDetailLevel' => 2]
        );
        if (!$response->successful() || !is_array($response->json('order'))) {
            throw new RuntimeException('Birbank order details unavailable (HTTP '.$response->status().').');
        }
        $bankOrder = $response->json('order');
        if ((string) ($bankOrder['id'] ?? '') !== (string) $payment->provider_order_id) {
            throw new RuntimeException('Birbank order mismatch.');
        }
        return $bankOrder;
    }

    /**
     * Capture a card only when the customer has explicitly opted in.
     * The bank issues the stored token after successful hosted checkout.
     */
    public function createOrderWithCardConsent(Order $order, string $language = 'az'): array
    {
        $result = $this->createOrder($order, $language);
        // Ordinary createOrder intentionally does not request COF capture.
        // To capture consent, the capture purposes must be included BEFORE
        // POST /order, so use the dedicated createStoredCardOrder below instead.
        return $result;
    }

    public function createStoredCardOrder(Order $order, string $language = 'az'): array
    {
        $order->loadMissing('paymentMethod');
        if ($order->paymentMethod?->code !== 'online_card' || !$order->customer_id) {
            throw ValidationException::withMessages(['payment' => 'Invalid online payment order.']);
        }
        if ($order->payments()->where('status', Payment::PAID)->exists()) {
            throw ValidationException::withMessages(['payment' => 'Order is already paid.']);
        }
        $amount = $this->money($order->total);
        $payment = Payment::create([
            'customer_id' => $order->customer_id, 'order_id' => $order->id,
            'provider' => 'birbank', 'amount' => $amount, 'status' => Payment::PENDING,
        ]);
        try {
            $bankOrder = $this->createBankOrder([
                'typeRid' => 'Order_SMS', 'amount' => $amount, 'currency' => 'AZN',
                'language' => in_array($language, ['az', 'en', 'ru'], true) ? $language : 'az',
                'title' => 'Parfumshop', 'description' => (string) $order->order_no,
                'hppRedirectUrl' => route('payment.birbank.return', ['payment' => $payment->id]),
                'hppCofCapturePurposes' => ['UnspecifiedMit', 'Cit', 'Recurring'],
            ]);
            $payment->update([
                'provider_order_id' => (string) $bankOrder['id'],
                'session_id' => (string) $bankOrder['password'],
            ]);
            return ['payment_id' => $payment->id, 'url' => $this->hppUrl($bankOrder)];
        } catch (\Throwable $e) {
            $payment->update(['response_text' => 'Bank creation outcome requires reconciliation.']);
            throw $e;
        }
    }

    /**
     * Save only bank-issued token IDs, never PAN/CVV. Call only after the
     * customer explicitly consented and the hosted payment was verified.
     */
    public function saveCardAfterConsent(Payment $payment, bool $consented): PaymentSavedCard
    {
        if (!$consented || $payment->status !== Payment::PAID) {
            throw new RuntimeException('Verified payment and card-storage consent are required.');
        }
        $bankOrder = $this->details($payment);
        $tokenId = data_get($bankOrder, 'storedTokens.0.id');
        if (!$tokenId) {
            throw new RuntimeException('Bank did not return a stored card token.');
        }
        $maskedPan = data_get($bankOrder, 'srcToken.displayName');
        return PaymentSavedCard::updateOrCreate(
            ['provider' => 'birbank', 'provider_token_id' => (string) $tokenId],
            ['customer_id' => $payment->customer_id,
                'masked_pan' => is_string($maskedPan) && str_contains($maskedPan, '*') ? $maskedPan : null,
                'active' => true]
        );
    }

    /**
     * Stored-card flow: Order_REC -> set-src-token -> exec-tran.
     * Never expose this method directly without authenticated customer consent.
     */
    public function chargeSavedCard(Order $order, PaymentSavedCard $card): Payment
    {
        if ($card->provider !== 'birbank' || !$card->active
            || (int) $card->customer_id !== (int) $order->customer_id) {
            throw new RuntimeException('Saved card does not belong to this customer.');
        }
        if ($order->payments()->where('status', Payment::PAID)->exists()) {
            throw new RuntimeException('Order is already paid.');
        }
        $amount = $this->money($order->total);
        $payment = Payment::create([
            'customer_id' => $order->customer_id, 'order_id' => $order->id,
            'provider' => 'birbank', 'amount' => $amount, 'status' => Payment::PENDING,
        ]);
        $bankOrder = $this->createBankOrder([
            'typeRid' => 'Order_REC', 'amount' => $amount, 'currency' => 'AZN',
            'description' => (string) $order->order_no,
        ]);
        $payment->update([
            'provider_order_id' => (string) $bankOrder['id'],
            'session_id' => (string) $bankOrder['password'],
        ]);

        $this->bankPost('/order/'.rawurlencode($payment->provider_order_id)
            .'/set-src-token?'.http_build_query(['password' => $payment->session_id]), [
                'order' => ['initiationEnvKind' => 'Server'],
                'token' => ['storedId' => $card->provider_token_id],
            ]);

        $this->execute($payment, 'recurring', ['phase' => 'Single', 'conditions' => ['cofUsage' => 'Recurring']]);
        return $this->verify($payment);
    }

    /**
     * Bank reversal (void): full or partial. Partial is allowed once per
     * documented bank rules. Only run after an operator-authorized action.
     */
    public function reverse(Payment $payment, ?string $amount = null, ?string $key = null): PaymentOperation
    {
        $this->assertPaid($payment);
        $bankOrder = $this->details($payment);
        if (!in_array($bankOrder['status'] ?? '', ['FullyPaid', 'Authorized'], true)) {
            throw new RuntimeException('Bank order cannot be reversed in its current status.');
        }
        $partial = $amount !== null;
        if ($partial && $payment->operations()->where('type', 'reversal')
            ->whereIn('status', ['pending', 'succeeded'])->whereNotNull('amount')->exists()) {
            throw new RuntimeException('A partial reversal was already requested.');
        }
        $tran = ['phase' => 'Single', 'voidKind' => $partial ? 'Partial' : 'Full'];
        if ($partial) {
            $tran['amount'] = $this->limitedAmount($amount, $payment->amount);
        }
        return $this->execute($payment, 'reversal', $tran, $key);
    }

    /**
     * Refund is a new bank transaction. The operation ledger prevents
     * cumulative refunds exceeding the original payment.
     */
    public function refund(Payment $payment, string $amount, ?string $key = null): PaymentOperation
    {
        $this->assertPaid($payment);
        $refundAmount = $this->limitedAmount($amount, $payment->amount);
        return DB::transaction(function () use ($payment, $refundAmount, $key) {
            $locked = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
            $already = $locked->operations()->where('type', 'refund')
                ->whereIn('status', ['pending', 'succeeded'])->sum('amount');
            if ((int) round(($already + (float) $refundAmount) * 100)
                > (int) round((float) $locked->amount * 100)) {
                throw new RuntimeException('Refund amount exceeds remaining refundable balance.');
            }
            return $this->execute($locked, 'refund',
                ['phase' => 'Single', 'type' => 'Refund', 'amount' => $refundAmount], $key);
        });
    }

    /**
     * Preauthorization: Order_DMS and HPP, then clearing after fulfillment.
     * Do not mix DMS with the normal Order_SMS checkout.
     */
    public function createPreauthorization(Order $order, string $language = 'az'): array
    {
        if (!$order->customer_id || $order->payments()->where('status', Payment::PAID)->exists()) {
            throw new RuntimeException('Invalid preauthorization order.');
        }
        $amount = $this->money($order->total);
        $payment = Payment::create([
            'customer_id' => $order->customer_id, 'order_id' => $order->id,
            'provider' => 'birbank', 'amount' => $amount, 'status' => Payment::PENDING,
        ]);
        $bankOrder = $this->createBankOrder([
            'typeRid' => 'Order_DMS', 'amount' => $amount, 'currency' => 'AZN',
            'language' => in_array($language, ['az', 'en', 'ru'], true) ? $language : 'az',
            'description' => (string) $order->order_no,
            'hppRedirectUrl' => route('payment.birbank.return', ['payment' => $payment->id]),
        ]);
        $payment->update([
            'provider_order_id' => (string) $bankOrder['id'],
            'session_id' => (string) $bankOrder['password'],
        ]);
        return ['payment_id' => $payment->id, 'url' => $this->hppUrl($bankOrder)];
    }

    public function clearPreauthorization(Payment $payment, ?string $amount = null, ?string $key = null): PaymentOperation
    {
        $bankOrder = $this->details($payment);
        if (($bankOrder['typeRid'] ?? '') !== 'Order_DMS') {
            throw new RuntimeException('Clearing requires an Order_DMS bank order.');
        }
        $tran = ['phase' => 'Clearing'];
        if ($amount !== null) {
            $tran['amount'] = $this->limitedAmount($amount, $payment->amount);
        }
        return $this->execute($payment, 'clearing', $tran, $key);
    }

    /**
     * Query and reconcile an operation after an ambiguous timeout. Never
     * blindly replay financial POST requests.
     */
    public function reconcile(Payment $payment): Payment
    {
        return $this->verify($payment);
    }

    private function assertBirbankPayment(Payment $payment): void
    {
        if ($payment->provider !== 'birbank' || !$payment->provider_order_id) {
            throw new RuntimeException('Invalid Birbank payment.');
        }
    }

    private function assertPaid(Payment $payment): void
    {
        $this->assertBirbankPayment($payment);
        if ($this->verify($payment)->status !== Payment::PAID) {
            throw new RuntimeException('Payment is not confirmed by the bank.');
        }
    }

    private function money(mixed $amount): string
    {
        if (!is_numeric($amount) || !is_finite((float) $amount) || (float) $amount <= 0) {
            throw new RuntimeException('Invalid positive payment amount.');
        }
        return number_format((float) $amount, 2, '.', '');
    }

    private function limitedAmount(mixed $amount, mixed $limit): string
    {
        $value = $this->money($amount);
        if ((int) round((float) $value * 100) > (int) round((float) $limit * 100)) {
            throw new RuntimeException('Amount exceeds original payment.');
        }
        return $value;
    }

    private function createBankOrder(array $data): array
    {
        $response = $this->http()->post($this->endpoint().'/order', ['order' => $data]);
        if (!$response->successful()) {
            throw new RuntimeException('Birbank create order failed (HTTP '.$response->status().').');
        }
        $order = $response->json('order');
        if (!is_array($order) || empty($order['id']) || empty($order['password'])) {
            throw new RuntimeException('Incomplete Birbank order response.');
        }
        return $order;
    }

    private function hppUrl(array $order): string
    {
        $url = rtrim((string) ($order['hppUrl'] ?? ''), '/');
        if (!str_ends_with(parse_url($url, PHP_URL_PATH) ?: '', '/flex')) {
            $url .= '/flex';
        }
        $host = parse_url($url, PHP_URL_HOST);
        if (!str_starts_with($url, 'https://') || !$host
            || !($host === parse_url($this->endpoint(), PHP_URL_HOST)
                || str_ends_with($host, '.kapitalbank.az'))) {
            throw new RuntimeException('Unexpected Birbank HPP URL.');
        }
        return $url.'?'.http_build_query([
            'id' => $order['id'], 'password' => $order['password'],
        ], '', '&', PHP_QUERY_RFC3986);
    }

    private function bankPost(string $path, array $data): array
    {
        $response = $this->http()->post($this->endpoint().$path, $data);
        if (!$response->successful() || $response->json('errorCode')) {
            throw new RuntimeException('Birbank operation failed (HTTP '.$response->status().').');
        }
        return $response->json() ?? [];
    }

    /**
     * Operation idempotency is local: if a request timed out, leave it
     * pending for reconciliation rather than sending it again.
     */
    private function execute(Payment $payment, string $type, array $tran, ?string $key = null): PaymentOperation
    {
        $this->assertBirbankPayment($payment);
        $key ??= (string) Str::uuid();
        $existing = PaymentOperation::where('idempotency_key', $key)->first();
        if ($existing) {
            if ($existing->payment_id !== $payment->id || $existing->type !== $type) {
                throw new RuntimeException('Idempotency key belongs to a different operation.');
            }
            return $existing;
        }

        $operation = PaymentOperation::create([
            'payment_id' => $payment->id,
            'type' => $type,
            'amount' => $tran['amount'] ?? null,
            'status' => 'pending',
            'idempotency_key' => $key,
        ]);
        try {
            $result = $this->bankPost(
                '/order/'.rawurlencode($payment->provider_order_id).'/exec-tran',
                ['tran' => $tran]
            );
            $operation->update([
                'status' => 'succeeded',
                'bank_action_id' => data_get($result, 'tran.match.tranActionId'),
                'response_text' => json_encode([
                    'pmoResultCode' => data_get($result, 'tran.pmoResultCode'),
                    'approvalCode' => data_get($result, 'tran.approvalCode'),
                ]),
            ]);
        } catch (\Throwable $e) {
            // A timeout does not prove the operation failed.
            $operation->update(['response_text' => 'Requires reconciliation: '.$e->getMessage()]);
            throw $e;
        }
        return $operation->refresh();
    }

}
