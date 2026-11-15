<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Finance\FinanceAccount;
use App\Models\Finance\MoneyMovement;
use App\Models\Order\Order;
use App\Models\Order\OrderItem;
use App\Models\Product\ProductVariant;
use App\Models\Production\MaterialPurchase;
use App\Models\Production\OwnProduct;
use App\Models\Production\RawMaterial;
use App\Models\Production\RawMaterialMovement;
use App\Services\FinanceService;
use App\Services\Production\OwnProduction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * İstehsal: hazır mallar (öz məhsullarımız, resept, partiyalar), xammal, xammal alışları, tədarükçülər.
 * Hesablamalar — OwnProduction.
 */
class ProductionController extends Controller
{
    public function __construct(private OwnProduction $production) {}

    // ───────────── Hazır mallar ─────────────

    public function products(): View
    {
        return view('backend.production.products', [
            'products' => OwnProduct::with(['product.brand', 'variant.size'])->get()->sortBy(fn ($p) => $p->label())->values(),
            'stocks' => $this->production->productStocks(),
            'waiting' => $this->production->waiting(),
        ]);
    }

    /** "Yeni məhsul" pəncərəsi: kataloqdan məhsulun ölçüsünü axtarır */
    public function variants(Request $request): JsonResponse
    {
        $q = trim(preg_replace('/\s+/u', ' ', strip_tags((string) $request->query('q'))));
        if (mb_strlen($q) < 2) {
            return response()->json([]);
        }
        $like = '%'.addcslashes($q, '%_\\').'%';
        $taken = OwnProduct::pluck('product_variant_id')->flip();

        $variants = ProductVariant::with(['product.brand', 'size'])
            ->whereHas('product', fn ($p) => $p->where('name', 'like', $like)->orWhereHas('brand', fn ($b) => $b->where('name', 'like', $like)))
            ->orderByDesc('product_id')->limit(30)->get();

        return response()->json($variants->map(fn ($v) => [
            'id' => $v->id,
            'label' => trim(html_entity_decode((string) $v->product?->brand?->name).' '.$v->product?->name).($v->size ? ' · '.$v->size->name_az : ''),
            'taken' => isset($taken[$v->id]),
        ])->values());
    }

    public function storeProduct(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'product_variant_id' => ['required', 'integer', 'exists:product_variants,id', Rule::unique('own_products', 'product_variant_id')],
        ], ['product_variant_id.required' => 'Məhsulu axtarıb seçin.', 'product_variant_id.unique' => 'Bu ölçü artıq öz məhsullarımızdadır.']);
        $variant = ProductVariant::findOrFail($data['product_variant_id']);
        $own = OwnProduct::create(['product_id' => $variant->product_id, 'product_variant_id' => $variant->id]);

        return redirect()->route('admin.production.product', $own)->with('success', 'Məhsul əlavə olundu. İndi reseptini yazın.');
    }

    public function product(OwnProduct $own): View
    {
        $own->load(['product.brand', 'variant.size', 'recipe.material', 'batches.materials.material', 'batches.creator']);
        $stocks = $this->production->productStocks();

        return view('backend.production.product', [
            'own' => $own,
            'stock' => $stocks[$own->id] ?? ['produced' => 0, 'held' => 0, 'stock' => 0],
            'waiting' => $this->production->waiting()[$own->id] ?? null,
            'materials' => RawMaterial::where('active', true)->orderBy('name')->get(),
            'materialStocks' => $this->production->materialStocks(),
        ]);
    }

    public function updateRecipe(Request $request, OwnProduct $own): RedirectResponse
    {
        $data = $request->validate([
            'recipe' => ['nullable', 'array', 'max:30'],
            'recipe.*.raw_material_id' => ['required', 'integer', 'distinct', 'exists:raw_materials,id'],
            'recipe.*.quantity' => ['required', 'numeric', 'gt:0', 'max:100000'],
        ], ['recipe.*.raw_material_id.distinct' => 'Eyni xammal iki dəfə yazılıb.', 'recipe.*.quantity.gt' => 'Miqdar 0-dan böyük olmalıdır.'],
            ['recipe.*.quantity' => 'miqdar', 'recipe.*.raw_material_id' => 'xammal']);

        DB::transaction(function () use ($own, $data) {
            $own->recipe()->delete();
            foreach ($data['recipe'] ?? [] as $row) {
                $own->recipe()->create(['raw_material_id' => $row['raw_material_id'], 'quantity' => round((float) $row['quantity'], 3)]);
            }
        });

        return back()->with('success', 'Resept yadda saxlanıldı.');
    }

    public function toggleProduct(OwnProduct $own): RedirectResponse
    {
        $own->forceFill(['active' => !$own->active])->save();

        return back()->with('success', $own->active ? 'Məhsul aktivdir — az qalanda ana səhifədə bildiriş görünür.' : 'Məhsul deaktiv edildi — ana səhifədə bildiriş görünmür.');
    }

    public function produce(Request $request, OwnProduct $own): RedirectResponse
    {
        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'produced_at' => ['required', 'date', 'before_or_equal:today'],
            'lines' => ['nullable', 'array', 'max:30'],
            'lines.*.raw_material_id' => ['required', 'integer', 'exists:raw_materials,id'],
            'lines.*.quantity' => ['nullable', 'numeric', 'min:0', 'max:10000000'],
            'extra_cost' => ['nullable', 'numeric', 'min:0', 'max:10000000'],
            'note' => ['nullable', 'string', 'max:500'],
        ], ['produced_at.before_or_equal' => 'Gələcək tarix yazılmaz.'], ['quantity' => 'say', 'produced_at' => 'tarix', 'extra_cost' => 'əlavə xərc']);

        $batch = $this->production->produce($own, (int) $data['quantity'], $data['produced_at'], $data['lines'] ?? [],
            (float) ($data['extra_cost'] ?? 0), $data['note'] ?? null, (int) auth('admin')->id());

        return back()->with('success', $batch->quantity.' ədəd istehsal edildi. Bir şüşənin mayası: '.number_format((float) $batch->unit_cost, 2).' AZN.');
    }

    /** Sifarişin məhsulunu öz anbardan götür (CRM / Depo təminatı) */
    public function allocate(Order $order, OrderItem $item): RedirectResponse
    {
        abort_unless((int) $item->order_id === (int) $order->id, 404);
        $allocation = $this->production->allocate($order, $item, (int) auth('admin')->id());

        return back()->with('success', 'Öz anbarımızdan '.$allocation->quantity.' ədəd götürüldü (maya '.number_format((float) $allocation->unit_cost, 2).' AZN).');
    }

    // ───────────── Xammal ─────────────

    public function materials(): View
    {
        return view('backend.production.materials', [
            'materials' => RawMaterial::orderByDesc('active')->orderBy('name')->get(),
            'stocks' => $this->production->materialStocks(),
        ]);
    }

    public function storeMaterial(Request $request): RedirectResponse
    {
        $data = $this->validateMaterial($request);
        $material = RawMaterial::create($data + ['active' => true]);

        return redirect()->route('admin.production.material', $material)->with('success', 'Xammal əlavə olundu.');
    }

    public function material(RawMaterial $material): View
    {
        $movements = $material->movements()->with(['purchase.supplier', 'batch.ownProduct.product.brand', 'batch.ownProduct.variant.size', 'user'])
            ->orderByDesc('occurred_at')->orderByDesc('id')->paginate(50);

        return view('backend.production.material', [
            'material' => $material, 'movements' => $movements, 'stock' => (float) ($this->production->materialStocks()[$material->id] ?? 0),
        ]);
    }

    public function updateMaterial(Request $request, RawMaterial $material): RedirectResponse
    {
        $data = $this->validateMaterial($request, $material) + ['active' => $request->boolean('active')];
        $material->update($data);

        return back()->with('success', 'Xammal yeniləndi.');
    }

    private function validateMaterial(Request $request, ?RawMaterial $material = null): array
    {
        // Hərəkəti olan xammalın vahidi dəyişmir (miqdarlar köhnə vahidlədir)
        $locked = $material && $material->movements()->exists();

        return $request->validate([
            'name' => ['required', 'string', 'max:150', Rule::unique('raw_materials', 'name')->ignore($material?->id)],
            'unit' => ['required', Rule::in($locked ? [$material->unit] : array_keys(RawMaterial::UNITS))],
            'note' => ['nullable', 'string', 'max:500'],
        ], ['name.unique' => 'Bu adda xammal var.', 'unit.in' => $locked ? 'Hərəkəti olan xammalın vahidi dəyişdirilmir.' : 'Vahidi seçin.'], ['name' => 'ad', 'unit' => 'vahid']);
    }

    // ───────────── Xammal alışları ─────────────

    public function purchases(FinanceService $finance): View
    {
        return view('backend.production.purchases', [
            'purchases' => MaterialPurchase::with(['supplier', 'lines.material', 'creator'])->orderByDesc('purchased_at')->orderByDesc('id')->paginate(50),
            'materials' => RawMaterial::where('active', true)->orderBy('name')->get(),
            'suppliers' => FinanceAccount::where('type', 'supplier')->where('active', true)->orderBy('name')->get(),
            'accounts' => FinanceAccount::whereIn('type', MoneyMovement::KINDS['supplier_payment'][1])->where('active', true)->orderBy('type')->orderBy('name')->get(),
            'balances' => $finance->balances(),
        ]);
    }

    public function storePurchase(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'purchased_at' => ['required', 'date', 'before_or_equal:today'],
            'payment' => ['required', Rule::in(array_keys(MaterialPurchase::PAYMENTS))],
            'supplier_account_id' => ['nullable', 'required_unless:payment,opening', 'integer'],
            'account_id' => ['nullable', 'required_if:payment,paid', 'integer'],
            'note' => ['nullable', 'string', 'max:500'],
            'lines' => ['required', 'array', 'min:1', 'max:30'],
            'lines.*.raw_material_id' => ['required', 'integer', 'exists:raw_materials,id'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0', 'max:10000000'],
            'lines.*.unit_cost' => ['required', 'numeric', 'min:0', 'max:1000000'],
        ], [
            'supplier_account_id.required_unless' => 'Tədarükçünü seçin.',
            'account_id.required_if' => 'Pulun çıxdığı hesabı seçin.',
            'purchased_at.before_or_equal' => 'Gələcək tarix yazılmaz.',
        ], ['purchased_at' => 'tarix', 'lines.*.quantity' => 'miqdar', 'lines.*.unit_cost' => '1 vahidin qiyməti', 'lines.*.raw_material_id' => 'xammal']);

        $purchase = $this->production->purchase($data, $data['lines'], (int) auth('admin')->id());

        return redirect()->route('admin.production.purchase', $purchase)->with('success', 'Alış yazıldı: '.number_format((float) $purchase->total, 2).' AZN'
            .match ($purchase->payment) { 'paid' => ' — balansdan ödənildi.', 'debt' => ' — tədarükçüyə borc.', default => ' — başlanğıc qalıq.' });
    }

    public function purchase(MaterialPurchase $purchase): View
    {
        $purchase->load(['supplier', 'lines.material', 'creator']);
        $payment = MoneyMovement::with('from')->where('kind', 'supplier_payment')->where('note', 'Xammal alışı #'.$purchase->id)->first();

        return view('backend.production.purchase', ['purchase' => $purchase, 'payment' => $payment]);
    }

    // ───────────── Tədarükçülər ─────────────

    public function suppliers(): View
    {
        $suppliers = FinanceAccount::where('type', 'supplier')->orderByDesc('active')->orderBy('name')->get();
        $bought = MaterialPurchase::whereNotNull('supplier_account_id')->selectRaw('supplier_account_id, COUNT(*) as c, MAX(purchased_at) as last')
            ->groupBy('supplier_account_id')->get()->keyBy('supplier_account_id');

        return view('backend.production.suppliers', ['suppliers' => $suppliers, 'debts' => $this->production->supplierDebts(), 'bought' => $bought]);
    }

    public function storeSupplier(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:255'],
        ], [], ['name' => 'ad']);
        $supplier = FinanceAccount::create($data + ['type' => 'supplier']);

        return redirect()->route('admin.production.supplier', $supplier)->with('success', 'Tədarükçü əlavə olundu.');
    }

    public function supplier(FinanceAccount $supplier, FinanceService $finance): View
    {
        abort_unless($supplier->type === 'supplier', 404);

        return view('backend.production.supplier', [
            'supplier' => $supplier,
            'debt' => $this->production->supplierDebts()[$supplier->id] ?? 0,
            'purchases' => MaterialPurchase::with('lines.material')->where('supplier_account_id', $supplier->id)->orderByDesc('purchased_at')->orderByDesc('id')->get(),
            'payments' => MoneyMovement::with(['from', 'user', 'reversedBy'])->where('kind', 'supplier_payment')->where('to_account_id', $supplier->id)
                ->orderByDesc('occurred_at')->orderByDesc('id')->get(),
            'accounts' => FinanceAccount::whereIn('type', MoneyMovement::KINDS['supplier_payment'][1])->where('active', true)->orderBy('type')->orderBy('name')->get(),
            'balances' => $finance->balances(),
        ]);
    }

    public function updateSupplier(Request $request, FinanceAccount $supplier): RedirectResponse
    {
        abort_unless($supplier->type === 'supplier', 404);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:255'],
        ], [], ['name' => 'ad']);
        $supplier->update($data + ['active' => $request->boolean('active')]);

        return back()->with('success', 'Tədarükçü yeniləndi.');
    }

    public function paySupplier(Request $request, FinanceAccount $supplier): RedirectResponse
    {
        abort_unless($supplier->type === 'supplier', 404);
        $data = $request->validate([
            'account_id' => ['required', 'integer'],
            'amount' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:10000000'],
            'note' => ['nullable', 'string', 'max:255'],
        ], ['account_id.required' => 'Pulun çıxdığı hesabı seçin.'], ['amount' => 'məbləğ']);
        $from = FinanceAccount::findOrFail($data['account_id']);
        $this->production->paySupplier($supplier, $from, (float) $data['amount'], $data['note'] ?? null, (int) auth('admin')->id());

        return back()->with('success', number_format((float) $data['amount'], 2).' AZN ödənildi.');
    }
}
