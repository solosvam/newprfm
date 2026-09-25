@extends('frontend.layout')

@section('content')
<main>
    <div class="container">
        <div class="cabinet">
            @include('frontend.partials.cabinet-sidebar')
            <div class="cabinet__sections">
                <div class="cabinet__sections__elem bonus">
                    <h2>{{ __('profile_your_bonus_balance') }}</h2>
                    <p>0,00 AZN</p>
                    <a href="#">
                        <img src="{{ asset('frontend/images/arrow-bonus.svg') }}" alt="">
                        <span>{{ __('profile_view_your_bonus_history') }}</span>
                    </a>
                </div>

                <div class="cabinet__sections__elem last-order">
                    <h2>{{ __('profile_your_latest_order') }}</h2>
                    <p>{{ __('profile_you_have_no_orders_yet') }}</p>
                </div>

                <div class="cabinet__sections__elem order-location">
                    <h2>{{ __('profile_where_is_my_order') }}</h2>
                    <p>{{ __('profile_no_active_orders') }}</p>
                </div>

                <div class="cabinet__sections__elem get-birkart">
                    <div class="info">
                        <h3>{{ __('profile_get_a_birkart') }}
                            <svg width="22" height="9" viewBox="0 0 22 9" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M0.648804 4.5L20.6488 4.5" stroke="black" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/>
                                <path d="M17.3931 1.24609L20.6489 4.50191L17.3931 7.75772" stroke="black" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </h3>
                        <span>{{ __('profile_having_trouble_ordering_on_credit_apply_for_a_birkart') }}</span>
                    </div>
                    <img src="{{ asset('frontend/images/birbank.png') }}" alt="">
                </div>

                <div class="cabinet__sections__elem invite-friend">
                    <ul>
                        <li>
                            <h1>{{ __('profile_invite_a_friend_and_earn_bonuses') }}</h1>
                            <button type="button" id="inviteButton">{{ __('profile_send_invitation') }}</button>
                        </li>
                        <li><img src="{{ asset('frontend/images/invite.png') }}" alt=""></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</main>

@endsection

