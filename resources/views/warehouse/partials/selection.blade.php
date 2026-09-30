{{--
  Anbar portalı → "Seçilənlər": operator bu anbarın təklifini seçib.
  Seçilib / Anbara bildirildi → "Rezerv etdim" və ya "Problem var"; Rezervdə / Problem — yalnız vəziyyət.
  Parametrlər: $allocation (orderItem.product.brand, orderItem.variant.size, logs), $token
--}}
@php
    $product = $allocation->orderItem?->product;
    $size = $allocation->orderItem?->variant?->size?->name_az;
    $open = in_array($allocation->status, \App\Services\WarehousePortalService::CONFIRMABLE, true);
    $restore = (string) old('form_allocation') === (string) $allocation->id;
    $problemNote = $allocation->status === 'problem' ? $allocation->logs->where('to_status', 'problem')->last()?->note : null;
@endphp
<article class="wp-card" id="allocation-{{ $allocation->id }}">
    <header class="wp-card__head">
        <div class="wp-card__brand">{{ $product?->brand?->name ?? 'Məhsul' }}</div>
        <h2 class="wp-card__title">{{ $product?->name ?? 'Məhsul məlumatı yoxdur' }}</h2>
        <div class="wp-chips">
            @if($size)<span class="wp-chip">{{ $size }}</span>@endif
            <span class="wp-chip wp-chip--need">Seçildi: {{ $allocation->quantity }} ədəd</span>
            <span class="wp-chip">{{ number_format((float) $allocation->unit_cost, 2) }} AZN / ədəd</span>
        </div>
        <div class="wp-card__meta">Seçim #{{ $allocation->id }} · {{ $allocation->created_at?->format('d.m.Y H:i') }}</div>
    </header>

    @if($open)
        <form method="POST" action="{{ route('warehouse.confirm', ['token' => $token, 'allocation' => $allocation->id]) }}" data-wp-once>
            @csrf
            <input type="hidden" name="action" value="reserve">
            <button type="submit" class="wp-submit">Rezerv etdim</button>
        </form>
        <details class="wp-edit wp-problem" @if($restore) open @endif>
            <summary><span class="wp-edit__open">Problem var</span><span class="wp-edit__close">Bağla</span></summary>
            <form method="POST" action="{{ route('warehouse.confirm', ['token' => $token, 'allocation' => $allocation->id]) }}" class="wp-form" data-wp-problem data-wp-once>
                @csrf
                <input type="hidden" name="action" value="problem">
                <input type="hidden" name="form_allocation" value="{{ $allocation->id }}">
                <fieldset class="wp-choices wp-choices--stack">
                    <legend>Nə baş verib?</legend>
                    @foreach(\App\Services\WarehousePortalService::PORTAL_PROBLEMS as $value => $label)
                        <label class="wp-choice wp-choice--no">
                            <input type="radio" name="problem_type" value="{{ $value }}" required @checked($restore && old('problem_type') === $value)>
                            <span><b>{{ $label }}</b></span>
                        </label>
                    @endforeach
                </fieldset>
                <div class="wp-fields">
                    <label class="wp-field" data-field="price" hidden>
                        <span>Yeni qiymət (bir ədəd)</span>
                        <span class="wp-money"><input type="number" name="unit_cost" min="0.01" max="9999999999.99" step="0.01" inputmode="decimal" placeholder="0.00" value="{{ $restore ? old('unit_cost') : '' }}"><em>AZN</em></span>
                    </label>
                    <label class="wp-field">
                        <span>Qeyd</span>
                        <textarea name="note" rows="2" maxlength="1000" placeholder="Məs.: 1 ədəd qalıb">{{ $restore ? old('note') : '' }}</textarea>
                    </label>
                </div>
                <button type="submit" class="wp-submit wp-submit--danger">Operatora bildir</button>
            </form>
        </details>
    @elseif($allocation->status === 'reserved')
        <div class="wp-answer is-yes"><strong>✓ Rezervdədir</strong><span>kuryer götürəcək</span></div>
    @else
        <div class="wp-answer is-no"><strong>Problem bildirilib</strong>@if($problemNote)<span>{{ $problemNote }}</span>@endif</div>
        <div class="wp-card__meta">Operator sizinlə əlaqə saxlayacaq.</div>
    @endif
</article>
