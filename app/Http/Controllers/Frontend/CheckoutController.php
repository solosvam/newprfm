<?php
namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Order\Order;
use App\Models\Order\OrderStatus;
use App\Models\PaymentMethod;
use App\Models\Product\ProductVariant;
use App\Services\BonusService;
use App\Support\LocalizedValidation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CheckoutController extends Controller
{
    public function index()
    {
        $addresses = auth() -> user()
            -> addresses()
            -> orderByDesc('is_default')
            -> latest()
            -> get();

        $paymentMethods = PaymentMethod ::where('active', 1)
            -> orderBy('sort_order')
            -> get();

        return view('frontend.checkout', compact(
            'addresses',
            'paymentMethods'
        ));
    }

    public function store(Request $request)
    {
        $data = $request -> validate(
            [
                'cart' => ['required', 'array', 'min:1'],
                'cart.*.variant_id' => ['required', 'integer'],
                'cart.*.quantity' => ['required', 'integer', 'min:1', 'max:99'],

                'address_mode' => [
                    'required',
                    Rule ::in(['existing', 'new']),
                ],
                'address_id' => ['nullable', 'integer'],

                'title' => ['nullable', 'string', 'max:50'],
                'city' => ['nullable', 'string', 'max:100'],
                'district' => ['nullable', 'string', 'max:100'],
                'address' => ['nullable', 'string', 'max:500'],
                'building' => ['nullable', 'string', 'max:50'],
                'entrance' => ['nullable', 'string', 'max:50'],
                'floor' => ['nullable', 'string', 'max:30'],
                'apartment' => ['nullable', 'string', 'max:30'],
                'address_note' => ['nullable', 'string', 'max:1000'],

                'payment_method_id' => [
                    'required',
                    'integer',
                    'exists:payment_methods,id',
                ],

                'gift_wrap' => ['nullable', 'boolean'],
                'customer_note' => ['nullable', 'string', 'max:1500'],
            ],
            LocalizedValidation ::messages(),
            LocalizedValidation ::attributes()
        );

        $customer = $request -> user();

        return DB ::transaction(function() use ($data, $customer) {

            // İlkin sifariş statusu
            $initialStatus = OrderStatus ::where('code', 'new')
                -> where('active', 1)
                -> firstOrFail();

            // Çatdırılma ünvanı
            if($data['address_mode'] === 'existing') {

                $address = $customer -> addresses()
                    -> findOrFail($data['address_id']);

            } else {

                validator(
                    $data,
                    [
                        'title' => ['required', 'string', 'max:50'],
                        'city' => ['required'],
                        'address' => ['required'],
                    ],
                    [
                        'title.required' => __('validation_address_name_is_required'),
                        'city.required' => __('validation_city_is_required'),
                        'address.required' => __('validation_street_and_address_are_required'),
                    ]
                ) -> validate();

                $address = $customer -> addresses() -> create([
                    'title' => $data['title'] ?? null,
                    'city' => $data['city'],
                    'district' => $data['district'] ?? null,
                    'address' => $data['address'],
                    'building' => $data['building'] ?? null,
                    'entrance' => $data['entrance'] ?? null,
                    'floor' => $data['floor'] ?? null,
                    'apartment' => $data['apartment'] ?? null,
                    'note' => $data['address_note'] ?? null,
                    'is_default' => $customer -> addresses() -> count() === 0,
                ]);
            }

            // Səbətdəki məhsullar
            $cart = collect($data['cart']) -> keyBy('variant_id');

            $variants = ProductVariant ::whereIn('id', $cart -> keys())
                -> where('active', 1)
                -> get();

            abort_if(
                $variants -> count() !== $cart -> count(),
                422,
                __('validation_your_cart_contains_an_unavailable_product')
            );

            // Məhsullar və yekun məbləğ
            $subtotal = 0;
            $items = [];

            foreach($variants as $variant) {
                $quantity = (int)$cart[$variant -> id]['quantity'];

                $lineTotal = round(
                    (float)$variant -> price * $quantity,
                    2
                );

                $subtotal += $lineTotal;

                $items[] = [
                    'product_id' => $variant -> product_id,
                    'product_variant_id' => $variant -> id,
                    'unit_price' => $variant -> price,
                    'quantity' => $quantity,
                    'total' => $lineTotal,
                ];
            }

            // Sifariş nömrəsi
            $nextOrderId = (Order ::max('id') ?? 0) + 1;

            $orderNo = 'PS'
                . now() -> format('ymd')
                . str_pad((string)$nextOrderId, 6, '0', STR_PAD_LEFT);

            // Sifarişin yaradılması
            $order = Order ::create([
                'order_no' => $orderNo,
                'customer_id' => $customer -> id,
                'customer_address_id' => $address -> id,
                'payment_method_id' => $data['payment_method_id'],
                'source' => 'website',
                'order_status_id' => $initialStatus -> id,
                'gift_wrap' => (bool)($data['gift_wrap'] ?? false),
                'customer_note' => $data['customer_note'] ?? null,
                'subtotal' => $subtotal,
                'discount' => 0,
                'total' => $subtotal,
            ]);

            // Sifariş məhsulları
            $order -> items() -> createMany($items);

            // Bonus hesablanması
            app(BonusService::class) -> earnForOrder(
                $customer,
                $order,
                (float)$order -> total
            );

            // Status tarixçəsi
            DB ::table('order_status_logs') -> insert([
                'order_id' => $order -> id,
                'status_id' => $initialStatus -> id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return response() -> json([
                'ok' => true,
                'order_no' => $order -> order_no,
                'redirect' => route('checkout.success', $order),
            ]);
        });
    }

    public function success(Order $order)
    {
        abort_unless(
            $order -> customer_id === auth() -> id(),
            403
        );

        return view('frontend.checkout-success', compact('order'));
    }
}
