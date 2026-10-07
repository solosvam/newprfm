@use('App\Models\Page')
@php
    $html_tag_data = [];
    $title = 'Məlumat səhifələri';
    $breadcrumbs = ['/admin' => 'ParfumShop', '' => 'Marketinq', '#' => $title];
@endphp
@extends('backend.layout', ['html_tag_data' => $html_tag_data, 'title' => $title])

@section('content')
    <div class="container">
        <div class="page-title-container">
            <div class="row">
                <div class="col-12 col-sm-6">
                    <h1 class="mb-0 pb-0 display-4" id="title">{{ $title }}</h1>
                    @include('backend._layout.breadcrumb', ['breadcrumbs' => $breadcrumbs])
                </div>
                <div class="col-12 col-sm-6 d-flex align-items-start justify-content-end">
                    <a href="{{ route('front.faq') }}" target="_blank" rel="noopener" class="btn btn-outline-primary btn-icon btn-icon-end w-100 w-sm-auto">
                        <span>FAQ səhifəsi</span>
                        <i data-acorn-icon="eye"></i>
                    </a>
                </div>
            </div>
        </div>

        <div class="card mb-5">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                        <tr>
                            <th class="text-muted text-small text-uppercase">Səhifə</th>
                            <th class="text-muted text-small text-uppercase">Ünvan</th>
                            <th class="text-muted text-small text-uppercase">Dillər</th>
                            <th class="text-muted text-small text-uppercase">Son yenilənmə</th>
                            <th></th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach(Page::PAGES as $key => [$slug, $name])
                            @php $page = $pages->get($key); @endphp
                            @continue(!$page)
                            <tr>
                                <td>
                                    <a href="{{ route('admin.pages.edit', $page) }}" class="body-link fw-bold">{{ $name }}</a>
                                    <div class="text-muted" style="font-size: 13px">{{ $page->title_az }}</div>
                                </td>
                                <td><a href="{{ $page->url() }}" target="_blank" rel="noopener" class="text-alternate">/{{ $slug }}</a></td>
                                <td>
                                    @foreach(['az' => 'AZ', 'en' => 'EN', 'ru' => 'RU'] as $locale => $label)
                                        @php $filled = trim(strip_tags((string) $page->{'body_'.$locale})) !== ''; @endphp
                                        <span class="badge {{ $filled ? 'bg-outline-success' : 'bg-outline-warning' }}" title="{{ $filled ? 'Mətn var' : 'Boşdur — AZ göstərilir' }}">{{ $label }}</span>
                                    @endforeach
                                </td>
                                <td class="text-alternate text-nowrap">{{ $page->updated_at?->format('d.m.Y H:i') }}</td>
                                <td class="text-end"><a href="{{ route('admin.pages.edit', $page) }}" class="btn btn-primary btn-sm">Redaktə et</a></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
