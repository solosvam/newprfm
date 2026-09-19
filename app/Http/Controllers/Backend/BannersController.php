<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Banners;
use App\Services\SeoUrl;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class BannersController extends Controller
{
    public function index()
    {
        $banners = Banners::paginate(25);

        return view('backend.banners.list',[
            'banners'    => $banners
        ]);
    }

    public function create(Request $request)
    {
        $request->validate([
            'location' => 'required',
            'device'   => 'required',
            'image'    => 'nullable|image|max:10240',
        ], [
            'location.required' => 'Yerləşmə seçilməlidir.',
            'device.required'   => 'Cihaz növü seçilməlidir.',
            'image.image'       => 'Yüklənən fayl şəkil formatında olmalıdır.',
            'image.max'         => 'Şəklin həcmi maksimum 10 MB ola bilər.',
        ]);

        $banner = Banners::create([
            'location' => $request->location,
            'device'   => $request->device,
        ]);

        $banner->url = $this->saveBannerImage(
            $request->file('image'),
            $request->device,
            $request->location,
            $banner->id
        );

        $banner->save();

        return redirect()->back()
            ->with('success', 'Banner əlavə edildi!');
    }

    public function edit($id)
    {
        $banner = Banners::findOrFail($id);
        return view('backend.banners.edit',[
            'banner' => $banner
        ]);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'location' => 'required',
            'device'   => 'required',
            'image'    => 'nullable|image|max:10240',
        ], [
            'location.required' => 'Yerləşmə seçilməlidir.',
            'device.required'   => 'Cihaz növü seçilməlidir.',
            'image.image'       => 'Yüklənən fayl şəkil formatında olmalıdır.',
            'image.max'         => 'Şəklin həcmi maksimum 10 MB ola bilər.',
        ]);

        $banner = Banners::findOrFail($id);

        $banner->location = $request->location;
        $banner->device   = $request->device;
        $banner->active   = $request->active;

        if ($request->hasFile('image')) {

            $oldImage = public_path(
                'frontend/uploads/banners/' . $banner->url
            );

            if (File::exists($oldImage)) {
                File::delete($oldImage);
            }

            $banner->url = $this->saveBannerImage(
                $request->file('image'),
                $request->device,
                $request->location,
                $banner->id
            );
        }

        $banner->save();

        return redirect(route('admin.banner.list'))
            ->with('success', 'Banner məlumatları yeniləndi!');
    }

    private function saveBannerImage($file, $device, $location, $bannerId)
    {
        if ($device === 'web') {
            $width  = 1920;
            $height = 660;
        } elseif ($location === 'top') {
            $width  = 800;
            $height = 560;
        } else {
            $width  = 800;
            $height = 220;
        }

        $imageName = SeoUrl::generateImageName([
                'id'    => $bannerId,
                'title' => $location . '-' . $device
            ]) . '.webp';

        $path = public_path('frontend/uploads/banners/' . $imageName);

        $manager = ImageManager::usingDriver(Driver::class);

        $manager
            ->decode($file)
            ->cover($width, $height)
            ->save($path, quality: 82);

        return $imageName;
    }

}
