<?php

namespace App\Http\Controllers\Backend\Product;

use App\Http\Controllers\Controller;
use App\Models\Product\Size;
use Illuminate\Http\Request;

class SizesController extends Controller
{
    public function index()
    {
        $sizes = Size::all();

        return view('backend.product_menu.sizes.list',[
            'sizes'    => $sizes
        ]);
    }

    public function create(Request $request)
    {
        Size::create($request->all());
        return redirect()->back()->with('success', 'Ölçü uğurla yaradıldı!');
    }

    public function edit($id)
    {
        $size = Size::findOrFail($id);
        return view('backend.product_menu.sizes.edit',[
            'size' => $size
        ]);
    }

    public function update(Request $request)
    {
        $size = Size::findOrFail($request->id);

        $size->name_az = $request->name_az;
        $size->name_en = $request->name_en;
        $size->name_ru = $request->name_ru;
        $size->save();

        return redirect(route('admin.size.list'))->with('success', 'Ölçü məlumatları yeniləndi!');
    }

}
