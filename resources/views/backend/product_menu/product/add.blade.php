@php
    $html_tag_data = [];
    $title = 'Məhsul əlavə et';
    $breadcrumbs = [
        "/admin" => "ParfumShop",
        "#" => "Məhsul əlavə et"
    ];
@endphp

@extends('backend.layout', ['title' => $title])

@section('css')
    <link rel="stylesheet" href="{{ asset('backend/css/vendor/select2.min.css') }}"/>
    <link rel="stylesheet" href="{{ asset('backend/css/vendor/select2-bootstrap4.min.css') }}"/>
@endsection

@section('js_page')
    <script src="{{ asset('backend/js/vendor/select2.full.min.js') }}"></script>
    <script src="{{ asset('backend/js/forms/controls.select2.js') }}"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const variantArea = document.querySelector('.variant_area');
            const imageArea = document.querySelector('.image_area');
            const fragranticaImportUrl = document.getElementById('fragrantica_import_url');
            const fragranticaImportButton = document.getElementById('fragranticaImportButton');
            const aiGenerateButton = document.getElementById('aiGenerateButton');
            const imageSearchButton = document.getElementById('imageSearchButton');
            const fragranticaImportResult = document.getElementById('fragranticaImportResult');
            const imageSearchResult = document.getElementById('imageSearchResult');
            const escapeHtml = (value) => String(value ?? '').replace(/[&<>'"]/g, (character) => ({
                '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;'
            }[character]));

            fragranticaImportButton.addEventListener('click', async function () {
                const url = fragranticaImportUrl.value.trim();

                if (!url) {
                    fragranticaImportResult.innerHTML = '<div class="alert alert-warning mb-0">Əvvəlcə Fragrantica linkini daxil edin.</div>';
                    return;
                }

                fragranticaImportButton.disabled = true;
                fragranticaImportButton.textContent = 'Məlumatlar alınır...';
                fragranticaImportResult.innerHTML = '';

                try {
                    const response = await fetch('{{ route('admin.product.import.fragrantica-preview') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        },
                        body: JSON.stringify({url}),
                    });
                    const data = await response.json();

                    if (!response.ok) {
                        throw new Error(data.message || 'Məlumatlar alına bilmədi.');
                    }

                    document.getElementById('name').value = data.product.name || '';

                    if (data.matches.brand_id) {
                        $('#brand_id').val(String(data.matches.brand_id)).trigger('change');
                    }

                    if (data.matches.gender_ids.length) {
                        $('#gender').val(data.matches.gender_ids.map(String)).trigger('change');
                    }

                    if (data.matches.ingredient_ids.length) {
                        $('#ingredients').val(data.matches.ingredient_ids.map(String)).trigger('change');
                    }

                    const notes = data.product.notes.length ? data.product.notes.map(escapeHtml).join(', ') : 'tapılmadı';
                    const accords = data.product.accords.length ? data.product.accords.map(escapeHtml).join(', ') : 'tapılmadı';
                    const sourceDescription = data.product.source_description
                        ? `<details class="mt-2"><summary>Mənbədən çıxarılan izah</summary><div class="small mt-2">${escapeHtml(data.product.source_description)}</div></details>`
                        : '';

                    fragranticaImportResult.innerHTML = `
                        <div class="alert alert-success mb-0">
                            <strong>${escapeHtml(data.product.brand)} — ${escapeHtml(data.product.name)}</strong><br>
                            ${data.product.year ? `Buraxılış ili: ${data.product.year}<br>` : ''}
                            ${data.product.perfumer ? `Parfümer: ${data.product.perfumer}<br>` : ''}
                            Notlar: ${notes}<br>
                            Akkordlar: ${accords}
                            ${sourceDescription}
                            <div class="small mt-2">Uyğun gələn brend, cinsiyyət və notlar formda avtomatik seçildi. Yoxlayıb düzəldə bilərsən.</div>
                        </div>`;
                } catch (error) {
                    fragranticaImportResult.innerHTML = `<div class="alert alert-danger mb-0">${error.message}</div>`;
                } finally {
                    fragranticaImportButton.disabled = false;
                    fragranticaImportButton.textContent = 'Məlumatları gətir';
                }
            });

            aiGenerateButton.addEventListener('click', async function () {
                const url = fragranticaImportUrl.value.trim();

                if (!url) {
                    fragranticaImportResult.innerHTML = '<div class="alert alert-warning mb-0">Əvvəlcə Fragrantica linkini daxil edin.</div>';
                    return;
                }

                aiGenerateButton.disabled = true;
                aiGenerateButton.textContent = 'AI yazır...';
                const startedAt = performance.now();
                fragranticaImportResult.innerHTML = '<div class="alert alert-info mb-0">AI sahələri və 3 dildə təsviri hazırlayır... <strong id="aiGenerationTimer">0.0 saniyə</strong></div>';
                const timer = window.setInterval(function () {
                    const seconds = ((performance.now() - startedAt) / 1000).toFixed(1);
                    const timerElement = document.getElementById('aiGenerationTimer');

                    if (timerElement) {
                        timerElement.textContent = `${seconds} saniyə`;
                    }
                }, 100);

                try {
                    const response = await fetch('{{ route('admin.product.import.ai-generate') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        },
                        body: JSON.stringify({url}),
                    });
                    const data = await response.json();

                    if (!response.ok) {
                        throw new Error(data.message || 'AI sahələri və təsvirləri hazırlaya bilmədi.');
                    }

                    document.getElementById('name').value = data.product.name || '';
                    document.querySelector('textarea[name="content_az"]').value = data.product.description_az || '';
                    document.querySelector('textarea[name="content_en"]').value = data.product.description_en || '';
                    document.querySelector('textarea[name="content_ru"]').value = data.product.description_ru || '';

                    if (data.matches.brand_id) {
                        $('#brand_id').val(String(data.matches.brand_id)).trigger('change');
                    }

                    if (data.matches.gender_ids.length) {
                        $('#gender').val(data.matches.gender_ids.map(String)).trigger('change');
                    }

                    if (data.matches.ingredient_ids.length) {
                        $('#ingredients').val(data.matches.ingredient_ids.map(String)).trigger('change');
                    }

                    const duration = (Number(data.meta?.duration_ms || 0) / 1000).toFixed(1);
                    fragranticaImportResult.innerHTML = `
                        <div class="alert alert-success mb-0">
                            <strong>3 dildə təsvir hazırdır.</strong><br>
                            ${escapeHtml(data.product.brand)} — ${escapeHtml(data.product.name)}
                            <div class="small mt-2">Hazırlanma vaxtı: ${duration} saniyə</div>
                        </div>`;
                } catch (error) {
                    fragranticaImportResult.innerHTML = `<div class="alert alert-danger mb-0">${escapeHtml(error.message)}</div>`;
                } finally {
                    window.clearInterval(timer);
                    aiGenerateButton.disabled = false;
                aiGenerateButton.textContent = 'AI ilə 3 dildə təsvir yarat';
                }
            });

            imageSearchButton.addEventListener('click', async function () {
                const name = document.getElementById('name').value.trim();
                const brand = document.getElementById('brand_id').selectedOptions[0]?.text.trim() || '';

                if (!name) {
                    imageSearchResult.innerHTML = '<div class="alert alert-warning mb-0">Əvvəlcə ətirin adını daxil edin və ya AI ilə sahələri doldurun.</div>';
                    return;
                }

                imageSearchButton.disabled = true;
                imageSearchButton.textContent = 'Şəkillər axtarılır...';
                imageSearchResult.innerHTML = '';

                try {
                    const response = await fetch('{{ route('admin.product.import.image-search') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        },
                        body: JSON.stringify({query: `${brand} ${name} perfume bottle`}),
                    });
                    const data = await response.json();

                    if (!response.ok) {
                        throw new Error(data.message || 'Şəkillər tapılmadı.');
                    }

                    if (!data.images.length) {
                        imageSearchResult.innerHTML = '<div class="alert alert-warning mb-0">Uyğun şəkil tapılmadı.</div>';
                        return;
                    }

                    imageSearchResult.innerHTML = `
                        <div class="row g-3 mt-1">
                            ${data.images.map((image) => `
                                <div class="col-6 col-md-3">
                                    <label class="border rounded p-2 d-block h-100">
                                        <img src="${escapeHtml(image.thumbnail_url)}" alt="${escapeHtml(image.title)}" class="img-fluid mb-2" style="width: 100%; height: 150px; object-fit: contain">
                                        <div class="form-check">
                                            <input class="form-check-input remote-image-checkbox" type="checkbox" form="productForm" name="remote_image_ids[]" value="${image.id}">
                                            <span class="form-check-label">Seç</span>
                                        </div>
                                        <div class="form-check mt-1">
                                            <input class="form-check-input remote-primary-image" type="radio" form="productForm" name="remote_primary_image_id" value="${image.id}">
                                            <span class="form-check-label">Əsas şəkil</span>
                                        </div>
                                        <div class="small text-muted mt-1 text-truncate">${escapeHtml(image.source)}</div>
                                    </label>
                                </div>
                            `).join('')}
                        </div>
                        <div class="form-text mt-2">Ən çox 5 şəkil seç. “Əsas şəkil” seçdiyin foto məhsulda birinci görünəcək.</div>`;
                } catch (error) {
                    imageSearchResult.innerHTML = `<div class="alert alert-danger mb-0">${escapeHtml(error.message)}</div>`;
                } finally {
                    imageSearchButton.disabled = false;
                    imageSearchButton.textContent = 'Şəkilləri tap';
                }
            });

            imageSearchResult.addEventListener('change', function (event) {
                if (event.target.classList.contains('remote-primary-image')) {
                    event.target.closest('label').querySelector('.remote-image-checkbox').checked = true;
                }

                if (event.target.classList.contains('remote-image-checkbox') && !event.target.checked) {
                    const primary = event.target.closest('label').querySelector('.remote-primary-image');

                    if (primary.checked) {
                        primary.checked = false;
                    }
                }
            });

            function reindexVariants() {
                variantArea.querySelectorAll('.variant-row').forEach(function (row, index) {
                    row.querySelector('.variant-size').name = `variants[${index}][size_id]`;
                    row.querySelector('.variant-price').name = `variants[${index}][price]`;
                    row.querySelector('.variant-active').name = `variants[${index}][active]`;
                });
            }

            document.getElementById('addVariant').addEventListener('click', function () {
                const firstRow = variantArea.querySelector('.variant-row');
                const newRow = firstRow.cloneNode(true);

                newRow.querySelector('.variant-size').selectedIndex = 0;
                newRow.querySelector('.variant-price').value = '';
                newRow.querySelector('.variant-active').checked = true;

                newRow.querySelector('.variant-action').innerHTML = `
                    <label class="d-block">&nbsp;</label>
                    <button class="btn btn-danger btn-sm removeVariant" type="button">-</button>
                `;

                variantArea.appendChild(newRow);
                reindexVariants();
            });

            document.addEventListener('click', function (e) {
                if (e.target.classList.contains('removeVariant')) {
                    e.target.closest('.variant-row').remove();
                    reindexVariants();
                }

                if (e.target.classList.contains('removeImage')) {
                    e.target.closest('.image-row').remove();
                }
            });

            document.getElementById('addImage').addEventListener('click', function () {
                const firstRow = imageArea.querySelector('.image-row');
                const newRow = firstRow.cloneNode(true);

                newRow.querySelector('input[type="file"]').value = '';

                newRow.querySelector('.image-action').innerHTML = `
                    <label class="d-block">&nbsp;</label>
                    <button class="btn btn-danger btn-sm removeImage" type="button">-</button>
                `;

                imageArea.appendChild(newRow);
            });

            reindexVariants();
        });
    </script>
@endsection

@section('content')
    <div class="container">
        <div class="page-title-container">
            <div class="row">
                <div class="col-12 col-sm-6">
                    <h1 class="mb-0 pb-0 display-4" id="title">{{ $title }}</h1>
                    @include('backend._layout.breadcrumb', ['breadcrumbs' => $breadcrumbs])
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col">
                <section class="scroll-section" id="hover">
                    <div class="card mb-5">
                        <div class="card-body">
                            <div class="border rounded p-3 mb-4 bg-light">
                                <label for="fragrantica_import_url" class="form-label fw-bold">Fragrantica-dan məlumat gətir</label>
                                <div class="input-group">
                                    <input type="url" id="fragrantica_import_url" class="form-control" placeholder="https://www.fragrantica.com/perfume/...">
                                    <button class="btn btn-outline-primary" type="button" id="fragranticaImportButton">Məlumatları gətir</button>
                                </div>
                                <button class="btn btn-primary mt-2" type="button" id="aiGenerateButton">AI ilə 3 dildə təsvir yarat</button>
                                <button class="btn btn-outline-primary mt-2" type="button" id="imageSearchButton">Şəkilləri tap</button>
                                <div class="form-text">Linkdən məhsul adı, brend, cinsiyyət, notlar, il və parfümer çıxarılır. Şəkilləri ayrıca “Şəkilləri tap” düyməsi ilə seçirsən.</div>
                                <div id="fragranticaImportResult" class="mt-3"></div>
                                <div id="imageSearchResult" class="mt-3"></div>
                            </div>

                            <ul class="nav nav-tabs nav-tabs-title nav-tabs-line-title responsive-tabs" role="tablist">
                                <li class="nav-item" role="presentation">
                                    <a class="nav-link active" data-bs-toggle="tab" href="#product-info" role="tab">Məhsul məlumatları</a>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <a class="nav-link" data-bs-toggle="tab" href="#product-variants" role="tab">Variantlar</a>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <a class="nav-link" data-bs-toggle="tab" href="#product-images" role="tab">Şəkillər</a>
                                </li>
                            </ul>

                            <form id="productForm" method="POST" action="{{ route('admin.product.add') }}" enctype="multipart/form-data">
                                @csrf

                                <div class="tab-content">
                                    <div class="tab-pane fade active show" id="product-info" role="tabpanel">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="row">
                                                    <div class="col-md-3">
                                                        <label for="old_id">Köhnə ID</label>
                                                        <input type="text" id="old_id" name="old_id" value="{{ old('old_id') }}" class="form-control" placeholder="Köhnə ID">
                                                    </div>

                                                    <div class="col-md-9">
                                                        <label for="brand_id">Brend</label>
                                                        <select class="form-select select2" id="brand_id" name="brand_id" required>
                                                            <option value="" @selected(!old('brand_id'))>Seçin</option>
                                                            @foreach($brands as $brand)
                                                                <option value="{{ $brand->id }}" @selected(old('brand_id') == $brand->id)>
                                                                    {{ $brand->name }}
                                                                </option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                </div>

                                                <div class="mt-3">
                                                    <label for="category">Kateqoriyalar</label>
                                                    <select class="form-select select-multiple" id="category" multiple name="categories[]" required>
                                                        @foreach($categories as $category)
                                                            <option value="{{ $category->id }}" @selected(in_array($category->id, old('categories', [])))>
                                                                {{ $category->name_az }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                </div>

                                                <div class="mt-3">
                                                    <label for="gender">Cinsiyyət</label>
                                                    <select class="form-select select-multiple" id="gender" multiple name="genders[]" required>
                                                        @foreach($genders as $gender)
                                                            <option value="{{ $gender->id }}" @selected(in_array($gender->id, old('genders', [])))>
                                                                {{ $gender->name_az }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                            </div>

                                            <div class="col-md-6">
                                                <div>
                                                    <label for="name">Ətir adı</label>
                                                    <input type="text" id="name" name="name" value="{{ old('name') }}" class="form-control" placeholder="Ətir adı" required>
                                                </div>

                                                <div class="mt-3">
                                                    <label for="type">Məhsul tipi</label>
                                                    <select id="type" class="form-select select2" name="type_id" required>
                                                        @foreach($types as $type)
                                                            <option value="{{ $type->id }}" @selected(old('type_id') == $type->id)>
                                                                {{ $type->name_az }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                </div>

                                                <div class="mt-3">
                                                    <label for="ingredients">İnqrediyentlər</label>
                                                    <select class="form-select select2-tags" multiple name="ingredients[]" id="ingredients">
                                                        @foreach($ingredients as $ingredient)
                                                            <option value="{{ $ingredient->id }}" @selected(in_array($ingredient->id, old('ingredients', [])))>
                                                                {{ $ingredient->name_az }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                            </div>
                                        </div>

                                        <hr>

                                        <div class="row">
                                            <div class="col-12">
                                                <ul class="nav nav-tabs nav-tabs-title nav-tabs-line-title responsive-tabs" role="tablist">
                                                    <li class="nav-item">
                                                        <a class="nav-link active" data-bs-toggle="tab" href="#descaz">İnformasiya AZ</a>
                                                    </li>
                                                    <li class="nav-item">
                                                        <a class="nav-link" data-bs-toggle="tab" href="#descen">İnformasiya EN</a>
                                                    </li>
                                                    <li class="nav-item">
                                                        <a class="nav-link" data-bs-toggle="tab" href="#descru">İnformasiya RU</a>
                                                    </li>
                                                </ul>

                                                <div class="tab-content">
                                                    <div class="tab-pane fade active show" id="descaz">
                                                        <textarea class="form-control" rows="6" name="content_az">{{ old('content_az') }}</textarea>
                                                    </div>

                                                    <div class="tab-pane fade" id="descen">
                                                        <textarea class="form-control" rows="6" name="content_en">{{ old('content_en') }}</textarea>
                                                    </div>

                                                    <div class="tab-pane fade" id="descru">
                                                        <textarea class="form-control" rows="6" name="content_ru">{{ old('content_ru') }}</textarea>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="tab-pane fade" id="product-variants" role="tabpanel">
                                        <div class="variant_area">
                                            <div class="row mt-3 variant-row align-items-center">
                                                <div class="col-md-5">
                                                    <label>Ölçü</label>
                                                    <select class="form-select variant-size" name="variants[0][size_id]" required>
                                                        @foreach($sizes as $size)
                                                            <option value="{{ $size->id }}">{{ $size->name_az }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>

                                                <div class="col-md-4">
                                                    <label>Qiymət</label>
                                                    <div class="input-group">
                                                        <input type="number" name="variants[0][price]" class="form-control variant-price" placeholder="0.00" step="0.01" min="0" required>
                                                        <span class="input-group-text">AZN</span>
                                                    </div>
                                                </div>

                                                <div class="col-md-2">
                                                    <label class="d-block">Aktiv</label>
                                                    <div class="form-check form-switch mt-2">
                                                        <input class="form-check-input variant-active" type="checkbox" name="variants[0][active]" value="1" checked>
                                                    </div>
                                                </div>

                                                <div class="col-md-1 variant-action">
                                                    <label class="d-block">&nbsp;</label>
                                                    <button class="btn btn-primary btn-sm" type="button" id="addVariant">+</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="tab-pane fade" id="product-images" role="tabpanel">
                                        <div class="image_area">
                                            <div class="row mt-3 image-row">
                                                <div class="col-md-11">
                                                    <label>Şəkil</label>
                                                    <input type="file" name="images[]" class="form-control" accept="image/*">
                                                </div>

                                                <div class="col-md-1 image-action">
                                                    <label class="d-block">&nbsp;</label>
                                                    <button class="btn btn-primary btn-sm" type="button" id="addImage">+</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="mt-4">
                                    <button type="submit" class="btn btn-primary">Məhsulu əlavə et</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </div>
@endsection
