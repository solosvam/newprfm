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

                            <form method="POST" action="{{ route('admin.product.add') }}" enctype="multipart/form-data">
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
