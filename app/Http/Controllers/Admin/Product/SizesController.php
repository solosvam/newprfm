<?php

namespace App\Http\Controllers\Admin\Product;

use App\Http\Controllers\Controller;
use App\Models\Product\Sizes;
use Illuminate\Http\Request;

class SizesController extends Controller
{
    public function index()
    {
        $sizes = Sizes::all();

        return view('admin.product.sizes.list',[
            'sizes'    => $sizes
        ]);
    }

    public function create(Request $request)
    {
        Sizes::create($request->all());
        return redirect()->back()->with('success', 'Ölçü uğurla yaradıldı!');
    }

    public function edit($id)
    {
        $size = Sizes::findOrFail($id);
        return view('admin.product.sizes.edit',[
            'size' => $size
        ]);
    }

    public function update(Request $request)
    {
        $size = Sizes::findOrFail($request->id);

        $size->name_az = $request->name_az;
        $size->name_en = $request->name_en;
        $size->name_ru = $request->name_ru;
        $size->save();

        return redirect(route('size.list'))->with('success', 'Ölçü məlumatları yeniləndi!');
    }

}
