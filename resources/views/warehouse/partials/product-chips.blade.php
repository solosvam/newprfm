{{--
  Anbar portalı: məhsulun cinsi (Qadın üçün / Kişi üçün) və növü (Eau de Parfum / Eau de Toilette …).
  Parametr: $product (genders, type yüklənmiş)
--}}
@php
    $genders = $product?->genders?->pluck('name_az')->filter()
        ->map(fn ($name) => str_contains(mb_strtolower($name), 'üçün') ? $name : $name.' üçün')
        ->implode(' / ');
    $type = $product?->type?->name_az;
@endphp
@if($genders)<span class="wp-chip wp-chip--gender">{{ $genders }}</span>@endif
@if($type)<span class="wp-chip wp-chip--type">{{ $type }}</span>@endif
