<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Payment\Payment;
use App\Services\Payment\Birbank;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RefundController extends Controller
{
    public function store(Request $request, Birbank $birbank): JsonResponse
    {
        $data = $request->validate([
            'payment_id' => ['required', 'integer', 'exists:payments,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
        ], [
            'payment_id.required' => 'Ödəniş seçilməyib',
            'payment_id.exists' => 'Ödəniş tapılmadı',
            'amount.required' => 'Məbləğ daxil edilməyib',
            'amount.numeric' => 'Məbləğ rəqəm olmalıdır',
            'amount.min' => 'Məbləğ 0.01-dən böyük olmalıdır',
        ]);

        $payment = Payment::findOrFail($data['payment_id']);

        if ($payment->provider !== 'birbank') {
            return response()->json([
                'success' => false,
                'message' => 'Bu ödəniş növü üçün geri ödəmə mümkün deyil.',
            ], 422);
        }

        if ($payment->status !== Payment::PAID) {
            return response()->json([
                'success' => false,
                'message' => 'Yalnız təsdiqlənmiş ödəniş geri qaytarıla bilər.',
            ], 422);
        }

        $amount = (float) $data['amount'];
        if ($amount > $payment->refundableAmount()) {
            return response()->json([
                'success' => false,
                'message' => 'Məbləğ qaytarıla bilən məbləğdən çoxdur.',
            ], 422);
        }

        try {
            $operation = $birbank->refund(
                $payment,
                number_format($amount, 2, '.', '')
            );
            rescue(fn () => app(\App\Services\FinanceService::class)->recordOnlineRefund($operation));
            return response()->json([
                'success' => true,
                'message' => 'Geri ödəmə uğurla həyata keçirildi.',
                'operation_id' => $operation->id,
            ]);
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }
    }

    public function byPayment(Payment $payment): View
    {
        $refunds = $payment->refunds()
            ->latest()
            ->get();

        return view('backend.crm.refunds', compact('payment', 'refunds'));
    }
}
