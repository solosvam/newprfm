<?php

namespace App\Http\Controllers\Backend\Product;

use App\Http\Controllers\Controller;
use App\Models\Product\ProductType;
use Illuminate\Http\Request;

class TypesController extends Controller
{
    public function index()
    {
        $types = ProductType::all();

        return view('backend.product_menu.types.list',[
            'types'    => $types
        ]);
    }

    public function create(Request $request)
    {
        ProductType::create($request->all());
        return redirect()->back()->with('success', 'Ətir növü uğurla yaradıldı!');
    }

    public function edit($id)
    {
        $type = ProductType::findOrFail($id);
        return view('backend.product_menu.types.edit',[
            'type' => $type
        ]);
    }

    public function update(Request $request)
    {
        $type = ProductType::findOrFail($request->id);

        $type->name_az = $request->name_az;
        $type->name_en = $request->name_en;
        $type->name_ru = $request->name_ru;
        $type->save();

        return redirect(route('admin.type.list'))->with('success', 'Ətir növü məlumatları yeniləndi!');
    }
}
