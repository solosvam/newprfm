{{-- _purchase-form.blade.php-nin skripti (js_page bölməsində) --}}
<script>
    (() => {
        const modal = document.getElementById('purchaseModal'), box = document.getElementById('purchaseLines');
        const togglePayment = () => {
            const mode = (modal.querySelector('[name="payment"]:checked') || modal.querySelector('input[type="hidden"][name="payment"]'))?.value;
            modal.querySelectorAll('[data-show]').forEach((el) => { el.hidden = !el.dataset.show.split(' ').includes(mode); });
        };
        const refresh = () => {
            let total = 0;
            box.querySelectorAll('[data-line]').forEach((line, i) => {
                line.querySelectorAll('[name]').forEach((el) => { el.name = el.name.replace(/lines\[\d+]/, 'lines[' + i + ']'); });
                const q = Number(line.querySelector('[data-qty]').value) || 0, price = Number(line.querySelector('[data-price]').value) || 0;
                const unit = line.querySelector('[data-material]').selectedOptions[0]?.dataset.unit;
                const a = Math.round(q * price * 100) / 100;
                total += a;
                line.querySelector('[data-price-label]').textContent = '1 ' + (unit || 'vahidin') + ' qiyməti, ₼';
                line.querySelector('[data-unit-price]').textContent = q > 0 && price > 0 ? q + ' × ' + price + ' = ' + a.toFixed(2) + ' ₼' : '';
                line.querySelector('[data-remove]').hidden = box.children.length < 2;
            });
            document.getElementById('purchaseTotal').textContent = 'Cəmi: ' + total.toFixed(2) + ' ₼';
        };
        modal.addEventListener('change', (e) => { if (e.target.name === 'payment') togglePayment(); refresh(); });
        box.addEventListener('input', refresh);
        box.addEventListener('click', (e) => { if (e.target.matches('[data-remove]')) { e.target.closest('[data-line]').remove(); refresh(); } });
        document.getElementById('addLine').addEventListener('click', () => {
            const copy = box.lastElementChild.cloneNode(true);
            copy.querySelectorAll('input').forEach((el) => { el.value = ''; });
            copy.querySelector('select').value = '';
            box.appendChild(copy);
            refresh();
        });
        togglePayment();
        refresh();
        @if($errors->any())
            new bootstrap.Modal(modal).show();
        @endif
    })();
</script>
