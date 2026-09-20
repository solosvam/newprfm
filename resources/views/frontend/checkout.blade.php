@extends('frontend.layout')
@section('content')
<main><div class="container"><div class="checkout-page">
<h1>Sifarişi rəsmiləşdir</h1><div class="checkout-grid"><section>
<h2>Çatdırılma ünvanı</h2>
@if($addresses->isNotEmpty())<select id="addressSelect">@foreach($addresses as $a)<option value="{{ $a->id }}">{{ $a->label }}</option>@endforeach<option value="new">+ Yeni ünvan əlavə et</option></select>@else<input type="hidden" id="addressSelect" value="new">@endif
<div id="newAddress" class="{{ $addresses->isNotEmpty() ? 'checkout-hidden' : '' }}"><div class="checkout-fields"><input id="addressTitle" placeholder="Ünvan adı (Ev, İş...)"><input id="city" placeholder="Şəhər *"><input id="district" placeholder="Rayon"><input id="address" placeholder="Küçə və ünvan *"><input id="building" placeholder="Bina"><input id="entrance" placeholder="Blok"><input id="floor" placeholder="Mərtəbə"><input id="apartment" placeholder="Mənzil"></div><textarea id="addressNote" placeholder="Ünvan qeydi"></textarea></div>
<h2>Ödəniş üsulu</h2><div class="checkout-payment">@foreach($paymentMethods as $m)<label><input type="radio" name="payment_method" value="{{ $m->id }}" @checked($loop->first)> <span>{{ $m->name }}</span></label>@endforeach</div>
<h2>Əlavə seçimlər</h2><label class="checkout-check"><input type="checkbox" id="giftWrap" value="1"><span>Sifariş hədiyyəlik bükülsün</span></label><textarea id="customerNote" placeholder="Sifarişlə bağlı qeydiniz"></textarea><div id="checkoutError" class="profile-errors"></div>
</section><aside class="checkout-summary"><h2>Sifarişiniz</h2><div id="checkoutItems"></div><div class="checkout-total"><span>Yekun</span><strong id="checkoutTotal">0.00 ₼</strong></div><button id="placeOrder">Sifarişi təsdiqlə</button></aside></div>
</div></div></main>
@endsection
@section('page-scripts')
<script>
const checkoutStore=@json(route('checkout.store')),cartProducts=@json(route('cart.products')),csrf=@json(csrf_token());
const getCheckoutCart=()=>{try{return JSON.parse(localStorage.getItem('parfumshop_cart')||'[]')}catch(e){return[]}};
const addressSelect=document.getElementById('addressSelect'),newAddressBox=document.getElementById('newAddress');
if(addressSelect&&addressSelect.tagName==='SELECT')addressSelect.addEventListener('change',()=>newAddressBox.classList.toggle('checkout-hidden',addressSelect.value!=='new'));
(async()=>{const cart=getCheckoutCart();if(!cart.length){location.href=@json(route('cart'));return}const r=await fetch(cartProducts+'?variants='+cart.map(x=>x.variant_id).join(','));const ps=await r.json();let total=0;ps.forEach(p=>{const x=cart.find(i=>i.variant_id===p.variant_id),line=p.price*x.quantity;total+=line;document.getElementById('checkoutItems').insertAdjacentHTML('beforeend','<div><span><b>'+((p.brand ? p.brand+\' \' : \'\')+p.name)+'</b><small>'+((p.size ? p.size+\' · \' : \'\')+\'× \'+x.quantity)+'</small></span><strong>'+line.toFixed(2)+' ₼</strong></div>')});document.getElementById('checkoutTotal').textContent=total.toFixed(2)+' ₼'})();
document.getElementById('placeOrder').onclick=async function(){const b=this;b.disabled=true;const isNew=!addressSelect||addressSelect.value==='new';const val=id=>document.getElementById(id)?.value||null;const body={cart:getCheckoutCart(),address_mode:isNew?'new':'existing',address_id:isNew?null:Number(addressSelect.value),title:val('addressTitle'),city:val('city'),district:val('district'),address:val('address'),building:val('building'),entrance:val('entrance'),floor:val('floor'),apartment:val('apartment'),address_note:val('addressNote'),payment_method_id:Number(document.querySelector('[name=payment_method]:checked')?.value),gift_wrap:document.getElementById('giftWrap').checked?1:0,customer_note:val('customerNote')};try{const r=await fetch(checkoutStore,{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrf},body:JSON.stringify(body)});const d=await r.json();if(!r.ok)throw new Error(d.message||'Xəta baş verdi');localStorage.removeItem('parfumshop_cart');location.href=d.redirect}catch(e){document.getElementById('checkoutError').textContent=e.message;b.disabled=false}};
</script>
@endsection