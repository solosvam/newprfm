<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Popup;
use App\Models\PopupStat;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * popup.js hadisələri: shown / click / close / dismiss → gün üzrə sayğac.
 * Daxil olmuş müştəri üçün bazada da saxlanılır (popup_dismissals) — başqa cihazda da çıxmasın:
 *  - dismiss ("Bir daha göstərmə");
 *  - shown — tezliyi "bir dəfə" olan popup (görüb, bir daha çıxmasın).
 * navigator.sendBeacon ilə gəlir (_token form sahəsində).
 */
class PopupController extends Controller
{
    public function event(Request $request, Popup $popup): Response
    {
        $type = $request->validate(['type' => ['required', Rule::in(array_keys(Popup::EVENTS))]])['type'];

        PopupStat::bump($popup->id, Popup::EVENTS[$type]);

        $hideForever = $type === 'dismiss' || ($type === 'shown' && $popup->frequency === 'once');
        if ($hideForever && ($customerId = auth()->id())) {
            DB::table('popup_dismissals')->insertOrIgnore([
                'popup_id' => $popup->id, 'customer_id' => $customerId, 'created_at' => now(),
            ]);
        }

        return response()->noContent();
    }
}
