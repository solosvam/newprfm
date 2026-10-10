<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Mail\OrderCreatedMail;
use App\Models\Credit\CreditApplication;
use App\Models\Credit\CreditPeriod;
use App\Models\Credit\CreditStatus;
use App\Models\Customer\Customer;
use App\Models\Customer\CustomerAddress;
use App\Models\Order\Order;
use App\Models\Order\OrderItem;
use App\Models\Order\OrderItemCancellation;
use App\Models\Order\OrderStatus;
use App\Models\Payment\PaymentMethod;
use App\Models\Procurement\OrderItemAllocation;
use App\Models\Product\ProductVariant;
use App\Services\Crm\CreditProfileUpdater;
use App\Services\IdCard\IdCardStorage;
use App\Services\Crm\CustomerRegistration;
use App\Services\OrderItemCancellationService;
use App\Services\OrderPayLinkService;
use App\Services\OrderStatusService;
use App\Services\OrderRefundService;
use App\Services\ShopPricing;
use App\Services\SmsService;
use App\Services\WarehouseNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;


class CrmController extends Controller
{
    public function index(Request $request): View
    {
        return view('backend.crm.index');
    }

    public function customer($id)
    {
        $customer = Customer::findOrFail($id);

        $counts = [
            'orders' => $customer->orders()
                ->where('payment_method_id', '!=', 4)
                ->count(),

            'installment' => $customer->orders()
                ->where('payment_method_id', 4)
                ->count(),

            'payments' => $customer->payments()->count(),
        ];

        // "Yeni sifariş" modalı üçün
        $pricing = app(ShopPricing::class);
        $orderForm = [
            'addresses' => $customer->addresses()->orderByDesc('is_default')->latest()->get(),
            'paymentMethods' => PaymentMethod::where('active', 1)->whereIn('code', self::ORDER_PAYMENT_CODES)->orderBy('sort_order')->get(),
            'creditPeriods' => CreditPeriod::where('active', 1)->orderBy('sort_order')->get(),
            'creditReady' => (bool) $customer->creditProfile?->isComplete(),
            'bonusBalance' => (float) $customer->bonus_balance,
            'delivery' => $pricing->delivery(),
            'giftWrap' => $pricing->giftWrap(),
            'bonusRate' => $pricing->bonusRate(),
            'cities' => \App\Models\City::forSelect()->get(['id', 'name']),
        ];

        return view('backend.crm.customer', compact('customer', 'counts', 'orderForm'));
    }

    public function tab(Customer $customer, string $tab): View
    {
        return match ($tab) {
            'orders' => view('backend.crm.tabs.orders', [
                'orders' => $customer->orders()
                    ->where('payment_method_id', '!=', 4)
                    ->with(['status', 'paymentMethod'])
                    ->withSum('itemCancellations as cancelled_amount', 'amount')
                    ->latest()
                    ->paginate(10),
            ]),
            'installment' => view('backend.crm.tabs.installment', [
                'orders' => $customer->orders()
                    ->where('payment_method_id', 4)
                    ->with(['status', 'creditApplication.status', 'creditApplication.period'])
                    ->latest()
                    ->paginate(10),
            ]),
            'payments' => view('backend.crm.tabs.payments', [
                'payments' => $customer->payments()
                    ->with(['order', 'refunds'])
                    ->latest()
                    ->paginate(10),
            ]),
            'balance' => view('backend.crm.tabs.balance', [
                'customer' => $customer,
                'transactions' => $customer->bonusTransactions()
                    ->with('order')
                    ->latest()
                    ->paginate(10),
            ]),
            'settings' => view('backend.crm.tabs.settings', [
                'customer' => $customer->load('addresses'),
                'cities' => \App\Models\City::forSelect()->get(['id', 'name']), // ünvan redaktəsi
            ]),
            'credit-profile' => view('backend.crm.tabs.credit-profile', [
                'customer' => $customer->load('creditProfile'),
                'profile' => $customer->creditProfile,
            ]),
            default => abort(404),
        };
    }

    public function update(Customer $customer, Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:30'],
            'surname' => ['required', 'string', 'max:30'],
            'mobile' => ['required', 'digits:12', 'unique:customers,mobile,' . $customer->id],
            // ehtiyat nömrə: könüllü, əsas nömrədən fərqli (unikal deyil — məs. ailə üzvü)
            'mobile_2' => ['nullable', 'regex:/^994\d{9}$/', 'different:mobile'],
            'email' => ['nullable', 'email', 'max:50', 'unique:customers,email,' . $customer->id],
            'gender' => ['required', 'in:0,1'],
            'active' => ['nullable', 'boolean'],
        ], [
            'mobile_2.regex' => 'Ehtiyat telefonu 994XXXXXXXXX formatında yazın.',
            'mobile_2.different' => 'Ehtiyat telefon əsas nömrədən fərqli olmalıdır.',
        ]);

        $customer->update([
            'name' => $data['name'],
            'surname' => $data['surname'],
            'mobile' => $data['mobile'],
            'mobile_2' => $data['mobile_2'] ?? null,
            'email' => $data['email'] ?? null,
            'gender' => (int) $data['gender'],
            'active' => $request->boolean('active'),
        ]);

        return redirect()
            ->route('admin.crm.customer', $customer->id)
            ->with('success', 'Müştəri məlumatları yeniləndi.');
    }

    /** Tənzimləmələr → ünvanın redaktəsi (qələm → pəncərə, AJAX); "Əsas ünvan" seçilsə digərlərindən götürülür */
    public function updateAddress(Customer $customer, CustomerAddress $address, Request $request): JsonResponse
    {
        abort_unless((int) $address->customer_id === (int) $customer->id, 404);

        // ünvanın adı ("Ev", "İş") könüllüdür; şəhər və küçə məcburidir
        $data = $request->validate(['title' => ['nullable', 'string', 'max:50']] + CustomerAddress::formRules() + ['is_default' => ['nullable', 'boolean']], [
            'city_id.required' => 'Şəhəri seçin.',
            'address.required' => 'Küçə və ünvanı yazın.',
        ]);

        DB::transaction(function () use ($customer, $address, $data, $request) {
            $address->update(CustomerAddress::attributesFromForm($data, $data['title'] ?? null));
            if ($request->boolean('is_default') && !$address->is_default) {
                $customer->addresses()->whereKeyNot($address->id)->update(['is_default' => false]);
                $address->update(['is_default' => true]);
            }
        });

        return response()->json(['ok' => true, 'message' => 'Ünvan yeniləndi.']);
    }

    public function creditProfileOcr(Customer $customer, Request $request, \App\Services\IdCard\IdCardReader $reader): JsonResponse
    {
        return $reader->read($request, $customer);
    }

    public function updateCreditProfile(Customer $customer, Request $request, CreditProfileUpdater $updater): RedirectResponse
    {
        $updater->update($customer, $request);

        return redirect()->route('admin.crm.customer', $customer->id)->with('success', 'Kredit profili yeniləndi.');
    }

    /** Vəsiqə şəkli (public qovluqda deyil) — hər baxış sensitive_access_logs-a yazılır */
    public function idCardImage(Customer $customer, string $side, Request $request)
    {
        $path = IdCardStorage::path($customer->creditProfile?->{'id_card_'.$side});
        abort_unless($path, 404);

        DB::table('sensitive_access_logs')->insert([
            'user_id' => (int) auth('admin')->id(), 'action' => 'crm_id_card_'.$side,
            'subject_type' => 'customer', 'subject_id' => $customer->id,
            'ip' => $request->ip(), 'user_agent' => mb_substr((string) $request->userAgent(), 0, 255),
            'created_at' => now(),
        ]);

        return response()->file($path, ['Cache-Control' => 'private, no-store']);
    }

    /**
     * Yeni müştəri (CRM axtarışında nömrə tapılmayanda). Aktiv yaradılır; şifrə təsadüfidir,
     * istəyə görə SMS ilə göndərilir (sonra "Şifrəni sıfırla" ilə də göndərmək olar).
     */
    public function storeCustomer(Request $request, CustomerRegistration $registration): RedirectResponse
    {
        $request->merge(['mobile' => CustomerRegistration::normalizeMobile($request->input('mobile'))]);
        $data = $request->validateWithBag('createCustomer', CustomerRegistration::rules(), CustomerRegistration::messages(), CustomerRegistration::attributes());

        ['customer' => $customer, 'sms' => $sms] = $registration->register($data, $request->boolean('send_password'));

        $message = 'Müştəri yaradıldı: '.$customer->fullname.'.';
        if ($sms !== null) {
            $message .= $sms ? ' Şifrə SMS ilə göndərildi.' : ' SMS göndərilmədi — "Şifrəni sıfırla" ilə yenidən göndərin.';
        }

        return redirect()->route('admin.crm.customer', $customer->id)->with('success', $message);
    }

    public function resetPassword($id)
    {
        $customer = Customer::findOrFail($id);

        $newPassword = rand(100000, 999999);

        $customer->update([
            'password' => bcrypt($newPassword),
        ]);

        \App\Models\SmsLog::deliver(
            app(SmsService::class),
            $customer->mobile,
            "Hörmətli {$customer->fullname}, yeni şifrəniz: {$newPassword}",
            'crm_password_reset', $customer, auth('admin')->id(), [$newPassword]
        );

        return response()->json(['success' => true]);
    }

    public function sms(Customer $customer, SmsService $sms): View
    {
        if (!$customer->mobile) {
            return view('backend.crm.sms', [
                'messages' => [],
                'error' => 'Müştərinin telefon nömrəsi qeyd edilməyib.',
            ]);
        }

        try {
            $messages = $sms->history($customer->mobile);
            $error = null;
        } catch (\RuntimeException $exception) {
            $messages = [];
            $error = $exception->getMessage();
        }

        return view('backend.crm.sms', compact('messages', 'error'));
    }

    public function confirmOneClick(Request $request, Customer $customer, Order $order, ShopPricing $pricing): RedirectResponse
    {
        abort_unless($order->one_click && $order->customer_id === $customer->id, 404);
        $data = $request->validate([
            'customer_address_id' => ['required', 'integer'],
            'payment_method_id' => ['required', 'integer', 'exists:payment_methods,id'],
        ]);
        $address = $customer->addresses()->findOrFail($data['customer_address_id']);
        $method = PaymentMethod::whereKey($data['payment_method_id'])->where('active', 1)->firstOrFail();
        abort_unless(in_array($method->code, ['cash', 'card_online'], true), 422);
        DB::transaction(function () use ($order, $address, $method, $pricing) {
            $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            abort_if($locked->payment_status === 'paid', 422, 'Ödənilmiş sifariş dəyişdirilə bilməz.');
            $delivery = $pricing->deliveryFee((float)$locked->subtotal - (float)$locked->discount);
            $locked->update([
                'customer_address_id' => $address->id,
                'payment_method_id' => $method->id,
                'payment_status' => $method->code === 'cash' ? 'cod' : 'pending',
                'delivery_fee' => $delivery,
                'total' => round((float)$locked->subtotal - (float)$locked->discount + $delivery + (float)$locked->gift_wrap_fee, 2),
            ]);
        });
        return redirect()->route('admin.crm.customer', $customer->id)
            ->with('success', 'Sifarişin ünvanı və ödəniş üsulu təsdiqləndi.');
    }

    public function order(Customer $customer, Order $order): View
    {
        abort_unless($order->customer_id === $customer->id, 404);

        $order->load([
            'items.product.brand', 'items.variant.size', 'items.allocations.warehouse', 'items.allocations.logs',
            'paymentMethod', 'status', 'address', 'statusLogs.status', 'statusLogs.user', 'courier',
            'payments' => fn ($query) => $query->latest('id'),
            'payments.operations', 'payments.items.refundItems.operation', 'itemCancellations.user', 'itemCancellations.orderItem.product',
        ]);

        // Ödəniş linki: ödənişə başlamaq mümkündür və ya yarımçıq cəhd var (Order::payLinkAvailable)
        $payLinkUrl = $order->payLinkAvailable() ? app(OrderPayLinkService::class)->url($order) : null;

        $requests = \App\Models\Procurement\WarehouseRequest::where('order_id', $order->id)
            ->with(['warehouse', 'items.offers', 'items.orderItem.product', 'items.orderItem.variant.size'])->latest('id')->get();

        // "Əməkdaş #3" əvəzinə ad: sorğu, cavab, seçim və status qeydlərini edənlər
        $staffIds = collect([$order->created_by])
            ->merge($requests->pluck('created_by'))
            ->merge($requests->flatMap->items->flatMap->offers->pluck('recorded_by'))
            ->merge($order->items->flatMap->allocations->flatMap->logs->pluck('user_id'))
            ->filter()->unique();
        $staff = \App\Models\User::whereIn('id', $staffIds)->get()->mapWithKeys(fn ($u) => [$u->id => $u->full_name]);

        // Məhsul ləğvi: hər məhsul üçün 1..aktiv say üzrə nəticə (modalda göstərilir)
        $cancellation = app(OrderItemCancellationService::class);
        $cancelBlock = $cancellation->blockReason($order);
        $cancelPreviews = $cancelBlock ? [] : $order->items
            ->filter(fn ($item) => $item->activeQuantity() > 0)
            ->mapWithKeys(fn ($item) => [$item->id => collect(range(1, min(50, $item->activeQuantity())))
                ->mapWithKeys(fn ($q) => [$q => $cancellation->preview($order, $item, $q)])->all()])
            ->all();

        // Hesablaşmalar (yalnız finance icazəsi ilə)
        $settlement = null;
        if (auth('admin')->user()?->can('finance')) {
            $finance = app(\App\Services\FinanceService::class);
            $finance->syncAccounts();
            $settlement = [
                'accounts' => \App\Models\Finance\FinanceAccount::whereIn('type', ['courier', 'cash', 'bank', 'owner'])->where('active', true)->orderBy('name')->get()
                    ->sortBy(fn ($a) => array_search($a->type, ['courier', 'cash', 'bank', 'owner'], true))->values(),
                'paid' => $finance->allocationPaid($order->items->flatMap->allocations->pluck('id')->all()),
                'movements' => \App\Models\Finance\MoneyMovement::with(['from', 'to', 'user', 'reversedBy'])
                    ->where('order_id', $order->id)->latest('occurred_at')->latest('id')->get(),
            ];
        }

        return view('backend.crm.order', [
            'settlement' => $settlement,
            'cancelBlock' => $cancelBlock,
            'doorBlock' => $cancellation->doorBlockReason($order),
            'orderCancelBlock' => $cancellation->orderBlockReason($order),
            'orderCancelPreview' => $cancellation->orderPreview($order),
            'cancelPreviews' => $cancelPreviews,
            // Tarixçə: aktiv statuslar + bu sifarişdə keçilmiş köhnələr
            'timelineStatuses' => OrderStatus::where(fn ($q) => $q->where('active', 1)->orWhereIn('id', $order->statusLogs->pluck('status_id')))
                ->orderBy('sort_order')->orderBy('id')->get(),
            'startBlock' => app(OrderStatusService::class)->startBlock($order),
            'courierBlock' => app(OrderStatusService::class)->courierBlock($order),
            'couriers' => $this->couriers(),
            'requests' => $requests,
            'staff' => $staff,
            'warehouses' => \App\Models\Procurement\Warehouse::where('active', true)->orderBy('name_az')->get(),
            'order' => $order,
            'payLinkUrl' => $payLinkUrl,
            'customer' => $customer,
            'addresses' => $customer->addresses()->get(),
            'oneClickPaymentMethods' => PaymentMethod::where('active', 1)->whereIn('code', ['cash', 'card_online'])->get(),
        ]);
    }

    /** CRM-dən sifariş yaratmaq üçün icazəli ödəniş üsulları */
    private const ORDER_PAYMENT_CODES = ['cash', 'card_online', 'birbank_installment', 'installment', 'bonus_balance'];

    /**
     * CRM → "Yeni sifariş": operator müştəri adından sifariş yaradır.
     * Frontdakı checkout-dan fərqləri:
     *  - source = operator, created_by = admin istifadəçi;
     *  - onlayn kart / Birbank taksit seçiləndə bank səhifəsinə yönləndirmə yoxdur —
     *    sifariş "gözləyir" statusunda yaranır, müştəri profilindən ödəyir;
     *  - yeni ünvanın adını operator vermir: "Ünvan #N";
     *  - promo kod tətbiq olunmur.
     */
    public function storeOrder(Request $request, Customer $customer): JsonResponse
    {
        $data = $request->validate([
            'cart' => ['required', 'array', 'min:1', 'max:50'],
            'cart.*.variant_id' => ['required', 'integer', 'distinct'],
            'cart.*.quantity' => ['required', 'integer', 'min:1', 'max:99'],
            // Operatorun satdığı vahid qiymət (boşdursa sayt qiyməti)
            'cart.*.price' => ['nullable', 'numeric', 'min:0', 'max:999999'],

            'address_mode' => ['required', Rule::in(['existing', 'new'])],
            'address_id' => ['nullable', 'required_if:address_mode,existing', 'integer'],
            // Ünvan adını operator vermir — "Ünvan #N"
            ...CustomerAddress::formRules('required_if:address_mode,new', withTitle: false),

            'payment_method_id' => ['required', 'integer'],
            'birbank_installment_months' => ['nullable', 'integer', Rule::in([2, 3, 6])],
            'credit_period_id' => ['nullable', 'integer'],
            'gift_wrap' => ['nullable', 'boolean'],
            // Operator qiyməti əl ilə dəyişibsə — bonus faizi 0..admin ayarı (standart 0)
            'bonus_percent' => ['nullable', 'integer', 'min:0', 'max:'.(int) floor((float) \App\Models\Setting::valueOf('order_bonus_percent', 5))],
        ], [
            'cart.required' => 'Səbət boşdur.',
            'address_id.required_if' => 'Ünvanı seçin.',
            'city_id.required_if' => 'Şəhəri seçin.',
            'city_id.exists' => 'Şəhəri siyahıdan seçin.',
            'address.required_if' => 'Küçə və ünvanı daxil edin.',
            'payment_method_id.required' => 'Ödəniş üsulunu seçin.',
        ]);

        $paymentMethod = PaymentMethod::whereKey($data['payment_method_id'])
            ->where('active', 1)
            ->whereIn('code', self::ORDER_PAYMENT_CODES)
            ->first();
        if (!$paymentMethod) {
            return response()->json(['success' => false, 'message' => 'Bu ödəniş üsulu deaktivdir.'], 422);
        }
        $code = $paymentMethod->code;

        if ($code === 'birbank_installment' && empty($data['birbank_installment_months'])) {
            return response()->json(['success' => false, 'message' => 'Birbank taksit müddətini seçin.'], 422);
        }
        $period = null;
        if ($code === 'installment') {
            if (!$customer->creditProfile?->isComplete()) {
                return response()->json(['success' => false, 'message' => 'Müştərinin kredit profili tamamlanmayıb.'], 422);
            }
            $period = CreditPeriod::whereKey($data['credit_period_id'] ?? 0)->where('active', 1)->first();
            if (!$period) {
                return response()->json(['success' => false, 'message' => 'Kredit müddətini seçin.'], 422);
            }
        }

        $order = DB::transaction(function () use ($data, $customer, $paymentMethod, $code, $period) {
            DB::table('customers')->where('id', $customer->id)->lockForUpdate()->first();

            $initialStatus = OrderStatus::where('code', 'new')->where('active', 1)->firstOrFail();

            // Ünvan
            if ($data['address_mode'] === 'existing') {
                $address = $customer->addresses()->findOrFail($data['address_id']);
            } else {
                $address = $customer->addresses()->create(
                    CustomerAddress::attributesFromForm($data, 'Ünvan #' . ($customer->addresses()->count() + 1)) + [
                        'is_default' => !$customer->addresses()->exists(),
                    ]
                );
            }

            // Məhsullar — sayt qiyməti bazadan; operator yalnız endirim edə bilər (qiymət ≤ sayt qiyməti)
            $cart = collect($data['cart'])->keyBy('variant_id');
            $variants = ProductVariant::with('product.activeDiscount')->whereIn('id', $cart->keys())
                ->where('active', 1)
                ->whereHas('product', fn ($p) => $p->where('active', 1))
                ->get();
            abort_if($variants->count() !== $cart->count(), 422, 'Səbətdə satışda olmayan məhsul var.');

            $subtotal = 0; // sayt qiymətləri ilə
            $discount = 0; // operatorun endirimi
            $items = [];
            $manualPrice = false; // operator qiyməti əl ilə dəyişib (avtomatik məhsul endirimi sayılmır)
            foreach ($variants as $variant) {
                $quantity = (int) $cart[$variant->id]['quantity'];
                $listPrice = round((float) $variant->price, 2);
                $price = $cart[$variant->id]['price'] ?? null;
                // qiymət göndərilməyibsə — məhsul endirimi avtomatik (salePrice); operator yenə də endirim edə bilər.
                // Hissə-hissə (öz kreditimiz): məhsul endirimi tətbiq olunmur — göndərilən qiymət endirimli qiymətdirsə, adi qiymət
                $sale = $variant->salePrice();
                $unitPrice = $price === null ? $sale : round((float) $price, 2);
                if ($code === 'installment' && $sale < $listPrice && ($price === null || abs($unitPrice - $sale) < 0.005)) {
                    $unitPrice = $listPrice;
                }
                abort_if($unitPrice > $listPrice, 422, 'Qiymət saytdakı qiymətdən yüksək ola bilməz.');
                $defaultPrice = $code === 'installment' ? $listPrice : $sale;
                if (abs($unitPrice - $defaultPrice) >= 0.005) {
                    $manualPrice = true;
                }

                $subtotal += $listPrice * $quantity;
                $discount += ($listPrice - $unitPrice) * $quantity;
                $items[] = [
                    'product_id' => $variant->product_id,
                    'product_variant_id' => $variant->id,
                    'unit_price' => $unitPrice,
                    'list_price' => $unitPrice < $listPrice ? $listPrice : null,
                    'quantity' => $quantity,
                    'total' => round($unitPrice * $quantity, 2),
                ];
            }
            $subtotal = round($subtotal, 2);
            $discount = round($discount, 2);
            $goods = round($subtotal - $discount, 2);

            $pricing = app(ShopPricing::class);
            $giftWrap = (bool) ($data['gift_wrap'] ?? false);
            $delivery = $pricing->deliveryFee($goods); // checkout-dakı kimi: endirimdən sonrakı məbləğə görə
            $giftWrapFee = $pricing->giftWrapFee($giftWrap);
            $payable = round($goods + $delivery + $giftWrapFee, 2);
            $total = $period ? round($payable * (1 + (float) $period->interest_rate / 100), 2) : $payable;

            $order = Order::create([
                'order_no' => 'TMP' . Str::random(20),
                'customer_id' => $customer->id,
                'customer_address_id' => $address->id,
                'payment_method_id' => $paymentMethod->id,
                'birbank_installment_months' => $code === 'birbank_installment' ? (int) $data['birbank_installment_months'] : null,
                'payment_status' => $code === 'bonus_balance' ? 'paid' : ($code === 'cash' ? 'cod' : 'pending'),
                'source' => 'operator',
                'created_by' => auth()->id(),
                'order_status_id' => $initialStatus->id,
                'gift_wrap' => $giftWrap,
                // qiymət əl ilə dəyişibsə — operatorun seçdiyi faiz (standart 0); yoxdursa sifariş anındakı admin faizi (sabitlənir)
                'bonus_percent' => $manualPrice ? (int) ($data['bonus_percent'] ?? 0) : app(\App\Services\BonusService::class)->currentPercent(),
                'subtotal' => $subtotal,
                'discount' => $discount,
                'delivery_fee' => $delivery,
                'gift_wrap_fee' => $giftWrapFee,
                'total' => $total,
            ]);
            $order->update(['order_no' => 'PS' . now()->format('ymd') . str_pad((string) $order->id, 6, '0', STR_PAD_LEFT)]);
            $order->items()->createMany($items);

            if ($code === 'bonus_balance') {
                $balance = (float) DB::table('customers')->where('id', $customer->id)->value('bonus_balance');
                abort_if(round($balance, 2) < round($total, 2), 422, 'Müştərinin bonus balansı kifayət etmir.');
                DB::table('customers')->where('id', $customer->id)->decrement('bonus_balance', $total);
                $customer->bonusTransactions()->create([
                    'order_id' => $order->id, 'type' => 'spend',
                    'amount' => -$total, 'note' => 'Sifariş bonusla ödənildi',
                ]);
            }
            if ($code === 'installment') {
                CreditApplication::create([
                    'customer_id' => $customer->id, 'order_id' => $order->id,
                    'credit_period_id' => $period->id, 'interest_rate' => $period->interest_rate,
                    'total' => $total, 'monthly' => round($total / $period->month, 2),
                    'credit_status_id' => CreditStatus::where('code', 'pending')->firstOrFail()->id,
                ]);
            }
            // Sifariş bonusu təhvildə yazılır (BonusService::earnOnDelivery)

            DB::table('order_status_logs')->insert([
                'order_id' => $order->id,
                'status_id' => $initialStatus->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $order;
        });

        if (!in_array($code, ['card_online', 'birbank_installment'], true) && filter_var($customer->email, FILTER_VALIDATE_EMAIL)) {
            Mail::to($customer->email)->queue(new OrderCreatedMail($order, 'az'));
        }

        // Kart / Birbank: müştəriyə SMS ilə ödəniş linki (login olmadan ödəyir)
        $message = "Sifariş {$order->order_no} yaradıldı.";
        if (in_array($code, Order::ONLINE_PAYMENT_CODES, true)) {
            try {
                app(OrderPayLinkService::class)->sendSms($order);
                $message .= ' Ödəniş linki müştəriyə SMS ilə göndərildi.';
            } catch (\Throwable $e) {
                report($e);
                $message .= ' Ödəniş linkini SMS ilə göndərmək alınmadı — sifarişin detalından yenidən göndərin.';
            }
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'order_no' => $order->order_no,
        ]);
    }

    /** Sifariş detalı → Məhsullar → "Ləğv et": məhsulu və ya onun bir hissəsini ləğv edir */
    public function cancelItem(Request $request, Customer $customer, Order $order, OrderItem $item, OrderItemCancellationService $service): RedirectResponse
    {
        abort_unless($order->customer_id === $customer->id && $item->order_id === $order->id, 404);

        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:9999'],
            'reason' => ['required', Rule::in(array_diff(array_keys(OrderItemCancellation::REASONS), ['door_refused']))], // qapıda imtina — ayrıca
            'note' => ['nullable', 'required_if:reason,other', 'string', 'max:2000'],
            'customer_agreed' => ['accepted'],
        ], [
            'reason.required' => 'Səbəbi seçin.',
            'note.required_if' => '"Digər" səbəbdə qeyd yazın.',
            'customer_agreed.accepted' => 'Müştəri ilə razılaşdırıldığını təsdiqləyin.',
        ]);

        $cancellation = $service->cancel($order, $item, (int) $data['quantity'], $data['reason'], $data['note'] ?? null, (int) (auth('admin')->id() ?? auth()->id()));

        $message = 'Məhsul ləğv edildi: '.$cancellation->quantity.' ədəd, '.number_format((float) $cancellation->amount, 2).' AZN.';
        $message .= match ($cancellation->refund_status) {
            OrderItemCancellation::REFUND_PENDING => ' Məbləğ müştərinin kartına qaytarılmalıdır.',
            OrderItemCancellation::REFUND_BONUS => ' Məbləğ bonus balansına qaytarıldı.',
            default => '',
        };

        return back()->with('success', $message);
    }

    /** Sifariş detalı → "Sifarişi ləğv et": bu sifarişin qalan bütün məhsulları ləğv olunur, status "Ləğv edildi" */
    public function cancelOrder(Request $request, Customer $customer, Order $order, OrderItemCancellationService $service, WarehouseNotifier $notifier): RedirectResponse
    {
        abort_unless($order->customer_id === $customer->id, 404);

        $data = $request->validate([
            'reason' => ['required', Rule::in(array_diff(array_keys(OrderItemCancellation::REASONS), ['door_refused']))],
            'note' => ['nullable', 'required_if:reason,other', 'string', 'max:2000'],
            'customer_agreed' => ['accepted'],
        ], [
            'reason.required' => 'Səbəbi seçin.',
            'note.required_if' => '"Digər" səbəbdə qeyd yazın.',
            'customer_agreed.accepted' => 'Müştəri ilə razılaşdırıldığını təsdiqləyin.',
        ]);

        $actor = (int) auth('admin')->id();
        $result = $service->cancelOrder($order, $data['reason'], $data['note'] ?? null, $actor);

        // Xəbərdar edilmiş / mal ayırmış anbarlara: "rezerv lazım deyil"
        $sms = OrderItemAllocation::with('warehouse')->whereIn('id', $result['notify'])->get()
            ->mapWithKeys(fn ($allocation) => [$allocation->warehouse->name_az => rescue(fn () => $notifier->notifyCancelled($allocation, $actor), null)]);

        $cancellations = collect($result['cancellations']);
        $refund = $cancellations->whereIn('refund_status', [OrderItemCancellation::REFUND_PENDING])->sum('amount');
        $bonus = $cancellations->where('refund_status', OrderItemCancellation::REFUND_BONUS)->sum('amount');

        // Müştəriyə SMS (şablon: crm_order_cancelled) — xəta ləğvi pozmur
        $customerSms = $this->notifyCustomerOrderCancelled($order);

        $message = 'Sifariş ləğv edildi.';
        $message .= $customerSms === true ? ' Müştəriyə SMS göndərildi.' : ($customerSms === false ? ' Müştəriyə SMS göndərilmədi.' : '');
        $message .= $refund > 0 ? ' '.number_format($refund, 2).' AZN müştərinin kartına qaytarılmalıdır ("Ödənişlər" bölməsi).' : '';
        $message .= $bonus > 0 ? ' '.number_format($bonus, 2).' AZN bonus balansına qaytarıldı.' : '';
        $message .= $sms->isNotEmpty() ? ' '.WarehouseNotifier::summary($sms) : '';

        return back()->with('success', trim($message));
    }

    /** "İcraya götür": Sifariş verildi → Hazırlanır. Bundan sonra anbar sorğusu göndərmək olar. */
    public function startOrder(Customer $customer, Order $order, OrderStatusService $statuses): RedirectResponse
    {
        abort_unless($order->customer_id === $customer->id, 404);
        $statuses->start($order, (int) auth('admin')->id());

        return back()->with('success', 'Sifariş icraya götürüldü: "Hazırlanır".');
    }

    /** "Kuryer təyin et": bütün məhsullar anbarlara təyin olunandan sonra */
    /** Qapıda imtina — operator (kuryer zəng edib bildirir) */
    public function refuseItem(Request $request, Customer $customer, Order $order, OrderItem $item, OrderItemCancellationService $service): RedirectResponse
    {
        abort_unless($order->customer_id === $customer->id && $item->order_id === $order->id, 404);
        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:9999'],
            'note' => ['nullable', 'string', 'max:2000'],
        ], ['quantity.required' => 'Sayı seçin.']);
        $cancellation = $service->refuseAtDoor($order, $item, (int) $data['quantity'], $data['note'] ?? null, (int) auth('admin')->id());

        return back()->with('success', 'Qapıda imtina qeyd olundu: '.$cancellation->quantity.' ədəd, −'
            .number_format((float) $cancellation->amount, 2).' AZN. Kuryer yeni məbləği alacaq və məhsulu anbara qaytaracaq.');
    }

    public function assignCourier(Request $request, Customer $customer, Order $order, OrderStatusService $statuses): RedirectResponse
    {
        abort_unless($order->customer_id === $customer->id, 404);
        $data = $request->validate(['courier_id' => ['required', 'integer']], ['courier_id.required' => 'Kuryeri seçin.']);
        $courier = $this->couriers()->firstWhere('id', (int) $data['courier_id']);
        abort_if(!$courier, 422, 'Kuryer tapılmadı.');
        $statuses->assignCourier($order, $courier, (int) auth('admin')->id());

        return back()->with('success', 'Kuryer təyin olundu: '.trim($courier->full_name).'.');
    }

    /** "Kuryer" rolundakı aktiv əməkdaşlar */
    private function couriers()
    {
        try {
            return \App\Models\User::role(\App\Services\FinanceService::COURIER_ROLE, 'admin')->where('active', 1)->orderBy('name')->get();
        } catch (\Spatie\Permission\Exceptions\RoleDoesNotExist) {
            return collect();
        }
    }

    /** Ödənişlər → Geri qaytarmalar → "Karta qaytar": ləğv olunan məhsulun pulu Birbank ilə qaytarılır */
    public function refundCancellation(Customer $customer, Order $order, OrderItemCancellation $cancellation, OrderRefundService $service): RedirectResponse
    {
        abort_unless($order->customer_id === $customer->id && $cancellation->order_id === $order->id, 404);

        $cancellation = $service->refundCancellation($cancellation);

        return back()->with('success', $cancellation->refund_status === OrderItemCancellation::REFUND_DONE
            ? number_format((float) $cancellation->amount, 2).' AZN müştərinin kartına qaytarıldı.'
            : 'Qaytarma bankda yoxlanılır.');
    }

    /** Sifariş detalı → "SMS ilə göndər": ödəniş linkini müştəriyə yenidən göndərir */
    public function sendPayLink(Customer $customer, Order $order, OrderPayLinkService $payLink): JsonResponse
    {
        abort_unless($order->customer_id === $customer->id, 404);

        try {
            $payLink->sendSms($order);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            report($e);
            return response()->json(['success' => false, 'message' => 'SMS göndərilmədi. Bir az sonra yenidən cəhd edin.'], 500);
        }

        return response()->json(['success' => true, 'message' => 'Ödəniş linki müştəriyə SMS ilə göndərildi.']);
    }

    /**
     * Operator sifarişi ləğv edəndə müştəriyə SMS. true — göndərildi, false — alınmadı,
     * null — şablon söndürülüb və ya nömrə yoxdur.
     */
    private function notifyCustomerOrderCancelled(Order $order): ?bool
    {
        $order->loadMissing('customer');
        $mobile = $order->customer?->mobile;
        $text = \App\Models\SmsTemplate::message('crm_order_cancelled', [
            'name' => $order->customer?->name ?? '', 'fullname' => $order->customer?->fullname ?? '', 'order_no' => $order->order_no,
        ]);
        if (!$mobile || !$text) {
            return null;
        }

        try {
            \App\Models\SmsLog::deliver(app(\App\Services\SmsService::class), $mobile, $text, 'crm_order_cancelled', $order, auth('admin')->id());

            return true;
        } catch (\Throwable $e) {
            report($e);

            return false;
        }
    }

    /** Sifariş detalı → "Bankdan yoxla": gözləyən ödənişin nəticəsini cron-u gözləmədən bankdan soruşur */
    public function checkPendingPayment(Customer $customer, Order $order, \App\Services\Payment\BirbankPaymentSync $sync): JsonResponse
    {
        abort_unless($order->customer_id === $customer->id, 404);

        $state = $sync->resumePending($order)['state'];

        return response()->json([
            'success' => true,
            'message' => match ($state) {
                'none' => 'Gözləyən ödəniş yoxdur.',
                'paid' => 'Ödəniş bankda tamamlanıb — sifariş ödənilib.',
                'released' => 'Əvvəlki cəhd bankda bağlanıb (uğursuz, ləğv və ya vaxtı bitib). Müştəri yenidən ödəyə bilər.',
                'resume' => 'Ödəniş bankda hələ açıqdır: müştəri linkə keçib eyni ödənişə davam edə bilər.',
                default => 'Bankdan nəticə alınmadı və ya əməliyyat icradadır. Bir az sonra yenidən yoxlayın.',
            },
        ]);
    }

    /** Sifariş detalı → "Müddəti yenilə": SMS göndərmədən ödəniş linkinin müddətini yenidən sayır */
    public function renewPayLink(Customer $customer, Order $order, OrderPayLinkService $payLink): JsonResponse
    {
        abort_unless($order->customer_id === $customer->id, 404);

        $order->loadMissing('paymentMethod', 'status');
        if (!$order->payLinkAvailable()) {
            return response()->json(['success' => false, 'message' => 'Bu sifariş üçün ödəniş linki aktiv deyil.'], 422);
        }

        $payLink->ensureToken($order);
        $payLink->renew($order);

        return response()->json([
            'success' => true,
            'message' => 'Link '.$order->pay_token_expires_at->timezone('Asia/Baku')->format('d.m.Y H:i').'-dək aktivdir.',
        ]);
    }
}
