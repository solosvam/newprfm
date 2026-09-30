<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Order\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Ferrum Capital-ın API-si yoxdur — operator məlumatları pm.ferrumcapital.az formasına doldurur.
 * Bu controller Chrome extension-u (extensions/ferrum-filler) üçün sifariş məlumatlarını JSON verir.
 * Admin sessiyası + "ferrum" icazəsi; hər açılış sensitive_access_logs-a yazılır.
 */
class FerrumController extends Controller
{
    /** Vəsiqə şəkilləri (CreditProfileController::UPLOAD_PATH ilə eyni qovluq) */
    private const ID_CARD_PATH = 'frontend/uploads/customers/';

    /** {ref} — sifariş nömrəsi (PS260930000123) və ya id */
    public function order(Request $request, string $ref): JsonResponse
    {
        $ref = trim($ref);
        $order = Order::with([
            'customer.creditProfile', 'address', 'paymentMethod', 'status',
            'items.product.brand', 'items.variant.size', 'creditApplication.period',
        ])->where('order_no', strtoupper($ref))
            ->when(ctype_digit($ref), fn ($q) => $q->orWhere('id', (int) $ref))
            ->first();
        if (!$order) {
            return response()->json(['message' => 'Sifariş tapılmadı: '.$ref], 404);
        }
        // Yalnız taksit sifarişləri — başqa sifarişin həssas məlumatı (FİN, vəsiqə) ümumiyyətlə verilmir
        if ($order->paymentMethod?->code !== 'installment') {
            return response()->json(['message' => $order->order_no.' taksit sifarişi deyil ('
                .($order->paymentMethod?->name_az ?? $order->paymentMethod?->name ?? 'ödəniş üsulu yoxdur').'). Ferrum yalnız taksit sifarişləri üçündür.'], 422);
        }
        $this->logAccess($request, 'ferrum_order', $order->id);

        $customer = $order->customer;
        $profile = $customer?->creditProfile;
        $address = $order->address;
        $credit = $order->creditApplication;
        $idCard = fn (string $side) => $profile?->{'id_card_'.$side}
            ? route('admin.ferrum.id-card', ['order' => $order->id, 'side' => $side], false) : null; // nisbi: extension seçilmiş sayta qoşur (APP_URL fərqli ola bilər)

        $data = [
            'order' => [
                'id' => $order->id,
                'order_no' => $order->order_no,
                'created_at' => $order->created_at?->format('d.m.Y H:i'),
                'status' => $order->status?->name_az,
                'payment_method' => ['code' => $order->paymentMethod?->code, 'name' => $order->paymentMethod?->name_az ?? $order->paymentMethod?->name],
                'subtotal' => (float) $order->subtotal,
                'discount' => (float) $order->discount,
                'delivery_fee' => (float) ($order->delivery_fee ?? 0),
                'total' => (float) $order->total,
                'admin_url' => $order->customer_id ? route('admin.crm.order', [$order->customer_id, $order->id], false) : null,
            ],
            'credit' => $credit ? [
                'months' => $credit->period?->month,
                'interest_rate' => (float) $credit->interest_rate,
                'total' => (float) $credit->total,
                'monthly' => (float) $credit->monthly,
            ] : null,
            'customer' => [
                'name' => $customer?->name,
                'surname' => $customer?->surname,
                'father_name' => $profile?->father_name,
                'fin' => $profile?->fin ? strtoupper($profile->fin) : null,
                'id_card_series' => $profile?->id_card_series,
                'id_card_number' => $profile?->id_card_number,
                'mobile' => $customer?->mobile,
                'email' => $customer?->email,
                'gender' => $customer?->gender,
            ],
            'work' => [
                'workplace' => $profile?->workplace_name,
                'salary' => $profile?->salary !== null ? (float) $profile->salary : null,
            ],
            'relatives' => [
                ['name' => $profile?->relative_1_name, 'phone' => $profile?->relative_1_phone],
                ['name' => $profile?->relative_2_name, 'phone' => $profile?->relative_2_phone],
            ],
            'address' => $address ? [
                'city' => $address->city,
                'district' => $address->district,
                'address' => $address->address,
                'building' => $address->building,
                'entrance' => $address->entrance,
                'floor' => $address->floor,
                'apartment' => $address->apartment,
                'full' => collect([$address->city, $address->district, $address->address,
                    $address->building ? 'bina '.$address->building : null,
                    $address->entrance ? 'giriş '.$address->entrance : null,
                    $address->floor ? 'mərtəbə '.$address->floor : null,
                    $address->apartment ? 'mənzil '.$address->apartment : null])->filter()->implode(', '),
            ] : null,
            'id_card' => ['front' => $idCard('front'), 'back' => $idCard('back')],
            'items' => $order->items->filter(fn ($item) => $item->activeQuantity() > 0)->map(fn ($item) => [
                'brand' => $item->product?->brand?->name,
                'product' => $item->product?->name,
                'size' => $item->variant?->size?->name_az,
                'quantity' => $item->activeQuantity(),
                'unit_price' => (float) $item->unit_price,
                'total' => round($item->activeQuantity() * (float) $item->unit_price, 2),
            ])->values(),
        ];
        $data['missing'] = $this->missing($data);
        $data['warnings'] = array_values(array_filter([
            !$credit ? 'Sifarişə kredit müraciəti bağlı deyil — müddət və aylıq ödəniş yoxdur.' : null,
            $order->isCancelled() ? 'Sifariş ləğv olunub.' : null,
        ]));

        return response()->json($data)->header('Cache-Control', 'no-store');
    }

    /** Vəsiqə şəkli — extension Ferrum formasındakı fayl sahəsinə yükləmək üçün çəkir */
    public function idCard(Request $request, Order $order, string $side): BinaryFileResponse
    {
        abort_unless(in_array($side, ['front', 'back'], true), 404);
        abort_unless($order->paymentMethod?->code === 'installment', 422, 'Ferrum yalnız taksit sifarişləri üçündür.');
        $file = $order->customer?->creditProfile?->{'id_card_'.$side};
        $path = $file ? public_path(self::ID_CARD_PATH.basename($file)) : null;
        abort_unless($path && is_file($path), 404, 'Vəsiqə şəkli tapılmadı.');
        $this->logAccess($request, 'ferrum_id_card_'.$side, $order->id);

        return response()->file($path, ['Cache-Control' => 'no-store']);
    }

    /** Ferrum üçün mütləq lazım olan, amma boş sahələr */
    private function missing(array $d): array
    {
        $checks = [
            'Ad' => $d['customer']['name'], 'Soyad' => $d['customer']['surname'], 'Ata adı' => $d['customer']['father_name'],
            'FİN' => $d['customer']['fin'], 'Vəsiqə seriya/nömrə' => $d['customer']['id_card_series'] && $d['customer']['id_card_number'],
            'Telefon' => $d['customer']['mobile'],
            'İş yeri' => $d['work']['workplace'], 'Maaş' => $d['work']['salary'],
            'Qohum 1' => $d['relatives'][0]['name'] && $d['relatives'][0]['phone'],
            'Qohum 2' => $d['relatives'][1]['name'] && $d['relatives'][1]['phone'],
            'Vəsiqə (ön)' => $d['id_card']['front'],
            'Vəsiqə (arxa)' => \App\Models\Customer\CustomerCreditProfile::needsBackSide($d['customer']['id_card_series']) ? $d['id_card']['back'] : true,
            'Ünvan' => $d['address']['full'] ?? null,
        ];

        return array_keys(array_filter($checks, fn ($v) => $v === null || $v === '' || $v === false));
    }

    private function logAccess(Request $request, string $action, int $orderId): void
    {
        DB::table('sensitive_access_logs')->insert([
            'user_id' => (int) auth('admin')->id(), 'action' => $action,
            'subject_type' => 'order', 'subject_id' => $orderId,
            'ip' => $request->ip(), 'user_agent' => mb_substr((string) $request->userAgent(), 0, 255),
            'created_at' => now(),
        ]);
    }
}
