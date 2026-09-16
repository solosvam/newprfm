<?php

namespace App\Http\Controllers\Admin\Product;

use App\Http\Controllers\Controller;
use App\Models\Product\Brands;
use App\Services\SeoUrl;
use Buglinjo\LaravelWebp\Webp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class BrandsController extends Controller
{
    public function index()
    {
        $brands = Brands::orderBy('name','asc')->paginate(25);

        return view('admin.product.brands.list',[
            'brands'    => $brands
        ]);
    }

    public function create(Request $request)
    {
        $brand = Brands::create([
            'name'  => $request->name
        ]);

        $webp = Webp::make($request->file('image'));
        $imageName = SeoUrl::generateImageName([
                    'id'    => $brand->id,
                    'title' => $brand->name
                ]).'.webp';

        $path = public_path('frontend/uploads/brands/' . $imageName);
        $webp->save($path);

        $brand->image = $imageName;
        $brand->save();

        return redirect()->back()->with('success', 'Brend əlavə edildi !');
    }

    public function edit($id)
    {
        $brand = Brands::findOrFail($id);
        return view('admin.product.brands.edit',[
            'brand' => $brand
        ]);
    }

    public function update(Request $request)
    {
        $brand = Brands::findOrFail($request->id);

        $brand->name = $request->name;
        $brand->active = $request->active;

        if($request->has('image')){
            $oldImage = public_path('frontend/uploads/brands/' . $brand->image);
            if (File::exists($oldImage)) {
                File::delete($oldImage);
            }

            $webp = Webp::make($request->file('image'));
            $imageName = SeoUrl::generateImageName([
                    'id'    => rand(100,999),
                    'title' => $request->name
                ]).'.webp';

            $path = public_path('frontend/uploads/brands/' . $imageName);
            $webp->save($path);

            $brand->image = $imageName;
        }

        $brand->save();

        return redirect(route('brand.list'))->with('success', 'Brend məlumatları yeniləndi!');
    }

}
