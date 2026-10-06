{{-- Endirim tabının formaları (əsas məhsul formasından kənarda; tabdakı sahələr form="…" ilə bağlanır) --}}
<form id="productDiscountForm" method="POST" action="{{ route('admin.product-discounts.store', $product) }}">@csrf</form>
@foreach($product->discounts as $discount)
    @if($discount->status() === 'active')
        <form id="discount-end-{{ $discount->id }}" method="POST" action="{{ route('admin.product-discounts.end', $discount) }}">@csrf</form>
    @elseif($discount->status() === 'scheduled')
        <form id="discount-delete-{{ $discount->id }}" method="POST" action="{{ route('admin.product-discounts.destroy', $discount) }}">@csrf @method('DELETE')</form>
    @endif
@endforeach
