<?php

namespace App\Services\Stock;

use App\Models\Order\Order;
use App\Models\Stock\GiftCard;
use App\Models\Stock\StockPurchase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Hədiyyə kartlarının stoku: alış (barkod aralığı ilə stoka giriş), satış qeydi və stoka qaytarma.
 * Hər dəyişiklik kartın tarixçəsinə yazılır (gift_card_events).
 */
class GiftCardStock
{
    /** Bir sətirdə ən çox kart (səhv aralıq minlərlə kart yaratmasın) */
    public const MAX_RANGE = 5000;

    /**
     * @param  array{purchased_at: string, supplier: ?string, note: ?string}  $data
     * @param  array<int, array{denomination: int, barcode_from: string, barcode_to: string, unit_cost: float|string}>  $lines
     */
    public function receive(array $data, array $lines, int $actor): StockPurchase
    {
        $this->ensure($lines !== [], 'lines', 'Ən azı bir sətir əlavə edin.');
        $ranges = [];
        foreach (array_values($lines) as $i => $line) {
            $field = 'lines.'.$i;
            $denomination = (int) $line['denomination'];
            [$from, $to] = [trim((string) $line['barcode_from']), trim((string) $line['barcode_to'])];
            $this->ensure(in_array($denomination, GiftCard::DENOMINATIONS, true), $field, 'Nominalı seçin.');
            $this->ensure(GiftCard::barcodeMatches($from, $denomination) && GiftCard::barcodeMatches($to, $denomination), $field,
                GiftCard::label($denomination).': barkod 13 rəqəm olmalı və '.substr(GiftCard::barcodeFor($denomination, 1), 0, 7).' ilə başlamalıdır.');
            $this->ensure($to >= $from, $field, 'Son barkod birincidən kiçik ola bilməz.');
            $quantity = (int) substr($to, 7) - (int) substr($from, 7) + 1;
            $this->ensure($quantity <= self::MAX_RANGE, $field, 'Bir sətirdə ən çox '.self::MAX_RANGE.' kart ola bilər.');
            foreach ($ranges as [$otherFrom, $otherTo]) {
                $this->ensure($to < $otherFrom || $from > $otherTo, $field, 'Barkod aralıqları üst-üstə düşür.');
            }
            $taken = GiftCard::whereBetween('barcode', [$from, $to])->orderBy('barcode')->limit(3)->pluck('barcode');
            $this->ensure($taken->isEmpty(), $field, 'Bu barkodlar artıq stokdadır: '.$taken->implode(', ').'…');
            $ranges[] = [$from, $to, $denomination, $quantity, round((float) $line['unit_cost'], 2)];
        }

        return DB::transaction(function () use ($data, $ranges, $actor) {
            $purchase = StockPurchase::create([
                'purchased_at' => $data['purchased_at'], 'supplier' => $data['supplier'] ?? null, 'note' => $data['note'] ?? null,
                'total_cost' => collect($ranges)->sum(fn ($r) => $r[3] * $r[4]), 'created_by' => $actor,
            ]);
            $now = now();
            foreach ($ranges as [$from, $to, $denomination, $quantity, $unitCost]) {
                $item = $purchase->items()->create([
                    'item_type' => 'gift_card', 'denomination' => $denomination, 'barcode_from' => $from, 'barcode_to' => $to,
                    'quantity' => $quantity, 'unit_cost' => $unitCost,
                ]);
                $first = (int) substr($from, 7);
                foreach (array_chunk(range($first, $first + $quantity - 1), 500) as $chunk) {
                    GiftCard::insert(array_map(fn ($seq) => [
                        'barcode' => GiftCard::barcodeFor($denomination, $seq), 'denomination' => $denomination, 'status' => GiftCard::IN_STOCK,
                        'stock_purchase_item_id' => $item->id, 'created_at' => $now, 'updated_at' => $now,
                    ], $chunk));
                }
                DB::table('gift_card_events')->insertUsing(['gift_card_id', 'event', 'user_id', 'created_at'],
                    GiftCard::where('stock_purchase_item_id', $item->id)->selectRaw("id, 'received', ?, ?", [$actor, $now]));
            }

            return $purchase;
        });
    }

    /** Satış qeydi (kart müştəriyə verildi). Sifariş nömrəsi istəyə görə. */
    public function markSold(GiftCard $card, ?string $orderNo, ?string $note, int $actor): void
    {
        $order = null;
        if ($orderNo = trim((string) $orderNo)) {
            $order = Order::where('order_no', $orderNo)->orWhere('id', ctype_digit($orderNo) ? (int) $orderNo : 0)->first();
            $this->ensure($order !== null, 'order_no', 'Sifariş tapılmadı: '.$orderNo);
        }
        DB::transaction(function () use ($card, $order, $note, $actor) {
            $locked = GiftCard::lockForUpdate()->findOrFail($card->id);
            $this->ensure($locked->status === GiftCard::IN_STOCK, 'status', 'Kart stokda deyil — artıq satılıb.');
            $locked->update(['status' => GiftCard::SOLD, 'sold_at' => now(), 'sold_order_id' => $order?->id]);
            $locked->events()->create(['event' => 'sold', 'order_id' => $order?->id, 'note' => $note, 'user_id' => $actor, 'created_at' => now()]);
        });
    }

    /** Kart geri gəldi (məs. satış səhv qeyd olunub) — yenidən stokda. Səbəb məcburidir. */
    public function returnToStock(GiftCard $card, string $note, int $actor): void
    {
        DB::transaction(function () use ($card, $note, $actor) {
            $locked = GiftCard::lockForUpdate()->findOrFail($card->id);
            $this->ensure($locked->status === GiftCard::SOLD, 'status', 'Kart artıq stokdadır.');
            $locked->update(['status' => GiftCard::IN_STOCK, 'sold_at' => null, 'sold_order_id' => null]);
            $locked->events()->create(['event' => 'returned', 'note' => $note, 'user_id' => $actor, 'created_at' => now()]);
        });
    }

    private function ensure(bool $condition, string $field, string $message): void
    {
        if (!$condition) {
            throw ValidationException::withMessages([$field => $message]);
        }
    }
}
