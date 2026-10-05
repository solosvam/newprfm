@use('App\Models\Customer\CustomerCreditProfile')
<!doctype html>
<html lang="az">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Parfumshop Assistant</title>
    <link rel="icon" type="image/x-icon" href="{{ asset('backend/icon/favicon.ico') }}">
    <link rel="stylesheet" href="{{ asset_v('frontend/css/vendor/cropper.min.css') }}">
    <link rel="stylesheet" href="{{ asset_v('backend/css/crm-credit-profile.css') }}">
    <link rel="stylesheet" href="{{ asset_v('backend/css/assistant.css') }}">
</head>
<body data-extension-origin="chrome-extension://{{ config('services.assistant.extension_id') }}"
      data-customer-url="{{ route('admin.assistant.customer') }}"
      data-search-url="{{ route('admin.assistant.search') }}"
      data-search-image-url="{{ route('admin.assistant.search-image') }}"
      data-poster-url="{{ route('admin.assistant.poster', ['product' => '__ID__']) }}"
      data-customer-store-url="{{ route('admin.assistant.customer.store') }}">
<main class="as">
    <header class="as-head">
        <strong>Parfumshop</strong>
        <span class="as-user">{{ $user->name }}</span>
    </header>

    {{-- Müştəri: WhatsApp söhbətinin nömrəsinə görə (assistant.js doldurur) --}}
    <section class="as-card" data-customer>
        <p class="as-muted">Söhbət seçilməyib</p>
    </section>

    {{-- Kredit profili: WhatsApp-da seçilmiş mətn (sağ klik → PS → Kredit → …) və vəsiqə şəkli (sağ klik → PS: Vəsiqə) buraya düşür --}}
    <section class="as-card as-credit" data-credit hidden
             data-ocr-url="{{ route('admin.assistant.credit.ocr', ['customer' => '__ID__']) }}"
             data-save-url="{{ route('admin.assistant.credit.update', ['customer' => '__ID__']) }}"
             data-double-side='@json(CustomerCreditProfile::DOUBLE_SIDE_SERIES)'>
        <button type="button" class="as-row as-credit-toggle" data-credit-toggle aria-expanded="false">
            <span class="as-label">Kredit profili</span>
            <span class="as-muted" data-credit-state></span>
        </button>
        <form class="as-form as-credit-form" data-credit-form hidden novalidate>
            <p class="as-muted as-hint">WhatsApp-da mətni seçib sağ klik → <b>PS</b> → <b>Kredit → …</b>; vəsiqə şəklinə sağ klik → <b>PS: Vəsiqə</b>.</p>
            <p class="as-credit-status" data-credit-status hidden></p>

            <div class="as-cards">
                @foreach(['id_card_front' => 'Vəsiqə — ön üz', 'id_card_back' => 'Vəsiqə — arxa üz'] as $side => $label)
                    <div class="as-field as-idcard" data-field="{{ $side }}" @if($side === 'id_card_back') data-back-side @endif>
                        <span>{{ $label }}</span>
                        <img alt="" data-idcard-preview="{{ $side }}" hidden>
                        <label class="as-btn as-btn-light as-btn-sm as-file">
                            Fayl seç<input type="file" accept="image/*" data-idcard-file="{{ $side }}" hidden>
                        </label>
                    </div>
                @endforeach
            </div>

            <label class="as-field"><span>Ata adı</span><input type="text" name="father_name" class="as-input" maxlength="100"></label>
            <label class="as-field"><span>FİN</span><input type="text" name="fin" class="as-input as-upper" maxlength="7"></label>
            <div class="as-pair">
                <label class="as-field"><span>Seriya</span>
                    <select name="id_card_series" class="as-input">
                        <option value=""></option>
                        @foreach(CustomerCreditProfile::ID_CARD_SERIES as $series)
                            <option value="{{ $series }}">{{ $series }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="as-field"><span>Vəsiqə nömrəsi</span><input type="text" name="id_card_number" class="as-input as-upper" maxlength="8"></label>
            </div>
            <label class="as-field"><span>Qohum 1 — ad</span><input type="text" name="relative_1_name" class="as-input" maxlength="100"></label>
            <label class="as-field"><span>Qohum 1 — nömrə</span><input type="tel" name="relative_1_phone" class="as-input" maxlength="16"></label>
            <label class="as-field"><span>Qohum 2 — ad</span><input type="text" name="relative_2_name" class="as-input" maxlength="100"></label>
            <label class="as-field"><span>Qohum 2 — nömrə</span><input type="tel" name="relative_2_phone" class="as-input" maxlength="16"></label>
            <label class="as-field"><span>İş yeri</span><input type="text" name="workplace_name" class="as-input" maxlength="255"></label>
            <label class="as-field"><span>Əmək haqqı (₼)</span><input type="text" name="salary" class="as-input" inputmode="decimal" maxlength="12"></label>
            <p class="as-warn as-form-error" data-form-error hidden></p>
            <button type="submit" class="as-btn">Yadda saxla</button>
        </form>
    </section>

    {{-- Ətir axtarışı: WhatsApp-da "Ətri axtar" və ya əl ilə --}}
    <section class="as-card">
        <form class="as-search" data-search-form>
            <input type="search" class="as-input" data-search-input placeholder="Ətir axtar: dior sauvage 100" maxlength="200" autocomplete="off">
        </form>
        {{-- "Ətri şəkildən axtar": müştərinin göndərdiyi şəkil --}}
        <img class="as-search-image" alt="" data-search-image hidden>
        <p class="as-muted as-search-note" data-search-note hidden></p>
        <ul class="as-results" data-search-results></ul>
    </section>

    {{-- Poster: nəticədə "Poster" basılanda; kopyalanıb WhatsApp-a Ctrl+V --}}
    <section class="as-card as-poster" data-poster hidden>
        <div class="as-row">
            <div class="as-label">Poster</div>
            <button type="button" class="as-close" data-poster-close aria-label="Bağla">×</button>
        </div>
        <p class="as-muted as-poster-status" data-poster-status></p>
        <img class="as-poster-img" alt="" data-poster-img hidden>
        <pre class="as-poster-caption" data-poster-caption hidden></pre>
        <div class="as-poster-actions">
            <button type="button" class="as-btn" data-poster-copy disabled>Kopyala</button>
            <button type="button" class="as-btn as-btn-light" data-poster-copy-text hidden>Mətni kopyala</button>
            <a class="as-btn as-btn-light" data-poster-download hidden>PNG endir</a>
        </div>
    </section>
</main>
{{-- Tapılmayan nömrə üçün "Yeni müştəri" forması (assistant.js klonlayır) — CRM-dəki modalın sahələri --}}
<template data-new-customer>
    <form class="as-form" novalidate>
        <div class="as-label">Yeni müştəri</div>
        <label class="as-field">
            <span>Mobil</span>
            <input type="text" name="mobile" class="as-input" readonly>
        </label>
        <label class="as-field">
            <span>Ad</span>
            <input type="text" name="name" class="as-input" maxlength="30" autocomplete="off" required>
        </label>
        <label class="as-field">
            <span>Soyad</span>
            <input type="text" name="surname" class="as-input" maxlength="30" autocomplete="off" required>
        </label>
        <label class="as-field">
            <span>E-poçt <small class="as-muted">(istəyə görə)</small></span>
            <input type="email" name="email" class="as-input" maxlength="50" autocomplete="off">
        </label>
        <div class="as-field" data-field="gender">
            <span>Cinsi</span>
            <div class="as-radios">
                <label><input type="radio" name="gender" value="1"> Kişi</label>
                <label><input type="radio" name="gender" value="0"> Qadın</label>
            </div>
        </div>
        <label class="as-check"><input type="checkbox" name="send_password" value="1" checked> Şifrəni müştəriyə SMS ilə göndər</label>
        <p class="as-warn as-form-error" data-form-error hidden></p>
        <button type="submit" class="as-btn">Müştərini yarat</button>
    </form>
</template>
@include('components.id-card-crop-dialog')
<script src="{{ asset_v('frontend/js/vendor/cropper.min.js') }}"></script>
<script src="{{ asset_v('frontend/js/id-card-cropper.js') }}"></script>
<script src="{{ asset_v('backend/js/product-poster.js') }}"></script>
<script src="{{ asset_v('backend/js/assistant.js') }}"></script>
<script src="{{ asset_v('backend/js/assistant-credit.js') }}"></script>
</body>
</html>
