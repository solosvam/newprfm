<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Order\Order;
use App\Models\Order\OrderStatus;
use App\Models\Payment\PaymentMethod;
use App\Models\Product\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class OneClickOrderController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:99'],
            'mobile' => ['nullable', 'string', 'max:24'],
        ]);

        $customer = auth()->user();
        $mobile = $customer
            ? preg_replace('/\D+/', '', (string) $customer->mobile)
            : preg_replace('/\D+/', '', (string) ($data['mobile'] ?? ''));

        if (strlen($mobile) === 9) {
            $mobile = '994' . $mobile;
        }
        if (!preg_match('/^994(?:10|50|51|55|70|77|99|60)[0-9]{7}$/', $mobile)) {
            return response()->json(['message' => 'Mobil nömrəni 994XXXXXXXXX formatında daxil edin.'], 422);
        }

        $order = DB::transaction(function () use ($data, $mobile) {
            $variant = ProductVariant::with('product.activeDiscount')->whereKey($data['variant_id'])
                ->where('active', 1)->firstOrFail();
            abort_unless($variant->product && (int) $variant->product->active === 1, 404);

            $cash = PaymentMethod::where('code', 'cash')->where('active', 1)->firstOrFail();
            $status = OrderStatus::where('code', 'new')->where('active', 1)->firstOrFail();
            $quantity = (int) $data['quantity'];
            // Məhsul endirimi: checkout kimi — unit_price endirimli, list_price adi, fərq discount-da
            $regular = (float) $variant->price;
            $sale = $variant->salePrice();
            $subtotal = round($regular * $quantity, 2);
            $lineTotal = round($sale * $quantity, 2);
            // Address is deliberately unknown: the operator confirms the delivery fee later.
            $order = Order::create([
                'bonus_percent' => app(\App\Services\BonusService::class)->currentPercent(), // sifariş anındakı faiz sabitlənir
                'order_no' => 'TMP' . Str::random(20),
                'customer_id' => null,
                'customer_address_id' => null,
                'guest_mobile' => $mobile,
                'one_click' => true,
                'payment_method_id' => $cash->id,
                'payment_status' => 'cod',
                'source' => 'website',
                'order_status_id' => $status->id,
                'gift_wrap' => false,
                'subtotal' => $subtotal,
                'discount' => round($subtotal - $lineTotal, 2),
                'delivery_fee' => 0,
                'gift_wrap_fee' => 0,
                'total' => $lineTotal,
            ]);
            $order->update(['order_no' => 'PS' . now()->format('ymd') . str_pad((string) $order->id, 6, '0', STR_PAD_LEFT)]);
            $order->items()->create([
                'product_id' => $variant->product_id,
                'product_variant_id' => $variant->id,
                'quantity' => $quantity,
                'unit_price' => $sale,
                'list_price' => $sale < $regular ? $regular : null,
                'total' => $lineTotal,
            ]);
            DB::table('order_status_logs')->insert([
                'order_id' => $order->id, 'status_id' => $status->id,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            return $order;
        });

        $redirect = URL::temporarySignedRoute('one-click.success', now()->addMinutes(30), ['order' => $order->id]);

        return response()->json(['ok' => true, 'redirect' => $redirect, 'order_no' => $order->order_no]);
    }

    public function success(Request $request, Order $order)
    {
        abort_unless($request->hasValidSignature() && $order->one_click && $order->customer_id === null, 403);
        return view('frontend.checkout-success', ['order' => $order, 'guestOneClick' => true]);
    }
}
