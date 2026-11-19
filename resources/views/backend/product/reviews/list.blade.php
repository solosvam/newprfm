@php
    $title = 'Məhsul rəyləri';
    $html_tag_data = [];
@endphp
@extends('backend.layout')

@section('content')
    <div class="container">
        <div class="page-title-container mb-4">
            <h1 class="mb-0 pb-0 display-4">Məhsul rəyləri</h1>
        </div>

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <form method="GET" class="card mb-4"><div class="card-body">
            <div class="row g-2 align-items-end">
                <div class="col-12 col-md-6 col-xl-5"><label class="form-label" for="revSearch">Ətir</label>
                    <input id="revSearch" type="search" name="q" class="form-control" value="{{ $search }}" placeholder="Brend və ya ətrin adı, məs.: creed aventus" maxlength="100"></div>
                <div class="col-6 col-md-3 col-xl-2"><label class="form-label" for="revRating">Reytinq</label>
                    <select id="revRating" name="rating" class="form-select">
                        <option value="">Hamısı</option>
                        @foreach([5, 4, 3, 2, 1] as $star)<option value="{{ $star }}" @selected($rating === $star)>{{ $star }} / 5</option>@endforeach
                    </select></div>
                <div class="col-6 col-md-3 col-xl-2"><button class="btn btn-primary w-100">Filter</button></div>
                @if($search !== '' || $rating)
                    <div class="col-12 col-xl-3"><a href="{{ route(request()->route()->getName()) }}" class="btn btn-link px-0">Filtri sıfırla</a></div>
                @endif
            </div>
        </div></form>

        @foreach(['pending' => 'Təsdiqlənməmiş rəylər', 'approved' => 'Təsdiqlənmiş rəylər'] as $group => $heading)
            @php($records = $group === 'pending' ? $pending : $approved)

            <div class="card mb-4">
                <div class="card-body">
                    <h2 class="small-title mb-3">{{ $heading }} ({{ $records->total() }})</h2>

                    <div class="table-responsive">
                        <table class="table table-striped align-middle">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Tarix</th>
                                    <th>Məhsul</th>
                                    <th>Müştəri</th>
                                    <th>Reytinq</th>
                                    <th>Rəy</th>
                                    <th>Əməliyyatlar</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($records as $review)
                                    <tr>
                                        <td>{{ $review->id }}</td>
                                        <td>{{ $review->created_at?->format('d.m.Y H:i') }}</td>
                                        <td>
                                            @if($review->product)
                                                <a href="{{ route('admin.product.edit', $review->product->id) }}">
                                                    {{ \App\Support\ProductTitle::of($review->product) }}
                                                </a>
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td>@if($review->customer){{ trim($review->customer->name.' '.$review->customer->surname) ?: '—' }}@elseif($review->author_name){{ $review->author_name }} <span class="text-muted small">(köhnə sayt)</span>@else — @endif</td>
                                        <td>{{ $review->rating }} / 5</td>
                                        <td style="min-width: 220px; white-space: pre-line;">{{ $review->comment }}</td>
                                        <td>
                                            <div class="d-flex flex-wrap gap-2">
                                                @if(!$review->active)
                                                    <form method="POST" action="{{ route('admin.product.review.approve', $review) }}">
                                                        @csrf
                                                        <button type="submit" class="btn btn-sm btn-success">Təsdiqlə</button>
                                                    </form>
                                                @endif

                                                <form method="POST" action="{{ route('admin.product.review.destroy', $review) }}"
                                                      onsubmit="return confirm('Bu rəyi silmək istəyirsiniz?')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-danger">Sil</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center text-muted py-4">Rəy yoxdur.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    {{ $records->appends(request()->except($group === 'pending' ? 'pending_page' : 'approved_page'))->links('backend.pagination') }}
                </div>
            </div>
        @endforeach
    </div>
@endsection
