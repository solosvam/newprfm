<?php

namespace App\Http\Controllers\Admin\Product;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AddProductRequest;
use App\Models\Product\Brands;
use App\Models\Product\Categories;
use App\Models\Product\Ingredients;
use App\Models\Product\ProductCategory;
use App\Models\Product\ProductGenders;
use App\Models\Product\ProductImages;
use App\Models\Product\ProductIngredients;
use App\Models\Product\Products;
use App\Models\Product\ProductSizes;
use App\Models\Product\Sizes;
use App\Models\Product\ProductTypes;
use App\Services\SeoUrl;
use Buglinjo\LaravelWebp\Webp;
use Illuminate\Http\Request;

class ProductsController extends Controller
{
    public function index()
    {
        $products = Products::paginate(25);
        return view('admin.product.products.list',[
            'products'    => $products,
        ]);
    }

    public function add()
    {
        $brands = Brands::Where('active',1)->get();
        $types = ProductTypes::all();
        $sizes = Sizes::all();
        $categories = Categories::all();
        $ingredients = Ingredients::all();

        return view('admin.product.products.add',[
            'brands'        => $brands,
            'types'         => $types,
            'sizes'         => $sizes,
            'categories'    => $categories,
            'ingredients'   => $ingredients,
        ]);
    }

    public function create(AddProductRequest $request)
    {
        $product = Products::create([
            'brand_id'      => $request->brand_id,
            'type_id'       => $request->type_id,
            'old_id'        => $request->old_id,
            'name'          => $request->name,
            'content_az'    => $request->content_az,
            'content_en'    => $request->content_en,
            'content_ru'    => $request->content_ru,
        ]);

        foreach ($request->category as $categoryId) {
            ProductCategory::create([
                'product_id'    => $product->id,
                'category_id'   => $categoryId,
            ]);
        }

        foreach ($request->gender as $genderId) {
            ProductGenders::create([
                'product_id'    => $product->id,
                'gender_id'     => $genderId,
            ]);
        }

        foreach ($request->ingredients as $ingredientId) {
            ProductIngredients::create([
                'product_id' => $product->id,
                'ingredient_id' => $ingredientId,
            ]);
        }

        foreach ($request->size as $index => $sizeId) {
            ProductSizes::create([
                'product_id' => $product->id,
                'size_id' => $sizeId,
                'price' => $request->size_price[$index] ?? 0,
            ]);
        }

        foreach ($request->image as $index => $image) {
            $webp = Webp::make($image);
            $imageName = SeoUrl::generateImageName([
                    'id'    => $product->id,
                    'title' => (rand(111,999)).'-'.$product->name . '-' . ($index + 1)
                ]).'.webp';

            $path = public_path('frontend/uploads/products/' . $imageName);
            $webp->save($path);

            ProductImages::create([
                'product_id' => $product->id,
                'image' => $imageName,
            ]);
        }

        return redirect()->route('product.list')->with('success', 'Məhsul əlavə edildi !');
    }

    public function edit($id)
    {
        $product = Products::findOrFail($id);
        $brands = Brands::Where('active',1)->get();
        $types = ProductTypes::all();
        $sizes = Sizes::all();
        $categories = Categories::all();
        $ingredients = Ingredients::all();

        return view('admin.product.products.edit',[
            'product'       => $product,
            'brands'        => $brands,
            'types'         => $types,
            'sizes'         => $sizes,
            'categories'    => $categories,
            'ingredients'   => $ingredients,
        ]);
    }

    public function update(AddProductRequest $request, $id)
    {
        $product = Products::findOrFail($id);

        $product->update([
            'brand_id'      => $request->brand_id,
            'type_id'       => $request->type_id,
            'old_id'        => $request->old_id,
            'name'          => $request->name,
            'content_az'    => $request->content_az,
            'content_en'    => $request->content_en,
            'content_ru'    => $request->content_ru,
        ]);

        $product->categories()->sync($request->category);

        $product->genders()->sync($request->gender);

        $product->ingredients()->sync($request->ingredients);

        $currentSizeIds = [];
        foreach ($request->size as $index => $sizeId) {
            $size = ProductSizes::updateOrCreate(
                ['product_id' => $product->id, 'size_id' => $sizeId],
                ['price' => $request->size_price[$index] ?? 0]
            );

            $currentSizeIds[] = $size->id;
        }

        ProductSizes::Where('product_id', $product->id)
            ->whereNotIn('id', $currentSizeIds)
            ->delete();

        if ($request->delete_images) {
            foreach ($request->delete_images as $imageId) {
                $image = ProductImages::find($imageId);
                if ($image) {
                    $path = public_path('frontend/uploads/products/' . $image->image);
                    if (file_exists($path)) {
                        unlink($path);
                    }
                    $image->delete();
                }
            }
        }

        if ($request->hasFile('image')) {
            foreach ($request->file('image') as $index => $image) {
                $webp = Webp::make($image);
                $imageName = SeoUrl::generateImageName([
                        'id'    => $product->id,
                        'title' => (rand(100,999)).'-'.$product->name . '-' . ($index + 1)
                    ]) . '.webp';

                $path = public_path('frontend/uploads/products/' . $imageName);
                $webp->save($path);

                ProductImages::create([
                    'product_id' => $product->id,
                    'image' => $imageName,
                ]);
            }
        }

        return redirect()->route('product.list')->with('success', 'Məhsul yeniləndi!');
    }

}
