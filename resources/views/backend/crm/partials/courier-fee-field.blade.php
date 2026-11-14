{{--
  Əlavə çatdırılma (uzaq ünvan): məbləğ + sürətli seçim (5, 10 AZN). Müştərinin yekununa əlavə olunur,
  tam məbləğ sifarişi çatdıran kuryerin qazancıdır — şirkətin satışına daxil deyil (CourierFees).
  $id — input id; $value — mövcud məbləğ (0 — yoxdur). Düymə dəyəri yazıb "input" hadisəsi göndərir (yekun yenilənsin).
--}}
@php $value = (float) ($value ?? 0); @endphp
<label for="{{ $id }}" class="form-label mb-1">Çatdırılma haqqı <span class="text-muted small">(uzaq ünvan)</span></label>
<div class="input-group courier-fee-field">
    @foreach(\App\Services\Courier\CourierFees::PRESETS as $preset)
        <button type="button" class="btn btn-outline-primary" data-courier-fee-preset="{{ $preset }}" data-courier-fee-target="{{ $id }}">{{ $preset }} ₼</button>
    @endforeach
    <input type="number" class="form-control" id="{{ $id }}" name="courier_fee" min="0" max="{{ \App\Services\Courier\CourierFees::MAX }}" step="0.01"
           inputmode="decimal" placeholder="0.00" value="{{ $value > 0 ? number_format($value, 2, '.', '') : '' }}">
    <span class="input-group-text">₼</span>
</div>
<div class="form-text">Müştəri "Çatdırılma" kimi ödəyir. Tam məbləğ sifarişi çatdıran kuryerin qazancıdır — satışa və mənfəətə daxil deyil.</div>
@once
    <script>
        document.addEventListener('click', event => {
            const button = event.target.closest('[data-courier-fee-preset]');
            const input = button && document.getElementById(button.dataset.courierFeeTarget);
            if (!input) return;
            input.value = Number(button.dataset.courierFeePreset).toFixed(2);
            input.dispatchEvent(new Event('input', { bubbles: true }));
        });
    </script>
@endonce
