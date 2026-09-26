<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Banners;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\Rule;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class BannersController extends Controller
{
    private const LOCALES = ['az', 'en', 'ru'];

    public function index()
    {
        return view('backend.banners.list', [
            'banners' => Banners::orderByDesc('id')->get(),
        ]);
    }

    public function create(Request $request)
    {
        $data = $this->validateBanner($request, true);

        $banner = Banners::create([
            'location' => $data['location'],
            'device' => $data['device'],
            'active' => 1,
            'link_url' => $data['link_url'] ?? null,
        ]);

        foreach (self::LOCALES as $locale) {
            $banner->{'url_' . $locale} = $this->saveBannerImage(
                $request->file('image_' . $locale),
                $banner->device,
                $banner->location,
                $banner->id,
                $locale
            );
        }

        $banner->save();

        return redirect()->route('admin.banner.list')
            ->with('success', 'Banner üç dildə əlavə edildi!');
    }

    public function edit($id)
    {
        return view('backend.banners.edit', [
            'banner' => Banners::findOrFail($id),
        ]);
    }

    public function update(Request $request, $id)
    {
        $banner = Banners::findOrFail($id);
        $data = $this->validateBanner($request, false, $banner);

        $banner->location = $data['location'];
        $banner->device = $data['device'];
        $banner->active = $data['active'];
        $banner->link_url = $data['link_url'] ?? null;

        $oldImages = [];

        foreach (self::LOCALES as $locale) {
            if (!$request->hasFile('image_' . $locale)) {
                continue;
            }

            $column = 'url_' . $locale;
            $oldImages[] = $banner->{$column};

            $banner->{$column} = $this->saveBannerImage(
                $request->file('image_' . $locale),
                $banner->device,
                $banner->location,
                $banner->id,
                $locale
            );
        }

        $banner->save();

        // Only remove replaced files if no banner still references them.
        foreach (array_unique(array_filter($oldImages)) as $filename) {
            if (Banners::where('url_az', $filename)
                ->orWhere('url_en', $filename)
                ->orWhere('url_ru', $filename)
                ->exists()) {
                continue;
            }

            File::delete(public_path('frontend/uploads/banners/' . basename($filename)));
        }

        return redirect()->route('admin.banner.list')
            ->with('success', 'Banner məlumatları yeniləndi!');
    }

    private function validateBanner(Request $request, bool $creating, ?Banners $banner = null): array
    {
        $rules = [
            'location' => ['required', Rule::in(['top', 'bottom'])],
            'device' => ['required', Rule::in(['mobile', 'web'])],
            'link_url' => ['nullable', 'url', 'max:2048', function ($attribute, $value, $fail) {
                if ($value && !in_array(strtolower(parse_url($value, PHP_URL_SCHEME) ?? ''), ['http', 'https'], true)) {
                    $fail('Link http və ya https ilə başlamalıdır.');
                }
            }],
        ];

        if (!$creating) {
            $rules['active'] = ['required', Rule::in(['0', '1'])];
        }

        foreach (self::LOCALES as $locale) {
            $rules['image_' . $locale] = [
                $creating ? 'required' : 'nullable',
                'image',
                'max:10240',
            ];
        }

        return $request->validate($rules, [
            'location.required' => 'Yerləşmə seçilməlidir.',
            'device.required' => 'Cihaz növü seçilməlidir.',
            'image_az.required' => 'Azərbaycan dili üçün şəkil seçin.',
            'image_en.required' => 'İngilis dili üçün şəkil seçin.',
            'image_ru.required' => 'Rus dili üçün şəkil seçin.',
            'image_*.image' => 'Yüklənən fayl şəkil formatında olmalıdır.',
            'image_*.max' => 'Şəklin həcmi maksimum 10 MB ola bilər.',
        ]);
    }

    private function saveBannerImage($file, string $device, string $location, int $bannerId, string $locale): string
    {
        [$width, $height] = Setting::bannerDimensions($device, $location);

        $imageName = $bannerId . '-' . $location . '-' . $device . '-' . $locale . '.webp';

        $directory = public_path('frontend/uploads/banners');
        File::ensureDirectoryExists($directory);

        $manager = ImageManager::usingDriver(Driver::class);

        $manager->decode($file)
            ->cover($width, $height)
            ->save($directory . '/' . $imageName, quality: 82);

        return $imageName;
    }
}
