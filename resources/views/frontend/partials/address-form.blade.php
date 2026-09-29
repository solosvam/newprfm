{{--
    Yeni ünvan forması — checkout və kredit ünvanı səhifələri.
    Lazımdır: $cities (App\Models\City::forSelect()). JS sahələri id ilə oxuyur.
--}}
<div class="address-form">
    <div class="checkout-fields">
        <div class="checkout-field">
            <input type="text" id="addressTitle" maxlength="50" placeholder="{{ __('checkout_address_name_home_work') }}">
        </div>
        <div class="checkout-field address-form__city">
            <select id="city" class="select2" data-placeholder="{{ __('checkout_city') }}">
                <option value=""></option>
                @foreach($cities as $city)
                    <option value="{{ $city->id }}">{{ $city->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="checkout-field checkout-field--full">
            <input type="text" id="address" maxlength="500" placeholder="{{ __('checkout_street_and_address') }}">
        </div>
    </div>

    <button type="button" class="address-form__toggle" data-address-details-toggle aria-expanded="false" aria-controls="addressDetails">
        {{ __('checkout_address_details') }}
    </button>

    <div class="checkout-fields address-form__details" id="addressDetails" hidden>
        <div class="checkout-field"><input type="text" id="building" maxlength="50" placeholder="{{ __('checkout_building') }}"></div>
        <div class="checkout-field"><input type="text" id="entrance" maxlength="50" placeholder="{{ __('checkout_entrance') }}"></div>
        <div class="checkout-field"><input type="text" id="floor" maxlength="30" placeholder="{{ __('checkout_floor') }}"></div>
        <div class="checkout-field"><input type="text" id="apartment" maxlength="30" placeholder="{{ __('checkout_apartment') }}"></div>
    </div>

    <div class="checkout-field address-form__note">
        <textarea id="addressNote" maxlength="1000" placeholder="{{ __('checkout_address_extra_info') }}"></textarea>
    </div>
</div>
