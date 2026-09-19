@php
    $title = 'Şifrə bərpası';
    $description = 'Şifrə bərpası'
@endphp
@extends('backend.layout_full',['title'=>$title, 'description'=>$description])
@section('js_vendor')
    <script src="{{asset('backend/js/vendor/imask.js')}}"></script>
@endsection
@section('js_page')
    <script src="{{asset('backend/js/forms/inputmask.js')}}"></script>
    @if(session()->has('requested'))
    <script>
        let seconds = {{ session()->get('requested')->otp->remaining }};
        let interval = setInterval(function(){
            seconds -= 1;
            if(seconds === 0){
                location.reload();
                clearInterval(interval);
            }
            $("#seconds").text(seconds);
        }, 1000);
    </script>
    @endif
@endsection
@section('content_left')
    <div class="min-h-100 d-flex align-items-center">
        <div class="w-100 w-lg-75 w-xxl-50">
            <div>
                <div class="mb-5">
                    <h1 class="display-3 text-white">Diqqət !</h1>
                    <h1 class="display-3 text-white">Təhlükəsizlik haqqında</h1>
                </div>
                <p class="h6 text-white lh-1-5 mb-5">
                    Şifrənizi heçvaxt cihazın yaddaşında saxlamayın. Bu sizin adınızdan sistemdə əməliyyatlar aparılmasının qarşısını alır.
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
                <h2 class="cta-1 mb-0 text-primary">Parol getdi?</h2>
                <h2 class="cta-1 text-primary">Gəl sıfırlayaq !</h2>
            </div>
            @if ($errors->has('attempts') || $errors->has('attempts'))
                <div class="alert alert-danger">
                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
            <div>
                @if(session()->has('requested'))
                <div class="mb-5">
                    <p class="h6">Mobil nömrənizə göndərilən 6 rəqəmli kodu yazın.</p>
                </div>
                <form id="forgotPasswordForm" class="tooltip-end-bottom" action="{{ route('backend.handleotp') }}" method="POST">
                    @csrf
                    <div class="mb-3 filled form-group tooltip-end-top">
                        <i data-acorn-icon="mobile"></i>
                        <input type="text" class="form-control" placeholder="6 rəqəmli kod" id="maskOtp" name="otp"/>
                        {!! validationResult('otp',$errors) !!}
                    </div>
                    <button type="submit" class="btn btn-lg btn-primary">Təsdiqlə</button>
                    @if(session()->get('requested')->otp->remaining > 0)
                        <button type="button" class="btn btn-lg btn-primary" disabled>Təkrar göndər</button>
                        <br>
                        <p class="mt-3">Təkrar sms göndərmək üçün <span id="seconds">{{ session()->get('requested')->otp->remaining }}</span> saniyə gözləyin.</p>
                    @else
                        <button type="submit" name="newotp" class="btn btn-lg btn-primary">Təkrar göndər</button>
                    @endif
                </form>
                @else
                <div class="mb-5">
                    <p class="h6">Parolu sıfırlamaq üçün hesabınıza bağlı olan mobil nömrənizi daxil edin.</p>
                    <p class="h6">Mobil nömrənizə şifrə sıfırlamaq üçün link göndəriləcək.</p>
                </div>
                <form id="forgotPasswordForm" class="tooltip-end-bottom" action="{{ route('backend.handleforgot') }}" method="POST">
                    @csrf
                    <div class="mb-3 filled form-group tooltip-end-top">
                        <i data-acorn-icon="mobile"></i>
                        <input class="form-control" placeholder="Mobil nömrəniz" name="mobile" id="mobileMask"/>
                        {!! validationResult('mobile',$errors) !!}
                    </div>
                    <button type="submit" class="btn btn-lg btn-primary">Göndər</button>
                </form>
                @endif
            </div>
        </div>
    </div>
@endsection
