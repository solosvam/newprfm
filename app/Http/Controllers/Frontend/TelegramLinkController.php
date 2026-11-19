<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Customer\CustomerMessengerAccount;
use App\Services\Messenger\MessengerAccounts;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Profil → Telegram: müştəri botunu saytdan bağlama (birdəfəlik linklə botu açır) və ayırma */
class TelegramLinkController extends Controller
{
    public static function enabled(): bool
    {
        return (string) config('services.telegram.customer_bot_token') !== '' && (string) config('services.telegram.customer_bot_username') !== '';
    }

    public function connect(Request $request, MessengerAccounts $accounts): RedirectResponse
    {
        abort_unless(self::enabled(), 404);
        $token = $accounts->createLinkToken($request->user());

        return redirect()->away('https://t.me/'.config('services.telegram.customer_bot_username').'?start='.$token);
    }

    public function disconnect(Request $request, MessengerAccounts $accounts): RedirectResponse
    {
        $accounts->unlink($request->user(), CustomerMessengerAccount::TELEGRAM);

        return back()->with('success', __('profile_telegram_disconnected'));
    }
}
