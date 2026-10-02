<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Customer\CustomerReferral;
use App\Services\Referral\ReferralService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

class ReferralController extends Controller
{
    /** Profil → Dostunu dəvət et */
    public function index(ReferralService $referrals)
    {
        $settings = $referrals->settings();
        // Proqram dayandırılsa da səhifə açılır: link bloklanır, köhnə dəvətlər və statistika görünür

        $customer = auth()->user();
        $blockReason = $referrals->blockReason($customer);

        $invites = $customer->referrals()
            ->with(['invitee' => fn ($q) => $q->select('id', 'name', 'surname')->withCount('orders')])
            ->latest()
            ->paginate(20);

        $stats = [
            'invited' => $customer->referrals()->count(),
            'rewarded' => $customer->referrals()->where('status', CustomerReferral::STATUS_REWARDED)->count(),
            'earned' => (float) $customer->referrals()->where('status', CustomerReferral::STATUS_REWARDED)->sum('referrer_amount'),
        ];

        return view('frontend.referral', [
            'settings' => $settings,
            'blockReason' => $blockReason,
            'link' => $blockReason === null ? $referrals->linkFor($customer) : null,
            'code' => $blockReason === null ? $referrals->codeFor($customer) : null,
            'shareText' => $settings->shareText(app()->getLocale()),
            'limitReached' => $referrals->limitReached($customer),
            'invites' => $invites,
            'stats' => $stats,
        ]);
    }

    /** Dəvət linki: /r/{code} — kod cookie-yə yazılır, qeydiyyat səhifəsinə yönləndirilir */
    public function track(Request $request, string $code, ReferralService $referrals)
    {
        $settings = $referrals->settings();
        $code = $referrals->normalize($code);

        if (!$settings->enabled() || !$referrals->isUsableCode($code)) {
            return redirect()->route('home');
        }

        if (auth()->check()) {
            return redirect()->route('home');
        }

        Cookie::queue(ReferralService::COOKIE, $code, $settings->cookieDays() * 24 * 60);

        return redirect()->route('front.register', ['ref' => $code]);
    }
}
