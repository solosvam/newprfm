<?php

namespace App\Http\Controllers\Backend\Product;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AddProductRequest;
use App\Models\Product\Brand;
use App\Models\Product\Category;
use App\Models\Product\Gender;
use App\Models\Product\Ingredient;
use App\Models\Product\ProductImage;
use App\Models\Product\Product;
use App\Models\Product\ProductVariant;
use App\Models\Product\Size;
use App\Models\Product\Type;
use App\Models\OrderItem;
use App\Services\SeoUrl;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class ProductsController extends Controller
{
    public function index()
    {
        $products = Product::paginate(25);
        return view('backend.product_menu.product.list',[
            'products'    => $products,
        ]);
    }

    public function add()
    {
        $brands = Brand::Where('active',1)->get();
        $types = Type::all();
        $sizes = Size::all();
        $genders = Gender::all();
        $categories = Category::where('active',1)->get();
        $ingredients = Ingredient::all();

        return view('backend.product_menu.product.add',compact('brands','types','genders','categories','ingredients','sizes'));
    }

    public function create(AddProductRequest $request)
    {
        DB::transaction(function () use ($request) {

            $product = Product::create([
                'brand_id'   => $request->brand_id,
                'type_id'    => $request->type_id,
                'old_id'     => $request->old_id,
                'name'       => $request->name,
                'content_az' => $request->content_az,
                'content_en' => $request->content_en,
                'content_ru' => $request->content_ru,
                'active'     => 1,
            ]);

            /*
             * Kateqoriyalar
             */
            $product->categories()->sync($request->categories);

            /*
             * Cinsiyyət
             */
            $product->genders()->sync($request->genders);

            /*
             * İnqrediyentlər
             */
            $product->ingredients()->sync(
                $request->ingredients ?? []
            );

            /*
             * Variantlar
             */
            foreach ($request->variants as $variant) {

                ProductVariant::create([
                    'product_id' => $product->id,
                    'size_id'    => $variant['size_id'],
                    'price'      => $variant['price'],
                    'active'     => $variant['active'] ?? 0,
                ]);
            }

            $manager = ImageManager::usingDriver(Driver::class);

            $this->storeSelectedRemoteImages($product, $request, $manager);

            /*
             * Əl ilə yüklənən şəkillər
             */
            if ($request->hasFile('images')) {
                $sortOrder = $this->nextImageSortOrder($product);

                foreach ($request->file('images') as $index => $image) {
                    $imageName = $this->generateProductImageName($product);

                    $path = public_path('frontend/uploads/products/' . $imageName);

                    $manager
                        ->decode($image)
                        ->cover(600, 600)
                        ->save($path, quality: 82);

                    ProductImage::create([
                        'product_id' => $product->id,
                        'image' => $imageName,
                        'sort_order' => $sortOrder++,
                    ]);
                }
            }
        });

        return redirect()
            ->route('admin.product.list')
            ->with('success', 'Məhsul əlavə edildi!');
    }

    public function edit($id)
    {
        $product = Product::findOrFail($id);
        $brands = Brand::Where('active',1)->get();
        $types = Type::all();
        $sizes = Size::all();
        $genders = Gender::all();
        $categories = Category::where('active',1)->get();
        $ingredients = Ingredient::all();

        return view('backend.product_menu.product.edit',compact('product','brands','types','genders','categories','ingredients','sizes'));
    }

    private function storeSelectedRemoteImages(Product $product, AddProductRequest $request, ImageManager $manager): void
    {
        $selectedIds = array_map('intval', $request->input('remote_image_ids', []));
        $primaryId = $request->filled('remote_primary_image_id')
            ? (int) $request->input('remote_primary_image_id')
            : null;

        if (!$selectedIds) {
            return;
        }

        if ($primaryId !== null && in_array($primaryId, $selectedIds, true)) {
            $selectedIds = array_values(array_unique(array_merge([$primaryId], $selectedIds)));
        }

        $candidates = $request->session()->get('product_image_candidates', []);
        $sortOrder = $this->nextImageSortOrder($product);

        foreach ($selectedIds as $id) {
            $candidate = $candidates[$id] ?? null;
            $url = $candidate['original_url'] ?? null;

            if (!$url) {
                continue;
            }

            $response = Http::timeout(20)
                ->connectTimeout(5)
                ->withoutRedirecting()
                ->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; ParfumShopImageImporter/1.0)'])
                ->get($url);

            if (!$response->successful() || strlen($response->body()) > 10 * 1024 * 1024) {
                continue;
            }

            $imageName = $this->generateProductImageName($product);
            $path = public_path('frontend/uploads/products/' . $imageName);

            $manager
                ->decode($response->body())
                ->cover(600, 600)
                ->save($path, quality: 82);

            ProductImage::create([
                'product_id' => $product->id,
                'image' => $imageName,
                'sort_order' => $sortOrder++,
            ]);
        }
    }

    private function nextImageSortOrder(Product $product): int
    {
        return ((int) $product->images()->max('sort_order')) + 1;
    }

    private function updateImageOrder(Product $product, array $imageIds): void
    {
        foreach (array_values(array_unique(array_map('intval', $imageIds))) as $index => $imageId) {
            ProductImage::where('product_id', $product->id)
                ->whereKey($imageId)
                ->update(['sort_order' => $index + 1]);
        }
    }

    public function update(AddProductRequest $request, $id)
    {
        DB::transaction(function () use ($request, $id) {
            $product = Product::findOrFail($id);

            $product->update([
                'brand_id'   => $request->brand_id,
                'type_id'    => $request->type_id,
                'old_id'     => $request->old_id,
                'name'       => $request->name,
                'content_az' => $request->content_az,
                'content_en' => $request->content_en,
                'content_ru' => $request->content_ru,
            ]);

            $product->categories()->sync($request->categories);
            $product->genders()->sync($request->genders);
            $product->ingredients()->sync($request->ingredients ?? []);

            /*
             * Variantlar
             */
            $currentVariantIds = [];

            foreach ($request->variants as $variant) {
                $productVariant = ProductVariant::updateOrCreate(
                    [
                        'product_id' => $product->id,
                        'size_id'    => $variant['size_id'],
                    ],
                    [
                        'price'  => $variant['price'],
                        'active' => $variant['active'] ?? 0,
                    ]
                );

                $currentVariantIds[] = $productVariant->id;
            }

            ProductVariant::where('product_id', $product->id)
                ->whereNotIn('id', $currentVariantIds)
                ->delete();

            /*
             * Silinən şəkillər
             */
            if ($request->filled('delete_images')) {
                $images = ProductImage::where('product_id', $product->id)
                    ->whereIn('id', $request->delete_images)
                    ->get();

                foreach ($images as $image) {
                    $path = public_path(
                        'frontend/uploads/products/' . $image->image
                    );

                    if (file_exists($path)) {
                        unlink($path);
                    }

                    $image->delete();
                }
            }

            // Formda sürüklənmiş mövcud şəkillərin sırasını saxlayırıq.
            $this->updateImageOrder($product, $request->input('image_order', []));

            /*
             * Yeni şəkillər
             */
            if ($request->hasFile('images')) {
                $manager = ImageManager::usingDriver(Driver::class);
                $sortOrder = $this->nextImageSortOrder($product);

                // brand_id dəyişmiş ola bilər, relation-u yenidən oxuyuruq
                $product->load('brand');

                foreach ($request->file('images') as $image) {
                    $imageName = $this->generateProductImageName($product);

                    $path = public_path(
                        'frontend/uploads/products/' . $imageName
                    );

                    $manager
                        ->decode($image)
                        ->cover(600, 600)
                        ->save($path, quality: 82);

                    ProductImage::create([
                        'product_id' => $product->id,
                        'image'      => $imageName,
                        'sort_order' => $sortOrder++,
                    ]);
                }
            }
        });

        return redirect()
            ->route('admin.product.list')
            ->with('success', 'Məhsul yeniləndi!');
    }

    public function destroy($id)
    {
        $product = Product::findOrFail($id);

        if (OrderItem::where('product_id', $product->id)->exists()) {
            return redirect()
                ->route('admin.product.edit', $product->id)
                ->with('error', 'Bu məhsul sifarişdə olduğu üçün silinə bilməz.');
        }

        $imageNames = DB::transaction(function () use ($product) {
            $imageNames = $product->images()->pluck('image')->all();

            $product->categories()->detach();
            $product->genders()->detach();
            $product->ingredients()->detach();
            $product->reviews()->delete();
            $product->variants()->delete();
            $product->images()->delete();
            DB::table('product_favorites')->where('product_id', $product->id)->delete();
            $product->delete();

            return $imageNames;
        });

        foreach ($imageNames as $imageName) {
            $path = public_path('frontend/uploads/products/' . $imageName);

            if (is_file($path)) {
                unlink($path);
            }
        }

        return redirect()
            ->route('admin.product.list')
            ->with('success', 'Məhsul və ona aid məlumatlar silindi.');
    }

    public function listData()
    {
        $products = Product::with([
            'brand',
            'type',
            'images',
            'variants',
            'categories',
        ])
            ->orderByDesc('id')
            ->get();

        return $products->map(function ($product) {
            $activeVariants = $product->variants
                ->where('active', 1);

            return [
                'id' => $product->id,

                'image' => $product->images
                    ->first()?->image,

                'brand' => $product->brand?->name ?? '-',

                'name' => $product->name,

                'type' => $product->type?->name_az ?? '-',

                'variant_count' => $product->variants->count(),

                'price' => $activeVariants
                    ->sortBy('price')
                    ->first()?->price,

                'category_count' => $product->categories->count(),

                'active' => (int) $product->active,
            ];
        })->values();
    }


    private function generateProductImageName(Product $product): string
    {
        $baseName = SeoUrl::generateImageName([
            'title' => $product->brand->name . '-' . $product->name,
        ]);

        $number = 1;

        do {
            $imageName = $baseName . '-' . $number . '.webp';

            $path = public_path(
                'frontend/uploads/products/' . $imageName
            );

            $number++;
        } while (file_exists($path));

        return $imageName;
    }

}
