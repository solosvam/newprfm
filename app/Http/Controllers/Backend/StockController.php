<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Stock\GiftCard;
use App\Models\Stock\StockPurchase;
use App\Services\Stock\GiftCardStock;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Stok → Alışlar və Hədiyyə kartları. Alış barkod aralığı ilə kartları stoka salır,
 * kartın səhifəsində satış qeydi və stoka qaytarma (tarixçə ilə).
 */
class StockController extends Controller
{
    public function purchases(): View
    {
        return view('backend.stock.purchases', [
            'purchases' => StockPurchase::with(['items', 'creator'])->orderByDesc('purchased_at')->orderByDesc('id')->get(),
        ]);
    }

    public function storePurchase(Request $request, GiftCardStock $stock): RedirectResponse
    {
        $data = $request->validate([
            'purchased_at' => ['required', 'date'],
            'supplier' => ['nullable', 'string', 'max:120'],
            'note' => ['nullable', 'string', 'max:500'],
            'lines' => ['required', 'array', 'min:1', 'max:20'],
            'lines.*.denomination' => ['required', 'integer'],
            'lines.*.barcode_from' => ['required', 'digits:13'],
            'lines.*.barcode_to' => ['required', 'digits:13'],
            'lines.*.unit_cost' => ['required', 'numeric', 'min:0', 'max:100000'],
        ], [], ['purchased_at' => 'tarix', 'lines.*.barcode_from' => 'ilk barkod', 'lines.*.barcode_to' => 'son barkod', 'lines.*.unit_cost' => 'bir kartın qiyməti']);

        $purchase = $stock->receive($data, $data['lines'], (int) auth('admin')->id());

        return redirect()->route('admin.stock.purchases.show', $purchase)
            ->with('success', $purchase->items->sum('quantity').' kart stoka daxil edildi.');
    }

    public function showPurchase(StockPurchase $purchase): View
    {
        $purchase->load(['items', 'creator']);
        $sold = GiftCard::whereIn('stock_purchase_item_id', $purchase->items->pluck('id'))->where('status', GiftCard::SOLD)
            ->selectRaw('stock_purchase_item_id, count(*) as c')->groupBy('stock_purchase_item_id')->pluck('c', 'stock_purchase_item_id');

        return view('backend.stock.purchase', ['purchase' => $purchase, 'sold' => $sold]);
    }

    /** Kartlar: nominal / status / barkod filtri, nominallar üzrə qalıq */
    public function cards(Request $request): View|RedirectResponse
    {
        $f = $request->validate([
            'denomination' => ['nullable', 'integer'], 'status' => ['nullable', 'in:'.implode(',', array_keys(GiftCard::STATUSES))],
            'q' => ['nullable', 'string', 'max:20'],
        ]);
        // Tam barkod oxudulubsa — birbaşa kartın səhifəsi (skaner)
        if (preg_match('/^\d{13}$/', trim((string) ($f['q'] ?? ''))) && ($card = GiftCard::where('barcode', trim($f['q']))->first())) {
            return redirect()->route('admin.stock.cards.show', $card);
        }

        $summary = GiftCard::selectRaw('denomination, status, count(*) as c')->groupBy('denomination', 'status')->get()
            ->groupBy(fn ($row) => (int) $row->denomination)
            ->map(fn ($rows) => $rows->pluck('c', 'status')->map(fn ($c) => (int) $c));

        $cards = GiftCard::with('soldOrder')
            ->when($f['denomination'] ?? null, fn ($q, $d) => $q->where('denomination', $d))
            ->when($f['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when(trim((string) ($f['q'] ?? '')), fn ($q, $text) => $q->where('barcode', 'like', '%'.addcslashes($text, '%_\\').'%'))
            ->orderBy('barcode')->paginate(50)->withQueryString();

        return view('backend.stock.cards', ['cards' => $cards, 'summary' => $summary, 'filters' => $f]);
    }

    public function showCard(GiftCard $card): View
    {
        $card->load(['events.user', 'events.order', 'soldOrder', 'purchaseItem.purchase']);

        return view('backend.stock.card', ['card' => $card]);
    }

    public function sell(Request $request, GiftCard $card, GiftCardStock $stock): RedirectResponse
    {
        $data = $request->validate(['order_no' => ['nullable', 'string', 'max:30'], 'note' => ['nullable', 'string', 'max:255']]);
        $stock->markSold($card, $data['order_no'] ?? null, $data['note'] ?? null, (int) auth('admin')->id());

        return back()->with('success', GiftCard::label($card->denomination).' '.$card->barcode.' satıldı.');
    }

    public function returnToStock(Request $request, GiftCard $card, GiftCardStock $stock): RedirectResponse
    {
        $data = $request->validate(['note' => ['required', 'string', 'max:255']], ['note.required' => 'Səbəbi yazın.']);
        $stock->returnToStock($card, $data['note'], (int) auth('admin')->id());

        return back()->with('success', 'Kart stoka qaytarıldı.');
    }
}
