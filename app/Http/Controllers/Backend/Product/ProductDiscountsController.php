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
 * Məhsul endirimi: məhsulun redaktə səhifəsində "Endirim" tabı (yarat / redaktə / vaxtından əvvəl bitir / sil)
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
        [$percent, $startsAt, $endsAt] = $this->validated($request, $product);

        $product->discounts()->create([
            'percent' => $percent,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'created_by' => auth('admin')->id(),
        ]);
        $this->notifySubscribers($product, $startsAt);

        return redirect()->to(route('admin.product.edit', $product->id).'#product-discount')->with('success', 'Endirim əlavə olundu.');
    }

    /** Aktiv endirimdə faiz və bitmə tarixi, planlaşdırılmışda hamısı dəyişir; bitmiş endirim dəyişmir */
    public function update(Request $request, ProductDiscount $discount): RedirectResponse
    {
        $status = $discount->status();
        if ($status === 'ended') {
            return back()->with('error', 'Bitmiş endirim redaktə olunmur — yenisini əlavə edin.');
        }
        $product = $discount->product;
        $oldPercent = (float) $discount->percent;
        // aktiv endirimin başlama vaxtı artıq keçib — dəyişmir
        if ($status === 'active') {
            $request->merge(['starts_at' => $discount->starts_at->format('Y-m-d H:i:s')]);
        }
        [$percent, $startsAt, $endsAt] = $this->validated($request, $product, $discount);

        $discount->update(['percent' => $percent, 'starts_at' => $startsAt, 'ends_at' => $endsAt]);
        // faiz artıbsa və ya başlama dəyişibsə — abunəçilərə (job qiyməti işə düşəndə yoxlayır)
        if ((float) $percent > $oldPercent || $status === 'scheduled') {
            $this->notifySubscribers($product, $startsAt);
        }

        return redirect()->to(route('admin.product.edit', $product->id).'#product-discount')->with('success', 'Endirim yeniləndi.');
    }

    /**
     * @return array{0: float, 1: Carbon, 2: Carbon}
     */
    private function validated(Request $request, Product $product, ?ProductDiscount $ignore = null): array
    {
        // redaktə xətaları ayrıca çantada — "Yeni endirim" formasının sahələrində görünməsin
        $bag = $ignore ? 'discountEdit' : 'discount';
        $data = $request->validateWithBag($bag, [
            'percent' => ['required', 'numeric', 'min:1', 'max:90'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['required', 'date'],
        ], [], ['percent' => 'faiz', 'starts_at' => 'başlama tarixi', 'ends_at' => 'bitmə tarixi']);

        // datetime-local — saytın vaxt qurşağında (Asia/Baku); boş və ya keçmiş başlama — indi
        $startsAt = $data['starts_at'] ? Carbon::parse($data['starts_at']) : now();
        if ($startsAt->lt(now()) && !($ignore && $ignore->status() === 'active')) {
            $startsAt = now();
        }
        $endsAt = Carbon::parse($data['ends_at']);
        if ($endsAt->lte($startsAt) || $endsAt->lte(now())) {
            throw ValidationException::withMessages(['ends_at' => 'Bitmə tarixi başlama tarixindən və indidən sonra olmalıdır.'])->errorBag($bag);
        }
        // bitmiş endirimlər (ends_at ≤ indi) tarixçədir — yeni endirimə mane olmur
        $overlap = $product->discounts()
            ->when($ignore, fn ($q) => $q->whereKeyNot($ignore->id))
            ->where('ends_at', '>', now())
            ->where('starts_at', '<', $endsAt)->where('ends_at', '>', $startsAt)
            ->exists();
        if ($overlap) {
            throw ValidationException::withMessages(['starts_at' => 'Bu dövrdə məhsulun başqa endirimi var — onu redaktə edin, bitirin və ya silin.'])->errorBag($bag);
        }

        return [(float) $data['percent'], $startsAt, $endsAt];
    }

    /** Endirim qiymətin enməsidir: "Qiymət enəndə xəbər ver" abunəçilərinə — başlayanda */
    private function notifySubscribers(Product $product, Carbon $startsAt): void
    {
        foreach ($product->variants()->where('active', 1)->pluck('id') as $variantId) {
            NotifyPriceDrop::dispatch((int) $variantId)->delay($startsAt->isFuture() ? $startsAt : null)->afterCommit();
        }
    }

    /** Aktiv endirimi indi bitir (tarixçədə qalır); dəqiqənin əvvəlinə — eyni dəqiqədən yenisi başlaya bilsin */
    public function end(ProductDiscount $discount): RedirectResponse
    {
        if ($discount->isActive()) {
            $discount->update(['ends_at' => now()->startOfMinute()->max($discount->starts_at)]);
        }

        return back()->with('success', 'Endirim bitirildi.');
    }

    /** İstənilən endirimi sil: planlaşdırılmış — ləğv, aktiv — dərhal dayanır, bitmiş — tarixçədən çıxır */
    public function destroy(ProductDiscount $discount): RedirectResponse
    {
        $discount->delete();

        return back()->with('success', 'Endirim silindi.');
    }
}
