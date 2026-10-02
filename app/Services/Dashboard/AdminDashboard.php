<?php

namespace App\Services\Dashboard;

use App\Models\Customer\Customer;
use App\Models\Finance\FinanceAccount;
use App\Models\Order\Order;
use App\Models\Order\OrderItemCancellation;
use App\Models\Order\OrderStatus;
use App\Models\Payment\Payment;
use App\Models\Procurement\OrderItemAllocation;
use App\Services\FinanceService;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Admin əsas səhifəsinin göstəriciləri.
 *  - Dövriyyə: ləğv edilməmiş sifarişlərin yekun məbləği (sifariş tarixinə görə).
 *  - Mənfəət: təhvil verilmiş sifarişlərdə mal satışı (yekun − çatdırılma − bükmə) − anbardan alış dəyəri.
 *  - Yeni müştərilər: köhnə sistemdən köçürülənlər (old_customer_id) xaric.
 * Dövr cari təqvim dövrüdür (bu gün / bu həftə / bu ay) və əvvəlki dövrün EYNİ anına qədəri ilə müqayisə olunur
 * (məs. çərşənbə 15:00 → keçən çərşənbə 15:00-a qədər), yoxsa dövrün əvvəlində həmişə "−90%" görünərdi.
 * Nəticələr qısa müddət keşdə saxlanılır — hər açılışda böyük cədvəllər sayılmasın.
 */
class AdminDashboard
{
    public const PERIODS = [
        'today' => 'Bu gün',
        'week' => 'Bu həftə',
        'month' => 'Bu ay',
    ];

    public const PREVIOUS = [
        'today' => 'Dünən bu vaxta qədər',
        'week' => 'Keçən həftə bu vaxta qədər',
        'month' => 'Keçən ay bu vaxta qədər',
    ];

    /** Hələ bitməmiş (aktiv) sifariş mərhələləri — axın sırası ilə */
    public const ACTIVE_STATUSES = [
        'new' => 'send',
        'preparing' => 'box',
        'warehouse_requested' => 'hourglass',
        'warehouses_assigned' => 'boxes',
        'courier_assigned' => 'user',
        'sent' => 'delivery-truck',
        'at_address' => 'pin',
    ];

    private const TTL = 300;

    public static function period(?string $period): string
    {
        return array_key_exists((string) $period, self::PERIODS) ? $period : 'today';
    }

    /**
     * Seçilmiş dövr və əvvəlki dövrün eyni anına qədəri üzrə əsas göstəricilər.
     *
     * @return array<string, array{value: float|int, previous: float|int, change: ?float}>
     */
    public function stats(string $period): array
    {
        $period = self::period($period);

        return Cache::remember("admin-dashboard:stats:$period", self::TTL, function () use ($period) {
            [$from, $to, $prevFrom, $prevTo] = $this->range($period);
            $current = $this->orderTotals($from, $to);
            $previous = $this->orderTotals($prevFrom, $prevTo);
            $profit = $this->profit($from, $to);
            $prevProfit = $this->profit($prevFrom, $prevTo);
            $customers = fn ($a, $b) => $this->newCustomers($a, $b)->count();

            return [
                'revenue' => $this->metric($current['sum'], $previous['sum']),
                'profit' => $this->metric($profit['profit'], $prevProfit['profit']) + [
                    'orders' => $profit['orders'],
                    'margin' => $profit['sales'] > 0 ? round($profit['profit'] / $profit['sales'] * 100, 1) : null,
                ],
                'orders' => $this->metric($current['count'], $previous['count']),
                'average' => $this->metric(
                    $current['count'] ? round($current['sum'] / $current['count'], 2) : 0,
                    $previous['count'] ? round($previous['sum'] / $previous['count'], 2) : 0,
                ),
                'customers' => $this->metric($customers($from, $to), $customers($prevFrom, $prevTo)),
            ];
        });
    }

    /**
     * Seçilmiş dövrdə ödəniş üsulları üzrə bölgü (ləğv edilməmiş sifarişlər), məbləğə görə azalan.
     *
     * @return array<int, array{code: string, name: string, orders: int, sum: float, share: float}>
     */
    public function paymentMethods(string $period): array
    {
        $period = self::period($period);

        return Cache::remember("admin-dashboard:payment-methods:$period", self::TTL, function () use ($period) {
            [$from, $to] = $this->range($period);
            $rows = $this->validOrders()
                ->join('payment_methods', 'payment_methods.id', '=', 'orders.payment_method_id')
                ->whereBetween('orders.created_at', [$from, $to])
                ->groupBy('payment_methods.id', 'payment_methods.code', 'payment_methods.name_az')
                ->selectRaw('payment_methods.code, COALESCE(payment_methods.name_az, payment_methods.code) as name, COUNT(*) as orders, SUM(orders.total) as total_sum')
                ->orderByDesc('total_sum')
                ->get();
            $all = (float) $rows->sum('total_sum');

            return $rows->map(fn ($r) => [
                'code' => $r->code,
                'name' => $r->name,
                'orders' => (int) $r->orders,
                'sum' => round((float) $r->total_sum, 2),
                'share' => $all > 0 ? round((float) $r->total_sum / $all * 100, 1) : 0.0,
            ])->all();
        });
    }

    /**
     * Son N gün üzrə günlük dövriyyə və sifariş sayı (qrafik üçün, bu gün daxil).
     *
     * @return array{labels: string[], revenue: float[], orders: int[], dates: string[]}
     */
    public function sales(int $days = 7): array
    {
        return Cache::remember("admin-dashboard:sales:$days", self::TTL, function () use ($days) {
            $start = CarbonImmutable::today()->subDays($days - 1);
            $rows = $this->validOrders()
                ->where('created_at', '>=', $start)
                ->selectRaw('DATE(created_at) as day, COUNT(*) as orders, SUM(total) as revenue')
                ->groupBy('day')
                ->get()
                ->keyBy('day');

            $weekdays = ['B.', 'B.e.', 'Ç.a.', 'Ç.', 'C.a.', 'C.', 'Ş.'];
            $result = ['labels' => [], 'dates' => [], 'revenue' => [], 'orders' => []];
            for ($i = 0; $i < $days; $i++) {
                $day = $start->addDays($i);
                $row = $rows->get($day->toDateString());
                $result['labels'][] = $day->isToday() ? 'Bu gün' : $weekdays[$day->dayOfWeek].' '.$day->format('d.m');
                $result['dates'][] = $day->toDateString();
                $result['revenue'][] = round((float) ($row->revenue ?? 0), 2);
                $result['orders'][] = (int) ($row->orders ?? 0);
            }

            return $result;
        });
    }

    /**
     * Aktiv sifarişlərin mərhələlər üzrə sayı (axın sırası ilə, 0 olanlar da göstərilir).
     *
     * @return array<int, array{code: string, name: string, icon: string, count: int}>
     */
    public function activeStatuses(): array
    {
        return Cache::remember('admin-dashboard:active-statuses', 60, function () {
            $statuses = OrderStatus::whereIn('code', array_keys(self::ACTIVE_STATUSES))->get()->keyBy('code');
            $counts = Order::whereIn('order_status_id', $statuses->pluck('id'))
                ->selectRaw('order_status_id, COUNT(*) as total')
                ->groupBy('order_status_id')
                ->pluck('total', 'order_status_id');

            $result = [];
            foreach (self::ACTIVE_STATUSES as $code => $icon) {
                if ($status = $statuses->get($code)) {
                    $result[] = [
                        'code' => $code,
                        'name' => $status->name_az,
                        'icon' => $icon,
                        'count' => (int) ($counts[$status->id] ?? 0),
                    ];
                }
            }

            return $result;
        });
    }

    /**
     * Seçilmiş dövrdə ən çox satılan məhsullar (ləğv edilməmiş sifarişlər, ləğv olunan miqdar çıxılır).
     *
     * @return array<int, array{id: int, name: string, brand: ?string, image: ?string, quantity: int, sum: float}>
     */
    public function topProducts(string $period, int $limit = 5): array
    {
        $period = self::period($period);

        return Cache::remember("admin-dashboard:top-products:$period:$limit", self::TTL, function () use ($period, $limit) {
            [$from, $to] = $this->range($period);
            $rows = DB::table('order_items')
                ->joinSub($this->validOrders()->whereBetween('created_at', [$from, $to])->select('id'), 'o', 'o.id', '=', 'order_items.order_id')
                ->join('products', 'products.id', '=', 'order_items.product_id')
                ->leftJoin('brands', 'brands.id', '=', 'products.brand_id')
                ->groupBy('products.id', 'products.name', 'brands.name')
                ->selectRaw('products.id, products.name, brands.name as brand,
                    SUM(order_items.quantity - COALESCE(order_items.cancelled_quantity, 0)) as qty,
                    SUM(order_items.total) as total_sum')
                ->havingRaw('SUM(order_items.quantity - COALESCE(order_items.cancelled_quantity, 0)) > 0')
                ->orderByDesc('qty')->orderByDesc('total_sum')
                ->limit($limit)
                ->get();

            $images = DB::table('product_images')->whereIn('product_id', $rows->pluck('id'))
                ->orderBy('sort_order')->orderBy('id')->get(['product_id', 'image'])
                ->unique('product_id')->pluck('image', 'product_id');

            return $rows->map(fn ($r) => [
                'id' => (int) $r->id,
                'name' => $r->name,
                'brand' => $r->brand,
                'image' => $images[$r->id] ?? null,
                'quantity' => (int) $r->qty,
                'sum' => round((float) $r->total_sum, 2),
            ])->all();
        });
    }

    /**
     * "Diqqət tələb edənlər": operatorun hərəkət etməli olduğu işlər.
     *
     * @return array<string, int>
     */
    public function attention(): array
    {
        return Cache::remember('admin-dashboard:attention', 60, function () {
            $cancelled = OrderStatus::where('code', 'cancelled')->value('id');
            $courier = OrderStatus::whereIn('code', ['courier_assigned', 'sent', 'at_address'])->pluck('id');

            return [
                // anbara sorğu göndərilib, 2 saatdır heç bir cavab yoxdur
                'warehouse' => DB::table('warehouse_request_items')
                    ->join('warehouse_requests', 'warehouse_requests.id', '=', 'warehouse_request_items.warehouse_request_id')
                    ->join('orders', 'orders.id', '=', 'warehouse_requests.order_id')
                    ->when($cancelled, fn ($q) => $q->where('orders.order_status_id', '!=', $cancelled))
                    ->where('warehouse_requests.created_at', '<', now()->subHours(2))
                    ->whereNotExists(fn ($q) => $q->from('warehouse_offers')
                        ->whereColumn('warehouse_offers.warehouse_request_item_id', 'warehouse_request_items.id'))
                    ->distinct()->count('orders.id'),
                'easy_orders' => Order::where('one_click', true)->whereNull('customer_id')
                    ->when($cancelled, fn ($q) => $q->where('order_status_id', '!=', $cancelled))->count(),
                'credit' => DB::table('credit_applications')
                    ->join('credit_statuses', 'credit_statuses.id', '=', 'credit_applications.credit_status_id')
                    ->whereIn('credit_statuses.code', ['pending', 'reviewing'])->count(),
                'reviews' => DB::table('product_reviews')->where('active', false)->count(),
                'refunds' => DB::table('order_item_cancellations')
                    ->whereIn('refund_status', [OrderItemCancellation::REFUND_PENDING, OrderItemCancellation::REFUND_PROCESSING])->count(),
                // kuryer mərhələsində 1 gündən çox dəyişməyən sifarişlər
                'courier' => Order::whereIn('order_status_id', $courier)->where('updated_at', '<', now()->subDay())->count(),
            ];
        });
    }

    /**
     * Saytda axtarışlar: uğurlu / nəticəsiz və ən çox nəticəsiz qalan sorğular.
     *
     * @return array{total: int, found: int, empty: int, top_empty: array<int, array{query: string, count: int}>}
     */
    public function searches(string $period): array
    {
        $period = self::period($period);

        return Cache::remember("admin-dashboard:searches:$period", self::TTL, function () use ($period) {
            [$from, $to] = $this->range($period);
            $logs = fn () => DB::table('product_search_logs')->whereBetween('searched_at', [$from, $to]);
            $total = $logs()->count();
            $empty = $logs()->where('result_count', 0)->count();

            return [
                'total' => $total,
                'found' => $total - $empty,
                'empty' => $empty,
                'top_empty' => $logs()->where('result_count', 0)
                    ->groupBy('normalized_query')
                    ->selectRaw('MAX(query) as query, COUNT(*) as total')
                    ->orderByDesc('total')->limit(5)->get()
                    ->map(fn ($r) => ['query' => $r->query, 'count' => (int) $r->total])->all(),
            ];
        });
    }

    /**
     * Onlayn ödəniş cəhdləri (bank): uğurlu, uğursuz/ləğv, gözləyən.
     *
     * @return array{paid: int, paid_sum: float, failed: int, failed_sum: float, pending: int, pending_sum: float, rate: ?float}
     */
    public function onlinePayments(string $period): array
    {
        $period = self::period($period);

        return Cache::remember("admin-dashboard:online-payments:$period", self::TTL, function () use ($period) {
            [$from, $to] = $this->range($period);
            $rows = DB::table('payments')->whereBetween('created_at', [$from, $to])
                ->groupBy('status')->selectRaw('status, COUNT(*) as total, SUM(amount) as total_sum')
                ->get()->keyBy('status');
            $count = fn (array $statuses) => (int) collect($statuses)->sum(fn ($s) => $rows[$s]->total ?? 0);
            $sum = fn (array $statuses) => round((float) collect($statuses)->sum(fn ($s) => $rows[$s]->total_sum ?? 0), 2);
            $paid = $count([Payment::PAID]);
            $failed = $count([Payment::FAILED, Payment::CANCELLED]);

            return [
                'paid' => $paid,
                'paid_sum' => $sum([Payment::PAID]),
                'failed' => $failed,
                'failed_sum' => $sum([Payment::FAILED, Payment::CANCELLED]),
                'pending' => $count([Payment::PENDING]),
                'pending_sum' => $sum([Payment::PENDING]),
                'rate' => $paid + $failed > 0 ? round($paid / ($paid + $failed) * 100, 1) : null,
            ];
        });
    }

    /** Sifariş mənbələri: kod => ad */
    public const SOURCES = [
        'customer' => 'Sayt — səbət',
        'one_click' => 'Bir kliklə sifariş',
        'operator' => 'Operator (CRM)',
        'other' => 'Digər',
    ];

    /**
     * Ləğv edilməmiş sifarişlərin mənbəyə görə bölgüsü.
     *
     * @return array<int, array{code: string, name: string, orders: int, sum: float, share: float}>
     */
    public function sources(string $period): array
    {
        $period = self::period($period);

        return Cache::remember("admin-dashboard:sources:$period", self::TTL, function () use ($period) {
            [$from, $to] = $this->range($period);
            $rows = $this->validOrders()->whereBetween('created_at', [$from, $to])
                ->selectRaw("CASE WHEN one_click = 1 THEN 'one_click'
                    WHEN source IN ('customer', 'operator') THEN source ELSE 'other' END as src,
                    COUNT(*) as total, SUM(total) as total_sum")
                ->groupBy('src')->get()->keyBy('src');
            $all = (int) $rows->sum('total');

            return collect(self::SOURCES)
                ->map(fn ($name, $code) => [
                    'code' => $code,
                    'name' => $name,
                    'orders' => (int) ($rows[$code]->total ?? 0),
                    'sum' => round((float) ($rows[$code]->total_sum ?? 0), 2),
                    'share' => $all ? round(($rows[$code]->total ?? 0) / $all * 100, 1) : 0.0,
                ])
                ->filter(fn ($row) => $row['orders'] > 0)
                ->sortByDesc('orders')->values()->all();
        });
    }

    /**
     * Yeni müştərilər mənbəyə görə (seçilmiş dövr). Saytda qeydiyyat yalnız SMS ilə təsdiqlənibsə sayılır.
     *
     * @return array<int, array{code: ?string, name: string, count: int}>
     */
    public function customerSources(string $period): array
    {
        $period = self::period($period);

        return Cache::remember("admin-dashboard:customer-sources:$period", self::TTL, function () use ($period) {
            [$from, $to] = $this->range($period);

            return $this->newCustomers($from, $to)
                ->selectRaw('source, COUNT(*) as total')->groupBy('source')->orderByDesc('total')->get()
                ->map(fn ($r) => [
                    'code' => $r->source,
                    'name' => Customer::SOURCES[$r->source] ?? 'Məlum deyil',
                    'count' => (int) $r->total,
                ])->all();
        });
    }

    /** Dövrdə yaranan real müştərilər: köçürülənlər və SMS-i təsdiqlənməmiş qeydiyyatlar xaric */
    private function newCustomers(CarbonImmutable $from, CarbonImmutable $to)
    {
        return Customer::query()
            ->whereNull('old_customer_id')
            ->where(fn ($q) => $q->whereNull('source')->orWhere('source', '!=', 'legacy'))
            ->where(fn ($q) => $q->where('source', '!=', 'website')->orWhereNull('source')->orWhere('active', true))
            ->whereBetween('created_at', [$from, $to]);
    }

    /**
     * Maliyyə vəziyyəti (indiki an): kassa, bank, kuryerlər, anbarlar, bonus öhdəliyi. Məbləğlər manatla.
     *
     * @return array{cash: float, bank: float, couriers_owe: float, owe_couriers: float, couriers: array, warehouses_debt: float, warehouses: int, bonus: float}
     */
    public function finance(): array
    {
        return Cache::remember('admin-dashboard:finance', 60, function () {
            $finance = app(FinanceService::class);
            $balances = $finance->balances();
            $accounts = FinanceAccount::with('user')->where('active', true)->get();
            $sum = fn (string $type) => $accounts->where('type', $type)->sum(fn ($a) => $balances[$a->id] ?? 0) / 100;

            $couriers = $accounts->where('type', 'courier')
                ->map(fn ($a) => ['id' => $a->id, 'name' => $a->name, 'balance' => ($balances[$a->id] ?? 0) / 100])
                ->filter(fn ($c) => $c['balance'] != 0)
                ->sortByDesc(fn ($c) => abs($c['balance']))->values();
            $debts = $finance->warehouseDebts()->filter(fn ($cents) => $cents > 0);

            return [
                'cash' => round($sum('cash'), 2),
                'bank' => round($sum('bank'), 2),
                // + kuryer şirkətə təhvil verməlidir, − şirkət kuryerə borcludur
                'couriers_owe' => round($couriers->where('balance', '>', 0)->sum('balance'), 2),
                'owe_couriers' => round(-$couriers->where('balance', '<', 0)->sum('balance'), 2),
                'couriers' => $couriers->take(5)->all(),
                'warehouses_debt' => round($debts->sum() / 100, 2),
                'warehouses' => $debts->count(),
                'bonus' => round((float) Customer::sum('bonus_balance'), 2),
            ];
        });
    }

    /**
     * Dizayna baxmaq üçün saxta göstəricilər (yalnız lokal mühitdə, /admin?demo=1).
     * Bazaya toxunmur; struktur stats()/paymentMethods()/sales() ilə eynidir.
     */
    public function demo(string $period): array
    {
        $period = self::period($period);
        $scale = ['today' => 1, 'week' => 5, 'month' => 21][$period];
        $metric = fn ($value, $previous) => $this->metric(round($value, 2), round($previous, 2));

        $sales = ['labels' => [], 'dates' => [], 'revenue' => [], 'orders' => []];
        $weekdays = ['B.', 'B.e.', 'Ç.a.', 'Ç.', 'C.a.', 'C.', 'Ş.'];
        $daily = [[9, 2665], [12, 2836], [8, 1324], [15, 4223], [11, 2176], [17, 5546], [10, 5183]];
        foreach ($daily as $i => [$orders, $revenue]) {
            $day = CarbonImmutable::today()->subDays(6 - $i);
            $sales['labels'][] = $day->isToday() ? 'Bu gün' : $weekdays[$day->dayOfWeek].' '.$day->format('d.m');
            $sales['dates'][] = $day->toDateString();
            $sales['revenue'][] = (float) $revenue;
            $sales['orders'][] = $orders;
        }

        $revenue = 5183 * $scale;
        $methods = [
            ['code' => 'cash', 'name' => 'Qapıda nağd', 'share' => 0.46],
            ['code' => 'card_online', 'name' => 'Kartla onlayn', 'share' => 0.27],
            ['code' => 'birbank_installment', 'name' => 'Birbank taksit', 'share' => 0.17],
            ['code' => 'installment', 'name' => 'Hissə-hissə ödəniş', 'share' => 0.10],
        ];

        return [
            'stats' => [
                'revenue' => $metric($revenue, $revenue / 1.12),
                'profit' => $metric($revenue * 0.21, $revenue * 0.21 / 0.94) + ['orders' => 18 * $scale, 'margin' => 21.4],
                'orders' => $metric(24 * $scale, 23 * $scale),
                'average' => $metric(215.96, 241.13),
                'customers' => $metric(6 * $scale, 4 * $scale),
            ],
            'payments' => array_map(fn ($m) => [
                'code' => $m['code'],
                'name' => $m['name'],
                'orders' => (int) round(24 * $scale * $m['share']),
                'sum' => round($revenue * $m['share'], 2),
                'share' => round($m['share'] * 100, 1),
            ], $methods),
            'sales' => $sales,
            'active' => array_map(fn ($code, $icon, $count) => [
                'code' => $code,
                'name' => ['new' => 'Sifariş verildi', 'preparing' => 'Hazırlanır', 'warehouse_requested' => 'Anbarlara sorğu göndərildi',
                    'warehouses_assigned' => 'Anbarlar təyin olundu', 'courier_assigned' => 'Kuryer təyin olundu',
                    'sent' => 'Yola çıxdı', 'at_address' => 'Kuryer ünvandadır'][$code],
                'icon' => $icon,
                'count' => $count,
            ], array_keys(self::ACTIVE_STATUSES), self::ACTIVE_STATUSES, [3, 2, 4, 1, 2, 2, 0]),
            'top' => array_map(fn ($p, $i) => [
                'id' => 0, 'name' => $p[1], 'brand' => $p[0], 'image' => null,
                'quantity' => (int) round($p[2] * $scale / 5), 'sum' => round($p[2] * $p[3] * $scale / 5, 2),
            ], [['Dior', 'Sauvage Eau de Parfum', 42, 285], ['Chanel', 'Bleu de Chanel', 35, 310], ['Creed', 'Aventus', 28, 520],
                ['Tom Ford', 'Lost Cherry', 21, 465], ['Mancera', 'Cedrat Boise', 17, 226]], [0, 1, 2, 3, 4]),
            'searches' => ['total' => 412 * $scale, 'found' => 371 * $scale, 'empty' => 41 * $scale, 'top_empty' => [
                ['query' => 'nargis stringent', 'count' => 7], ['query' => 'dior savaj', 'count' => 5], ['query' => 'kilian angel share', 'count' => 4],
                ['query' => 'ermani kod', 'count' => 3], ['query' => 'baccarat rouge', 'count' => 2],
            ]],
            'online' => ['paid' => 9 * $scale, 'paid_sum' => 2350.0 * $scale, 'failed' => 2 * $scale, 'failed_sum' => 540.0 * $scale, 'pending' => 1, 'pending_sum' => 285.0, 'rate' => 81.8],
            'sources' => [
                ['code' => 'customer', 'name' => self::SOURCES['customer'], 'orders' => 11 * $scale, 'sum' => 2400.0 * $scale, 'share' => 45.8],
                ['code' => 'operator', 'name' => self::SOURCES['operator'], 'orders' => 9 * $scale, 'sum' => 1950.0 * $scale, 'share' => 37.5],
                ['code' => 'one_click', 'name' => self::SOURCES['one_click'], 'orders' => 4 * $scale, 'sum' => 833.0 * $scale, 'share' => 16.7],
            ],
            'customerSources' => [
                ['code' => 'crm', 'name' => Customer::SOURCES['crm'], 'count' => 3 * $scale],
                ['code' => 'website', 'name' => Customer::SOURCES['website'], 'count' => 2 * $scale],
                ['code' => 'easy_order', 'name' => Customer::SOURCES['easy_order'], 'count' => 1 * $scale],
            ],
            'finance' => [
                'cash' => 1840.5, 'bank' => 12650.0, 'couriers_owe' => 620.0, 'owe_couriers' => 35.0,
                'couriers' => [['id' => 0, 'name' => 'Fərid', 'balance' => 420.0], ['id' => 0, 'name' => 'Elvin', 'balance' => 200.0], ['id' => 0, 'name' => 'Rauf', 'balance' => -35.0]],
                'warehouses_debt' => 3480.0, 'warehouses' => 4, 'bonus' => 9215.4,
            ],
            'attention' => ['warehouse' => 3, 'easy_orders' => 2, 'credit' => 4, 'reviews' => 0, 'refunds' => 1, 'courier' => 0],
        ];
    }

    /** Ləğv edilməmiş sifarişlər */
    private function validOrders()
    {
        $cancelled = OrderStatus::where('code', 'cancelled')->value('id');

        return Order::query()->when($cancelled, fn ($q) => $q->where(
            fn ($w) => $w->where('orders.order_status_id', '!=', $cancelled)->orWhereNull('orders.order_status_id')
        ));
    }

    /** @return array{count: int, sum: float} */
    private function orderTotals(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $row = $this->validOrders()
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('COUNT(*) as orders, COALESCE(SUM(total), 0) as revenue')
            ->first();

        return ['count' => (int) $row->orders, 'sum' => round((float) $row->revenue, 2)];
    }

    /**
     * Təhvil verilmiş sifarişlər üzrə mənfəət.
     * Mal satışı = yekun − çatdırılma − hədiyyə bükməsi (endirim və ləğv olunan məhsullar yekunda artıq çıxılıb);
     * alış = anbardan götürülən hissələrin (ləğv/qaytarılma xaric) miqdar × alış qiyməti.
     *
     * @return array{sales: float, cost: float, profit: float, orders: int}
     */
    private function profit(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $delivered = OrderStatus::where('code', 'delivered')->value('id');
        if (!$delivered) {
            return ['sales' => 0.0, 'cost' => 0.0, 'profit' => 0.0, 'orders' => 0];
        }
        $orders = Order::where('order_status_id', $delivered)->whereBetween('created_at', [$from, $to]);

        $sales = (clone $orders)
            ->selectRaw('COUNT(*) as orders, COALESCE(SUM(total - COALESCE(delivery_fee, 0) - COALESCE(gift_wrap_fee, 0)), 0) as sales')
            ->first();
        $cost = (float) DB::table('order_item_allocations')
            ->join('order_items', 'order_items.id', '=', 'order_item_allocations.order_item_id')
            ->whereIn('order_items.order_id', (clone $orders)->select('id'))
            ->whereNotIn('order_item_allocations.status', OrderItemAllocation::SUPPLY_INACTIVE)
            ->sum(DB::raw('order_item_allocations.quantity * order_item_allocations.unit_cost'));

        $salesSum = round((float) $sales->sales, 2);

        return [
            'sales' => $salesSum,
            'cost' => round($cost, 2),
            'profit' => round($salesSum - $cost, 2),
            'orders' => (int) $sales->orders,
        ];
    }

    /**
     * Cari dövr [başlanğıc, indi] və əvvəlki dövr [başlanğıc, eyni an].
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable, 2: CarbonImmutable, 3: CarbonImmutable}
     */
    private function range(string $period): array
    {
        $now = CarbonImmutable::now();

        return match ($period) {
            'week' => [$now->startOfWeek(CarbonInterface::MONDAY), $now, $now->startOfWeek(CarbonInterface::MONDAY)->subWeek(), $now->subWeek()],
            // ayın 31-i keçən ayda yoxdursa, keçən ayın sonuna qədər
            'month' => [$now->startOfMonth(), $now, $now->startOfMonth()->subMonthNoOverflow(), $now->subMonthNoOverflow()],
            default => [$now->startOfDay(), $now, $now->startOfDay()->subDay(), $now->subDay()],
        };
    }

    private function metric(float|int $value, float|int $previous): array
    {
        return [
            'value' => $value,
            'previous' => $previous,
            // əvvəlki dövr 0 və ya mənfidirsə faiz mənasızdır
            'change' => $previous > 0 ? round(($value - $previous) / $previous * 100, 1) : null,
        ];
    }
}
