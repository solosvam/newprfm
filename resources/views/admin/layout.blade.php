<!DOCTYPE html>
<html lang="en" data-url-prefix="/" data-footer="true"
@isset($html_tag_data)
    @foreach ($html_tag_data as $key=> $value) data-{{$key}}='{{$value}}' @endforeach
@endisset
>

<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1"/>
    <title>ParfumShop | {{$title}}</title>
    <meta name="description" content=""/>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @include('admin._layout.head')
</head>

<body>
<div id="root">
    <div id="nav" class="nav-container d-flex" @isset($custom_nav_data) @foreach ($custom_nav_data as $key=> $value)
    data-{{$key}}="{{$value}}"
        @endforeach
        @endisset
    >
        @include('admin._layout.nav')
    </div>
    <main>
        @yield('content')
    </main>
    @include('admin._layout.footer')
</div>

<div class="position-fixed bottom-0 end-0 p-3" style="z-index: 5">
    <div id="liveToast" class="toast hide" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="toast-header text-white">
            <strong class="me-auto" id="toast-head"></strong>
            <button type="button" class="btn-close text-white" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
        <div class="toast-body text-white" id="toastMessage"></div>
    </div>
</div>

@include('admin._layout.modal_settings')
@include('admin._layout.modal_search')
@include('admin._layout.scripts')

</body>

</html>
