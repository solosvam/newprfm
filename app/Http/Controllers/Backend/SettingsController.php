<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function index(): View
    {
        $bannerSizes = [];

        foreach (Setting::BANNER_DIMENSIONS as $key => [$defaultWidth, $defaultHeight]) {
            $bannerSizes[$key] = [
                'width' => Setting::valueOf("{$key}_width", $defaultWidth),
                'height' => Setting::valueOf("{$key}_height", $defaultHeight),
            ];
        }

        return view('backend.settings.index', [
            'bonusPercent' => Setting::valueOf('order_bonus_percent', 5),
            'registrationBonusEnabled' => (bool) Setting::valueOf('registration_bonus_enabled', 1),
            'registrationBonusAmount' => Setting::valueOf('registration_bonus_amount', 10),
            'bannerSizes' => $bannerSizes,
            'deliveryMode' => Setting::valueOf('delivery_mode','free'),
            'deliveryFee' => Setting::valueOf('delivery_fee',0),
            'freeDeliveryFrom' => Setting::valueOf('free_delivery_from',0),
            'orderTermsUrl' => Setting::valueOf('order_terms_url', ''),
            'creditTermsUrl' => Setting::valueOf('credit_terms_url', ''),
            'giftWrapMode' => Setting::valueOf('gift_wrap_mode', 'free'),
            'giftWrapFee' => Setting::valueOf('gift_wrap_fee', 0),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $rules = [
            'order_bonus_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'registration_bonus_enabled' => ['required', 'boolean'],
            'registration_bonus_amount' => ['required', 'numeric', 'min:0', 'max:10000'],
            'order_terms_url' => ['nullable', 'url:http,https', 'max:2048'],
            'credit_terms_url' => ['nullable', 'url:http,https', 'max:2048'],
            'delivery_mode' => ['required', 'in:free,paid,threshold'],
            'gift_wrap_mode' => ['required', 'in:free,paid'],
            'gift_wrap_fee' => ['required_if:gift_wrap_mode,paid', 'nullable', 'numeric', 'min:0'],
            'delivery_fee' => ['required_if:delivery_mode,paid,threshold', 'nullable','numeric','min:0'],
            'free_delivery_from' => ['required_if:delivery_mode,threshold','nullable','numeric','gt:0'],
        ];

        foreach (array_keys(Setting::BANNER_DIMENSIONS) as $key) {
            $rules["{$key}_width"] = ['required', 'integer', 'min:1', 'max:10000'];
            $rules["{$key}_height"] = ['required', 'integer', 'min:1', 'max:10000'];
        }

        $data = $request->validate($rules);

        foreach ($data as $key => $value) {
            Setting::set($key, in_array($key, ['order_terms_url', 'credit_terms_url'], true) ? ($value ?? '') : ($value ?? 0));
        }

        return back()->with('success', 'Ayarlar yeniləndi!');
    }
}
