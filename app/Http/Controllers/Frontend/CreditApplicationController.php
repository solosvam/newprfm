<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\CreditApplication;
use App\Models\CreditPeriod;
use App\Models\Product\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CreditApplicationController extends Controller
{
    public function store(Request $request)
    {
        $customer = $request->user();
        if (!$customer->creditProfile?->isComplete()) {
            return response()->json([
                'message' => __('credit_application_complete_profile'),
                'redirect' => route('profile.credit'),
            ], 422);
        }

        $data = $request->validate([
            'product_variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'credit_period_id' => ['required', 'integer', 'exists:credit_periods,id'],
            'accept_terms' => ['accepted'],
        ]);

        $variant = ProductVariant::with('product')->whereKey($data['product_variant_id'])->where('active', 1)->firstOrFail();
        abort_unless($variant->product?->active, 404);
        $period = CreditPeriod::whereKey($data['credit_period_id'])->where('active', 1)->firstOrFail();

        $price = (float) $variant->price;
        $rate = (float) $period->interest_rate;
        $total = round($price * (1 + $rate / 100), 2);
        $monthly = round($total / $period->month, 2);

        $application = CreditApplication::create([
            'customer_id' => $customer->id,
            'product_variant_id' => $variant->id,
            'credit_period_id' => $period->id,
            'product_price' => $price,
            'interest_rate' => $rate,
            'total' => $total,
            'monthly' => $monthly,
            'status' => 'pending',
        ]);

        return response()->json([
            'message' => __('credit_application_success'),
            'application_id' => $application->id,
        ], 201);
    }
}
