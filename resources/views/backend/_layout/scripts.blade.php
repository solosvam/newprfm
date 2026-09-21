<!-- Vendor Scripts Start -->
<script src="{{asset('backend/js/vendor/jquery-3.5.1.min.js')}}"></script>
<script src="{{asset('backend/js/vendor/bootstrap.bundle.min.js')}}"></script>
<script src="{{asset('backend/js/vendor/OverlayScrollbars.min.js')}}"></script>
<script src="{{asset('backend/js/vendor/autoComplete.min.js')}}"></script>
<script src="{{asset('backend/js/vendor/clamp.min.js')}}"></script>

<script src="{{asset('backend/icon/acorn-icons.js')}}"></script>
<script src="{{asset('backend/icon/acorn-icons-interface.js')}}"></script>
<script src="{{asset('backend/icon/acorn-icons-commerce.js')}}"></script>
@yield('js_vendor')
<!-- Vendor Scripts End -->
<!-- Template Base Scripts Start -->
<script src="{{asset('backend/js/base/helpers.js')}}"></script>
<script src="{{asset('backend/js/base/globals.js')}}"></script>
<script src="{{asset('backend/js/base/nav.js')}}"></script>
<script src="{{asset('backend/js/base/search.js')}}"></script>
<script src="{{asset('backend/js/base/settings.js')}}"></script>
<!-- Template Base Scripts End -->
<!-- Page Specific Scripts Start -->

<script src="{{asset('backend/js/common.js')}}"></script>
<script src="{{asset('backend/js/scripts.js')}}"></script>
<script src="{{asset('backend/js/vendor/bootstrap-notify.min.js')}}"></script>
<script src="{{asset('backend/js/pages/dashboard.default.js')}}"></script>
<script src="{{asset('backend/js/forms/controls.datepicker.js')}}"></script>
<script src="{{asset('backend/js/vendor/datepicker/bootstrap-datepicker.min.js')}}"></script>
<script src="{{asset('backend/js/vendor/datepicker/locales/bootstrap-datepicker.es.min.js')}}"></script>
<!-- Page Specific Scripts End -->
@yield('js_page')
<script src="{{asset('backend/js/custom.js')}}"></script>
<script>
    let urls = {
        assets: "{{ asset('backend/') }}",
        ajaxurls: {
            setRolePermission: "{{ route('admin.ajax.set-role-permission') }}"
        }
    }

    @if(session('success') || session('error'))

    @php
        $type = session('success') ? 'success' : 'danger';
        $message = session('success') ?? session('error');
        $sound = session('success') ? 'success.mp3' : 'error.mp3';
    @endphp

    jQuery.notify(
        {
            title: 'Bildiriş!',
            message: @json($message)
        },
        {
            type: '{{ $type }}',
            delay: 3000,
            allow_dismiss: false
        }
    );

    new Audio('/backend/sound/{{ $sound }}').play();

    @endif

    @if($errors->any())
    @php($delay = 20000)
    @foreach ($errors->all() as $error)
    @php($delay += 10000)
    jQuery.notify(
        {
            title: 'Bildiriş!',
            message: "{{$error}}"
        },
        {
            type: 'danger',
            delay: {{$delay}},
            allow_dismiss: true
        }
    );
    @endforeach
    new Audio('/backend/sound/error.mp3').play();
    @endif
</script>
