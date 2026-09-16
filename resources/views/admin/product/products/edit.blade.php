@php
    $html_tag_data = [];
    $title = 'Məhsullar';
    $breadcrumbs = ["/admin"=>"ParfumShop", "#"=>"Məhsullar"]
@endphp
@extends('admin.layout',['title'=>$title])

@section('css')
    <link rel="stylesheet" href="{{asset('backend/css/vendor/select2.min.css')}}"/>
    <link rel="stylesheet" href="{{asset('backend/css/vendor/select2-bootstrap4.min.css')}}"/>
@endsection

@section('js_page')
    <script src="{{asset('backend/js/vendor/select2.full.min.js')}}"></script>
    <script src="{{asset('backend/js/cs/scrollspy.js')}}"></script>
    <script src="{{asset('backend/js/forms/controls.select2.js')}}"></script>
@endsection

@section('content')
    <div class="container">
        <div class="page-title-container">
            <div class="row">
                <!-- Title Start -->
                <div class="col-12 col-sm-6">
                    <h1 class="mb-0 pb-0 display-4" id="title">{{$title}}</h1>
                    @include('admin._layout.breadcrumb',['breadcrumbs'=>$breadcrumbs])
                </div>
                <div class="col-12 col-sm-6 d-flex align-items-start justify-content-end">

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
                                    <a class="nav-link active" data-bs-toggle="tab" href="#product1" role="tab" aria-selected="true">Məhsul məlumatları</a>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <a class="nav-link" data-bs-toggle="tab" href="#product2" role="tab" aria-selected="false">Ölçülər</a>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <a class="nav-link" data-bs-toggle="tab" href="#product3" role="tab" aria-selected="false">Şəkillər</a>
                                </li>
                            </ul>
                            <form method="POST" action="{{route('product.update',$product->id)}}" enctype="multipart/form-data">
                                @csrf
                                <div class="tab-content">
                                    <div class="tab-pane fade active show" id="product1" role="tabpanel">
                                        <div class="row">
                                            <div class="col-6">
                                                <div class="row">
                                                    <div class="col-2">
                                                        <label>Köhnə ID</label>
                                                        <input type="text" name="old_id" class="form-control" placeholder="Köhnə ID" value="{{$product->old_id}}">
                                                    </div>
                                                    <div class="col-10">
                                                        <label>Brend</label>
                                                        <select class="form-select select2" name="brand_id" required>
                                                            @foreach($brands as $brand)
                                                                <option value="{{$brand->id}}" {{$product->brand_id == $brand->id ? 'selected' : ''}}>
                                                                    {{$brand->name}}
                                                                </option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                </div>

                                                <label for="category">Kateqoriya</label>
                                                <select class="form-select select-multiple" id="category" multiple="multiple" name="category[]" required>
                                                    @foreach($categories as $category)
                                                        <option value="{{$category->id}}" {{in_array($category->id, $product->categories->pluck('id')->toArray()) ? 'selected' : ''}}>
                                                            {{$category->name_az}}
                                                        </option>
                                                    @endforeach
                                                </select>

                                                <label for="gender">Cinsiyyət</label>
                                                <select id="gender" class="form-select select-multiple" multiple="multiple" name="gender[]" required>
                                                    <option value="0" {{in_array(0, $product->genders->pluck('id')->toArray()) ? 'selected' : ''}}>Unisex</option>
                                                    <option value="1" {{in_array(1, $product->genders->pluck('id')->toArray()) ? 'selected' : ''}}>Kişi üçün</option>
                                                    <option value="2" {{in_array(2, $product->genders->pluck('id')->toArray()) ? 'selected' : ''}}>Qadın üçün</option>
                                                </select>
                                            </div>
                                            <div class="col-6">
                                                <label for="name">Ətir adı</label>
                                                <input type="text" id="name" name="name" class="form-control" value="{{$product->name}}" placeholder="Ətir adı" required="">

                                                <label for="type">Məhsul tipi</label>
                                                <select id="type" class="form-select select2" name="type_id" required>
                                                    @foreach($types as $type)
                                                        <option value="{{$type->id}}" {{$product->type_id == $type->id ? 'selected' : ''}}>
                                                            {{$type->name_az}}
                                                        </option>
                                                    @endforeach
                                                </select>

                                                <label for="ingredients">İnqredientlər</label>
                                                <select class="form-select select2-tags" multiple="multiple" name="ingredients[]" id="ingredients" required>
                                                    @foreach($ingredients as $ingredient)
                                                        <option value="{{$ingredient->id}}" {{in_array($ingredient->id, $product->ingredients->pluck('id')->toArray()) ? 'selected' : ''}}>
                                                            {{$ingredient->name_az}}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>
                                        <hr>
                                        <div class="row">
                                            <div class="col-12">
                                                <ul class="nav nav-tabs nav-tabs-title nav-tabs-line-title responsive-tabs" role="tablist">
                                                    <li class="nav-item" role="presentation">
                                                        <a class="nav-link active" data-bs-toggle="tab" href="#descaz" role="tab" aria-selected="true">İnformasiya AZ</a>
                                                    </li>
                                                    <li class="nav-item" role="presentation">
                                                        <a class="nav-link" data-bs-toggle="tab" href="#descen" role="tab" aria-selected="false">İnformasiya EN</a>
                                                    </li>
                                                    <li class="nav-item" role="presentation">
                                                        <a class="nav-link" data-bs-toggle="tab" href="#descru" role="tab" aria-selected="false">İnformasiya RU</a>
                                                    </li>
                                                </ul>
                                                <div class="tab-content">
                                                    <div class="tab-pane fade active show" id="descaz" role="tabpanel">
                                                        <textarea class="form-control" rows="5" name="content_az">{{$product->content_az}}</textarea>
                                                    </div>
                                                    <div class="tab-pane fade " id="descen" role="tabpanel">
                                                        <textarea class="form-control" rows="5" name="content_en">{{$product->content_en}}</textarea>
                                                    </div>
                                                    <div class="tab-pane fade " id="descru" role="tabpanel">
                                                        <textarea class="form-control" rows="5" name="content_ru">{{$product->content_ru}}</textarea>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="tab-pane fade show" id="product2" role="tabpanel">
                                        <div class="size_area">
                                            @foreach($product->sizes as $size)
                                                <div class="row mt-2 size-row">
                                                    <div class="col-6">
                                                        <select class="form-control" name="size[]">
                                                            @foreach($sizes as $sizeOption)
                                                                <option value="{{$sizeOption->id}}" {{$sizeOption->id == $size->id ? 'selected' : ''}}>
                                                                    {{$sizeOption->name_az}}
                                                                </option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                    <div class="col-5">
                                                        <input type="text" name="size_price[]" class="form-control" value="{{$size->pivot->price}}" placeholder="Qiymət" required="">
                                                    </div>
                                                    <div class="col-1">
                                                        @if($loop->first)
                                                        <button class="btn btn-primary btn-sm" type="button" id="addSize">+</button>
                                                        @else
                                                        <button class="btn btn-danger btn-sm removeSize" type="button">-</button>
                                                        @endif
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>

                                    <div class="tab-pane fade show" id="product3" role="tabpanel">
                                        <div class="image_area">
                                            @foreach($product->images as $image)
                                                <div class="row mt-2">
                                                    <div class="col-11">
                                                        <img src="{{asset('frontend/uploads/products/' . $image->image)}}" alt="Image" width="100">
                                                        <input type="checkbox" name="delete_images[]" value="{{$image->id}}"> Sil
                                                    </div>
                                                </div>
                                            @endforeach
                                            <div class="row mt-2">
                                                <div class="col-11">
                                                    <input type="file" name="image[]" class="form-control">
                                                </div>
                                                <div class="col-1">
                                                    <button class="btn btn-primary btn-sm" type="button" id="addImage">+</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-primary mt-3">Əlavə et</button>
                            </form>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </div>
@endsection
