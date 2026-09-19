<?php

namespace App\Http\Controllers\Backend\Product;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AddProductRequest;
use App\Models\Product\Brand;
use App\Models\Product\Category;
use App\Models\Product\Ingredient;
use App\Models\Product\ProductCategory;
use App\Models\Product\ProductGender;
use App\Models\Product\ProductImage;
use App\Models\Product\ProductIngredient;
use App\Models\Product\Product;
use App\Models\Product\ProductSize;
use App\Models\Product\Size;
use App\Models\Product\ProductType;
use App\Services\SeoUrl;
use Buglinjo\LaravelWebp\Webp;
use Illuminate\Http\Request;

class ProductsController extends Controller
{
    public function index()
    {
        $products = Product::paginate(25);
        return view('backend.product.list',[
            'products'    => $products,
        ]);
    }

    public function add()
    {
        $brands = Brand::Where('active',1)->get();
        $types = ProductType::all();
        $sizes = Size::all();
        $categories = Category::all();
        $ingredients = Ingredient::all();

        return view('backend.product.add',[
            'brands'        => $brands,
            'types'         => $types,
            'sizes'         => $sizes,
            'categories'    => $categories,
            'ingredients'   => $ingredients,
        ]);
    }

    public function create(AddProductRequest $request)
    {
        $product = Product::create([
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
            ProductGender::create([
                'product_id'    => $product->id,
                'gender_id'     => $genderId,
            ]);
        }

        foreach ($request->ingredients as $ingredientId) {
            ProductIngredient::create([
                'product_id' => $product->id,
                'ingredient_id' => $ingredientId,
            ]);
        }

        foreach ($request->size as $index => $sizeId) {
            ProductSize::create([
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

            ProductImage::create([
                'product_id' => $product->id,
                'image' => $imageName,
            ]);
        }

        return redirect()->route('product.list')->with('success', 'Məhsul əlavə edildi !');
    }

    public function edit($id)
    {
        $product = Product::findOrFail($id);
        $brands = Brand::Where('active',1)->get();
        $types = ProductType::all();
        $sizes = Size::all();
        $categories = Category::all();
        $ingredients = Ingredient::all();

        return view('backend.product.edit',[
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
        $product = Product::findOrFail($id);

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
            $size = ProductSize::updateOrCreate(
                ['product_id' => $product->id, 'size_id' => $sizeId],
                ['price' => $request->size_price[$index] ?? 0]
            );

            $currentSizeIds[] = $size->id;
        }

        ProductSize::Where('product_id', $product->id)
            ->whereNotIn('id', $currentSizeIds)
            ->delete();

        if ($request->delete_images) {
            foreach ($request->delete_images as $imageId) {
                $image = ProductImage::find($imageId);
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

                ProductImage::create([
                    'product_id' => $product->id,
                    'image' => $imageName,
                ]);
            }
        }

        return redirect()->route('product.list')->with('success', 'Məhsul yeniləndi!');
    }

}
