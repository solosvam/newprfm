{{--
  Sidebar məhsul siyahısı (Tövsiyə olunanlar / Ən çox satılanlar).
  Parametrlər: $items, $title, $panelId, $ranked (bool — sıra nömrəsi göstərilsin),
  $dynamic (bool — qonaq: main.js brauzer bəyəndiklərinə görə /recommendations ilə əvəz edir)
  Mobil (≤1024px): başlığa toxunanda açılır (data-collapsible-panel).
--}}
@if($items->isNotEmpty())
    <section class="side-panel filter-card side-list" data-collapsible-panel>
        <button type="button" class="side-panel__toggle" data-panel-toggle aria-expanded="false" aria-controls="{{ $panelId }}">
            <h3>{{ $title }}</h3>
            <svg class="side-panel__chevron" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>
        </button>

        <div class="side-panel__body" id="{{ $panelId }}">
            <ol class="side-list__items" @if($dynamic ?? false) data-guest-recommendations @endif>
                @include('frontend.includes.sidebar-products-items', ['items' => $items, 'ranked' => $ranked ?? false])
            </ol>
        </div>
    </section>
@endif
