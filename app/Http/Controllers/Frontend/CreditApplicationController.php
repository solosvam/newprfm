<?php
namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Credit\CreditApplication;
use App\Models\Credit\CreditPeriod;
use App\Models\Credit\CreditStatus;
use App\Models\Order\Order;
use App\Models\Order\OrderStatus;
use App\Models\Payment\PaymentMethod;
use App\Models\Product\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CreditApplicationController extends Controller
{
    private function requireProfile(Request $request): void
    {
        if (!$request->user()->creditProfile?->isComplete()) {
            throw ValidationException::withMessages([
                'profile' => __('credit_application_complete_profile'),
            ]);
        }
    }

    private function selection(array $draft): array
    {
        $variant = ProductVariant::with(['product.brand', 'size'])
            ->whereKey($draft['product_variant_id'])->where('active', 1)->firstOrFail();
        abort_unless($variant->product?->active, 404);
        $period = CreditPeriod::whereKey($draft['credit_period_id'])->where('active', 1)->firstOrFail();
        $price = (float) $variant->price;
        $rate = (float) $period->interest_rate;
        $total = round($price * (1 + $rate / 100), 2);
        $monthly = round($total / $period->month, 2);
        return compact('variant', 'period', 'price', 'rate', 'total', 'monthly');
    }

    private function draft(Request $request): array
    {
        $draft = $request->session()->get('credit.application');
        abort_unless(is_array($draft) && isset($draft['created_at']) && now()->timestamp - $draft['created_at'] <= 1800, 404);
        return $draft;
    }

    public function store(Request $request)
    {
        if (!$request->user()->creditProfile?->isComplete()) {
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
        $this->selection($data);
        $request->session()->put('credit.application', [
            'product_variant_id' => $data['product_variant_id'],
            'credit_period_id' => $data['credit_period_id'],
            'created_at' => now()->timestamp,
        ]);

        return response()->json([
            'redirect' => route('credit.application.address'),
        ]);
    }

    public function address(Request $request)
    {
        $this->requireProfile($request);
        $selection = $this->selection($this->draft($request));
        $addresses = $request->user()->addresses()->orderByDesc('is_default')->latest()->get();
        return view('frontend.credit-address', array_merge($selection, compact('addresses')));
    }

    public function confirm(Request $request)
    {
        $this->requireProfile($request);
        $draft = $this->draft($request);
        $data = $request->validate([
            'address_mode' => ['required', Rule::in(['existing', 'new'])],
            'address_id' => ['required_if:address_mode,existing', 'nullable', 'integer'],
            'title' => ['required_if:address_mode,new', 'nullable', 'string', 'max:50'],
            'city' => ['required_if:address_mode,new', 'nullable', 'string', 'max:100'],
            'district' => ['nullable', 'string', 'max:100'],
            'address' => ['required_if:address_mode,new', 'nullable', 'string', 'max:500'],
            'building' => ['nullable', 'string', 'max:50'],
            'entrance' => ['nullable', 'string', 'max:50'],
            'floor' => ['nullable', 'string', 'max:30'],
            'apartment' => ['nullable', 'string', 'max:30'],
            'address_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $customer = $request->user();
        $result = DB::transaction(function () use ($customer, $draft, $data) {
            // Lock the customer to serialize duplicate confirmation requests.
            DB::table('customers')->where('id', $customer->id)->lockForUpdate()->first();
            $selection = $this->selection($draft);
            extract($selection);
            $address = $data['address_mode'] === 'existing'
                ? $customer->addresses()->findOrFail($data['address_id'])
                : $customer->addresses()->create([
                    'title' => $data['title'],
                    'city' => $data['city'],
                    'district' => $data['district'] ?? null,
                    'address' => $data['address'],
                    'building' => $data['building'] ?? null,
                    'entrance' => $data['entrance'] ?? null,
                    'floor' => $data['floor'] ?? null,
                    'apartment' => $data['apartment'] ?? null,
                    'note' => $data['address_note'] ?? null,
                    'is_default' => $customer->addresses()->count() === 0,
                ]);

            $payment = PaymentMethod::where('code', 'installment')->firstOrFail();
            $status = OrderStatus::where('code', 'new')->where('active', 1)->firstOrFail();
            $creditStatus = CreditStatus::where('code', 'pending')->firstOrFail();
            $order = Order::create([
                'order_no' => 'TMP'.\Illuminate\Support\Str::random(20),
                'customer_id' => $customer->id,
                'customer_address_id' => $address->id,
                'payment_method_id' => $payment->id,
                'source' => 'website',
                'order_status_id' => $status->id,
                'gift_wrap' => false,
                'subtotal' => $price,
                'discount' => 0,
                'total' => $total,
            ]);
            $order->update(['order_no' => 'PS'.now()->format('ymd').str_pad((string)$order->id, 6, '0', STR_PAD_LEFT)]);
            $order->items()->create([
                'product_id' => $variant->product_id,
                'product_variant_id' => $variant->id,
                'unit_price' => $price,
                'quantity' => 1,
                'total' => $price,
            ]);
            DB::table('order_status_logs')->insert([
                'order_id' => $order->id, 'status_id' => $status->id,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            CreditApplication::create([
                'customer_id' => $customer->id,
                'order_id' => $order->id,
                'credit_period_id' => $period->id,
                'interest_rate' => $rate,
                'total' => $total,
                'monthly' => $monthly,
                'credit_status_id' => $creditStatus->id,
            ]);
            return $order;
        });

        $request->session()->forget('credit.application');
        return response()->json([
            'message' => __('credit_application_success'),
            'redirect' => route('checkout.success', $result),
        ], 201);
    }
}
