<?php

namespace App\Http\Controllers\Frontend;

use App\Exceptions\GiftCardException;
use App\Http\Controllers\Controller;
use App\Models\GiftCard\GiftCard;
use App\Services\GiftCard\GiftCardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Virtual hədiyyə kartı: alış səhifəsi, kartın səhifəsi (paylaşılan link), səbətdə tətbiq.
 * Qaydalar — GiftCardService.
 */
class GiftCardController extends Controller
{
    public function __construct(private GiftCardService $cards) {}

    public function index(Request $request): View
    {
        $myCards = $request->user()
            ? GiftCard::where('customer_id', $request->user()->id)->where('status', '!=', GiftCard::PENDING)->latest('id')->limit(20)->get()
            : collect();

        return view('frontend.gift-card.index', [
            'amounts' => $this->cards->amounts(),
            'min' => GiftCardService::MIN, 'max' => GiftCardService::MAX, 'step' => GiftCardService::STEP,
            'messageMax' => GiftCardService::MESSAGE_MAX, 'senderMax' => GiftCardService::SENDER_MAX,
            'validMonths' => GiftCardService::VALID_MONTHS,
            'myCards' => $myCards,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'integer'],
            'message' => ['nullable', 'string', 'max:'.GiftCardService::MESSAGE_MAX],
            'sender_name' => ['nullable', 'string', 'max:'.GiftCardService::SENDER_MAX],
            'accept_terms' => ['accepted'],
        ], [
            'accept_terms.accepted' => __('gift_card_accept_terms_required'),
            'message.max' => __('gift_card_message_too_long', ['max' => GiftCardService::MESSAGE_MAX]),
        ]);

        $card = $this->cards->purchase($request->user(), $data + ['design' => 'standard']);

        return $this->redirectToPayment($card);
    }

    /** Kartın səhifəsi: kod yalnız ödənişdən sonra görünür. Link paylaşılır — giriş tələb olunmur. */
    public function show(Request $request, string $token): View
    {
        $card = GiftCard::where('share_token', $token)->firstOrFail();

        return view('frontend.gift-card.show', [
            'card' => $card,
            'isOwner' => (int) $request->user()?->id === (int) $card->customer_id,
            'shareUrl' => route('gift-card.show', $card->share_token),
        ]);
    }

    /** Ödənilməmiş kart üçün ödənişə yenidən başlamaq (yalnız alan müştəri) */
    public function pay(Request $request, string $token): RedirectResponse
    {
        $card = GiftCard::where('share_token', $token)->firstOrFail();
        abort_unless((int) $request->user()->id === (int) $card->customer_id, 403);

        return $this->redirectToPayment($card);
    }

    /** Səbətdə "Hədiyyə kartını tətbiq et" */
    public function apply(Request $request): JsonResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:30']]);
        try {
            $card = $this->cards->find($data['code']);
        } catch (GiftCardException $e) {
            session()->forget('gift_card_code');

            return response()->json(['message' => $e->getMessage()], 422);
        }
        session(['gift_card_code' => $card->code]);

        return response()->json(['code' => $card->formattedCode(), 'amount' => (float) $card->amount]);
    }

    public function remove(): \Illuminate\Http\Response
    {
        session()->forget('gift_card_code');

        return response()->noContent();
    }

    private function redirectToPayment(GiftCard $card): RedirectResponse
    {
        try {
            $url = $this->cards->startPayment($card, app()->getLocale());
        } catch (GiftCardException $e) {
            return redirect()->route('gift-card.show', $card->share_token)->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            report($e);

            return redirect()->route('gift-card.show', $card->share_token)->with('error', __('gift_card_payment_start_failed'));
        }

        return $url
            ? redirect()->away($url)
            : redirect()->route('gift-card.show', $card->share_token);
    }
}
