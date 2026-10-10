<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\PriceList\WarehousePriceItem;
use App\Models\PriceList\WarehousePriceList;
use App\Models\Procurement\Warehouse;
use App\Services\PriceList\PriceListImporter;
use App\Services\PriceList\PriceListMatcher;
use App\Services\PriceList\PriceListNameParser;
use App\Services\PriceList\PriceListReader;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\View\View;
use RuntimeException;

/**
 * Anbarların price listləri: Excel yükləmə → sütunların seçimi → import → uyğunlaşdırma.
 * Sifarişdə axtarış (search) CRM sifariş səhifəsinin "Proses" tabından çağırılır.
 */
class PriceListController extends Controller
{
    public function index(): View
    {
        $current = WarehousePriceList::whereIn('id', WarehousePriceList::currentIds())->get()->keyBy('warehouse_id');
        $matched = WarehousePriceItem::whereIn('price_list_id', $current->pluck('id'))->whereNotNull('product_variant_id')
            ->selectRaw('price_list_id, count(*) as total')->groupBy('price_list_id')->pluck('total', 'price_list_id');

        return view('backend.price_lists.index', [
            'warehouses' => Warehouse::orderBy('name_az')->get(),
            'current' => $current,
            'matched' => $matched,
        ]);
    }

    /** 1-ci addım: fayl müvəqqəti saxlanır, sütun seçimi səhifəsi açılır */
    public function upload(Request $request, PriceListReader $reader): RedirectResponse
    {
        $data = $request->validate([
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'file' => ['required', 'file', 'max:20480', 'mimes:xls,xlsx,csv,txt'],
        ], ['file.mimes' => 'Fayl Excel (.xls, .xlsx) və ya CSV olmalıdır.']);

        $token = (string) Str::uuid();
        $file = $request->file('file');
        $extension = strtolower($file->getClientOriginalExtension() ?: 'xlsx');
        File::ensureDirectoryExists($this->tmpDir());
        $file->move($this->tmpDir(), $token.'.'.$extension);

        try {
            $reader->sheets($this->tmpDir().'/'.$token.'.'.$extension);
        } catch (RuntimeException $e) {
            File::delete($this->tmpDir().'/'.$token.'.'.$extension);

            return back()->withErrors(['file' => $e->getMessage()]);
        }
        session(['price_list_upload.'.$token => ['file' => $token.'.'.$extension, 'name' => $file->getClientOriginalName(), 'warehouse_id' => (int) $data['warehouse_id']]]);

        return redirect()->route('admin.price-lists.map', $token);
    }

    /** 2-ci addım: faylın ilk sətirləri və sütunların seçimi (anbarın əvvəlki seçimi hazır gəlir) */
    public function map(Request $request, string $token, PriceListReader $reader, PriceListImporter $importer): View|RedirectResponse
    {
        if (!$upload = session('price_list_upload.'.$token)) {
            return redirect()->route('admin.price-lists.index')->withErrors(['file' => 'Yükləmə tapılmadı — faylı yenidən seçin.']);
        }
        $path = $this->tmpDir().'/'.$upload['file'];
        $sheets = $reader->sheets($path);
        $sheet = min(max(0, (int) $request->query('sheet', 0)), count($sheets) - 1);
        $rows = $reader->rows($path, $sheet, 200);

        $previous = WarehousePriceList::where('warehouse_id', $upload['warehouse_id'])->latest('id')->value('mapping');
        $mapping = ($previous && !$request->has('sheet') ? $previous : null) ?? (['sheet' => $sheet] + $importer->guessMapping($rows));
        $mapping['sheet'] = $request->has('sheet') ? $sheet : (int) ($mapping['sheet'] ?? 0);
        if ($mapping['sheet'] !== $sheet && $mapping['sheet'] < count($sheets)) {
            $rows = $reader->rows($path, $mapping['sheet'], 200);
        }

        return view('backend.price_lists.map', [
            'token' => $token,
            'upload' => $upload,
            'warehouse' => Warehouse::findOrFail($upload['warehouse_id']),
            'sheets' => $sheets,
            'rows' => array_slice($rows, 0, 15),
            'columns' => max(array_map('count', $rows ?: [[]])),
            'mapping' => $mapping,
            'remembered' => (bool) $previous && !$request->has('sheet'),
        ]);
    }

    /** 3-cü addım: import */
    public function import(Request $request, string $token, PriceListImporter $importer): RedirectResponse
    {
        if (!$upload = session('price_list_upload.'.$token)) {
            return redirect()->route('admin.price-lists.index')->withErrors(['file' => 'Yükləmə tapılmadı — faylı yenidən seçin.']);
        }
        $mapping = $request->validate([
            'sheet' => ['required', 'integer', 'min:0'],
            'first_row' => ['required', 'integer', 'min:1'],
            'name_col' => ['required', 'integer', 'min:0', 'different:price_col'],
            'price_col' => ['required', 'integer', 'min:0'],
            'brand_mode' => ['required', 'in:group,column,none'],
            'brand_col' => ['nullable', 'required_if:brand_mode,column', 'integer', 'min:0'],
        ], ['name_col.different' => 'Ad və qiymət eyni sütun ola bilməz.', 'brand_col.required_if' => 'Brend sütununu seçin.']);
        // Brend sütunu yalnız "Ayrıca sütun"da göndərilir — saxlanan xəritədə açar həmişə olsun
        $mapping = array_map(fn ($value) => is_numeric($value) ? (int) $value : $value, $mapping) + ['brand_col' => null];

        try {
            $list = $importer->import(Warehouse::findOrFail($upload['warehouse_id']), $this->tmpDir().'/'.$upload['file'], $upload['name'], $mapping, auth('admin')->id());
        } catch (RuntimeException $e) {
            return back()->withErrors(['file' => $e->getMessage()])->withInput();
        }
        File::delete($this->tmpDir().'/'.$upload['file']);
        session()->forget('price_list_upload.'.$token);

        return redirect()->route('admin.price-lists.show', $list)->with('success', 'Price list yükləndi: '.$list->rows_count.' sətir.');
    }

    public function show(Request $request, WarehousePriceList $priceList, PriceListMatcher $matcher): View
    {
        $tab = in_array($request->query('tab'), ['matched', 'all'], true) ? $request->query('tab') : 'unmatched';
        $items = $priceList->items()
            ->when($tab === 'unmatched', fn ($q) => $q->whereNull('product_variant_id'))
            ->when($tab === 'matched', fn ($q) => $q->whereNotNull('product_variant_id'))
            ->when($request->filled('q'), fn ($q) => $this->whereName($q, (string) $request->query('q')))
            ->when($request->filled('brand'), fn ($q) => $q->where('brand_raw', $request->query('brand')))
            ->with(['variant.product.brand', 'variant.size'])
            ->orderBy('row_no')->paginate(50)->withQueryString();

        // Bağlanmamış sətirlərin neçə namizədi var (pəncərəni açmadan görünsün): [hamısı, eyni ad və ölçü, eyni ölçü]
        $similar = [];
        foreach ($items as $item) {
            if (!$item->product_variant_id) {
                $found = $this->findCandidates($item, '', $matcher);
                $similar[$item->id] = [$found->count(), $found->where('exact', true)->count(), $found->where('same_size', true)->count()];
            }
        }

        $counts = $priceList->items()->selectRaw('count(*) as total, sum(case when product_variant_id is null then 0 else 1 end) as matched')->first();

        return view('backend.price_lists.show', [
            'list' => $priceList->load('warehouse'),
            'tab' => $tab,
            'items' => $items,
            'similar' => $similar,
            'total' => (int) $counts->total,
            'matched' => (int) $counts->matched,
            // Bizim brendə bağlanmayan brendlər: bunların sətirləri avtomatik uyğunlaşa bilmir
            'unknownBrands' => $priceList->items()->whereNull('brand_id')->whereNotNull('brand_raw')
                ->selectRaw('brand_raw, count(*) as total')->groupBy('brand_raw')->orderBy('brand_raw')->pluck('total', 'brand_raw'),
            'brandNames' => $priceList->items()->whereNotNull('brand_raw')->distinct()->orderBy('brand_raw')->pluck('brand_raw'),
            'brands' => DB::table('brands')->orderBy('name')->pluck('name', 'id'),
            'isCurrent' => in_array($priceList->id, WarehousePriceList::currentIds(), true),
        ]);
    }

    /** Fayldakı brend adı bizim brendə bağlanır (bütün anbarlar üçün yadda qalır), sətirlər yenidən yoxlanır */
    public function matchBrand(Request $request, WarehousePriceList $priceList, PriceListMatcher $matcher): RedirectResponse
    {
        $data = $request->validate(['brand_raw' => ['required', 'string', 'max:255'], 'brand_id' => ['required', 'exists:brands,id']]);

        DB::table('price_list_brand_matches')->updateOrInsert(
            ['brand_key' => PriceListNameParser::key($data['brand_raw'])],
            ['brand_id' => (int) $data['brand_id'], 'created_by' => auth('admin')->id(), 'updated_at' => now(), 'created_at' => now()],
        );
        $matcher->forgetBrands();
        $matched = $matcher->rematch($priceList->id);

        return back()->with('success', $data['brand_raw'].' brendi bağlandı — '.$matched.' sətir avtomatik uyğunlaşdı.');
    }

    /**
     * Fayldakı brend bizdə yoxdursa: brend yaradılır (ad operatorun yazdığı kimi — rəsmi yazılışla), bağlanır və sətirlər yenidən yoxlanır.
     * Eyni adlı brend artıq varsa, yenisi yaradılmır — mövcud olan bağlanır.
     */
    public function createBrand(Request $request, WarehousePriceList $priceList, PriceListMatcher $matcher): RedirectResponse
    {
        $data = $request->validate(['brand_raw' => ['required', 'string', 'max:255'], 'name' => ['required', 'string', 'max:255']],
            ['name.required' => 'Brendin adını yazın.']);
        $name = trim($data['name']);

        $brand = \App\Models\Product\Brand::all()->first(fn ($b) => PriceListNameParser::key($b->name) === PriceListNameParser::key($name))
            ?? \App\Models\Product\Brand::create(['name' => $name, 'active' => 1]);
        DB::table('price_list_brand_matches')->updateOrInsert(
            ['brand_key' => PriceListNameParser::key($data['brand_raw'])],
            ['brand_id' => $brand->id, 'created_by' => auth('admin')->id(), 'updated_at' => now(), 'created_at' => now()],
        );
        $matcher->forgetBrands();
        $matcher->rematch($priceList->id);

        return back()->with('success', ($brand->wasRecentlyCreated ? '"'.$brand->name.'" brendi yaradıldı' : '"'.$brand->name.'" brendi artıq var idi').' və bağlandı. Məhsulları hələ yoxdur — loqo və məhsulları Kataloq bölməsindən əlavə edin.');
    }

    /** Sətir üçün namizədlər (uyğunlaşdırma pəncərəsi) */
    public function candidates(Request $request, WarehousePriceItem $item, PriceListMatcher $matcher): JsonResponse
    {
        return response()->json(['items' => $this->findCandidates($item, trim((string) $request->query('q', '')), $matcher)]);
    }

    /**
     * Namizədlər: axtarış boşdursa — eyni brenddə adın bütün sözləri keçən variantlar; axtarış yazılıbsa — bütün məhsullarda.
     * Eyni ad + həcm ("exact") yuxarıda, sonra eyni ölçü, cinsi uyğun gələn, aktiv olan.
     * Siyahı səhifəsindəki "N oxşar" sayı da buradan gəlir — pəncərədə görünənlə eyni olsun deyə.
     */
    private function findCandidates(WarehousePriceItem $item, string $q, PriceListMatcher $matcher): \Illuminate\Support\Collection
    {
        $parsed = PriceListNameParser::parse($item->raw_name, $item->brand_raw);
        $ids = array_column($matcher->candidates($item->brand_id, $parsed, false), 'variant_id');

        $search = DB::table('product_variants as v')
            ->join('products as p', 'p.id', '=', 'v.product_id')
            ->join('sizes as s', 's.id', '=', 'v.size_id')
            ->leftJoin('brands as b', 'b.id', '=', 'p.brand_id')
            ->leftJoin('types as t', 't.id', '=', 'p.type_id');
        $words = array_filter(explode(' ', PriceListNameParser::key($q !== '' ? $q : $parsed['core'])), fn ($word) => strlen($word) > 1);
        if ($q === '' && $item->brand_id) {
            $search->where('p.brand_id', $item->brand_id);
        }
        foreach ($words as $word) {
            $search->where(fn ($w) => $w->where('p.name', 'like', '%'.$word.'%')->orWhere('b.name', 'like', '%'.$word.'%')->orWhere('s.name_az', 'like', $word.'%'));
        }
        // Nə söz, nə brend var — axtarmağa heç nə yoxdur
        if (!$words && !$item->brand_id) {
            return collect();
        }

        $rows = $search->orderByRaw('p.active desc')->orderBy('p.name')->limit(60)
            ->get(['v.id', 'v.product_id', 'p.name', 'p.active', 'b.name as brand', 't.name_az as type', 's.name_az as size', 'v.price']);
        $volume = $parsed['volume'];
        // Məhsulun cinsi: eyni adlı kişi və qadın ətirlərini ayırmaq üçün (sətrin cinsi addakı L / M / UNISEX-dən)
        $genderNames = DB::table('product_genders as pg')->join('genders as g', 'g.id', '=', 'pg.gender_id')
            ->whereIn('pg.product_id', $rows->pluck('product_id')->unique())->get(['pg.product_id', 'g.name_az'])
            ->groupBy('product_id')->map(fn ($group) => $group->pluck('name_az')->map(fn ($name) => trim(str_replace('üçün', '', $name)))->unique()->values());
        $wanted = ['L' => 'QAD', 'M' => 'KIS', 'U' => 'UNISEX'][$parsed['gender']] ?? null;

        return $rows->map(fn ($row) => [
            'id' => $row->id,
            'label' => trim(html_entity_decode($row->brand ?? '').' '.$row->name),
            'type' => $row->type,
            'size' => $row->size,
            'price' => number_format((float) $row->price, 2),
            'active' => (bool) $row->active,
            'exact' => in_array($row->id, $ids, true),
            'same_size' => $volume !== null && (float) $row->size === (float) $volume,
            'genders' => ($names = $genderNames[$row->product_id] ?? collect())->all(),
            // true — sətrin cinsi ilə eyni, false — fərqli, null — müqayisə etmək olmur
            'same_gender' => $wanted && $names->isNotEmpty() ? $names->contains(fn ($name) => str_contains(PriceListNameParser::key($name), $wanted)) : null,
        ])->sortByDesc(fn ($row) => [$row['exact'], $row['same_size'], $row['same_gender'] !== false, $row['active']])->values();
    }

    /** Operatorun seçimi: sətir varianta bağlanır və yadda qalır (növbəti importda avtomatik) */
    public function match(Request $request, WarehousePriceItem $item): JsonResponse
    {
        $data = $request->validate(['variant_id' => ['required', 'exists:product_variants,id']]);

        DB::transaction(function () use ($item, $data) {
            DB::table('warehouse_price_matches')->updateOrInsert(
                ['warehouse_id' => $item->warehouse_id, 'name_key' => $item->name_key],
                ['product_variant_id' => (int) $data['variant_id'], 'created_by' => auth('admin')->id(), 'updated_at' => now(), 'created_at' => now()],
            );
            $item->update(['product_variant_id' => (int) $data['variant_id'], 'matched_by' => 'manual']);
        });

        return response()->json(['ok' => true]);
    }

    /** Uyğunluq səhvdirsə: sətir boşalır, yaddaşdan da silinir */
    public function unmatch(WarehousePriceItem $item): JsonResponse
    {
        DB::transaction(function () use ($item) {
            DB::table('warehouse_price_matches')->where('warehouse_id', $item->warehouse_id)->where('name_key', $item->name_key)->delete();
            $item->update(['product_variant_id' => null, 'matched_by' => null]);
        });

        return response()->json(['ok' => true]);
    }

    /** Bütün anbarların cari siyahılarında mətnlə axtarış (sifarişdə Ctrl+F-in əvəzi) */
    public function search(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        if (mb_strlen($q) < 2) {
            return response()->json(['items' => []]);
        }
        $items = $this->whereName(WarehousePriceItem::query()->whereIn('price_list_id', WarehousePriceList::currentIds()), $q)
            ->with(['warehouse:id,name_az', 'priceList:id,created_at'])
            ->orderBy('price')->limit(60)->get();

        return response()->json(['items' => $items->map(fn (WarehousePriceItem $item) => [
            'name' => $item->raw_name,
            'warehouse' => $item->warehouse?->name_az,
            'price' => number_format((float) $item->price, 2),
            'date' => $item->priceList?->created_at?->format('d.m.Y'),
            'matched' => $item->product_variant_id !== null,
        ])]);
    }

    /** Hər söz adda olmalıdır, sırası vacib deyil ("eros 50" → "VERSACE EROS EDP L 50ML") */
    private function whereName($query, string $text)
    {
        foreach (array_filter(explode(' ', PriceListNameParser::key($text))) as $word) {
            $query->where('name_key', 'like', '%'.$word.'%');
        }

        return $query;
    }

    private function tmpDir(): string
    {
        return storage_path('app/private/price-lists');
    }
}
