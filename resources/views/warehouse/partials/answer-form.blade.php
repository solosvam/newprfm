{{--
  Anbar cavabı forması: ilk cavab ($offer = null) və ya düzəliş ($offer = düzəldilən cavab, "replaces").
  Parametrlər: $item, $token, $quantity (tələb), $offer
--}}
@php
    $restore = (string) old('form_item') === (string) $item->id;
    $choice = $restore ? old('availability') : ($offer
        ? ($offer->available_quantity <= 0 ? 'unavailable' : ($offer->available_quantity >= $quantity ? 'available' : 'partial'))
        : null);
    $qtyValue = $restore ? old('quantity') : ($choice === 'partial' ? $offer?->available_quantity : '');
    $costValue = $restore ? old('unit_cost') : ($offer?->unit_cost !== null ? number_format((float) $offer->unit_cost, 2, '.', '') : '');
    $noteValue = $restore ? old('note') : $offer?->note;
@endphp
<form method="POST" action="{{ route('warehouse.answer', ['token' => $token, 'item' => $item->id]) }}" class="wp-form" data-wp-form>
    @csrf
    <input type="hidden" name="form_item" value="{{ $item->id }}">
    @if($offer)<input type="hidden" name="replaces" value="{{ $offer->id }}">@endif

    <fieldset class="wp-choices">
        <legend>Mövcudluq</legend>
        <label class="wp-choice wp-choice--yes">
            <input type="radio" name="availability" value="available" required @checked($choice === 'available')>
            <span><b>Var</b><small>{{ $quantity }} ədəd</small></span>
        </label>
        @if($quantity > 1)
            <label class="wp-choice wp-choice--part">
                <input type="radio" name="availability" value="partial" @checked($choice === 'partial')>
                <span><b>Qismən</b><small>az sayda</small></span>
            </label>
        @endif
        <label class="wp-choice wp-choice--no">
            <input type="radio" name="availability" value="unavailable" @checked($choice === 'unavailable')>
            <span><b>Yoxdur</b></span>
        </label>
    </fieldset>

    <div class="wp-fields">
        <label class="wp-field" data-field="quantity" hidden>
            <span>Neçə ədəd var?</span>
            <input type="number" name="quantity" min="1" max="{{ max(1, $quantity - 1) }}" inputmode="numeric" value="{{ $qtyValue }}">
        </label>
        <label class="wp-field" data-field="price" hidden>
            <span>Bir ədədin qiyməti</span>
            <span class="wp-money"><input type="number" name="unit_cost" min="0.01" max="9999999999.99" step="0.01" inputmode="decimal" placeholder="0.00" value="{{ $costValue }}"><em>AZN</em></span>
        </label>
        <label class="wp-field">
            <span>Qeyd <small>(istəyə bağlı)</small></span>
            <textarea name="note" rows="2" maxlength="1000" placeholder="Məs.: sabah axşam hazır olar">{{ $noteValue }}</textarea>
        </label>
    </div>

    <button type="submit" class="wp-submit">{{ $offer ? 'Düzəlişi yadda saxla' : 'Cavabı göndər' }}</button>
</form>
