@extends('frontend.layout')
@section('content')
<main><div class="container"><div class="checkout-page">
<h1>{{ __('cart_checkout') }}</h1><div class="checkout-grid"><section>
<h2>{{ __('checkout_delivery_address') }}</h2>
@if($addresses->isNotEmpty())<select id="addressSelect">@foreach($addresses as $a)<option value="{{ $a->id }}">{{ $a->label }}</option>@endforeach<option value="new">{{ __('checkout_add_new_address') }}</option></select>@else<input type="hidden" id="addressSelect" value="new">@endif
<div id="newAddress" class="{{ $addresses->isNotEmpty() ? 'checkout-hidden' : '' }}">
<div class="checkout-fields">
<div class="checkout-field"><input type="text" id="addressTitle" placeholder="{{ __('checkout_address_name_home_work') }}"></div>
<div class="checkout-field"><input type="text" id="city" placeholder="{{ __('checkout_city') }}"></div>
<div class="checkout-field"><input type="text" id="district" placeholder="{{ __('checkout_district') }}"></div>
<div class="checkout-field"><input type="text" id="address" placeholder="{{ __('checkout_street_and_address') }}"></div>
<div class="checkout-field"><input type="text" id="building" placeholder="{{ __('checkout_building') }}"></div>
<div class="checkout-field"><input type="text" id="entrance" placeholder="{{ __('checkout_entrance') }}"></div>
<div class="checkout-field"><input type="text" id="floor" placeholder="{{ __('checkout_floor') }}"></div>
<div class="checkout-field"><input type="text" id="apartment" placeholder="{{ __('checkout_apartment') }}"></div>
</div>
<div class="checkout-field checkout-field--textarea"><textarea id="addressNote" placeholder="{{ __('checkout_address_note') }}"></textarea></div>
</div>
<h2>{{ __('checkout_payment_method') }}</h2><div class="checkout-payment">@foreach($paymentMethods as $m)<label><input type="radio" name="payment_method" value="{{ $m->id }}" @checked($loop->first)> <span>{{ $m->name }}</span></label>@endforeach</div>
<h2>{{ __('checkout_additional_options') }}</h2><label class="checkout-check"><input type="checkbox" id="giftWrap" value="1"><span>{{ __('checkout_gift_wrap_the_order') }}</span></label><textarea id="customerNote" placeholder="{{ __('checkout_order_note') }}"></textarea><div id="checkoutError" class="profile-errors"></div>
</section><aside class="checkout-summary"><h2>{{ __('checkout_your_order') }}</h2><div id="checkoutItems"></div><div class="checkout-total"><span>{{ __('checkout_total') }}</span><strong id="checkoutTotal">0.00 ₼</strong></div><button id="placeOrder">{{ __('checkout_confirm_order') }}</button></aside></div>
</div></div></main>
@endsection
@section('page-scripts')
<script>
const checkoutStore=@json(route('checkout.store')),cartProducts=@json(route('cart.products')),csrf=@json(csrf_token());
const checkoutMessages={error:@json(__('auth_something_went_wrong'))};
const getCheckoutCart=()=>{try{return JSON.parse(localStorage.getItem('parfumshop_cart')||'[]')}catch(e){return[]}};
const addressSelect=document.getElementById('addressSelect'),newAddressBox=document.getElementById('newAddress');
function syncAddressForm(){
    if(!addressSelect || !newAddressBox) return;
    newAddressBox.classList.toggle('checkout-hidden', addressSelect.value !== 'new');
}
if(addressSelect){
    addressSelect.addEventListener('change', syncAddressForm);
    addressSelect.addEventListener('input', syncAddressForm);
    syncAddressForm();
}
(async()=>{
    const cart=getCheckoutCart();
    if(!cart.length){ location.href=@json(route('cart')); return; }
    const r=await fetch(cartProducts+'?variants='+cart.map(x=>x.variant_id).join(','));
    const ps=await r.json();
    let total=0;
    ps.forEach(p=>{
        const x=cart.find(i=>i.variant_id===p.variant_id);
        const line=p.price*x.quantity;
        total+=line;
        const productTitle=(p.brand ? p.brand+' ' : '')+p.name;
        const productMeta=(p.size ? p.size+' · ' : '')+'× '+x.quantity;
        document.getElementById('checkoutItems').insertAdjacentHTML('beforeend',
            '<div><span><b>'+productTitle+'</b><small>'+productMeta+'</small></span><strong>'+line.toFixed(2)+' ₼</strong></div>'
        );
    });
    document.getElementById('checkoutTotal').textContent=total.toFixed(2)+' ₼';
})();
document.getElementById('placeOrder').onclick=async function(){const b=this;b.disabled=true;const isNew=!addressSelect||addressSelect.value==='new';const val=id=>document.getElementById(id)?.value||null;const body={cart:getCheckoutCart(),address_mode:isNew?'new':'existing',address_id:isNew?null:Number(addressSelect.value),title:val('addressTitle'),city:val('city'),district:val('district'),address:val('address'),building:val('building'),entrance:val('entrance'),floor:val('floor'),apartment:val('apartment'),address_note:val('addressNote'),payment_method_id:Number(document.querySelector('[name=payment_method]:checked')?.value),gift_wrap:document.getElementById('giftWrap').checked?1:0,customer_note:val('customerNote')};try{const r=await fetch(checkoutStore,{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrf},body:JSON.stringify(body)});const d=await r.json();if(!r.ok){
    document.querySelectorAll('.checkout-field.is-invalid').forEach(el=>el.classList.remove('is-invalid'));
    if(d.errors){
        const fieldMap={title:'addressTitle',city:'city',address:'address'};
        Object.keys(d.errors).forEach(key=>{
            const input=document.getElementById(fieldMap[key]||key);
            if(input) input.closest('.checkout-field')?.classList.add('is-invalid');
        });
    }
    const errors=d.errors?Object.values(d.errors).flat():[];
    throw new Error(errors.length?errors.join(' | '):(d.message||checkoutMessages.error));
}
document.querySelectorAll('.checkout-field.is-invalid').forEach(el=>el.classList.remove('is-invalid'));localStorage.removeItem('parfumshop_cart');location.href=d.redirect}catch(e){const message=e.message||checkoutMessages.error;document.getElementById('checkoutError').textContent='';if(window.jQuery&&typeof jQuery.notify==='function'){jQuery.notify(message,{className:'error',position:'top right',autoHideDelay:5000});}else{document.getElementById('checkoutError').textContent=message;}b.disabled=false}};
</script>
@endsection