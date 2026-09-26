<?php

namespace App\Http\Controllers\Backend\Product;

use App\Http\Controllers\Controller;
use App\Models\Product\Category;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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
        $data = $request->validate([
            'name_az' => ['required', 'string', 'max:255'],
            'name_en' => ['required', 'string', 'max:255'],
            'name_ru' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('categories', 'slug')],
        ]);
        Category::create($data);
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
        $data = $request->validate([
            'name_az' => ['required', 'string', 'max:255'],
            'name_en' => ['required', 'string', 'max:255'],
            'name_ru' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('categories', 'slug')->ignore($category->id)],
            'active' => ['required', 'boolean'],
        ]);

        $category->name_az = $data['name_az'];
        $category->name_en = $data['name_en'];
        $category->name_ru = $data['name_ru'];
        if (!empty($data['slug'])) {
            $category->slug = $data['slug'];
        }
        $category->active = $request->active;
        $category->save();

        return redirect(route('admin.category.list'))->with('success', 'Kateqoriya məlumatları yeniləndi!');
    }
}
