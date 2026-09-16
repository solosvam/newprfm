<?php

namespace App\Http\Controllers\Admin\Product;

use App\Http\Controllers\Controller;
use App\Models\Product\Ingredients;
use Illuminate\Http\Request;

class IngredientController extends Controller
{
    public function index()
    {
        $ingredients = Ingredients::all();

        return view('admin.product.ingredients.list',[
            'ingredients'    => $ingredients
        ]);
    }

    public function create(Request $request)
    {
        Ingredients::create($request->all());
        return redirect()->back()->with('success', 'Tərkib uğurla yaradıldı!');
    }

    public function edit($id)
    {
        $size = Ingredients::findOrFail($id);
        return view('admin.product.ingredients.edit',[
            'size' => $size
        ]);
    }

    public function update(Request $request)
    {
        $size = Ingredients::findOrFail($request->id);

        $size->name_az = $request->name_az;
        $size->name_en = $request->name_en;
        $size->name_ru = $request->name_ru;
        $size->save();

        return redirect(route('ingredients.list'))->with('success', 'Tərkib məlumatları yeniləndi!');
    }
}
