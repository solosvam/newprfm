<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\GiftCard\GiftCard;
use App\Services\GiftCard\GiftCardService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/** Admin: virtual hədiyyə kartları — siyahı, tarixçə, müddətin uzadılması, pulun qaytarılması, qadağan sözlər */
class GiftCardsController extends Controller
{
    public function __construct(private GiftCardService $cards) {}

    public function index(Request $request): View
    {
        $status = (string) $request->query('status', '');
        $q = trim((string) $request->query('q', ''));

        $cards = GiftCard::with(['customer', 'usedOrder'])
            ->when($status === 'expired', fn ($query) => $query->where(fn ($w) => $w->where('status', GiftCard::EXPIRED)
                ->orWhere(fn ($a) => $a->where('status', GiftCard::ACTIVE)->where('expires_at', '<', now()))))
            ->when($status === GiftCard::ACTIVE, fn ($query) => $query->where('status', GiftCard::ACTIVE)->where('expires_at', '>=', now()))
            ->when($status !== '' && !in_array($status, ['expired', GiftCard::ACTIVE], true), fn ($query) => $query->where('status', $status))
            ->when($q !== '', function ($query) use ($q) {
                $code = $this->cards->normalize($q);
                $query->where(function ($w) use ($q, $code) {
                    if ($code !== '') {
                        $w->where('code', 'like', '%'.$code.'%');
                    }
                    $w->orWhereHas('customer', fn ($c) => $c->where('name', 'like', '%'.$q.'%')->orWhere('surname', 'like', '%'.$q.'%')->orWhere('mobile', 'like', '%'.$q.'%'));
                });
            })
            ->latest('id')->paginate(30)->withQueryString();

        // Müştərilərə borc: ödənilmiş, istifadə olunmamış, müddəti bitməmiş kartlar
        $outstanding = (float) GiftCard::where('status', GiftCard::ACTIVE)->where('expires_at', '>=', now())->sum('amount');
        $outstandingCount = GiftCard::where('status', GiftCard::ACTIVE)->where('expires_at', '>=', now())->count();
        $soldTotal = (float) GiftCard::whereNotNull('paid_at')->sum('amount');

        return view('backend.gift-cards.index', compact('cards', 'status', 'q', 'outstanding', 'outstandingCount', 'soldTotal') + [
        ]);
    }

    public function show(GiftCard $card): View
    {
        $card->load(['customer', 'usedOrder', 'events.user', 'payments']);

        return view('backend.gift-cards.show', ['card' => $card]);
    }

    public function extend(Request $request, GiftCard $card): RedirectResponse
    {
        $data = $request->validate(['expires_at' => ['required', 'date', 'after:today']], [
            'expires_at.after' => 'Yeni tarix bu gündən sonra olmalıdır.',
        ]);
        $this->cards->extend($card, Carbon::parse($data['expires_at']), (int) auth()->id());

        return back()->with('success', 'Kartın müddəti uzadıldı.');
    }

    public function refund(GiftCard $card): RedirectResponse
    {
        $this->cards->refund($card, (int) auth()->id());

        return back()->with('success', 'Kartın pulu karta qaytarıldı, kart bağlandı.');
    }
}
