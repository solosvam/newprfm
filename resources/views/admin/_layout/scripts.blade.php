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
@yield('js_page')
<script src="{{asset('backend/js/common.js')}}"></script>
<script src="{{asset('backend/js/custom.js')}}"></script>
<script src="{{asset('backend/js/scripts.js')}}"></script>
<script src="{{asset('backend/js/vendor/bootstrap-notify.min.js')}}"></script>
<script src="{{asset('backend/js/cs/scrollspy.js')}}"></script>
<!-- Page Specific Scripts End -->
<script>
    let urls = {
        assets: "{{ asset('backend/') }}",
        ajaxurls: {
            setRolePermission: "{{ route('ajax.set-role-permission') }}"
        }
    }

    @if(Session::has('success') || Session::has('error'))
    jQuery.notify(
        {
            title: 'Bildiriş!',
            message: "{{ Session::has('success') ? Session::get('success') : Session::get('error') }}"
        },
        {
            type: "{{ Session::has('success') ? 'success' : 'danger' }}",
            delay: 5000,
            placement: {
                from: 'top',
                align: 'right',
            },
            allow_dismiss: false
        }
    );
    @endif

    @if($errors->any())
    @php($delay = 2000)
    @foreach ($errors->all() as $error)
    @php($delay += 1000)
    jQuery.notify(
        {
            title: 'Bildiriş!',
            message: "{{$error}}"
        },
        {
            type: 'danger',
            delay: {{$delay}},
            allow_dismiss: false
        }
    );
    @endforeach
    @endif
</script>
