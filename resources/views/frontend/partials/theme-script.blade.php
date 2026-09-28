<script src="{{ asset_v('frontend/js/vendor/jquery.min.js') }}"></script>
<script src="{{ asset_v('frontend/js/vendor/notify.min.js') }}"></script>
<script src="{{ asset_v('frontend/js/select2.full.min.js') }}"></script>
<script defer src="{{ asset_v('frontend/js/main.js') }}"></script>
<script>
    document.getElementById('theme-toggle').addEventListener('click', function () {
        var html = document.documentElement;
        var next = html.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
        html.setAttribute('data-theme', next);
        localStorage.setItem('theme', next);
    });

    $(document).ready(function () {
        if ($.fn.select2) {
            $('.select2').select2({
                placeholder: @json(__('home_select_brand')),
                width: '100%'
            });
        }
    });
</script>
