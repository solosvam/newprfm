{{-- Endirimdəki kartın sol yuxarı küncündəki lent: "20% endirim" (Product::visibleDiscount) --}}
@if($discount = $product->visibleDiscount())
    <span class="card-ribbon">{{ __('discount_ribbon', ['percent' => $discount->percentLabel()]) }}</span>
@endif
