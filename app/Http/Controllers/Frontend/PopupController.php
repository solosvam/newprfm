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
 * dismiss ("Bir daha göstərmə") daxil olmuş müştəri üçün bazada da saxlanılır — başqa cihazda da çıxmasın.
 * navigator.sendBeacon ilə gəlir (_token form sahəsində).
 */
class PopupController extends Controller
{
    public function event(Request $request, Popup $popup): Response
    {
        $type = $request->validate(['type' => ['required', Rule::in(array_keys(Popup::EVENTS))]])['type'];

        PopupStat::bump($popup->id, Popup::EVENTS[$type]);

        if ($type === 'dismiss' && ($customerId = auth()->id())) {
            DB::table('popup_dismissals')->insertOrIgnore([
                'popup_id' => $popup->id, 'customer_id' => $customerId, 'created_at' => now(),
            ]);
        }

        return response()->noContent();
    }
}
