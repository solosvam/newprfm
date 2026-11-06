{{-- Səhifələmə: əvvəlki / növbəti ($paginator — LengthAwarePaginator) --}}
@if($paginator->hasPages())
    <nav class="pager" aria-label="Səhifələr">
        @if($paginator->onFirstPage())<span class="btn btn--ghost is-disabled">‹ Əvvəlki</span>@else<a class="btn btn--ghost" href="{{ $paginator->previousPageUrl() }}">‹ Əvvəlki</a>@endif
        <span class="pager__info">{{ $paginator->currentPage() }} / {{ $paginator->lastPage() }}</span>
        @if($paginator->hasMorePages())<a class="btn btn--ghost" href="{{ $paginator->nextPageUrl() }}">Növbəti ›</a>@else<span class="btn btn--ghost is-disabled">Növbəti ›</span>@endif
    </nav>
@endif
