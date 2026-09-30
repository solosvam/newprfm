document.addEventListener('DOMContentLoaded', function () {
    initCreditProfileRequiredDialog();
    initCreditApplicationModal();
});

function initCreditProfileRequiredDialog() {
    const dialog = document.getElementById('creditProfileRequiredDialog');
    if (!dialog) return;

    document.querySelectorAll('[data-credit-profile-required]').forEach(button =>
        button.addEventListener('click', () => dialog.showModal())
    );
    dialog.querySelector('[data-credit-profile-close]')?.addEventListener('click', () => dialog.close());
    dialog.addEventListener('click', event => { if (event.target === dialog) dialog.close(); });
}

function initCreditApplicationModal() {
    const dialog = document.getElementById('creditApplicationDialog');
    if (!dialog) return;

    const form = document.getElementById('creditApplicationForm');
    const variantOptions = document.getElementById('creditVariantOptions');
    const periodOptions = document.getElementById('creditPeriodOptions');
    const message = document.getElementById('creditApplicationMessage');
    const submitButtons = [form.querySelector('[type=submit]'), dialog.querySelector('.credit-modal-mobile-submit')];
    const rulesScroll = dialog.querySelector('.credit-modal-rules-scroll');
    const rulesHint = document.getElementById('creditRulesScrollHint');
    const acceptTerms = dialog.querySelector('[name=accept_terms]');

    const errorMessage = dialog.dataset.errorMessage || '';
    const successMessage = dialog.dataset.successMessage || '';

    const rulesAtBottom = () => rulesScroll.scrollTop + rulesScroll.clientHeight >= rulesScroll.scrollHeight - 5;

    function updateRulesHint() {
        rulesHint.hidden = rulesScroll.scrollHeight <= rulesScroll.clientHeight + 5 || rulesAtBottom();
    }
    rulesScroll.addEventListener('scroll', updateRulesHint, { passive: true });

    acceptTerms.addEventListener('click', function (event) {
        if (!rulesAtBottom()) {
            event.preventDefault();
            acceptTerms.checked = false;
            rulesScroll.scrollTo({ top: rulesScroll.scrollHeight, behavior: 'smooth' });
        }
    });

    const currency = new Intl.NumberFormat(document.documentElement.lang || 'az', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const format = value => currency.format(value) + ' ₼';

    let creditRule = null;
    try { creditRule = JSON.parse(document.getElementById('app-data')?.textContent || '{}').creditRule || null; } catch (e) {}

    // ≤ limit qiymətdə yalnız icazəli aylar (CreditPeriod::availableFor)
    function filterPeriods(price) {
        const inputs = [...periodOptions.querySelectorAll('input[name=credit_period_id]')];
        inputs.forEach(input => {
            input.closest('label').hidden = Boolean(creditRule) && price <= creditRule.limit
                && !creditRule.months.includes(Number(input.dataset.months));
        });
        const checked = inputs.find(input => input.checked);
        if (!checked || checked.closest('label').hidden) {
            const first = inputs.find(input => !input.closest('label').hidden);
            if (first) first.checked = true;
        }
    }

    function calculate() {
        const price = Number(variantOptions.querySelector('input:checked')?.dataset.price || 0);
        filterPeriods(price);
        const rate = Number(periodOptions.querySelector('input:checked')?.dataset.rate || 0);
        const months = Number(periodOptions.querySelector('input:checked')?.dataset.months || 1);
        const total = Math.round(price * (1 + rate / 100) * 100) / 100;

        document.getElementById('creditPrice').textContent = format(price);
        document.getElementById('creditMonthly').textContent = format(total / months);
        document.getElementById('creditTotal').textContent = format(total);
    }

    variantOptions.addEventListener('change', calculate);
    periodOptions.addEventListener('change', calculate);

    document.querySelectorAll('[data-credit-apply]').forEach(button => button.addEventListener('click', function () {
        const selectedVariant = document.querySelector('[data-buybox] .size-pill.active-size-amount');
        const selectedPeriod = document.querySelector('[data-installment] input[name=installment]:checked');

        if (selectedVariant) {
            const radio = variantOptions.querySelector('input[value="' + selectedVariant.dataset.variantId + '"]');
            if (radio) radio.checked = true;
        }
        if (selectedPeriod) {
            const radio = [...periodOptions.querySelectorAll('input')].find(input => Number(input.dataset.months) === Number(selectedPeriod.value));
            if (radio) radio.checked = true;
        }

        message.textContent = '';
        message.className = 'credit-modal-message';
        calculate();
        rulesScroll.scrollTop = 0;
        acceptTerms.checked = false;
        dialog.showModal();
        updateRulesHint();
    }));

    dialog.querySelector('[data-credit-close]').addEventListener('click', () => dialog.close());
    dialog.addEventListener('click', event => { if (event.target === dialog) dialog.close(); });

    form.addEventListener('submit', async function (event) {
        event.preventDefault();
        if (!form.reportValidity()) return;

        submitButtons.forEach(button => { button.disabled = true; });
        message.textContent = '';
        message.className = 'credit-modal-message';

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': form.querySelector('[name=_token]').value },
                body: new FormData(form),
                credentials: 'same-origin',
            });
            const data = await response.json();

            if (!response.ok) {
                if (data.redirect) { window.location.href = data.redirect; return; }
                throw new Error(data.message || Object.values(data.errors || {})[0]?.[0] || errorMessage);
            }

            if (data.redirect) { window.location.href = data.redirect; return; }

            message.classList.add('credit-modal-message--success');
            message.textContent = data.message || successMessage;
            acceptTerms.checked = false;
        } catch (error) {
            message.classList.add('credit-modal-message--error');
            message.textContent = error.message || errorMessage;
        } finally {
            submitButtons.forEach(button => { button.disabled = false; });
        }
    });
}
