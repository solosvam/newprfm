@php
    $html_tag_data = [];
    $title = 'Banner edit';
    $breadcrumbs = ["/"=>"ParfumShop", ""=>"Banner edit"]
@endphp
@extends('backend.layout',['html_tag_data'=>$html_tag_data, 'title'=>$title])

@section('css')

@endsection

@section('content')
    <div class="container">
        <div class="page-title-container">
            <div class="row">
                <!-- Title Start -->
                <div class="col-12 col-sm-6">
                    <h1 class="mb-0 pb-0 display-4" id="title">{{$title}}</h1>
                    @include('backend._layout.breadcrumb',['breadcrumbs'=>$breadcrumbs])
                </div>
                <!-- Title End -->

                <!-- Top Buttons Start -->
                <div class="col-12 col-sm-6 d-flex align-items-start justify-content-end">
                    <!-- Tour Button Start -->

                    <!-- Tour Button End -->
                </div>
                <!-- Top Buttons End -->
            </div>
        </div>
        <div class="row">
            <div class="col">
                <section class="scroll-section" id="userButtons">
                    <div class="card h-100-card">
                        <div class="card-body">
                            <form action="{{route('admin.banner.update',$banner->id)}}" method="post" enctype="multipart/form-data">
                                @csrf
                                <div class="mb-3 row">
                                    <label class="col-lg-2 col-md-3 col-sm-4 col-form-label">Yerləşmə</label>
                                    <div class="col-sm-8 col-md-9 col-lg-10">
                                        <select class="form-select" name="location">
                                            <option value="top" {{ ($banner->location == 'top') ? 'selected' : '' }}>Üst banner</option>
                                            <option value="bottom" {{ ($banner->location == 'bottom') ? 'selected' : '' }}>Alt banner</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="mb-3 row">
                                    <label class="col-lg-2 col-md-3 col-sm-4 col-form-label">For</label>
                                    <div class="col-sm-8 col-md-9 col-lg-10">
                                        <select class="form-select" name="device">
                                            <option value="mobile" {{ ($banner->device == 'mobile') ? 'selected' : '' }}>Mobil</option>
                                            <option value="web" {{ ($banner->device == 'web') ? 'selected' : '' }}>Web</option>
                                        </select>
                                    </div>
                                </div>

                                @foreach(['az' => 'Azərbaycan', 'en' => 'English', 'ru' => 'Русский'] as $locale => $label)
                                    @php
                                        $filename = $banner->{'url_' . $locale};
                                        if ($locale === 'az' && !$filename) {
                                            $filename = $banner->url;
                                        }
                                    @endphp
                                    <div class="mb-3 row">
                                        <label class="col-lg-2 col-md-3 col-sm-4 col-form-label">
                                            Şəkil — {{ $label }}
                                        </label>
                                        <div class="col-sm-8 col-md-9 col-lg-10">
                                            @if($filename)
                                                <img src="{{ asset('frontend/uploads/banners/' . $filename) }}"
                                                     class="img-fluid rounded mb-2" style="max-height: 140px;"
                                                     alt="{{ $label }} banner">
                                            @else
                                                <div class="alert alert-warning py-2">
                                                    Bu dil üçün banner yüklənməyib.
                                                </div>
                                            @endif
                                            <input type="file" name="image_{{ $locale }}" id="image_{{ $locale }}"
                                                   accept="image/*"
                                                   class="form-control @error('image_' . $locale) is-invalid @enderror"
                                                   >
                                            <small class="text-muted">
                                                {{ $filename ? 'Yeni şəkil seçilməsə mövcud şəkil saxlanacaq.' : 'Şəkil yüklənənədək Azərbaycan versiyası göstəriləcək.' }}
                                            </small>
                                            @error('image_' . $locale)
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                @endforeach

                                <div class="mb-3 row">
                                    <label class="col-lg-2 col-md-3 col-sm-4 col-form-label">Aktivlik</label>
                                    <div class="col-sm-8 col-md-9 col-lg-10">
                                        <select class="form-select" name="active">
                                            <option value="1" {{ $banner->active ? 'selected' : '' }}>Aktiv</option>
                                            <option value="0" {{ !$banner->active ? 'selected' : '' }}>Deaktiv</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="mb-3 row mt-5">
                                    <div class="col-sm-8 col-md-9 col-lg-10 ms-auto">
                                        <button type="submit" class="btn btn-primary">Yenilə</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </div>
@endsection
