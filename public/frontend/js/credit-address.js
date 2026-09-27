document.addEventListener('DOMContentLoaded', () => {
    const config = window.creditAddressConfig;
    const select = document.getElementById('addressSelect');
    const box = document.getElementById('newAddress');
    const button = document.getElementById('confirmCreditOrder');
    const error = document.getElementById('creditAddressError');
    const sync = () => box.classList.toggle('checkout-hidden', select.value !== 'new');
    select.addEventListener('change', sync);
    sync();
    button.addEventListener('click', async () => {
        button.disabled = true;
        error.hidden = true;
        const isNew = select.value === 'new';
        const val = id => document.getElementById(id)?.value || null;
        const payload = {
            address_mode: isNew ? 'new' : 'existing',
            address_id: isNew ? null : Number(select.value),
            title: val('addressTitle'), city: val('city'), district: val('district'),
            address: val('address'), building: val('building'), entrance: val('entrance'),
            floor: val('floor'), apartment: val('apartment'), address_note: val('addressNote'),
        };
        try {
            const response = await fetch(config.url, {
                method: 'POST',
                headers: {'Accept':'application/json','Content-Type':'application/json','X-CSRF-TOKEN':config.csrf},
                credentials:'same-origin', body:JSON.stringify(payload),
            });
            const data = await response.json();
            if (!response.ok) throw new Error(Object.values(data.errors || {}).flat().join(' | ') || data.message || config.error);
            window.location.href = data.redirect;
        } catch (e) {
            error.textContent = e.message || config.error;
            error.hidden = false;
            button.disabled = false;
        }
    });
});