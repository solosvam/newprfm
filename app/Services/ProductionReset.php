<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

/**
 * MÜVƏQQƏTİ: canlıya çıxmazdan əvvəl test məlumatını silmək (admin/reset, menyuda yoxdur). İş bitəndən sonra
 * bu sinif, ResetController, admin/reset ünvanı və görünüşü silinəcək (docs/gorulecek-isler.md).
 *
 * Bölmələr:
 *  - finance: kassa və bütün pul hərəkətləri (borc / alacaq / kuryer qalıqları hərəkətlərdən hesablanır — sıfırlanır);
 *    hesabların özü (kassa, bank, kuryer, anbar) qalır;
 *  - orders: sifarişlər və asan sifarişlər, onlara bağlı hər şey (məhsullar, statuslar, ödənişlər, anbar sorğuları və seçimləri,
 *    ləğvlər, kredit müraciətləri, bu sifarişlərə görə pul hərəkətləri); sifarişin müştəriyə verdiyi / xərclətdiyi bonus geri alınır,
 *    promokodların istifadə sayı azaldılır;
 *  - price_lists: anbarların price listləri, uyğunlaşdırmalar, brend bağlantıları və müvəqqəti yüklənmiş fayllar.
 */
class ProductionReset
{
    public const SECTIONS = [
        'finance' => 'Kassa və maliyyə axını: bütün pul hərəkətləri, borclar və alacaqlar, kuryer qalıqları',
        'orders' => 'Sifarişlər və asan sifarişlər (statuslar, ödənişlər, anbar sorğuları və seçimləri, kuryer mərhələləri ilə birlikdə)',
        'price_lists' => 'Price listlər, uyğunlaşdırmalar və yüklənmiş fayllar',
    ];

    /** Hər bölmədə neçə sətir silinəcək: bölmə => [cədvəl => say] */
    public function counts(): array
    {
        $count = fn (string $table, ?callable $where = null) => Schema::hasTable($table) ? ($where ? $where(DB::table($table)) : DB::table($table))->count() : 0;

        return [
            'finance' => ['Pul hərəkətləri' => $count('money_movements')],
            'orders' => [
                'Sifarişlər' => $count('orders'),
                'Onlardan asan sifariş' => $count('orders', fn ($q) => $q->where('one_click', true)),
                'Sifariş məhsulları' => $count('order_items'),
                'Ödənişlər' => $count('payments'),
                'Anbar sorğuları' => $count('warehouse_requests'),
                'Anbar seçimləri' => $count('order_item_allocations'),
                'Sifarişə bağlı bonus əməliyyatları' => $count('customer_bonus_transactions', fn ($q) => $q->whereIn('order_id', DB::table('orders')->select('id'))),
            ],
            'price_lists' => [
                'Price listlər' => $count('warehouse_price_lists'),
                'Sətirlər' => $count('warehouse_price_items'),
                'Yadda qalan uyğunluqlar' => $count('warehouse_price_matches'),
                'Brend bağlantıları' => $count('price_list_brand_matches'),
                'Müvəqqəti fayllar' => count(File::files($this->priceListDir())),
            ],
        ];
    }

    /** @param  list<string>  $sections */
    public function run(array $sections): void
    {
        DB::transaction(function () use ($sections) {
            if (in_array('orders', $sections, true)) {
                $this->orders();
            }
            if (in_array('finance', $sections, true)) {
                $this->deleteMovements(DB::table('money_movements'));
            }
            if (in_array('price_lists', $sections, true)) {
                DB::table('warehouse_price_items')->delete();
                DB::table('warehouse_price_lists')->delete();
                DB::table('warehouse_price_matches')->delete();
                DB::table('price_list_brand_matches')->delete();
            }
        });

        // Fayllar tranzaksiyadan kənarda (bazadakı silmə alınmasa, fayllar qalsın)
        if (in_array('price_lists', $sections, true)) {
            File::cleanDirectory($this->priceListDir());
        }
    }

    private function orders(): void
    {
        $orderIds = DB::table('orders')->pluck('id');
        $itemIds = DB::table('order_items')->whereIn('order_id', $orderIds)->pluck('id');
        $paymentIds = DB::table('payments')->whereIn('order_id', $orderIds)->pluck('id');
        $requestIds = DB::table('warehouse_requests')->whereIn('order_id', $orderIds)->pluck('id');
        $requestItemIds = DB::table('warehouse_request_items')->whereIn('warehouse_request_id', $requestIds)->pluck('id');
        $allocationIds = DB::table('order_item_allocations')->whereIn('order_item_id', $itemIds)->pluck('id');

        // Sifarişin bonusu: qazanılan / xərclənən geri qaytarılır (məbləğlər işarəlidir: qazanc +, xərc −)
        foreach (DB::table('customer_bonus_transactions')->whereIn('order_id', $orderIds)->selectRaw('customer_id, sum(amount) as total')->groupBy('customer_id')->get() as $row) {
            $balance = (float) DB::table('customers')->where('id', $row->customer_id)->value('bonus_balance');
            DB::table('customers')->where('id', $row->customer_id)->update(['bonus_balance' => max(0, round($balance - (float) $row->total, 2))]);
        }
        DB::table('customer_bonus_transactions')->whereIn('order_id', $orderIds)->delete();
        // Promokodun istifadə sayı: silinən sifarişlər qədər azalır
        foreach (DB::table('orders')->whereNotNull('promo_code_id')->selectRaw('promo_code_id, count(*) as total')->groupBy('promo_code_id')->get() as $row) {
            $used = (int) DB::table('promo_codes')->where('id', $row->promo_code_id)->value('used_count');
            DB::table('promo_codes')->where('id', $row->promo_code_id)->update(['used_count' => max(0, $used - (int) $row->total)]);
        }
        DB::table('customer_referrals')->whereIn('order_id', $orderIds)->update(['order_id' => null]);

        // Bu sifarişlərə görə pul hərəkətləri (kuryerin müştəridən aldığı, anbara ödəniş, onlayn ödəniş)
        $this->deleteMovements(DB::table('money_movements')->where(fn ($q) => $q->whereIn('order_id', $orderIds)->orWhereIn('order_item_allocation_id', $allocationIds)->orWhereIn('payment_id', $paymentIds)));

        // Uşaqdan valideynə doğru (xarici açarlar restrict-dir)
        $operationIds = DB::table('payment_operations')->whereIn('payment_id', $paymentIds)->pluck('id');
        DB::table('payment_refund_items')->whereIn('payment_operation_id', $operationIds)->delete();
        DB::table('order_item_cancellations')->whereIn('order_id', $orderIds)->delete();
        DB::table('payment_operations')->whereIn('id', $operationIds)->delete();
        DB::table('payment_items')->whereIn('payment_id', $paymentIds)->delete();
        DB::table('payments')->whereIn('id', $paymentIds)->delete();
        DB::table('allocation_status_logs')->whereIn('order_item_allocation_id', $allocationIds)->delete();
        DB::table('order_item_allocations')->whereIn('id', $allocationIds)->delete();
        DB::table('warehouse_offers')->whereIn('warehouse_request_item_id', $requestItemIds)->delete();
        DB::table('warehouse_request_items')->whereIn('id', $requestItemIds)->delete();
        DB::table('warehouse_requests')->whereIn('id', $requestIds)->delete();
        DB::table('credit_applications')->whereIn('order_id', $orderIds)->delete();
        DB::table('order_status_logs')->whereIn('order_id', $orderIds)->delete();
        DB::table('order_items')->whereIn('id', $itemIds)->delete();
        DB::table('orders')->whereIn('id', $orderIds)->delete();
    }

    /** Pul hərəkətləri: geri alma (reversal_of_id) eyni cədvələ istinad edir — əvvəl istinad boşaldılır, sonra silinir */
    private function deleteMovements($query): void
    {
        $ids = (clone $query)->pluck('id');
        DB::table('money_movements')->whereIn('reversal_of_id', $ids)->update(['reversal_of_id' => null]);
        DB::table('money_movements')->whereIn('id', $ids)->delete();
    }

    private function priceListDir(): string
    {
        File::ensureDirectoryExists($dir = storage_path('app/private/price-lists'));

        return $dir;
    }
}
