<?php

namespace App\Http\Controllers\Backend\Product;

use App\Services\ProductPosterData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AddProductRequest;
use App\Models\Order\OrderItem;
use App\Models\Product\Brand;
use App\Models\Product\Category;
use App\Models\Product\Gender;
use App\Models\Product\Ingredient;
use App\Models\Product\Product;
use App\Models\Product\ProductImage;
use App\Models\Product\ProductVariant;
use App\Models\Product\Size;
use App\Models\Product\Type;
use App\Services\SeoUrl;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use Throwable;

class ProductsController extends Controller
{
    public function index(Request $request)
    {
        return view('backend.product_menu.product.list', [
            'status' => $this->listStatus($request),
            'counts' => [
                'active' => Product::where('active', 1)->count(),
                'inactive' => Product::where('active', '!=', 1)->count(),
            ],
        ]);
    }

    /** Siyahı tabı: aktiv (standart) və ya deaktiv */
    private function listStatus(Request $request): string
    {
        return $request->query('status') === 'inactive' ? 'inactive' : 'active';
    }

    public function poster(Product $product, ProductPosterData $poster): \Illuminate\Http\JsonResponse
    {
        $data = $poster->for($product);

        return $data
            ? response()->json($data)
            : response()->json(['message' => 'Poster üçün məhsul şəkli və ən azı bir aktiv ölçü olmalıdır.'], 422);
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
        $failedImages = DB::transaction(function () use ($request) {

            $product = Product::create([
                'brand_id'   => $request->brand_id,
                'type_id'    => $request->type_id,
                'old_id'     => $request->old_id,
                'name'       => $request->name,
                'slug'       => $request->filled('slug') ? $request->slug : null,
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

            $failedImages = $this->storeSelectedRemoteImages($product, $request, $manager);

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

            return $failedImages;
        });

        if ($failedImages > 0) {
            return redirect()
                ->route('admin.product.list')
                ->with('error', "Məhsul əlavə edildi, amma seçilən şəkillərdən {$failedImages} ədədi yüklənmədi (mənbə sayt icazə vermədi). Başqa şəkil seçin.");
        }

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

    /**
     * Axtarışdan seçilən şəkilləri endirib məhsula əlavə edir.
     * "Əsas şəkil" seçilibsə, o, mövcud şəkillərdən də qabağa keçir.
     * Qaytarır: yüklənə bilməyən şəkillərin sayı.
     */
    private function storeSelectedRemoteImages(Product $product, AddProductRequest $request, ImageManager $manager): int
    {
        $selectedIds = array_values(array_unique(array_map('strval', $request->input('remote_image_ids', []))));
        $primaryId = $request->filled('remote_primary_image_id')
            ? (string) $request->input('remote_primary_image_id')
            : null;

        if (!$selectedIds) {
            return 0;
        }

        if ($primaryId !== null && in_array($primaryId, $selectedIds, true)) {
            $selectedIds = array_values(array_unique(array_merge([$primaryId], $selectedIds)));
        } else {
            $primaryId = null;
        }

        $candidates = $request->session()->get('product_image_candidates', []);
        $sortOrder = $this->nextImageSortOrder($product);
        $failed = 0;
        $primaryImage = null;

        foreach ($selectedIds as $id) {
            $candidate = $candidates[$id] ?? null;
            $contents = $candidate ? $this->downloadRemoteImage($candidate) : null;

            if ($contents === null) {
                $failed++;
                continue;
            }

            $imageName = $this->generateProductImageName($product);
            $path = public_path('frontend/uploads/products/' . $imageName);

            try {
                $manager
                    ->decode($contents)
                    ->cover(600, 600)
                    ->save($path, quality: 82);
            } catch (Throwable $exception) {
                report($exception);
                $failed++;
                continue;
            }

            $image = ProductImage::create([
                'product_id' => $product->id,
                'image' => $imageName,
                'sort_order' => $sortOrder++,
            ]);

            if ($id === $primaryId) {
                $primaryImage = $image;
            }
        }

        if ($primaryImage) {
            $this->moveImageToFront($product, $primaryImage);
        }

        return $failed;
    }

    /**
     * Orijinal şəkli endirir. Yönləndirmələrə icazə verilir (yalnız https), brauzer kimi sorğu göndərilir,
     * Referer mənbənin öz domeni olur (hotlink qadağası olan saytlar üçün). Alınmasa null.
     */
    private function downloadRemoteImage(array $candidate): ?string
    {
        $url = $candidate['original_url'] ?? null;

        if (!$url || parse_url($url, PHP_URL_SCHEME) !== 'https') {
            return null;
        }

        try {
            $response = Http::timeout(20)
                ->connectTimeout(5)
                ->withOptions(['allow_redirects' => ['max' => 3, 'protocols' => ['https']]])
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0 Safari/537.36',
                    'Accept' => 'image/avif,image/webp,image/png,image/jpeg,image/*;q=0.8',
                    'Referer' => 'https://' . parse_url($url, PHP_URL_HOST) . '/',
                ])
                ->get($url);
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }

        $body = $response->body();
        $type = strtolower((string) $response->header('Content-Type'));

        if (!$response->successful() || $body === '' || strlen($body) > 10 * 1024 * 1024) {
            return null;
        }

        // HTML səhifə (məs. "giriş qadağandır") şəkil kimi qəbul edilməsin
        if (str_starts_with($type, 'text/')) {
            return null;
        }

        return $body;
    }

    /** Şəkli 1-ci sıraya keçirir, qalanlarının indiki sırasını saxlayır */
    private function moveImageToFront(Product $product, ProductImage $first): void
    {
        $ids = $product->images()->pluck('id')
            ->reject(fn ($id) => $id === $first->id)
            ->prepend($first->id)
            ->all();

        $this->updateImageOrder($product, $ids);
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
        $failedImages = DB::transaction(function () use ($request, $id) {
            $product = Product::findOrFail($id);

            $product->update([
                'slug'       => $request->filled('slug') ? $request->slug : $product->slug,
                'brand_id'   => $request->brand_id,
                'type_id'    => $request->type_id,
                'old_id'     => $request->old_id,
                'name'       => $request->name,
                'content_az' => $request->content_az,
                'content_en' => $request->content_en,
                'content_ru' => $request->content_ru,
                // Forma həmişə active göndərir (hidden 0 + checkbox 1); köhnə formada yoxdursa dəyişmir
                'active'     => $request->has('active') ? $request->boolean('active') : $product->active,
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

            $manager = ImageManager::usingDriver(Driver::class);

            // Edit zamanı axtarışdan seçilən uzaq şəkilləri də məhsula əlavə et.
            $failedImages = $this->storeSelectedRemoteImages($product, $request, $manager);

            /*
             * Yeni şəkillər
             */
            if ($request->hasFile('images')) {
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

            return $failedImages;
        });

        // Deaktiv məhsul yadda saxlananda Deaktiv tabına qayıt
        $listParams = Product::whereKey($id)->value('active') == 1 ? [] : ['status' => 'inactive'];

        if ($failedImages > 0) {
            return redirect()
                ->route('admin.product.list', $listParams)
                ->with('error', "Məhsul yeniləndi, amma seçilən şəkillərdən {$failedImages} ədədi yüklənmədi (mənbə sayt icazə vermədi). Başqa şəkil seçin.");
        }

        return redirect()
            ->route('admin.product.list', $listParams)
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

    public function listData(Request $request)
    {
        $products = Product::with([
            'brand',
            'type',
            'images',
            'variants',
            'categories',
        ])
            ->where('active', $this->listStatus($request) === 'inactive' ? '!=' : '=', 1)
            ->withCount('ingredients')
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

                'ingredient_count' => (int) $product->ingredients_count,

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
