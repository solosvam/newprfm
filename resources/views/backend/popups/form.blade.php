@use('App\Models\Popup')
@php
    $html_tag_data = [];
    $editing = $popup->exists;
    $title = $editing ? 'Popup: '.$popup->name : 'Yeni popup';
    $breadcrumbs = ['/admin' => 'ParfumShop', route('admin.popups.index') => 'Popup-lar', '#' => $editing ? 'Redaktə' : 'Yeni'];
    $languages = ['az' => 'Azərbaycan', 'en' => 'English', 'ru' => 'Русский'];
    $dt = fn ($field) => old($field, $popup->{$field}?->format('Y-m-d\TH:i'));
    $daily = $daily ?? collect();
@endphp
@extends('backend.layout', ['html_tag_data' => $html_tag_data, 'title' => $title])

@section('css')
    <style>
        .popup-section-title { font-size: 13px; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; color: var(--muted); margin-bottom: 1rem; }
        .popup-help { font-size: 13px; color: var(--muted); margin-top: 4px; line-height: 1.45; }
        .popup-choice { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 8px; }
        .popup-choice input { position: absolute; opacity: 0; pointer-events: none; }
        .popup-choice label {
            display: flex; flex-direction: column; align-items: center; gap: 8px; padding: 12px 8px; margin: 0;
            border: 1px solid var(--separator); border-radius: var(--border-radius-md); cursor: pointer; text-align: center; font-size: 13px;
            transition: border-color .15s, background-color .15s;
        }
        .popup-choice input:checked + label { border-color: var(--primary); background: rgba(var(--primary-rgb), .08); color: var(--primary); }
        .popup-choice input:focus-visible + label { outline: 2px solid var(--primary); outline-offset: 2px; }
        /* mövqe sxemi: ekran içində yerləşmə */
        .pos-scheme { position: relative; width: 64px; height: 44px; border: 2px solid var(--separator); border-radius: 6px; background: var(--background); }
        .pos-scheme.is-mobile { width: 30px; height: 52px; border-radius: 6px; }
        .pos-scheme::after { content: ''; position: absolute; background: currentColor; border-radius: 3px; opacity: .85; }
        .pos-center::after { inset: 25% 22%; }
        .pos-corner::after { right: 4px; bottom: 4px; width: 40%; height: 38%; }
        .pos-bar::after { left: 3px; right: 3px; bottom: 3px; height: 22%; }
        .is-mobile.pos-corner::after { left: 3px; right: 3px; width: auto; height: 30%; }

        /* canlı önizləmə */
        .pv-stage { position: relative; height: 380px; border-radius: var(--border-radius-md); overflow: hidden; background: repeating-linear-gradient(135deg, var(--background), var(--background) 12px, var(--separator-light) 12px, var(--separator-light) 24px); margin: 0 auto; transition: width .2s; }
        .pv-stage.is-mobile { width: 260px; height: 460px; border: 6px solid var(--alternate); border-radius: 26px; }
        .pv-box { position: absolute; background: #fff; color: #1a1520; border-radius: 12px; box-shadow: 0 12px 30px rgba(0,0,0,.18); overflow: hidden; font-family: -apple-system, "Segoe UI", Roboto, Arial, sans-serif; }
        .pv-backdrop { position: absolute; inset: 0; background: rgba(15,10,25,.45); }
        .pv-media { background: #f4f2f7 center / cover no-repeat; aspect-ratio: 16 / 10; }
        .pv-content { padding: 12px 14px; }
        .pv-title { font-weight: 700; font-size: 14px; line-height: 1.3; margin-bottom: 4px; }
        .pv-text { font-size: 11.5px; line-height: 1.45; color: #6b6575; white-space: pre-line; }
        .pv-button { margin-top: 10px; background: #2b1f4a; color: #fff; border-radius: 8px; padding: 8px; font-size: 12px; font-weight: 600; text-align: center; }
        .pv-never { margin-top: 6px; font-size: 11px; color: #a39dae; text-decoration: underline; text-align: center; }
        .pv-close { position: absolute; top: 6px; right: 6px; width: 20px; height: 20px; border-radius: 50%; background: rgba(255,255,255,.9); font-size: 12px; line-height: 20px; text-align: center; box-shadow: 0 1px 3px rgba(0,0,0,.15); }
        .pv--center .pv-box { left: 50%; top: 50%; transform: translate(-50%, -50%); width: 62%; max-width: 260px; text-align: center; }
        .is-mobile .pv--center .pv-box { width: 86%; }
        .pv--corner .pv-box { right: 12px; bottom: 12px; width: 44%; max-width: 220px; }
        .is-mobile .pv--corner .pv-box { left: 8px; right: 8px; width: auto; max-width: none; }
        .pv--bar .pv-box { left: 10px; right: 10px; bottom: 10px; display: flex; align-items: center; }
        .pv--bar .pv-media { width: 70px; aspect-ratio: auto; align-self: stretch; flex: 0 0 70px; }
        .pv--bar .pv-content { flex: 1; display: flex; align-items: center; gap: 10px; }
        .pv--bar .pv-copy { flex: 1; }
        .pv--bar .pv-button { margin-top: 0; padding: 6px 10px; }
        .pv--bar .pv-never { display: none; }
        .is-mobile .pv--bar .pv-box { left: 0; right: 0; bottom: 0; border-radius: 14px 14px 0 0; flex-direction: column; align-items: stretch; }
        .is-mobile .pv--bar .pv-media { width: auto; flex: none; aspect-ratio: 16 / 7; }
        .is-mobile .pv--bar .pv-content { flex-direction: column; align-items: stretch; gap: 0; }
        .is-mobile .pv--bar .pv-button { margin-top: 10px; padding: 8px; }
        .is-mobile .pv--bar .pv-never { display: block; }
        .pv-stats-bar { display: flex; align-items: flex-end; gap: 4px; height: 70px; }
        .pv-stats-bar span { flex: 1; background: rgba(var(--primary-rgb), .25); border-radius: 3px 3px 0 0; min-height: 2px; position: relative; }
        .pv-stats-bar span i { position: absolute; left: 0; right: 0; bottom: 0; background: var(--primary); border-radius: 3px 3px 0 0; }
        @media (min-width: 1200px) { .popup-preview-sticky { position: sticky; top: 100px; } }
    </style>
@endsection

@section('content')
    <div class="container">
        <div class="page-title-container">
            <div class="row g-2">
                <div class="col-12 col-md">
                    <h1 class="mb-0 pb-0 display-4" id="title">{{ $editing ? $popup->name : 'Yeni popup' }}</h1>
                    @include('backend._layout.breadcrumb', ['breadcrumbs' => $breadcrumbs])
                </div>
                <div class="col-12 col-md-auto d-flex align-items-start gap-2">
                    @if($editing)
                        <button type="submit" form="delete-popup-form" class="btn btn-outline-danger">Sil</button>
                    @endif
                    <button type="submit" form="popupForm" class="btn btn-primary">Yadda saxla</button>
                </div>
            </div>
        </div>

        @if($errors->any())
            <div class="alert alert-danger mb-4">{{ $errors->first() }}</div>
        @endif

        <form id="popupForm" method="POST" enctype="multipart/form-data"
              action="{{ $editing ? route('admin.popups.update', $popup) : route('admin.popups.store') }}">
            @csrf
            <div class="row">
                <div class="col-12 col-xl-7">
                    {{-- Məzmun --}}
                    <div class="card mb-4">
                        <div class="card-body">
                            <div class="popup-section-title">Məzmun</div>
                            <div class="mb-3">
                                <label class="form-label">Ad (yalnız admində görünür)</label>
                                <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" maxlength="120" required
                                       value="{{ old('name', $popup->name) }}" placeholder="Məs.: Novruz kampaniyası">
                            </div>

                            <ul class="nav nav-tabs nav-tabs-line mb-3" role="tablist">
                                @foreach($languages as $locale => $label)
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link {{ $loop->first ? 'active' : '' }}" type="button" data-bs-toggle="tab"
                                                data-bs-target="#lang-{{ $locale }}" data-lang="{{ $locale }}" role="tab">{{ $label }}</button>
                                    </li>
                                @endforeach
                            </ul>
                            <div class="tab-content">
                                @foreach($languages as $locale => $label)
                                    <div class="tab-pane fade {{ $loop->first ? 'show active' : '' }}" id="lang-{{ $locale }}" role="tabpanel">
                                        <div class="mb-3">
                                            <label class="form-label">Başlıq</label>
                                            <input type="text" name="title_{{ $locale }}" maxlength="150" data-pv="title" data-lang="{{ $locale }}"
                                                   class="form-control @error('title_'.$locale) is-invalid @enderror" value="{{ old('title_'.$locale, $popup->{'title_'.$locale}) }}">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Mətn</label>
                                            <textarea name="body_{{ $locale }}" rows="3" maxlength="1000" data-pv="body" data-lang="{{ $locale }}"
                                                      class="form-control">{{ old('body_'.$locale, $popup->{'body_'.$locale}) }}</textarea>
                                        </div>
                                        <div class="mb-1">
                                            <label class="form-label">Düymə mətni</label>
                                            <input type="text" name="button_{{ $locale }}" maxlength="60" data-pv="button" data-lang="{{ $locale }}"
                                                   class="form-control" value="{{ old('button_'.$locale, $popup->{'button_'.$locale}) }}" placeholder="Məs.: Kampaniyaya bax">
                                        </div>
                                        @unless($locale === 'az')
                                            <div class="popup-help">Boş qalan sahələrdə Azərbaycan dilindəki mətn göstərilir.</div>
                                        @endunless
                                    </div>
                                @endforeach
                            </div>

                            <div class="mb-3 mt-3">
                                <label class="form-label">Link (düyməyə və ya şəklə basanda)</label>
                                <input type="text" name="link_url" maxlength="2048" class="form-control @error('link_url') is-invalid @enderror"
                                       value="{{ old('link_url', $popup->link_url) }}" placeholder="https://parfumshop.az/... və ya /brands">
                            </div>

                            <div>
                                <label class="form-label">Şəkil</label>
                                <input type="file" name="image" accept="image/*" id="popupImage" class="form-control @error('image') is-invalid @enderror">
                                <div class="popup-help">Tövsiyə: üfüqi, 1200×750 (16:10). Maksimum 10 MB; avtomatik kiçildilir.</div>
                                @if($popup->image)
                                    <div class="form-check mt-2">
                                        <input class="form-check-input" type="checkbox" name="remove_image" value="1" id="removeImage">
                                        <label class="form-check-label" for="removeImage">Mövcud şəkli sil</label>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>

                    {{-- Mövqe --}}
                    <div class="card mb-4">
                        <div class="card-body">
                            <div class="popup-section-title">Mövqe</div>
                            @foreach(['position_desktop' => 'Desktop (kompüter)', 'position_mobile' => 'Mobil'] as $field => $label)
                                <label class="form-label">{{ $label }}</label>
                                <div class="popup-choice mb-3">
                                    @foreach(Popup::POSITIONS as $value => $name)
                                        <input type="radio" name="{{ $field }}" value="{{ $value }}" id="{{ $field }}-{{ $value }}" data-pv-pos="{{ $field === 'position_mobile' ? 'mobile' : 'desktop' }}"
                                               @checked(old($field, $popup->{$field}) === $value)>
                                        <label for="{{ $field }}-{{ $value }}">
                                            <span class="pos-scheme pos-{{ $value }} {{ $field === 'position_mobile' ? 'is-mobile' : '' }}"></span>
                                            {{ $name }}
                                        </label>
                                    @endforeach
                                </div>
                            @endforeach
                        </div>
                    </div>

                    {{-- Kimə, harada, nə vaxt, tezlik --}}
                    <div class="card mb-4">
                        <div class="card-body">
                            <div class="popup-section-title">Göstərilmə qaydası</div>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Kimə</label>
                                    <select name="audience" class="form-select">
                                        @foreach(Popup::AUDIENCES as $value => $name)
                                            <option value="{{ $value }}" @selected(old('audience', $popup->audience) === $value)>{{ $name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    @php
                                        $pagesAll = (bool) old('pages_all', $popup->pages === 'all' || !$popup->pages);
                                        // köhnə formadan qalan old('pages') sətir ola bilər — həmişə massiv
                                        $pagesSelected = array_filter((array) old('pages', $popup->pageList()), 'is_string');
                                    @endphp
                                    <label class="form-label">Harada</label>
                                    <div class="form-check">
                                        <input type="hidden" name="pages_all" value="0">
                                        <input class="form-check-input" type="checkbox" name="pages_all" value="1" id="pagesAll" @checked($pagesAll)>
                                        <label class="form-check-label fw-bold" for="pagesAll">Bütün səhifələr</label>
                                    </div>
                                    <div id="pagesList" class="ps-3 border-start border-separator-light mt-1">
                                        @foreach(Popup::PAGES as $value => $name)
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="pages[]" value="{{ $value }}" id="page-{{ $value }}"
                                                       @checked(in_array($value, $pagesSelected, true)) @disabled($pagesAll)>
                                                <label class="form-check-label" for="page-{{ $value }}">{{ $name }}</label>
                                            </div>
                                        @endforeach
                                    </div>
                                    @error('pages')<div class="text-danger popup-help">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Başlama</label>
                                    <input type="datetime-local" name="starts_at" class="form-control" value="{{ $dt('starts_at') }}">
                                    <div class="popup-help">Boş — dərhal.</div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Bitmə</label>
                                    <input type="datetime-local" name="ends_at" class="form-control @error('ends_at') is-invalid @enderror" value="{{ $dt('ends_at') }}">
                                    <div class="popup-help">Boş — müddətsiz.</div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Səhifə açıldıqdan sonra (saniyə)</label>
                                    <input type="number" name="delay_seconds" min="0" max="300" class="form-control" required
                                           value="{{ old('delay_seconds', $popup->delay_seconds) }}">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Tezlik</label>
                                    <select name="frequency" class="form-select">
                                        @foreach(Popup::FREQUENCIES as $value => $name)
                                            <option value="{{ $value }}" @selected(old('frequency', $popup->frequency) === $value)>{{ $name }}</option>
                                        @endforeach
                                    </select>
                                    <div class="popup-help">"Bir daha göstərmə" seçən görməyəcək — tezlikdən asılı olmayaraq.</div>
                                </div>
                                <div class="col-12">
                                    <div class="form-check form-switch">
                                        <input type="hidden" name="active" value="0">
                                        <input class="form-check-input" type="checkbox" name="active" value="1" id="popupActive" @checked(old('active', $popup->active))>
                                        <label class="form-check-label" for="popupActive">Aktiv</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="mb-5">
                        <button type="submit" class="btn btn-primary">Yadda saxla</button>
                    </div>
                </div>

                {{-- Canlı önizləmə + statistika --}}
                <div class="col-12 col-xl-5">
                    <div class="popup-preview-sticky">
                        <div class="d-flex justify-content-between align-items-center">
                            <h2 class="small-title">Önizləmə</h2>
                            <div class="btn-group btn-group-sm mb-2" role="group">
                                <button type="button" class="btn btn-primary" data-pv-device="desktop">Desktop</button>
                                <button type="button" class="btn btn-outline-primary" data-pv-device="mobile">Mobil</button>
                            </div>
                        </div>
                        <div class="card mb-4">
                            <div class="card-body">
                                <div class="pv-stage" id="pvStage">
                                    <div id="pvLayer">
                                        <div class="pv-backdrop"></div>
                                        <div class="pv-box">
                                            <div class="pv-close">×</div>
                                            <div class="pv-media" id="pvMedia"></div>
                                            <div class="pv-content">
                                                <div class="pv-copy">
                                                    <div class="pv-title" id="pvTitle"></div>
                                                    <div class="pv-text" id="pvText"></div>
                                                </div>
                                                <div>
                                                    <div class="pv-button" id="pvButton"></div>
                                                    <div class="pv-never">Bir daha göstərmə</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        @if($editing)
                            @php
                                $days = collect(range(13, 0))->map(fn ($i) => today()->subDays($i)->toDateString());
                                $maxShown = max(1, (int) $daily->max('shown'));
                                $totals = ['shown' => $daily->sum('shown'), 'clicked' => $daily->sum('clicked'), 'closed' => $daily->sum('closed'), 'dismissed' => $daily->sum('dismissed')];
                            @endphp
                            <h2 class="small-title">Son 14 gün</h2>
                            <div class="card mb-5">
                                <div class="card-body">
                                    <div class="row g-0 text-center mb-3">
                                        @foreach(['shown' => ['Göstərilib', 'primary'], 'clicked' => ['Klik', 'success'], 'closed' => ['Bağlayıb', 'muted'], 'dismissed' => ['Bir daha göstərmə', 'danger']] as $key => [$label, $color])
                                            <div class="col">
                                                <div class="cta-3 text-{{ $color }}">{{ $totals[$key] }}</div>
                                                <div class="text-muted" style="font-size: 12px">{{ $label }}</div>
                                            </div>
                                        @endforeach
                                    </div>
                                    <div class="pv-stats-bar" title="Göstərilmə (açıq) və klik (tünd) gün üzrə">
                                        @foreach($days as $day)
                                            @php $row = $daily->get($day); $shown = (int) ($row->shown ?? 0); $clicked = (int) ($row->clicked ?? 0); @endphp
                                            <span style="height: {{ round($shown / $maxShown * 100) }}%" title="{{ \Illuminate\Support\Carbon::parse($day)->format('d.m') }}: {{ $shown }} göstərilmə, {{ $clicked }} klik">
                                                <i style="height: {{ $shown ? round($clicked / $shown * 100) : 0 }}%"></i>
                                            </span>
                                        @endforeach
                                    </div>
                                    <div class="d-flex justify-content-between text-muted mt-1" style="font-size: 12px">
                                        <span>{{ \Illuminate\Support\Carbon::parse($days->first())->format('d.m') }}</span>
                                        <span>bu gün</span>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </form>

        @if($editing)
            <form id="delete-popup-form" method="POST" action="{{ route('admin.popups.destroy', $popup) }}"
                  onsubmit="return confirm('Popup silinsin? Statistikası da silinəcək.')">
                @csrf
                @method('DELETE')
            </form>
        @endif
    </div>
@endsection

@section('js_page')
    <script>
        // Canlı önizləmə: seçilmiş dil, cihaz və mövqeyə görə
        (() => {
            const form = document.getElementById('popupForm');
            const stage = document.getElementById('pvStage');
            const layer = document.getElementById('pvLayer');
            const media = document.getElementById('pvMedia');
            const els = {title: document.getElementById('pvTitle'), body: document.getElementById('pvText'), button: document.getElementById('pvButton')};
            const fileInput = document.getElementById('popupImage');
            const removeImage = document.getElementById('removeImage');
            let imageUrl = @json($popup->imageUrl());
            let lang = 'az';
            let device = 'desktop';

            const value = (field, l) => form.querySelector(`[data-pv="${field}"][data-lang="${l}"]`)?.value.trim() || '';
            const text = (field) => value(field, lang) || value(field, 'az');

            function render() {
                Object.keys(els).forEach((field) => {
                    els[field].textContent = text(field);
                    els[field].hidden = !text(field);
                });
                const showImage = imageUrl && !(removeImage && removeImage.checked && !fileInput.files.length);
                media.hidden = !showImage;
                media.style.backgroundImage = showImage ? `url('${imageUrl}')` : '';
                const pos = form.querySelector(`[data-pv-pos="${device}"]:checked`)?.value || 'center';
                layer.className = 'pv--' + pos;
                layer.querySelector('.pv-backdrop').hidden = pos !== 'center';
                stage.classList.toggle('is-mobile', device === 'mobile');
            }

            // "Bütün səhifələr" seçiləndə ayrı-ayrı səhifələr söndürülür
            const pagesAll = document.getElementById('pagesAll');
            pagesAll.addEventListener('change', () => {
                document.querySelectorAll('#pagesList input').forEach((input) => { input.disabled = pagesAll.checked; });
            });

            form.addEventListener('input', render);
            form.addEventListener('change', render);
            fileInput.addEventListener('change', () => {
                const file = fileInput.files[0];
                if (file) imageUrl = URL.createObjectURL(file);
                render();
            });
            document.querySelectorAll('[data-bs-toggle="tab"][data-lang]').forEach((tab) => tab.addEventListener('shown.bs.tab', () => {
                lang = tab.dataset.lang;
                render();
            }));
            document.querySelectorAll('[data-pv-device]').forEach((button) => button.addEventListener('click', () => {
                device = button.dataset.pvDevice;
                document.querySelectorAll('[data-pv-device]').forEach((b) => {
                    b.classList.toggle('btn-primary', b === button);
                    b.classList.toggle('btn-outline-primary', b !== button);
                });
                render();
            }));
            render();
        })();
    </script>
@endsection
