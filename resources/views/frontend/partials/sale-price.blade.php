{{--
  Ölçünün qiyməti: endirim bu ziyarətçiyə aiddirsə — köhnə qiymət üstündən xətt + endirimli (ProductVariant::salePrice).
  Parametrlər: $variant (product.activeDiscount yüklənməlidir).
--}}
@php
    $regular = (float) $variant->price;
    $sale = $variant->salePrice();
@endphp
{{-- &nbsp; — məbləğ və ₼ ayrı sətrə düşməsin; köhnə və yeni qiymət də ayrı-ayrı bütöv qalır --}}
@if($sale < $regular)<s class="price-old">{{ number_format($regular, 2) }}</s> <span class="price-sale">{{ number_format($sale, 2) }}&nbsp;₼</span>@else<span class="price-regular">{{ number_format($regular, 2) }}&nbsp;₼</span>@endif
