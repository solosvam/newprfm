{{-- MÜVƏQQƏTİ: test məlumatının silinməsi (ResetController). Menyuda yoxdur; iş bitəndən sonra silinəcək. --}}
@php
    $html_tag_data = [];
    $title = 'Sıfırlama';
    $breadcrumbs = ['/' => 'ParfumShop', '#' => $title];
@endphp

@extends('backend.layout', ['html_tag_data' => $html_tag_data, 'title' => $title])

@section('content')
    <div class="container">
        <div class="page-title-container">
            <h1 class="mb-0 pb-0 display-4" id="title">{{ $title }}</h1>
            @include('backend._layout.breadcrumb', ['breadcrumbs' => $breadcrumbs])
        </div>

        @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
        @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

        <div class="alert alert-danger">
            <strong class="d-block">Silinən məlumat geri qaytarılmır.</strong>
            Başlamazdan əvvəl bazanın ehtiyat nüsxəsini götürün. Məhsullar, müştərilər, brendlər, anbarlar, ayarlar və SMS jurnalı silinmir.
        </div>

        <form method="POST" action="{{ route('admin.reset.run') }}">
            @csrf
            <div class="row g-3 mb-3">
                @foreach($sections as $key => $label)
                    <div class="col-12 col-lg-4">
                        <div class="card h-100"><div class="card-body">
                            <label class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" name="sections[]" value="{{ $key }}" @checked(in_array($key, old('sections', []), true))>
                                <span class="form-check-label fw-bold">{{ $label }}</span>
                            </label>
                            @foreach($counts[$key] as $name => $count)
                                <div class="d-flex justify-content-between text-small py-1 border-bottom">
                                    <span class="text-muted">{{ $name }}</span><span class="{{ $count ? '' : 'text-muted' }}">{{ $count }}</span>
                                </div>
                            @endforeach
                        </div></div>
                    </div>
                @endforeach
            </div>
            <div class="card"><div class="card-body">
                <label class="form-label" for="resetConfirm">Təsdiq üçün <strong>SIFIRLA</strong> yazın</label>
                <div class="d-flex gap-2" style="max-width: 420px">
                    <input id="resetConfirm" name="confirm" class="form-control" autocomplete="off" required>
                    <button class="btn btn-danger text-nowrap">Seçilənləri sil</button>
                </div>
                <div class="form-text">Sifarişlər silinəndə onların verdiyi və xərclətdiyi bonus müştərinin balansından geri alınır, promokodların istifadə sayı azalır.</div>
            </div></div>
        </form>
    </div>
@endsection
