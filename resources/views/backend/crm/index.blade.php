@php
    $html_tag_data = [];
    $title = 'CRM';
    $breadcrumbs = [
        '/admin' => 'ParfumShop',
        '#' => 'CRM',
    ];
@endphp

@extends('backend.layout', ['title' => $title])

@section('js_page')
    <script src="{{asset('backend/js/crm.js')}}"></script>
@endsection

@section('content')
    <div class="container">
        <!-- Title and Top Buttons Start -->
        <div class="page-title-container">
            <div class="row">
                <!-- Title Start -->
                <div class="col-12 col-md-7">
                    <h1 class="mb-0 pb-0 display-4" id="title">{{ $title }}</h1>
                    @include('backend._layout.breadcrumb',['breadcrumbs'=>$breadcrumbs])
                </div>
                <!-- Title End -->

                <!-- Top Buttons Start -->
                <div class="col-12 col-md-5 d-flex align-items-start justify-content-end">

                </div>
                <!-- Top Buttons End -->
            </div>
        </div>
        <!-- Title and Top Buttons End -->

        <div class="card mb-4">
            <div class="card-body">
                <h2 class="h5 mb-3">Müştəriyə bağlanmamış “Bir kliklə al” sifarişləri</h2>
                @forelse($unassignedOrders as $order)
                    <div class="border rounded p-3 mb-3">
                        <div class="d-flex justify-content-between flex-wrap gap-2">
                            <div>
                                <strong>{{ $order->order_no }}</strong>
                                <span class="text-muted ms-2">{{ $order->created_at?->format('d.m.Y H:i') }}</span>
                                <div>Mobil: <strong>{{ $order->guest_mobile }}</strong> · {{ number_format((float)$order->total, 2) }} ₼</div>
                                <div class="small text-muted">{{ $order->items->map(fn($item) => ($item->product?->name ?? 'Məhsul') . ' ×' . $item->quantity)->join(', ') }}</div>
                            </div>
                            <form action="{{ route('admin.crm.one-click.assign', $order) }}" method="POST" class="d-flex align-items-center gap-2 flex-wrap">
                                @csrf
                                <label for="assign-{{ $order->id }}" class="small">Müştəri ID</label>
                                <input id="assign-{{ $order->id }}" type="number" min="1" name="customer_id" required class="form-control" style="width:120px" placeholder="ID">
                                <button type="submit" class="btn btn-primary">Müştəriyə bağla</button>
                            </form>
                        </div>
                    </div>
                @empty
                    <p class="text-muted mb-0">Bağlanmamış sifariş yoxdur.</p>
                @endforelse
                {{ $unassignedOrders->links() }}
            </div>
        </div>
        <div class="row gx-4 gy-5">
            <div class="col-12">
                <!-- Biography Start -->
                <div class="card mb-5">
                    <div class="card-body">
                        <div class="position-relative">
                            <input type="text"
                                   id="crm-search"
                                   class="form-control form-control-lg"
                                   placeholder="Mobil no, Ad Soyad, .FIN"
                                   autocomplete="off"
                                   data-bs-toggle="popover"
                                   data-bs-placement="bottom"
                                   data-bs-trigger="focus"
                                   data-bs-html="true"
                                   data-bs-content="
                                   <ul class='mb-0 ps-3 small'>
                                       <li><b>Mobil no:</b> 0 ilə başlayan 10 rəqəm (0103227575)</li>
                                       <li><b>Ad Soyad:</b> boşluqla ayır (Ruf Ibr)</li>
                                       <li><b>FİN:</b> nöqtə ilə başlayan 8 simvol (.A1B2C34)</li>
                                   </ul>
                                   ">
                            <div id="search-results"
                                 class="position-absolute w-100 bg-white border rounded shadow-sm z-3"
                                 style="display:none; top: 100%; left:0; max-height: 300px; overflow-y: auto;z-index:1"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
