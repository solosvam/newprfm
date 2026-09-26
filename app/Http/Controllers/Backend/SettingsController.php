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
            'bannerSizes' => $bannerSizes,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $rules = [
            'order_bonus_percent' => ['required', 'numeric', 'min:0', 'max:100'],
        ];

        foreach (array_keys(Setting::BANNER_DIMENSIONS) as $key) {
            $rules["{$key}_width"] = ['required', 'integer', 'min:1', 'max:10000'];
            $rules["{$key}_height"] = ['required', 'integer', 'min:1', 'max:10000'];
        }

        $data = $request->validate($rules);

        foreach ($data as $key => $value) {
            Setting::set($key, $value);
        }

        return back()->with('success', 'Ayarlar yeniləndi!');
    }
}
