@extends('frontend.layout')
@section('content')
<main><div class="container"><div class="cart-page">
    <div class="cart-breadcrumb"><a href="{{ route('home') }}">← {{ __('Geri qayıt') }}</a><h1>{{ __('Səbət') }}</h1></div>
    <div class="cart-layout">
        <div id="cartItems" class="cart-items"></div>
        <aside class="cart-summary">
            <div id="cartSummaryLines"></div>
            <hr>
            <div><span>{{ __('Ümumi məbləğ:') }}</span><strong id="cartSubtotal">0.00 ₼</strong></div>
            <div><span>{{ __('Endirim məbləği:') }}</span><strong>0.00 ₼</strong></div>
            <div class="cart-summary__total"><span>{{ __('Yekun məbləğ:') }}</span><strong id="cartTotal">0.00 ₼</strong></div>
            @auth<a class="cart-checkout-button" href="{{ route('checkout') }}">{{ __('Sifarişi rəsmiləşdir') }}</a>@else<a class="cart-checkout-button" href="{{ route('front.login', ['redirect' => route('checkout')]) }}">{{ __('Sifarişi rəsmiləşdir') }}</a>@endauth
        </aside>
    </div>
    <div id="cartEmpty" class="cart-empty"><p>{{ __('Səbətiniz boşdur.') }}</p><a href="{{ route('home') }}">{{ __('Məhsullara bax') }}</a></div>
</div></div></main>
@endsection
@section('page-styles')
<style>
.cart-page{padding:42px 0 80px}.cart-breadcrumb{margin-bottom:26px}.cart-breadcrumb h1{font-size:22px;margin-top:24px}.cart-layout{display:grid;grid-template-columns:minmax(0,1fr) 310px;gap:32px;align-items:start}.cart-items{border-top:1px solid #d6d6d6}.cart-row{display:grid;grid-template-columns:160px minmax(0,1fr) auto;gap:20px;padding:24px 0;border-bottom:1px solid #d6d6d6}.cart-row__image{width:160px;height:180px;background:#f2f2f2;display:flex;align-items:center;justify-content:center}.cart-row__image img{max-width:115px;max-height:135px;object-fit:contain}.cart-row__name{font-size:18px;font-weight:500}.cart-row__brand,.cart-row__meta{color:#b8b4b4;margin-top:5px}.cart-row__price{font-size:22px;font-weight:500;margin-top:18px}.cart-row__bottom{display:flex;align-items:center;gap:16px;margin-top:26px}.cart-row__qty{display:flex;border:1px solid #ccc;height:46px}.cart-row__qty button,.cart-row__qty span{width:42px;border:0;background:#fff;display:flex;align-items:center;justify-content:center}.cart-remove{border:0;background:#fff;color:#999}.cart-summary{border:1px solid #c8c8c8;padding:28px}.cart-summary>div>div,.cart-summary>div:not(#cartSummaryLines){display:flex;justify-content:space-between;gap:16px;margin:15px 0}.cart-summary hr{border:0;border-top:1px solid #bbb;margin:18px 0}.cart-summary__total strong{font-size:20px}.cart-summary button,.cart-checkout-button{box-sizing:border-box;display:block;width:100%;border:0;background:#232a38;color:#fff;padding:16px;margin-top:18px;text-align:center;text-decoration:none}.cart-empty{display:none;padding:60px 20px;text-align:center;background:#f7f7f7}.cart-empty a{display:inline-block;margin-top:18px;background:#232a38;color:#fff;padding:13px 24px}@media(max-width:768px){.cart-layout{grid-template-columns:1fr}.cart-row{grid-template-columns:95px 1fr}.cart-row__image{width:95px;height:120px}.cart-row__actions{grid-column:2}.cart-summary{width:100%}}
</style>
@endsection
@section('page-scripts')
<script>
const cartProductUrl = @json(route('cart.products'));
const cartMessages = {remove: @json(__('Səbətdən sil'))};
function getCart(){try{return JSON.parse(localStorage.getItem('parfumshop_cart')||'[]')}catch(e){return []}}
function saveCart(cart){localStorage.setItem('parfumshop_cart',JSON.stringify(cart));window.dispatchEvent(new CustomEvent('parfumshop:cart-updated',{detail:cart}));renderCart()}
async function renderCart(){
 const cart=getCart(),items=document.getElementById('cartItems'),empty=document.getElementById('cartEmpty'),layout=document.querySelector('.cart-layout');
 if(!cart.length){layout.style.display='none';empty.style.display='block';return}
 layout.style.display='grid';empty.style.display='none';
 const ids=cart.map(i=>i.variant_id);
 const res=await fetch(cartProductUrl+'?variants='+encodeURIComponent(ids.join(','))); const products=await res.json();
 let total=0; items.innerHTML=''; document.getElementById('cartSummaryLines').innerHTML='';
 cart.forEach(item=>{const p=products.find(x=>x.variant_id===item.variant_id);if(!p)return;const line=p.price*item.quantity;total+=line;
 items.insertAdjacentHTML('beforeend',`<div class="cart-row"><div class="cart-row__image">${p.image?'<img src="'+p.image+'" alt="">':''}</div><div><div class="cart-row__name">${p.name}</div><div class="cart-row__brand">${p.brand||''}</div><div class="cart-row__meta">${p.gender||''}${p.type?' | '+p.type:''}</div><div class="cart-row__price">${Number(p.price).toFixed(2)} ₼</div><div class="cart-row__bottom"><span>${p.size||''}</span><div class="cart-row__qty"><button data-action="minus" data-id="${item.variant_id}">-</button><span>${item.quantity}</span><button data-action="plus" data-id="${item.variant_id}">+</button></div><button class="cart-remove" data-action="remove" data-id="${item.variant_id}">${cartMessages.remove}</button></div></div></div>`);
 document.getElementById('cartSummaryLines').insertAdjacentHTML('beforeend',`<div><span>${p.name}</span><strong>${line.toFixed(2)} ₼</strong></div>`)});
 document.getElementById('cartSubtotal').textContent=total.toFixed(2)+' ₼';document.getElementById('cartTotal').textContent=total.toFixed(2)+' ₼';
}
document.addEventListener('click',e=>{const b=e.target.closest('[data-action]');if(!b)return;let cart=getCart(),id=Number(b.dataset.id),item=cart.find(x=>x.variant_id===id);if(!item)return;if(b.dataset.action==='plus')item.quantity++;if(b.dataset.action==='minus')item.quantity=Math.max(1,item.quantity-1);if(b.dataset.action==='remove')cart=cart.filter(x=>x.variant_id!==id);saveCart(cart)});
renderCart();
</script>
@endsection
