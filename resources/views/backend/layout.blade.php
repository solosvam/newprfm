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
    @include('backend._layout.head')
</head>

<body>
<div id="root">
    <div id="nav" class="nav-container d-flex" @isset($custom_nav_data) @foreach ($custom_nav_data as $key=> $value)
    data-{{$key}}="{{$value}}"
        @endforeach
        @endisset
    >
        @include('backend._layout.nav')
    </div>
    <main>
        @yield('content')
    </main>
    @include('backend._layout.footer')
</div>


@include('backend._layout.modal_settings')
@include('backend._layout.modal_search')
@include('backend._layout.scripts')

</body>

</html>
