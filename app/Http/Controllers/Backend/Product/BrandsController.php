<?php

namespace App\Http\Controllers\Backend\Product;

use App\Http\Controllers\Controller;
use App\Models\Product\Brand;
use App\Services\SeoUrl;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use Illuminate\Support\Facades\File;

class BrandsController extends Controller
{
    public function index()
    {
        $brands = Brand::orderBy('name','asc')->paginate(25);

        return view('backend.product_menu.brands.list',[
            'brands'    => $brands
        ]);
    }

    public function create(Request $request)
    {
        $request->validate([
            'name'  => 'required|string|max:255',
            'slug' => ['nullable', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('brands', 'slug')],
            'image' => 'required|image|max:10240',
        ], [
            'name.required'  => 'Brend adı daxil edilməlidir.',
            'image.required' => 'Brend şəkli seçilməlidir.',
            'image.image'    => 'Yüklənən fayl şəkil formatında olmalıdır.',
            'image.max'      => 'Şəklin həcmi maksimum 10 MB ola bilər.',
        ]);

        $brand = Brand::create([
            'name' => $request->name,
            'slug' => $request->filled('slug') ? $request->slug : null,
        ]);

        $brand->image = $this->saveBrandImage(
            $request->file('image'),
            $brand
        );

        $brand->save();

        return redirect()->back()
            ->with('success', 'Brend əlavə edildi!');
    }

    public function edit($id)
    {
        $brand = Brand::findOrFail($id);
        return view('backend.product_menu.brands.edit',[
            'brand' => $brand
        ]);
    }

    public function update(Request $request)
    {
        $request->validate([
            'id'     => 'required',
            'name'   => 'required|string|max:255',
            'slug'   => ['nullable', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('brands', 'slug')->ignore($request->id)],
            'image'  => 'nullable|image|max:10240',
            'active' => 'required',
        ], [
            'id.required'     => 'Brend ID tapılmadı.',
            'name.required'   => 'Brend adı daxil edilməlidir.',
            'image.image'     => 'Yüklənən fayl şəkil formatında olmalıdır.',
            'image.max'       => 'Şəklin həcmi maksimum 10 MB ola bilər.',
            'active.required' => 'Status seçilməlidir.',
        ]);

        $brand = Brand::findOrFail($request->id);

        $brand->name   = $request->name;
        if ($request->filled('slug')) {
            $brand->slug = $request->slug;
        }
        $brand->active = $request->active;

        if ($request->hasFile('image')) {

            $oldImage = public_path(
                'frontend/uploads/brands/' . $brand->image
            );

            if ($brand->image && File::exists($oldImage)) {
                File::delete($oldImage);
            }

            $brand->image = $this->saveBrandImage(
                $request->file('image'),
                $brand
            );
        }

        $brand->save();

        return redirect(route('brand.list'))
            ->with('success', 'Brend məlumatları yeniləndi!');
    }

    private function saveBrandImage($file, $brand)
    {
        $imageName = SeoUrl::generateImageName([
                'id'    => $brand->id,
                'title' => $brand->name
            ]) . '.webp';

        $path = public_path('frontend/uploads/brands/' . $imageName);

        $manager = ImageManager::usingDriver(Driver::class);

        $manager
            ->decode($file)
            ->cover(600, 600)
            ->save($path, quality: 82);

        return $imageName;
    }

}
