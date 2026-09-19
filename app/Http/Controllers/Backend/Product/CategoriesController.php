<?php

namespace App\Http\Controllers\Backend\Product;

use App\Http\Controllers\Controller;
use App\Models\Product\Category;
use Illuminate\Http\Request;

class CategoriesController extends Controller
{
    public function index()
    {
        $categories = Category::all();

        return view('backend.product_menu.category.list',[
            'categories'    => $categories
        ]);
    }

    public function create(Request $request)
    {
        Category::create($request->all());
        return redirect()->back()->with('success', 'Kateqoriya uğurla yaradıldı!');
    }

    public function edit($id)
    {
        $category = Category::findOrFail($id);
        return view('backend.product_menu.category.edit',[
            'category' => $category
        ]);
    }

    public function update(Request $request)
    {
        $category = Category::findOrFail($request->id);

        $category->name_az = $request->name_az;
        $category->name_en = $request->name_en;
        $category->name_ru = $request->name_ru;
        $category->active = $request->active;
        $category->save();

        return redirect(route('admin.category.list'))->with('success', 'Kateqoriya məlumatları yeniləndi!');
    }
}
