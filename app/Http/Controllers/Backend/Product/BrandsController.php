<?php

namespace App\Http\Controllers\Backend\Product;

use App\Http\Controllers\Controller;
use App\Models\Product\Brand;
use App\Models\Product\SearchAlias;
use App\Services\OpenAiPerfumeService;
use App\Services\Search\ProductSearchNormalizer;
use App\Services\BrandLogoService;
use App\Services\SerperImageSearchService;
use App\Services\WorldVectorLogoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;
use Throwable;

class BrandsController extends Controller
{
    public function index()
    {
        $brands = Brand::withCount(['products as products_count' => fn ($q) => $q->where('active', 1), 'searchAliases'])
            ->when(request('logo') === 'missing', fn ($q) => $q->where(fn ($w) => $w->whereNull('image')->orWhere('image', '')))
            ->when(request('aliases') === 'missing', fn ($q) => $q->doesntHave('searchAliases'))
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
        $brand = Brand::with('searchAliases')->findOrFail($id);
        return view('backend.product_menu.brands.edit',[
            'brand' => $brand
        ]);
    }

    public function update(Request $request, int $id)
    {
        // Brend ünvandakı {id}-dən tapılır: formada "id" sahəsi yoxdur, ona görə onu tələb etmək hər saxlamada xəta verirdi
        $brand = Brand::findOrFail($id);

        $request->validate([
            'name'   => 'required|string|max:255',
            'slug'   => ['nullable', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('brands', 'slug')->ignore($brand->id)],
            'image'  => 'nullable|image|max:10240',
            'active' => 'required',
        ], [
            'name.required'   => 'Brend adı daxil edilməlidir.',
            'image.image'     => 'Yüklənən fayl şəkil formatında olmalıdır.',
            'image.max'       => 'Şəklin həcmi maksimum 10 MB ola bilər.',
            'active.required' => 'Status seçilməlidir.',
        ]);

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

    public function suggestAliases(Brand $brand, OpenAiPerfumeService $ai): JsonResponse
    {
        try {
            $suggestions = $ai->suggestBrandAliases($brand->name, $brand->searchAliases()->pluck('alias')->all());
            $blocked = SearchAlias::pluck('alias_normalized')->merge(
                Brand::pluck('name')->map(fn ($name) => ProductSearchNormalizer::normalize($name))
            )->flip();
            $aliases = [];
            foreach ($suggestions as $suggestion) {
                if (!is_string($suggestion)) continue;
                $suggestion = trim($suggestion);
                if (preg_match('/\p{Cyrillic}/u', $suggestion)) continue;
                $normalized = ProductSearchNormalizer::normalize($suggestion);
                if (mb_strlen($suggestion) > 100 || strlen($normalized) < 3 || isset($blocked[$normalized])) continue;
                $aliases[$normalized] = $suggestion;
                if (count($aliases) >= 30) break;
            }

            return response()->json(['aliases' => array_values($aliases)]);
        } catch (Throwable $e) {
            report($e);
            return response()->json(['message' => 'Alias təklifləri alınmadı. AI bağlantısını yoxlayıb yenidən cəhd edin.'], 422);
        }
    }

    public function storeAliases(Request $request, Brand $brand): JsonResponse
    {
        $data = $request->validate([
            'aliases' => ['required', 'array', 'min:1', 'max:30'],
            'aliases.*' => ['required', 'string', 'max:100'],
        ]);
        $aliases = [];
        foreach ($data['aliases'] as $alias) {
            $normalized = ProductSearchNormalizer::normalize($alias);
            if (strlen($normalized) < 3) {
                throw ValidationException::withMessages(['aliases' => 'Alias ən azı 3 hərf və ya rəqəmdən ibarət olmalıdır.']);
            }
            $aliases[$normalized] = trim($alias);
        }

        DB::transaction(function () use ($aliases, $brand) {
            $conflict = SearchAlias::whereIn('alias_normalized', array_keys($aliases))->exists();
            $brandNames = Brand::pluck('name')->map(fn ($name) => ProductSearchNormalizer::normalize($name));
            if ($conflict || $brandNames->intersect(array_keys($aliases))->isNotEmpty()) {
                throw ValidationException::withMessages(['aliases' => 'Seçilən yazılışlardan biri artıq lüğətdə və ya brend adlarında var. Təklifləri yenidən alın.']);
            }
            foreach ($aliases as $alias) {
                SearchAlias::create(['alias' => $alias, 'type' => SearchAlias::BRAND,
                    'brand_id' => $brand->id, 'created_by' => auth('admin')->id()]);
            }
        });

        return response()->json(['message' => count($aliases).' alias əlavə edildi.', 'aliases' => $brand->searchAliases()->get(['id', 'alias'])]);
    }

    public function destroyAlias(Brand $brand, SearchAlias $alias): JsonResponse
    {
        abort_unless($alias->brand_id == $brand->id && $alias->type === SearchAlias::BRAND, 404);
        $alias->delete();
        return response()->json(['message' => 'Alias silindi.']);
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
