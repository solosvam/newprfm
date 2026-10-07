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
    });

});
