@php
    $title = 'Şifrə yeniləmə';
    $description = 'Şifrə yeniləmə'
@endphp
@extends('backend.layout_full',['title'=>$title, 'description'=>$description])

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
                <h2 class="cta-1 text-primary">Şifrəni yenilə !</h2>
            </div>
            <div class="mb-5">
                <p class="h6">Şifrə minimum 6 simvoldan ibarət olmalıdır.</p>
            </div>
            <div>
                <form id="resetForm" class="tooltip-end-bottom" action="{{ route('backend.handlereset') }}" method="POST">
                    @csrf
                    <div class="mb-3 filled">
                        <i data-acorn-icon="lock-off"></i>
                        <input class="form-control" minlength="6" name="password" type="password" placeholder="Şifrə" required/>
                        {!! validationResult('password',$errors) !!}
                    </div>
                    <div class="mb-3 filled">
                        <i data-acorn-icon="lock-on"></i>
                        <input class="form-control" minlength="6" name="password_confirmation" type="password" placeholder="Şifrənin təkrarı" required/>
                        {!! validationResult('password_confirmation',$errors) !!}
                    </div>
                    <button type="submit" class="btn btn-lg btn-primary">Reset Password</button>
                </form>
            </div>
        </div>
    </div>
@endsection
