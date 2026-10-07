{{-- Sonsuz scroll-un sonu: görünəndə növbəti səhifə yüklənir (frontend/js/infinite-list.js).
     JS işləməsə — adi səhifə linkləri (data-infinite-fallback, JS gizlədir). Lazım olan: $paginator, $links --}}
@if($paginator->hasMorePages())
    <div class="infinite-sentinel" data-infinite-sentinel
         data-loading="{{ __('infinite_loading') }}" data-error="{{ __('infinite_error') }}" data-retry="{{ __('infinite_retry') }}" data-more="{{ __('infinite_more') }}">
        <span class="infinite-sentinel__spinner" aria-hidden="true"></span>
        <span class="infinite-sentinel__text" aria-live="polite"></span>
    </div>
@endif
@if($paginator->hasPages())
    <div class="infinite-fallback" data-infinite-fallback>{{ $links }}</div>
@endif
<script defer src="{{ asset_v('frontend/js/infinite-list.js') }}"></script>
