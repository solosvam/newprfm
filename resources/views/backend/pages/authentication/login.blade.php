@php
    $title = 'Login';
    $description = 'Panelə giriş';
@endphp
@extends('backend.layout_full',['title'=>$title, 'description'=>$description])
@section('js_vendor')
    <script src="{{asset('backend/js/vendor/bootstrap-notify.min.js')}}"></script>
@endsection

@section('js_page')
    @if(Session::has('success'))
        <script>
            jQuery.notify(
                {title: 'Uğurlu!', message: '{{Session::get('success')}}', icon:'cs-check'},
                {
                    type: 'success',
                    delay: 3000,
                    placement: {
                        from: 'top',
                        align: 'right',
                    },
                },
            );
        </script>
    @endif
@endsection
@section('content_left')
    <div class="min-h-100 d-flex align-items-center">
        <div class="w-100 w-lg-75 w-xxl-50">
            <div>
                <div class="mb-5">
                    <h1 class="display-3 text-white">Qoxuya</h1>
                    <h1 class="display-3 text-white">Hazırsan?</h1>
                </div>
                <p class="h6 text-white lh-1-5 mb-5">
                    Həyatınızın ən ətirli səhifəsi.
                </p>
                <div class="mb-5">

                </div>
            </div>
        </div>
    </div>
@endsection

@section('content_right')
    <div class="sw-lg-70 min-h-100 bg-foreground d-flex justify-content-center align-items-center shadow-deep py-5 full-page-content-right-border">
        <div class="sw-lg-50 px-5">
            <div class="sh-11">
                <a href="/">
                    <div class="logo-default"></div>
                </a>
            </div>
            <div class="mb-5">
                <h2 class="cta-1 mb-0 text-primary">Xoş gəldin,</h2>
                <h2 class="cta-1 text-primary">başlayaq!</h2>
            </div>
            <div>
                <form id="loginForm" class="tooltip-end-bottom" action="{{ route('admin.login.submit') }}" method="POST">
                    @csrf
                    <div class="mb-3 filled form-group tooltip-end-top">
                        <i data-acorn-icon="email"></i>
                        <input class="form-control" placeholder="Email" name="email" value="{{old('email')}}" />
                        @if($errors->has('email'))
                            <span class="invalid-feedback" role="alert">
                                <strong>{{ $errors->first('login') }}</strong>
                            </span>
                        @enderror
                    </div>
                    <div class="mb-3 filled form-group tooltip-end-top">
                        <i data-acorn-icon="lock-off"></i>
                        <input class="form-control pe-7" name="password" type="password" placeholder="Parol" />
                        {!! validationResult('password',$errors) !!}
                        <a class="text-small position-absolute t-3 e-3" href="#">Unutdun?</a>
                    </div>
                    <button type="submit" class="btn btn-lg btn-primary">Daxil ol</button>
                </form>
            </div>
        </div>
    </div>
@endsection
