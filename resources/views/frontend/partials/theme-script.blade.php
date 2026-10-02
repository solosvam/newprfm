<script src="{{ asset_v('frontend/js/vendor/jquery.min.js') }}"></script>
<script src="{{ asset_v('frontend/js/vendor/notify.min.js') }}"></script>
<script src="{{ asset_v('frontend/js/select2.full.min.js') }}"></script>
<script defer src="{{ asset_v('frontend/js/cart-store.js') }}"></script>
<script defer src="{{ asset_v('frontend/js/main.js') }}"></script>
@if(config('services.onesignal.app_id'))
    <script defer src="https://cdn.onesignal.com/sdks/web/v16/OneSignalSDK.page.js"></script>
    <script defer src="{{ asset_v('frontend/js/push.js') }}"></script>
@endif
