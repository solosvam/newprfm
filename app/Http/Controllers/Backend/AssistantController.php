<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Customer\Customer;
use App\Models\Order\Order;
use App\Models\Product\Product;
use App\Services\Crm\CreditProfileUpdater;
use App\Services\Crm\CustomerRegistration;
use App\Services\IdCard\IdCardReader;
use App\Services\ProductPosterData;
use App\Services\Search\ImageProductSearch;
use App\Services\Search\ProductQueryParser;
use App\Services\Search\ProductSearchLogger;
use App\Services\Search\ProductSearchNormalizer;
use App\Services\Search\ProductSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Operator yan paneli (extensions/ps-side) — WhatsApp-ın yanında dar ekran */
class AssistantController extends Controller
{

    public function index(): View
    {
        return view('backend.assistant.index', ['user' => auth('admin')->user()]);
    }

    /** WhatsApp söhbətinin nömrəsi ilə müştəri: 994501234567 / 0501234567 / +994 50 123 45 67 */
    public function customer(Request $request): JsonResponse
    {
        $digits = preg_replace('/\D+/', '', (string) $request->query('phone'));
        if (!preg_match('/^(?:994|0)?([1-9]\d{8})$/', $digits, $m)) {
            return response()->json(['valid' => false]); // xarici və ya natamam nömrə
        }
        $mobile = '994'.$m[1];

        $customer = Customer::with('creditProfile')->where('mobile', $mobile)->first();
        if (!$customer) {
            // CRM-də "Yeni müştəri" modalı nömrə yazılmış halda açılır (crm.js: ?new_customer=)
            return response()->json(['valid' => true, 'mobile' => $mobile, 'customer' => null, 'crm_url' => route('admin.crm.index', ['new_customer' => $mobile])]);
        }

        $orders = $customer->orders()->with('status')->latest()->limit(3)->get()
            ->map(fn (Order $order) => [
                'no' => $order->order_no,
                'total' => number_format((float) $order->total, 2, '.', ''),
                'status' => $order->status?->name_az,
                'date' => $order->created_at?->format('d.m.Y'),
                'url' => route('admin.crm.order', ['customer' => $customer->id, 'order' => $order->id]),
            ]);

        return response()->json([
            'valid' => true,
            'mobile' => $mobile,
            'customer' => [
                'id' => $customer->id,
                'fullname' => $customer->fullname,
                'bonus' => number_format((float) $customer->bonus_balance, 2, '.', ''),
                'credit_ready' => (bool) $customer->creditProfile?->isComplete(),
                'credit' => $this->creditData($customer),
                'orders_count' => $customer->orders()->count(),
                'url' => route('admin.crm.customer', $customer->id),
            ],
            'orders' => $orders,
        ]);
    }

    /**
     * "Ətri axtar": WhatsApp-da seçilmiş mətn ("dior savaj 100 lük neçəyədi") → məhsullar bütün ölçüləri ilə.
     * Saytın axtarışı (ProductSearchService — lüğət: search_aliases) istifadə olunur; ölçü rəqəmi ayrıca götürülür.
     */
    public function search(Request $request, ProductSearchService $search, ProductSearchLogger $logger): JsonResponse
    {
        ['query' => $query, 'size' => $size] = ProductQueryParser::parse((string) $request->query('q'));
        if (mb_strlen($query) < 2) {
            return response()->json(['query' => $query, 'size' => $size, 'interpreted' => null, 'results' => []]);
        }

        $found = $search->search($query, 8);
        $ids = array_column($found['results'], 'id');

        // operatorların axtarışları da "Nəticəsiz axtarışlar"a düşsün — lüğət buradan böyüyür
        $logger->record($query, $query, 'admin:'.auth('admin')->id(), null, $ids);

        $products = Product::query()
            ->whereIn('id', $ids)
            ->with([
                'brand:id,name',
                'type:id,name_az',
                'images' => fn ($images) => $images->limit(1),
                'variants' => fn ($variants) => $variants->where('active', 1)->with('size:id,name_az')->orderBy('price'),
            ])
            ->get()
            ->sortBy(fn (Product $product) => array_search($product->id, $ids))
            ->values();

        return response()->json([
            'query' => $query,
            'size' => $size,
            'interpreted' => $found['interpreted'] ?? null,
            'results' => $products->map(fn (Product $product) => [
                'id' => $product->id,
                'brand' => $product->brand?->name,
                'name' => $product->name,
                'type' => $product->type?->name_az,
                'image' => ($image = $product->images->first()) ? asset('frontend/uploads/products/'.$image->image) : null,
                'url' => route('product', $product->slug),
                'variants' => $product->variants->map(fn ($variant) => [
                    'id' => $variant->id,
                    'size' => $variant->size?->name_az ?? '—',
                    'price' => number_format((float) $variant->price, 2, '.', ''),
                    // müştəri "100" yazıbsa — "100 ml" variantı seçilmiş görünür
                    'match' => $size !== null && preg_replace('/\D+/', '', (string) $variant->size?->name_az) === $size,
                ])->values(),
            ]),
        ]);
    }

    /** @see ProductQueryParser::parse() — testlər və köhnə çağırışlar üçün */
    public static function parseQuery(string $text): array
    {
        return ProductQueryParser::parse($text);
    }

    /** Paneldə poster: məhsul siyahısındakı posterin eyni məlumatı (CRM icazəsi ilə) */
    public function poster(Product $product, ProductPosterData $poster): JsonResponse
    {
        $data = $poster->for($product);

        return $data
            ? response()->json($data)
            : response()->json(['message' => 'Poster üçün məhsul şəkli və ən azı bir aktiv ölçü olmalıdır.'], 422);
    }

    /** Paneldən yeni müştəri (CRM-dəki "Yeni müştəri" ilə eyni qaydalar) — JSON; səhvlər 422 ilə */
    public function storeCustomer(Request $request, CustomerRegistration $registration): JsonResponse
    {
        $request->merge(['mobile' => CustomerRegistration::normalizeMobile($request->input('mobile'))]);
        $data = $request->validate(CustomerRegistration::rules(), CustomerRegistration::messages(), CustomerRegistration::attributes());

        ['customer' => $customer, 'sms' => $sms] = $registration->register($data, $request->boolean('send_password'));

        return response()->json([
            'id' => $customer->id,
            'mobile' => $customer->mobile,
            'sms' => $sms,
            'message' => 'Müştəri yaradıldı: '.$customer->fullname.'.'.match ($sms) {
                true => ' Şifrə SMS ilə göndərildi.',
                false => ' SMS göndərilmədi — CRM-də "Şifrəni sıfırla" ilə göndərin.',
                default => '',
            },
        ], 201);
    }

    /** Panelin kredit profili forması üçün mövcud məlumat (vəsiqə şəkilləri — önizləmə) */
    private function creditData(Customer $customer): array
    {
        $profile = $customer->creditProfile;
        $image = fn (?string $file) => $file ? asset('frontend/uploads/customers/'.basename($file)) : null;

        return [
            'father_name' => $profile?->father_name,
            'fin' => $profile?->fin,
            'id_card_series' => $profile?->id_card_series,
            'id_card_number' => $profile?->id_card_number,
            'relative_1_name' => $profile?->relative_1_name,
            'relative_1_phone' => $profile?->relative_1_phone,
            'relative_2_name' => $profile?->relative_2_name,
            'relative_2_phone' => $profile?->relative_2_phone,
            'workplace_name' => $profile?->workplace_name,
            'salary' => $profile?->salary !== null ? (string) $profile->salary : null,
            'id_card_front' => $image($profile?->id_card_front),
            'id_card_back' => $image($profile?->id_card_back),
        ];
    }

    /** WhatsApp-dan gələn vəsiqə şəkli (kəsilmiş) → OCR: FİN, ata adı, seriya/nömrə + ad uyğunluğu */
    public function creditOcr(Customer $customer, Request $request, IdCardReader $reader): JsonResponse
    {
        return $reader->read($request, $customer);
    }

    /** Paneldən kredit profili (CRM-dəki "Kredit profili" ilə eyni qaydalar) — JSON; səhvlər 422 ilə */
    public function creditProfile(Customer $customer, Request $request, CreditProfileUpdater $updater): JsonResponse
    {
        $profile = $updater->update($customer, $request);
        $customer->setRelation('creditProfile', $profile);

        return response()->json([
            'message' => 'Kredit profili yadda saxlanıldı.',
            'complete' => $profile->isComplete(),
            'credit' => $this->creditData($customer),
        ]);
    }

    /** WhatsApp-da şəklə sağ klik → "Ətri şəkildən axtar": Google Vision (web + mətn) → axtarış sorğusu */
    public function searchImage(Request $request, ImageProductSearch $images): JsonResponse
    {
        $request->validate(['image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192']]);

        // 5xx yox: Cloudflare 502/503/504 cavabını öz səhifəsi ilə əvəz edir, mesaj itir
        if (!$images->isConfigured()) {
            return response()->json(['message' => 'Şəkil tanıma qurulmayıb (Google Vision açarı yoxdur).'], 422);
        }

        try {
            return response()->json($images->find(file_get_contents($request->file('image')->getRealPath())));
        } catch (\Throwable $e) {
            report($e);

            return response()->json(array_filter([
                'message' => 'Şəkil tanınmadı — yenidən cəhd edin.',
                'debug' => config('app.debug') ? get_class($e).': '.$e->getMessage() : null,
            ]), 422);
        }
    }
}
