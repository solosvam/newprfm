<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Common\PaymentRefund;
use App\Models\Payment;
use Illuminate\Http\Request;

class RefundController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'payment_id' => 'required|exists:payments,id',
            'amount'     => 'required|numeric|min:0.01',
        ], [
            'payment_id.required' => 'Ödəniş seçilməyib',
            'payment_id.exists'   => 'Ödəniş tapılmadı',
            'amount.required'     => 'Məbləğ daxil edilməyib',
            'amount.numeric'      => 'Məbləğ rəqəm olmalıdır',
            'amount.min'          => 'Məbləğ 0.01-dən böyük olmalıdır',
        ]);

        $payment = Payment::with('customerable')->findOrFail($request->payment_id);

        if (!in_array($payment->provider, [
            Payment::PROVIDER_PAYTR,
            Payment::PROVIDER_BIRBANK,
        ])) {
            return response()->json([
                'success' => false,
                'message' => 'Bu ödəniş növü üçün geri ödəmə mümkün deyil',
            ]);
        }

        $result = RefundService::refund(
            payment:  $payment,
            amount:   (float) $request->amount,
            provider: $payment->provider
        );

        return response()->json($result);
    }

    public function byPayment(int $id)
    {
        $refunds = PaymentRefund::with('user')
            ->where('payment_id', $id)
            ->orderBy('id', 'desc')
            ->get();

        return view('admin.common.refund.by-payment', compact('refunds'));
    }
}
