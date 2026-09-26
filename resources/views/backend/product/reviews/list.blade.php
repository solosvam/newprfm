@extends('backend.layout')

@section('content')
    <div class="container">
        <div class="page-title-container mb-4">
            <h1 class="mb-0 pb-0 display-4">Məhsul rəyləri</h1>
        </div>

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        @foreach(['pending' => 'Təsdiqlənməmiş rəylər', 'approved' => 'Təsdiqlənmiş rəylər'] as $group => $heading)
            @php($records = $$group)

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
                                                    {{ $review->product->name }}
                                                </a>
                                                <div class="text-muted">{{ $review->product->brand?->name }}</div>
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td>{{ trim(($review->customer?->name ?? '') . ' ' . ($review->customer?->surname ?? '')) ?: '—' }}</td>
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

                    {{ $records->appends(request()->except($group === 'pending' ? 'pending_page' : 'approved_page'))->links() }}
                </div>
            </div>
        @endforeach
    </div>
@endsection
