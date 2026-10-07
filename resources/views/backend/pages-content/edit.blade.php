@use('App\Models\Page')
@php
    $html_tag_data = [];
    $name = Page::PAGES[$page->key][1] ?? $page->key;
    $title = $name;
    $breadcrumbs = ['/admin' => 'ParfumShop', route('admin.pages.index') => 'Məlumat səhifələri', '#' => $name];
    $languages = ['az' => 'Azərbaycan', 'en' => 'English', 'ru' => 'Русский'];
@endphp
@extends('backend.layout', ['html_tag_data' => $html_tag_data, 'title' => $title])

@section('css')
    <link rel="stylesheet" href="{{ asset('backend/css/vendor/quill.snow.css') }}">
    <style>
        .page-editor { min-height: 360px; font-size: 15px; }
        .page-editor .ql-editor { min-height: 360px; line-height: 1.65; }
        .page-editor .ql-editor h2 { font-size: 1.35em; margin-top: 1em; }
        .page-editor .ql-editor h3 { font-size: 1.15em; margin-top: .8em; }
        .page-help { font-size: 13px; color: var(--muted); margin-top: 4px; }
    </style>
@endsection

@section('content')
    <div class="container">
        <div class="page-title-container">
            <div class="row g-2">
                <div class="col-12 col-md">
                    <h1 class="mb-0 pb-0 display-4" id="title">{{ $name }}</h1>
                    @include('backend._layout.breadcrumb', ['breadcrumbs' => $breadcrumbs])
                </div>
                <div class="col-12 col-md-auto d-flex align-items-start gap-2">
                    <a href="{{ $page->url() }}" target="_blank" rel="noopener" class="btn btn-outline-primary">Saytda bax</a>
                    <button type="submit" form="pageForm" class="btn btn-primary">Yadda saxla</button>
                </div>
            </div>
        </div>

        @if($errors->any())
            <div class="alert alert-danger mb-4">{{ $errors->first() }}</div>
        @endif

        @if($page->key === 'delivery')
            <div class="alert alert-info d-flex gap-3 align-items-start mb-4" role="note" style="font-size: 15px; line-height: 1.55;">
                <i data-acorn-icon="info-hexagon" class="flex-shrink-0 mt-1"></i>
                <div>Çatdırılma haqqı və ödəniş üsulları bu səhifədə <strong>ayarlardan avtomatik</strong> göstərilir
                    (Ayarlar → Sifariş və çatdırılma). Mətndə məbləğ yazmayın ki, ayar dəyişəndə köhnə qalmasın.</div>
            </div>
        @endif

        <form id="pageForm" method="POST" action="{{ route('admin.pages.update', $page) }}">
            @csrf
            <div class="card mb-5">
                <div class="card-body">
                    <ul class="nav nav-tabs nav-tabs-line mb-4" role="tablist">
                        @foreach($languages as $locale => $label)
                            <li class="nav-item" role="presentation">
                                <button class="nav-link {{ $loop->first ? 'active' : '' }}" type="button" data-bs-toggle="tab"
                                        data-bs-target="#lang-{{ $locale }}" role="tab">{{ $label }}</button>
                            </li>
                        @endforeach
                    </ul>
                    <div class="tab-content">
                        @foreach($languages as $locale => $label)
                            <div class="tab-pane fade {{ $loop->first ? 'show active' : '' }}" id="lang-{{ $locale }}" role="tabpanel">
                                <div class="mb-3">
                                    <label class="form-label">Başlıq</label>
                                    <input type="text" name="title_{{ $locale }}" maxlength="150" class="form-control @error('title_'.$locale) is-invalid @enderror"
                                           value="{{ old('title_'.$locale, $page->{'title_'.$locale}) }}" @if($locale === 'az') required @endif>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Mətn</label>
                                    <div class="page-editor" data-editor="body_{{ $locale }}"></div>
                                    <textarea name="body_{{ $locale }}" hidden>{{ old('body_'.$locale, $page->{'body_'.$locale}) }}</textarea>
                                    @unless($locale === 'az')
                                        <div class="page-help">Boş saxlasanız, saytda Azərbaycan dilindəki mətn göstərilir.</div>
                                    @endunless
                                </div>
                                <div>
                                    <label class="form-label">Google üçün qısa təsvir (meta description)</label>
                                    <textarea name="meta_description_{{ $locale }}" rows="2" maxlength="300" class="form-control">{{ old('meta_description_'.$locale, $page->{'meta_description_'.$locale}) }}</textarea>
                                    <div class="page-help">Axtarış nəticəsində başlığın altında görünən 1–2 cümlə (150–160 simvol ideal).</div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <div class="mt-5">
                        <button type="submit" class="btn btn-primary">Yadda saxla</button>
                        <span class="text-muted ms-3" style="font-size: 13px">Son yenilənmə: {{ $page->updated_at?->format('d.m.Y H:i') }}</span>
                    </div>
                </div>
            </div>
        </form>
    </div>
@endsection

@section('js_vendor')
    <script src="{{ asset('backend/js/vendor/quill.min.js') }}"></script>
@endsection

@section('js_page')
    <script>
        // Quill redaktoru: hər dil üçün; göndərəndə HTML gizli textarea-ya yazılır (server Page::clean ilə təmizləyir)
        (() => {
            if (typeof Quill === 'undefined') return;
            const toolbar = [
                [{header: [2, 3, false]}],
                ['bold', 'italic', 'underline'],
                [{list: 'ordered'}, {list: 'bullet'}],
                ['link', 'blockquote'],
                ['clean'],
            ];
            const editors = [...document.querySelectorAll('[data-editor]')].map((el) => {
                const field = document.querySelector(`textarea[name="${el.dataset.editor}"]`);
                const quill = new Quill(el, {theme: 'snow', modules: {toolbar}});
                quill.clipboard.dangerouslyPasteHTML(field.value || '');
                return {quill, field};
            });
            document.getElementById('pageForm').addEventListener('submit', () => {
                editors.forEach(({quill, field}) => {
                    field.value = quill.getText().trim() === '' ? '' : quill.root.innerHTML;
                });
            });
        })();
    </script>
@endsection
