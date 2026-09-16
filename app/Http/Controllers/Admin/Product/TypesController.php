<?php

namespace App\Http\Controllers\Admin\Product;

use App\Http\Controllers\Controller;
use App\Models\Product\ProductTypes;
use Illuminate\Http\Request;

class TypesController extends Controller
{
    public function index()
    {
        $types = ProductTypes::all();

        return view('admin.product.types.list',[
            'types'    => $types
        ]);
    }

    public function create(Request $request)
    {
        ProductTypes::create($request->all());
        return redirect()->back()->with('success', 'Ətir növü uğurla yaradıldı!');
    }

    public function edit($id)
    {
        $type = ProductTypes::findOrFail($id);
        return view('admin.product.types.edit',[
            'type' => $type
        ]);
    }

    public function update(Request $request)
    {
        $type = ProductTypes::findOrFail($request->id);

        $type->name_az = $request->name_az;
        $type->name_en = $request->name_en;
        $type->name_ru = $request->name_ru;
        $type->save();

        return redirect(route('type.list'))->with('success', 'Ətir növü məlumatları yeniləndi!');
    }
}
