<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Banners;
use App\Services\SeoUrl;
use Buglinjo\LaravelWebp\Webp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class BannersController extends Controller
{
    public function index()
    {
        $banners = Banners::paginate(25);

        return view('admin.banners.list',[
            'banners'    => $banners
        ]);
    }

    public function create(Request $request)
    {
        $banner = Banners::create([
            'location'  => $request->location,
            'device'  => $request->device,
        ]);

        $webp = Webp::make($request->file('image'));
        $imageName = SeoUrl::generateImageName([
                    'id'    => $banner->id,
                    'title' => $banner->location
                ]).'.webp';

        $path = public_path('frontend/uploads/banners/' . $imageName);
        $webp->save($path);

        $banner->url = $imageName;
        $banner->save();

        return redirect()->back()->with('success', 'Banner əlavə edildi !');
    }

    public function edit($id)
    {
        $banner = Banners::findOrFail($id);
        return view('admin.banners.edit',[
            'banner' => $banner
        ]);
    }

    public function update(Request $request)
    {
        $banner = Banners::findOrFail($request->id);

        $banner->location = $request->location;
        $banner->device = $request->device;
        $banner->active = $request->active;

        if($request->has('image')){
            $oldImage = public_path('frontend/uploads/banners/' . $banner->url);
            if (File::exists($oldImage)) {
                File::delete($oldImage);
            }

            $webp = Webp::make($request->file('image'));
            $imageName = SeoUrl::generateImageName([
                    'id'    => rand(100,999),
                    'title' => $request->location
                ]).'.webp';

            $path = public_path('frontend/uploads/banners/' . $imageName);
            $webp->save($path);

            $banner->url = $imageName;
        }

        $banner->save();

        return redirect(route('banner.list'))->with('success', 'Banner məlumatları yeniləndi!');
    }

}
