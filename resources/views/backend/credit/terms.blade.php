@php
    $html_tag_data = [];
    $title = 'Kredit şərtləri və qaydaları';
    $breadcrumbs = ["/"=>"ParfumShop", ""=>"Kredit şərtləri və qaydaları"];
@endphp
@extends('backend.layout',['html_tag_data'=>$html_tag_data, 'title'=>$title])

@section('content')
<div class="container">
    <div class="page-title-container">
        <h1 class="mb-0 pb-0 display-4">{{$title}}</h1>
        @include('backend._layout.breadcrumb',['breadcrumbs'=>$breadcrumbs])
    </div>
    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.credit.terms.update') }}">
                @csrf
                <div class="mb-3">
                    <label class="form-label">Şərtlər və qaydalar AZ</label>
                    <textarea class="form-control" rows="10" name="content_az">{{ old('content_az', $terms->content_az) }}</textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label">Şərtlər və qaydalar EN</label>
                    <textarea class="form-control" rows="10" name="content_en">{{ old('content_en', $terms->content_en) }}</textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label">Şərtlər və qaydalar RU</label>
                    <textarea class="form-control" rows="10" name="content_ru">{{ old('content_ru', $terms->content_ru) }}</textarea>
                </div>
                <button class="btn btn-primary" type="submit">Yadda saxla</button>
            </form>
        </div>
    </div>
</div>
@endsection
