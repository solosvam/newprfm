{{-- Ayarlar → Əlaqə məlumatları: footer və Əlaqə səhifəsi (App\Services\ContactInfo). Boş sahə saytda göstərilmir. --}}
@php
    $value = fn ($key) => old($key, $contact[$key] ?? '');
    $locales = ['az' => 'Azərbaycan', 'en' => 'English', 'ru' => 'Русский'];
@endphp
<div class="row g-4">
    <div class="col-xl-6">
        <div class="card h-100">
            <div class="card-body">
                <div class="settings-card-head">
                    <h5 class="mb-0">Telefon və e-poçt</h5>
                    <span class="text-muted small">Footer və Əlaqə səhifəsi</span>
                </div>
                @foreach([
                    'contact_phone' => ['Telefon', 'tel', '(012) 310 22 55', 'Saytda basanda zəng açılır.'],
                    'contact_whatsapp' => ['WhatsApp', 'tel', '(055) 551 07 00', 'Basanda WhatsApp söhbəti açılır.'],
                    'contact_email' => ['E-poçt', 'email', 'info@parfumshop.az', null],
                ] as $key => [$label, $type, $placeholder, $hint])
                    <div class="mb-3">
                        <label for="{{ $key }}" class="form-label">{{ $label }}</label>
                        <input id="{{ $key }}" name="{{ $key }}" type="{{ $type }}" maxlength="120" placeholder="{{ $placeholder }}"
                               value="{{ $value($key) }}" @class(['form-control', 'is-invalid' => $errors->has($key)])>
                        @error($key)<div class="invalid-feedback">{{ $message }}</div>@enderror
                        @if($hint)<div class="form-text">{{ $hint }}</div>@endif
                    </div>
                @endforeach

                <label class="form-label mt-2">İş saatları və ünvan</label>
                <ul class="nav nav-tabs nav-tabs-line mb-3" role="tablist">
                    @foreach($locales as $locale => $label)
                        <li class="nav-item" role="presentation">
                            <button type="button" role="tab" data-bs-toggle="tab" data-bs-target="#contactLang_{{ $locale }}"
                                    @class(['nav-link', 'active' => $loop->first])>{{ $label }}</button>
                        </li>
                    @endforeach
                </ul>
                <div class="tab-content">
                    @foreach($locales as $locale => $label)
                        <div id="contactLang_{{ $locale }}" role="tabpanel" @class(['tab-pane fade', 'show active' => $loop->first])>
                            <div class="mb-3">
                                <label for="contact_hours_{{ $locale }}" class="form-label">İş saatları</label>
                                <input id="contact_hours_{{ $locale }}" name="contact_hours_{{ $locale }}" maxlength="120"
                                       value="{{ $value('contact_hours_'.$locale) }}" class="form-control">
                            </div>
                            <div>
                                <label for="contact_address_{{ $locale }}" class="form-label">Ünvan</label>
                                <input id="contact_address_{{ $locale }}" name="contact_address_{{ $locale }}" maxlength="200"
                                       value="{{ $value('contact_address_'.$locale) }}" class="form-control" placeholder="Boş — göstərilmir">
                            </div>
                            @unless($locale === 'az')
                                <div class="form-text">Boş saxlanılarsa, Azərbaycan dilindəki göstərilir.</div>
                            @endunless
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-6">
        <div class="card h-100">
            <div class="card-body">
                <div class="settings-card-head">
                    <h5 class="mb-0">Sosial şəbəkələr</h5>
                    <span class="text-muted small">Footer-dəki ikonlar</span>
                </div>
                @foreach([
                    'contact_instagram' => ['Instagram', 'https://www.instagram.com/parfumshop.az'],
                    'contact_facebook' => ['Facebook', 'https://www.facebook.com/ParfumShopAZ'],
                    'contact_youtube' => ['YouTube', 'https://www.youtube.com/@parfumshop'],
                ] as $key => [$label, $placeholder])
                    <div class="mb-3">
                        <label for="{{ $key }}" class="form-label">{{ $label }}</label>
                        <input id="{{ $key }}" name="{{ $key }}" type="url" maxlength="300" placeholder="{{ $placeholder }}"
                               value="{{ $value($key) }}" @class(['form-control', 'is-invalid' => $errors->has($key)])>
                        @error($key)<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                @endforeach
                <div class="form-text">Link boş saxlanılan şəbəkənin ikonu footer-də göstərilmir.</div>
            </div>
        </div>
    </div>
</div>
