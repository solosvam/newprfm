<?php

namespace App\Http\Controllers\Backend\Product;

use App\Http\Controllers\Controller;
use App\Models\Product\Brand;
use App\Services\BrandLogoService;
use App\Services\SerperImageSearchService;
use App\Services\WorldVectorLogoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rule;
use Throwable;

class BrandsController extends Controller
{
    public function index()
    {
        $brands = Brand::withCount(['products as products_count' => fn ($q) => $q->where('active', 1)])
            ->when(request('logo') === 'missing', fn ($q) => $q->where(fn ($w) => $w->whereNull('image')->orWhere('image', '')))
            ->orderBy('name','asc')->get(); // DataTables: səhifələmə və axtarış brauzerdə

        return view('backend.product_menu.brands.list',[
            'brands'    => $brands
        ]);
    }

    public function create(Request $request)
    {
        $request->validate([
            'name'  => 'required|string|max:255',
            'slug' => ['nullable', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('brands', 'slug')],
            'image' => 'required|image|max:10240',
        ], [
            'name.required'  => 'Brend adı daxil edilməlidir.',
            'image.required' => 'Brend şəkli seçilməlidir.',
            'image.image'    => 'Yüklənən fayl şəkil formatında olmalıdır.',
            'image.max'      => 'Şəklin həcmi maksimum 10 MB ola bilər.',
        ]);

        $brand = Brand::create([
            'name' => $request->name,
            'slug' => $request->filled('slug') ? $request->slug : null,
        ]);

        $brand->image = app(BrandLogoService::class)->store($request->file('image')->get(), $brand);
        $brand->save();

        return redirect()->back()
            ->with('success', 'Brend əlavə edildi!');
    }

    public function edit($id)
    {
        $brand = Brand::findOrFail($id);
        return view('backend.product_menu.brands.edit',[
            'brand' => $brand
        ]);
    }

    public function update(Request $request)
    {
        $request->validate([
            'id'     => 'required',
            'name'   => 'required|string|max:255',
            'slug'   => ['nullable', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('brands', 'slug')->ignore($request->id)],
            'image'  => 'nullable|image|max:10240',
            'active' => 'required',
        ], [
            'id.required'     => 'Brend ID tapılmadı.',
            'name.required'   => 'Brend adı daxil edilməlidir.',
            'image.image'     => 'Yüklənən fayl şəkil formatında olmalıdır.',
            'image.max'       => 'Şəklin həcmi maksimum 10 MB ola bilər.',
            'active.required' => 'Status seçilməlidir.',
        ]);

        $brand = Brand::findOrFail($request->id);

        $brand->name   = $request->name;
        if ($request->filled('slug')) {
            $brand->slug = $request->slug;
        }
        $brand->active = $request->active;

        if ($request->hasFile('image')) {
            // köhnə fayllar (orijinal və logo/) servisdə silinir
            $brand->image = app(BrandLogoService::class)->store($request->file('image')->get(), $brand);
        }

        $brand->save();

        return redirect(route('admin.brand.list'))
            ->with('success', 'Brend məlumatları yeniləndi!');
    }

    /**
     * Brend loqosu axtarışı. Mənbə: vector (worldvectorlogo, default) və ya google (Serper).
     * Namizədlər sessiyada saxlanılır — tətbiq zamanı yalnız onlardan biri seçilə bilər.
     */
    public function searchLogo(Request $request, Brand $brand, SerperImageSearchService $serper, WorldVectorLogoService $vectors): JsonResponse
    {
        $source = $request->query('source') === 'google' ? 'google' : 'vector';
        $query = trim((string) $request->query('q')) ?: ($source === 'google' ? $brand->name . ' logo png' : $brand->name);

        try {
            if ($source === 'vector') {
                $candidates = $vectors->search($query);
                $images = collect($candidates)->map(fn ($logo, $i) => [
                    'id' => $i, 'thumbnail' => $logo['preview_url'], 'title' => $logo['name'], 'source' => $logo['name'],
                ]);
            } else {
                $candidates = $serper->search($query);
                $images = collect($candidates)->map(fn ($img, $i) => [
                    'id' => $i, 'thumbnail' => $img['thumbnail_url'], 'title' => $img['title'], 'source' => $img['source'],
                ]);
            }
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        $request->session()->put('brand_logo_candidates.' . $brand->id, ['source' => $source, 'items' => $candidates]);

        return response()->json(['success' => true, 'source' => $source, 'query' => $query, 'images' => $images->values()]);
    }

    /** Seçilmiş namizədi yükləyir, emal edir və brendə bağlayır */
    public function applyLogo(Request $request, Brand $brand, BrandLogoService $logos, WorldVectorLogoService $vectors): JsonResponse
    {
        $data = $request->validate(['candidate' => ['required', 'integer', 'min:0']]);
        $stored = $request->session()->get('brand_logo_candidates.' . $brand->id, []);
        $candidate = $stored['items'][$data['candidate']] ?? null;
        if (!$candidate) {
            return response()->json(['success' => false, 'message' => 'Axtarışı yenidən edin.'], 422);
        }

        try {
            $svg = null;
            if (($stored['source'] ?? null) === 'vector') {
                $binary = $vectors->png($candidate['slug']);   // raster: og:image və ehtiyat
                $svg = $vectors->svg($candidate['preview_url']); // vektor: saytda rənglə göstərmək üçün
            } else {
                $response = Http::timeout(20)
                    ->connectTimeout(5)
                    ->withoutRedirecting()
                    ->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; ParfumShopImageImporter/1.0)'])
                    ->get($candidate['original_url']);

                if (!$response->successful() || strlen($response->body()) > 10 * 1024 * 1024) {
                    throw new \RuntimeException('Şəkil yüklənmədi (sayt icazə vermir və ya fayl çox böyükdür). Başqa variant seçin.');
                }
                $binary = $response->body();
            }

            $brand->image = $logos->store($binary, $brand, $svg);
            $brand->save();
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'message' => $brand->name . ' loqosu yeniləndi' . ($svg ? ' (vektor)' : '') . '.',
            'logo' => BrandLogoService::svgUrl($brand->image) ?? BrandLogoService::url($brand->image),
        ]);
    }
}
