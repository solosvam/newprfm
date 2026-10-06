{{--
  Məhsul → "Endirim" tabı (ProductDiscountsController). Digər tablarla birlikdə əsas məhsul formasının içindədir —
  forma içində forma olmur, ona görə sahələr form="…" ilə kənardakı formalara bağlanır (partials/discount-forms).
  Faiz bütün ölçülərə aiddir, endirimi hamı (qonaqlar da) görür.
--}}
@php
    $discounts = $product->discounts;
    $current = $discounts->first(fn ($d) => $d->status() !== 'ended');
    $statusLabels = ['active' => ['Aktiv', 'bg-success'], 'scheduled' => ['Planlaşdırılıb', 'bg-outline-primary'], 'ended' => ['Bitib', 'bg-outline-muted']];
    $discountErrors = $errors->getBag('discount');
    $minPrice = (float) $product->variants->where('active', 1)->min('price');
@endphp
<div class="tab-pane fade" id="product-discount" role="tabpanel">
    <div class="row g-4">
        <div class="col-12 col-lg-5">
            <h2 class="small-title">Yeni endirim</h2>
            <div class="card"><div class="card-body">
                <div class="mb-3">
                    <label class="form-label" for="discount-percent">Endirim faizi *</label>
                    <div class="input-group">
                        <input type="number" step="0.01" min="1" max="90" id="discount-percent" name="percent" form="productDiscountForm" value="{{ old('percent') }}"
                               @class(['form-control', 'is-invalid' => $discountErrors->has('percent')]) required data-discount-percent data-min-price="{{ $minPrice }}">
                        <span class="input-group-text">%</span>
                    </div>
                    @error('percent', 'discount')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    <div class="form-text" data-discount-preview>Bütün ölçülərə tətbiq olunur.</div>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-sm-6">
                        <label class="form-label" for="discount-starts">Başlama</label>
                        <input type="datetime-local" id="discount-starts" name="starts_at" form="productDiscountForm" value="{{ old('starts_at') }}"
                               @class(['form-control', 'is-invalid' => $discountErrors->has('starts_at')])>
                        <div class="form-text">Boş — dərhal başlayır</div>
                        @error('starts_at', 'discount')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label" for="discount-ends">Bitmə *</label>
                        <input type="datetime-local" id="discount-ends" name="ends_at" form="productDiscountForm" value="{{ old('ends_at') }}" required
                               @class(['form-control', 'is-invalid' => $discountErrors->has('ends_at')])>
                        @error('ends_at', 'discount')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                </div>
                <p class="text-small text-muted mb-3">Endirimli məhsula promo kod tətbiq olunmur. "Qiymət enəndə xəbər ver" abunəçilərinə endirim başlayanda bildiriş gedir.</p>
                <button type="submit" form="productDiscountForm" class="btn btn-primary" @disabled($current && $current->status() === 'active')>Endirimi əlavə et</button>
                @if($current && $current->status() === 'active')
                    <div class="form-text text-warning">Məhsulun aktiv endirimi var — yenisini əlavə etmək üçün əvvəlcə onu bitirin.</div>
                @endif
            </div></div>
        </div>

        <div class="col-12 col-lg-7">
            <h2 class="small-title">Endirimlər</h2>
            <div class="card"><div class="card-body">
                @if($discounts->isEmpty())
                    <div class="text-muted text-center py-4">Bu məhsulun endirimi olmayıb</div>
                @else
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead><tr>
                                <th class="text-muted text-small text-uppercase">Faiz</th>
                                <th class="text-muted text-small text-uppercase">Dövr</th>
                                <th class="text-muted text-small text-uppercase">Status</th>
                                <th></th>
                            </tr></thead>
                            <tbody>
                            @foreach($discounts as $discount)
                                @php [$label, $class] = $statusLabels[$discount->status()]; @endphp
                                <tr>
                                    <td class="fw-bold">−{{ $discount->percentLabel() }}%</td>
                                    <td class="text-small text-nowrap">{{ $discount->starts_at->format('d.m.Y H:i') }}<br>{{ $discount->ends_at->format('d.m.Y H:i') }}</td>
                                    <td><span class="badge {{ $class }}">{{ $label }}</span></td>
                                    <td class="text-end">
                                        @if($discount->status() === 'active')
                                            <button type="submit" form="discount-end-{{ $discount->id }}" class="btn btn-sm btn-outline-danger" onclick="return confirm('Endirim indi bitirilsin?')">Bitir</button>
                                        @elseif($discount->status() === 'scheduled')
                                            <button type="submit" form="discount-delete-{{ $discount->id }}" class="btn btn-sm btn-outline-danger" onclick="return confirm('Planlaşdırılmış endirim silinsin?')">Sil</button>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div></div>
        </div>
    </div>
</div>
<script>
    // Endirimli qiymətin önizləməsi (ən ucuz ölçü üzrə)
    (() => {
        const input = document.querySelector('[data-discount-percent]');
        const preview = document.querySelector('[data-discount-preview]');
        if (!input || !preview) return;
        const base = Number(input.dataset.minPrice || 0);
        const render = () => {
            const percent = Number(input.value || 0);
            preview.textContent = base > 0 && percent > 0 && percent <= 90
                ? `Bütün ölçülərə tətbiq olunur. Məs. ${base.toFixed(2)} ₼ → ${(Math.round(base * (100 - percent)) / 100).toFixed(2)} ₼`
                : 'Bütün ölçülərə tətbiq olunur.';
        };
        input.addEventListener('input', render);
        render();
    })();
    // Endirim tabında məhsulun "Yadda saxla" / "Məhsulu sil" düymələri gizlənir (endirimin öz düymələri var)
    document.addEventListener('shown.bs.tab', (event) => {
        const actions = document.querySelector('[data-product-actions]');
        if (actions) actions.hidden = event.target.getAttribute('href') === '#product-discount';
    });
    // #product-discount ilə açılanda (əlavə/xəta sonrası) — Endirim tabı açıq gəlsin
    document.addEventListener('DOMContentLoaded', () => {
        if (location.hash === '#product-discount' || {{ $discountErrors->any() ? 'true' : 'false' }}) {
            const tab = document.querySelector('a[href="#product-discount"]');
            if (tab && window.bootstrap) bootstrap.Tab.getOrCreateInstance(tab).show();
        }
    });
</script>
