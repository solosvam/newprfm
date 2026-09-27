@extends('frontend.layouts.app')
@section('content')
<main class="checkout-page">
<h1 class="cart-title">{{ __('credit_address_title') }}</h1>
<div class="checkout-grid">
<section class="account-panel">
<h2 class="checkout-section-title">{{ __('checkout_delivery_address') }}</h2>
@if($addresses->isNotEmpty())
<select id="addressSelect" class="brand-select checkout-address-select">
@foreach($addresses as $a)<option value="{{ $a->id }}">{{ $a->label }}</option>@endforeach
<option value="new">{{ __('checkout_add_new_address') }}</option>
</select>
@else<input type="hidden" id="addressSelect" value="new">@endif
<div id="newAddress" class="checkout-address-form {{ $addresses->isNotEmpty() ? 'checkout-hidden' : '' }}">
<div class="checkout-fields">
@foreach(['addressTitle'=>'checkout_address_name_home_work','city'=>'checkout_city','district'=>'checkout_district','address'=>'checkout_street_and_address','building'=>'checkout_building','entrance'=>'checkout_entrance','floor'=>'checkout_floor','apartment'=>'checkout_apartment'] as $field=>$translation)
<div class="checkout-field"><input type="text" id="{{ $field }}" placeholder="{{ __($translation) }}"></div>
@endforeach
</div>
<div class="checkout-field checkout-field--textarea"><textarea id="addressNote" placeholder="{{ __('checkout_address_note') }}"></textarea></div>
</div>
<div id="creditAddressError" class="form-alert form-alert-error checkout-error" role="alert" hidden></div>
</section>
<aside class="cart-summary checkout-summary">
<h2 class="checkout-section-title">{{ __('checkout_your_order') }}</h2>
<p><strong>{{ $variant->product->brand?->name }} {{ $variant->product->name }}</strong></p>
<p>{{ $variant->size?->{'name_'.app()->getLocale()} ?: $variant->size?->name_az }}</p>
<p>{{ $period->month }} {{ __('product_month') }}</p>
<div class="cart-summary__row"><span>{{ __('product_price') }}</span><strong>{{ number_format($price,2) }} ₼</strong></div>
<div class="cart-summary__row"><span>{{ __('credit_modal_monthly') }}</span><strong>{{ number_format($monthly,2) }} ₼</strong></div>
<div class="cart-summary__row cart-summary__total"><span>{{ __('credit_modal_total') }}</span><strong>{{ number_format($total,2) }} ₼</strong></div>
<button type="button" class="btn btn-dark checkout-submit" id="confirmCreditOrder">{{ __('credit_address_confirm') }}</button>
<a href="{{ route('product',$variant->product->slug) }}">{{ __('credit_address_back') }}</a>
</aside>
</div>
</main>
@endsection
@section('page-scripts')
<script>window.creditAddressConfig={url:@json(route('credit.application.confirm')),csrf:@json(csrf_token()),error:@json(__('credit_modal_error'))};</script>
<script src="{{ asset('frontend/js/credit-address.js') }}" defer></script>
@endsection
