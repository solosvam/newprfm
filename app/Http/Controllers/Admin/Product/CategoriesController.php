<?php

namespace App\Http\Controllers\Admin\Product;

use App\Http\Controllers\Controller;
use App\Models\Product\Categories;
use Illuminate\Http\Request;

class CategoriesController extends Controller
{
    public function index()
    {
        $categories = Categories::all();

        return view('admin.product.categories.list',[
            'categories'    => $categories
        ]);
    }

    public function create(Request $request)
    {
        Categories::create($request->all());
        return redirect()->back()->with('success', 'Kateqoriya uğurla yaradıldı!');
    }

    public function edit($id)
    {
        $category = Categories::findOrFail($id);
        return view('admin.product.categories.edit',[
            'category' => $category
        ]);
    }

    public function update(Request $request)
    {
        $category = Categories::findOrFail($request->id);

        $category->name_az = $request->name_az;
        $category->name_en = $request->name_en;
        $category->name_ru = $request->name_ru;
        $category->active = $request->active;
        $category->save();

        return redirect(route('category.list'))->with('success', 'Kateqoriya məlumatları yeniləndi!');
    }
}
