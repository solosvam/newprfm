{{-- Tester ətir: şəklin sol yuxarı küncündə 45° lent (Product::isTester). Valideyndə position: relative və overflow: hidden olmalıdır. --}}
@if($product->isTester())
    <span class="tester-ribbon">Tester</span>
@endif
