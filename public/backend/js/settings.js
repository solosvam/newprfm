/**
 * Admin → Ayarlar: asılı sahələrin göstərilməsi (bütün bölmələr üçün bir fayl; olmayan element nəzərə alınmır).
 *  - data-settings-toggle="x" açarı söndürüləndə data-settings-target="x" gizlənir (dəyər yadda qalır);
 *  - çatdırılma / bükülmə rejimi, qeydiyyat bonusu;
 *  - referal: "promo ilə birlikdə" yalnız endirim rejimində; proqram sönülüdürsə kart solğun.
 */
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('settingsForm');
    if (!form) return;
    const $ = (id) => document.getElementById(id);

    const show = (wrapper, input, visible) => {
        wrapper?.classList.toggle('d-none', !visible);
        if (input) {
            input.disabled = !visible;
            input.required = visible;
        }
    };

    const sync = () => {
        form.querySelectorAll('[data-settings-toggle]').forEach((toggle) => {
            form.querySelectorAll('[data-settings-target="' + toggle.dataset.settingsToggle + '"]')
                .forEach((el) => el.classList.toggle('d-none', !toggle.checked));
        });

        const delivery = $('delivery_mode');
        if (delivery) {
            show($('deliveryFeeField'), $('delivery_fee'), delivery.value !== 'free');
            show($('deliveryThreshold'), $('free_delivery_from'), delivery.value === 'threshold');
        }
        const gift = $('gift_wrap_mode');
        if (gift) show($('giftWrapFeeField'), $('gift_wrap_fee'), gift.value === 'paid');

        const registration = $('registration_bonus_enabled');
        const registrationAmount = $('registration_bonus_amount');
        if (registration && registrationAmount) {
            registrationAmount.disabled = !registration.checked;
            registrationAmount.required = registration.checked;
        }

        const referral = $('referralSettings');
        if (referral) {
            const mode = referral.querySelector('input[name="referral_invitee_mode"]:checked')?.value;
            referral.querySelectorAll('[data-referral-show]').forEach((el) => el.classList.toggle('d-none', el.dataset.referralShow !== mode));
            referral.querySelector('.row.g-4')?.classList.toggle('opacity-50', !$('referral_enabled')?.checked);
        }
    };

    form.addEventListener('change', sync);
    sync();

    // Bannerlər: ölçü yazıldıqca nisbət önizləməsi yenilənir
    form.addEventListener('input', (event) => {
        const key = event.target.dataset?.bannerSize;
        if (!key) return;
        const w = Math.max(1, Number(form.querySelector('[data-banner-size="' + key + '"][data-banner-dimension="width"]')?.value) || 1);
        const h = Math.max(1, Number(form.querySelector('[data-banner-size="' + key + '"][data-banner-dimension="height"]')?.value) || 1);
        const preview = form.querySelector('[data-banner-preview="' + key + '"]');
        if (preview) preview.style.aspectRatio = w + ' / ' + h;
        const label = form.querySelector('[data-banner-label="' + key + '"]');
        if (label) label.textContent = w + '×' + h;
        bannerWarning(key, w, h);
        bannerScreen(key, w, h);
    });

    // Bannerlər: ilk ekranda (brauzerin öz zolaqlarından sonra qalan yerdə) birinci sıra məhsul kartının nə qədəri görünür.
    // Banner böyüdükcə kartlar aşağı düşür; kartın görünən hissəsi həddən azalanda çərçivə qırmızı olur.
    const bannerScreen = (key, w, h) => {
        const info = form.querySelector('[data-banner-screen="' + key + '"]');
        if (!info) return;
        const d = info.dataset;
        const banner = Number(d.screenWidth) * h / w;
        const left = Number(d.screenViewport) - Number(d.screenAbove) - banner;
        const visible = Math.max(0, Math.min(100, Math.round(left / Number(d.screenCard) * 100)));
        const over = visible < Number(d.screenLimit);
        info.textContent = 'banner ' + Math.round(banner) + ' px · ilk ekranda məhsul kartının ' + visible + '%-i görünür';
        info.classList.toggle('text-danger', over);
        info.classList.toggle('text-muted', !over);
        form.querySelector('[data-banner-device="' + key + '"]')?.classList.toggle('is-over', over);
    };

    // Bannerlər: həddi aşan ölçü yazılan kimi səbəbi ilə xəbərdarlıq (server də eyni hədləri yoxlayır — Setting::bannerLimits)
    const bannerWarning = (key, w, h) => {
        const row = form.querySelector('[data-banner-limits="' + key + '"]');
        const box = form.querySelector('[data-banner-warning="' + key + '"]');
        if (!row || !box) return;
        const d = row.dataset;
        let text = '';
        if (w > Number(d.maxWidth)) {
            text = 'En ' + w + ' px çox böyükdür. Saytda bu banner ' + d.displayWidth + ' px enində göstərilir — ən çox ' + d.maxWidth + ' px yazmaq olar. Böyük şəkil keyfiyyəti artırmır, yalnız səhifəni ağırlaşdırır.';
        } else if (w < Number(d.minWidth)) {
            text = 'En ' + w + ' px çox kiçikdir. Saytda bu banner ' + d.displayWidth + ' px enində göstərilir — ən azı ' + d.minWidth + ' px olmalıdır, yoxsa bulanıq görünər.';
        } else if (w / h < Number(d.minRatio)) {
            text = 'Banner çox hündürdür — saytda ilk ekranı tutacaq. ' + w + ' px en üçün hündürlük ən çox ' + Math.floor(w / Number(d.minRatio)) + ' px ola bilər.';
        } else if (w / h > Number(d.maxRatio)) {
            text = 'Banner çox nazikdir. ' + w + ' px en üçün hündürlük ən azı ' + Math.ceil(w / Number(d.maxRatio)) + ' px olmalıdır.';
        }
        box.classList.toggle('d-none', !text);
        box.lastElementChild.textContent = text;
    };
    const bannerInputs = (key) => ['width', 'height'].map((dimension) => form.querySelector('[data-banner-size="' + key + '"][data-banner-dimension="' + dimension + '"]'));
    form.querySelectorAll('[data-banner-limits]').forEach((row) => {
        const [w, h] = bannerInputs(row.dataset.bannerLimits);
        if (!w || !h) return;
        bannerWarning(row.dataset.bannerLimits, Number(w.value) || 1, Number(h.value) || 1);
        bannerScreen(row.dataset.bannerLimits, Number(w.value) || 1, Number(h.value) || 1);
    });
    form.querySelector('[data-banner-reset]')?.addEventListener('click', () => {
        form.querySelectorAll('[data-banner-size]').forEach((input) => {
            input.value = input.placeholder;
            input.dispatchEvent(new Event('input', { bubbles: true }));
        });
    });

});
