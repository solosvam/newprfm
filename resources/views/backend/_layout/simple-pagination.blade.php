{{-- Sadə səhifələmə (server tərəfdə paginate): "1–50 / 841 · Əvvəlki · 1 / 17 · Növbəti". Parametr: $paginator.
     Acorn-un .pagination .page-link-i sabit ölçülü kvadratdır (rəqəm üçün) — mətnli keçidlər üst-üstə düşür, ona görə düymələr işlədilir. --}}
@if($paginator->hasPages())
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
        <span class="text-muted text-small">{{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} / {{ $paginator->total() }}</span>
        <div class="d-flex align-items-center gap-2">
            @if($paginator->onFirstPage())
                <span class="btn btn-sm btn-outline-primary disabled" aria-disabled="true">Əvvəlki</span>
            @else
                <a class="btn btn-sm btn-outline-primary" href="{{ $paginator->previousPageUrl() }}" rel="prev">Əvvəlki</a>
            @endif
            <span class="text-muted text-nowrap">{{ $paginator->currentPage() }} / {{ $paginator->lastPage() }}</span>
            @if($paginator->hasMorePages())
                <a class="btn btn-sm btn-outline-primary" href="{{ $paginator->nextPageUrl() }}" rel="next">Növbəti</a>
            @else
                <span class="btn btn-sm btn-outline-primary disabled" aria-disabled="true">Növbəti</span>
            @endif
        </div>
    </div>
@endif
