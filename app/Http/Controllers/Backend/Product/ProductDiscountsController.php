<?php

namespace App\Http\Controllers\Backend\Product;

use App\Http\Controllers\Controller;
use App\Jobs\NotifyPriceDrop;
use App\Models\Product\Product;
use App\Models\Product\ProductDiscount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Məhsul endirimi: məhsulun redaktə səhifəsində "Endirim" tabı (yarat / vaxtından əvvəl bitir / planlaşdırılanı sil)
 * və Satış → "Endirimdəki məhsullar" siyahısı.
 */
class ProductDiscountsController extends Controller
{
    public function index(Request $request): View
    {
        $status = in_array($request->query('status'), ['active', 'scheduled', 'ended'], true) ? $request->query('status') : 'active';

        $discounts = ProductDiscount::query()
            ->with(['product.brand', 'product.images' => fn ($images) => $images->limit(1),
                'product.variants' => fn ($variants) => $variants->where('active', 1)->orderBy('price')])
            ->when($status === 'active', fn ($q) => $q->active()->orderBy('ends_at'))
            ->when($status === 'scheduled', fn ($q) => $q->where('starts_at', '>', now())->orderBy('starts_at'))
            ->when($status === 'ended', fn ($q) => $q->where('ends_at', '<=', now())->orderByDesc('ends_at'))
            ->paginate(30)->withQueryString();

        $counts = [
            'active' => ProductDiscount::active()->count(),
            'scheduled' => ProductDiscount::where('starts_at', '>', now())->count(),
        ];

        return view('backend.product_menu.discounts.index', compact('discounts', 'status', 'counts'));
    }

    public function store(Request $request, Product $product): RedirectResponse
    {
        $data = $request->validate([
            'percent' => ['required', 'numeric', 'min:1', 'max:90'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['required', 'date'],
        ], [], ['percent' => 'faiz', 'starts_at' => 'başlama tarixi', 'ends_at' => 'bitmə tarixi']);

        // datetime-local — saytın vaxt qurşağında (Asia/Baku); boş başlama — indi
        $startsAt = $data['starts_at'] ? Carbon::parse($data['starts_at']) : now();
        $endsAt = Carbon::parse($data['ends_at']);
        if ($endsAt->lte($startsAt) || $endsAt->lte(now())) {
            throw ValidationException::withMessages(['ends_at' => 'Bitmə tarixi başlama tarixindən və indidən sonra olmalıdır.'])->errorBag('discount');
        }
        $overlap = $product->discounts()->where('starts_at', '<', $endsAt)->where('ends_at', '>', $startsAt)->exists();
        if ($overlap) {
            throw ValidationException::withMessages(['starts_at' => 'Bu dövrdə məhsulun başqa endirimi var — əvvəlcə onu bitirin və ya silin.'])->errorBag('discount');
        }

        $product->discounts()->create([
            'percent' => $data['percent'],
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'created_by' => auth('admin')->id(),
        ]);

        // Endirim qiymətin enməsidir: "Qiymət enəndə xəbər ver" abunəçilərinə — başlayanda
        foreach ($product->variants()->where('active', 1)->pluck('id') as $variantId) {
            NotifyPriceDrop::dispatch((int) $variantId)->delay($startsAt->isFuture() ? $startsAt : null)->afterCommit();
        }

        return redirect()->to(route('admin.product.edit', $product->id).'#product-discount')->with('success', 'Endirim əlavə olundu.');
    }

    /** Aktiv endirimi indi bitir (tarixçədə qalır) */
    public function end(ProductDiscount $discount): RedirectResponse
    {
        if ($discount->isActive()) {
            $discount->update(['ends_at' => now()]);
        }

        return back()->with('success', 'Endirim bitirildi.');
    }

    /** Hələ başlamamış endirimi sil */
    public function destroy(ProductDiscount $discount): RedirectResponse
    {
        if ($discount->status() !== 'scheduled') {
            return back()->with('error', 'Yalnız hələ başlamamış endirim silinir. Aktiv endirimi "Bitir" ilə dayandırın.');
        }
        $discount->delete();

        return back()->with('success', 'Planlaşdırılmış endirim silindi.');
    }
}
