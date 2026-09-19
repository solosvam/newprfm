<?php

namespace App\Http\Controllers\Backend\Product;

use App\Http\Controllers\Controller;
use App\Models\Product\Ingredient;
use Illuminate\Http\Request;

class IngredientController extends Controller
{
    public function index()
    {
        $ingredients = Ingredient::all();

        return view('backend.product_menu.ingredients.list',[
            'ingredients'    => $ingredients
        ]);
    }

    public function create(Request $request)
    {
        Ingredient::create($request->all());
        return redirect()->back()->with('success', 'Tərkib uğurla yaradıldı!');
    }

    public function edit($id)
    {
        $size = Ingredient::findOrFail($id);
        return view('backend.product_menu.ingredients.edit',[
            'size' => $size
        ]);
    }

    public function update(Request $request)
    {
        $size = Ingredient::findOrFail($request->id);

        $size->name_az = $request->name_az;
        $size->name_en = $request->name_en;
        $size->name_ru = $request->name_ru;
        $size->save();

        return redirect(route('admin.ingredients.list'))->with('success', 'Tərkib məlumatları yeniləndi!');
    }
}
