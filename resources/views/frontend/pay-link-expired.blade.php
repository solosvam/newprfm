{{-- SMS ödəniş linkinin vaxtı bitib (PayLinkController::show). Sifarişin heç bir məlumatı göstərilmir. --}}
@extends('frontend.layouts.app')

@section('page-css')
<link rel="stylesheet" href="{{ asset_v('frontend/css/pages/order-detail.css') }}">
<link rel="stylesheet" href="{{ asset_v('frontend/css/pages/pay-link.css') }}">
@endsection

@section('content')
    <main class="paylink">
        <div class="paylink__state paylink__state--muted" role="status">
            <span class="paylink__state-icon" aria-hidden="true">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
            </span>
            <div>
                <strong>{{ __('paylink_expired') }}</strong>
                <span>{{ __('paylink_expired_hint') }}</span>
            </div>
        </div>

        @if($contact->phoneUrl())
            <a href="{{ $contact->phoneUrl() }}" class="btn btn-dark paylink__btn">{{ __('paylink_expired_call') }}</a>
        @endif
        @if($contact->whatsappUrl())
            <a href="{{ $contact->whatsappUrl() }}" class="btn btn-outline paylink__btn" rel="noopener">{{ __('paylink_expired_whatsapp') }}</a>
        @endif
    </main>
@endsection
