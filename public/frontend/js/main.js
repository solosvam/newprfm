(() => {
    'use strict';
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    const loggedIn = document.body.dataset.auth === '1';
    const favoritesKey = 'parfumshop_favorites';

    function notify(message, type = 'success') {
        if (!window.jQuery || typeof window.jQuery.notify !== 'function') return;
        const $ = window.jQuery;
        const icons = { success: '✓', error: '✕', warning: '!', info: 'i' };
        const variant = Object.prototype.hasOwnProperty.call(icons, type) ? type : 'info';
        const style = 'parfumshop-' + variant;

        if (!$.notify.getStyle(style)) {
            $.notify.addStyle(style, {
                html: '<div><div class="ps-notify"><span class="ps-notify__check">' +
                    icons[variant] + '</span><span data-notify-text></span></div></div>'
            });
        }

        $.notify(message, {
            style,
            className: variant,
            globalPosition: 'top right',
            autoHideDelay: 3000,
            showAnimation: 'fadeIn',
            hideAnimation: 'fadeOut'
        });
    }

    window.parfumshopNotify = notify;

    function initHeaderSearch() {
        const form = document.getElementById('searchForm');
        const input = document.getElementById('searchInput');
        const resultsBox = document.getElementById('searchResults');
        if (!form || !input || !resultsBox) return;

        let timer;
        let controller;
        let results = [];
        let activeIndex = -1;
        let searchLogId = null;

        const show = () => { resultsBox.hidden = false; };
        const hide = () => {
            resultsBox.hidden = true;
            resultsBox.replaceChildren();
            activeIndex = -1;
        };
        const text = (tag, className, value) => {
            const node = document.createElement(tag);
            node.className = className;
            node.textContent = value || '';
            return node;
        };

        function render(data) {
            results = data.results || [];
            searchLogId = data.search_log_id || null;
            resultsBox.replaceChildren();
            activeIndex = -1;

            if (results.length === 0) {
                const empty = text('div', 'search-results-empty', 'Axtardığınız məhsul tapılmadı.');
                if (data.suggestion) {
                    empty.append(document.createElement('br'));
                    empty.append(text('strong', '', 'Bəlkə “' + data.suggestion + '” nəzərdə tutursunuz?'));
                }
                resultsBox.append(empty);
                show();
                return;
            }

            results.forEach((product, index) => {
                const link = document.createElement('a');
                link.className = 'search-result-item';
                link.href = product.url;
                link.dataset.searchIndex = String(index);
                link.addEventListener('click', () => recordClick(product, index));

                const thumb = document.createElement('div');
                thumb.className = 'search-result-thumb';
                if (product.image) {
                    const image = document.createElement('img');
                    image.src = product.image;
                    image.alt = product.name || '';
                    thumb.append(image);
                } else {
                    thumb.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="26" height="26"><path d="M9 3h6l1 4H8l1-4Z"/><path d="M8 7h8l1 13a2 2 0 0 1-2 2H9a2 2 0 0 1-2-2L8 7Z"/></svg>';
                }

                const info = document.createElement('div');
                info.className = 'search-result-info';
                info.append(text('p', 'search-result-brand', product.brand));
                info.append(text('p', 'search-result-name', product.name));
                info.append(text('div', 'search-result-meta', [product.type, product.gender].filter(Boolean).join(' · ')));

                const price = [product.size, product.price ? product.price + ' ₼' : null]
                    .filter(Boolean)
                    .join(' / ');

                link.append(thumb, info, text('span', 'search-result-price', price));
                resultsBox.append(link);
            });
            show();
        }

        function recordClick(product, index) {
            if (!searchLogId || !form.dataset.clickUrl) return;

            fetch(form.dataset.clickUrl, {
                method: 'POST',
                keepalive: true,
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf || '',
                },
                body: JSON.stringify({
                    search_log_id: searchLogId,
                    product_id: product.id,
                    result_rank: index + 1,
                }),
            }).catch(() => {});
        }

        async function request(query) {
            controller?.abort();
            controller = new AbortController();
            const url = new URL(form.dataset.suggestionsUrl, window.location.origin);
            url.searchParams.set('q', query);

            try {
                const response = await fetch(url, {
                    headers: {'Accept': 'application/json'},
                    signal: controller.signal,
                });
                if (!response.ok) throw new Error('Axtarış sorğusu alınmadı');
                render(await response.json());
            } catch (error) {
                if (error.name !== 'AbortError') hide();
            }
        }

        input.addEventListener('input', () => {
            const query = input.value.trim();
            clearTimeout(timer);
            if (query.length < 2) {
                controller?.abort();
                hide();
                return;
            }
            timer = setTimeout(() => request(query), 350);
        });

        input.addEventListener('keydown', event => {
            if (resultsBox.hidden || results.length === 0) return;
            if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                event.preventDefault();
                activeIndex = event.key === 'ArrowDown'
                    ? (activeIndex + 1) % results.length
                    : (activeIndex - 1 + results.length) % results.length;
                resultsBox.querySelectorAll('.search-result-item').forEach((item, index) => {
                    item.classList.toggle('is-active', index === activeIndex);
                });
            }
            if (event.key === 'Escape') hide();
        });

        form.addEventListener('submit', event => {
            if (results.length === 0) return;
            event.preventDefault();
            const index = activeIndex >= 0 ? activeIndex : 0;
            recordClick(results[index], index);
            window.location.href = results[index].url;
        });

        document.addEventListener('click', event => {
            if (!form.contains(event.target) && !resultsBox.contains(event.target)) hide();
        });
    }

    // Laravel redirect-lərindən sonra bütün yeni Blade səhifələrində flash bildirişləri göstər.
    const flash = window.parfumshopFlash || {};
    for (const type of ['success', 'error', 'warning', 'info']) {
        if (typeof flash[type] === 'string' && flash[type].trim()) {
            notify(flash[type], type);
        }
    }

    function readArray(key) {
        try {
            const value = JSON.parse(localStorage.getItem(key) || '[]');
            return Array.isArray(value) ? value : [];
        } catch { return []; }
    }

    function updateCounts() {
        const cart = readArray('parfumshop_cart');
        const cartCount = cart.reduce((sum, item) => sum + (Number(item.quantity) || 1), 0);
        const cartBadge = document.getElementById('headerCartCount');
        if (cartBadge) {
            cartBadge.textContent = String(cartCount);
            cartBadge.classList.toggle('is-empty', cartCount === 0);
        }
        const wishlistBadge = document.getElementById('headerWishlistCount');
        if (wishlistBadge) {
            const count = readArray(favoritesKey).length;
            wishlistBadge.textContent = String(count);
            wishlistBadge.classList.toggle('is-empty', count === 0);
        }
    }

    async function syncFavorites() {
        if (!loggedIn) {
            const ids = readArray(favoritesKey).map(Number);
            document.querySelectorAll('.fav-btn').forEach(btn => btn.classList.toggle('active', ids.includes(Number(btn.dataset.productId))));
            updateCounts();
            return;
        }
        try {
            const response = await fetch('/favorites/ids', {headers: {'Accept':'application/json'}});
            if (!response.ok) return;
            const ids = (await response.json()).ids.map(Number);
            localStorage.setItem(favoritesKey, JSON.stringify(ids));
            document.querySelectorAll('.fav-btn').forEach(btn => btn.classList.toggle('active', ids.includes(Number(btn.dataset.productId))));
            updateCounts();
        } catch (error) { console.warn('Seçilmişlər yüklənmədi', error); }
    }

    const buybox = document.querySelector('[data-buybox]');
    const installment = document.querySelector('[data-installment]');

    function updateInstallments() {
        if (!buybox || !installment) return;
        const variant = buybox.querySelector('.size-pill.active-size-amount');
        const price = Number(variant?.dataset.price || buybox.dataset.basePrice || 0);
        const priceDisplay = buybox.querySelector('[data-price-display]');
        if (priceDisplay) priceDisplay.textContent = price.toFixed(2) + ' ₼';

        installment.querySelectorAll('tr[data-month]').forEach(row => {
            const months = Number(row.dataset.month);
            const rate = Number(row.dataset.rate || 0);
            const total = price * (1 + rate / 100);
            const monthly = months > 0 ? total / months : 0;
            const monthlyCell = row.querySelector('[data-installment-monthly]');
            const totalCell = row.querySelector('[data-installment-total]');
            if (monthlyCell) monthlyCell.textContent = monthly.toFixed(2) + ' ₼';
            if (totalCell) totalCell.textContent = total.toFixed(2) + ' ₼';
            const radio = row.querySelector('input[name="installment"]');
            if (radio) {
                radio.dataset.monthly = monthly.toFixed(2);
                radio.dataset.total = total.toFixed(2);
            }
        });

        // Birbank is always displayed as six interest-free installments.
        const headline = installment.querySelector('[data-installment-headline]');
        if (headline) headline.textContent = (price / 6).toFixed(2) + ' ₼ x 6 ay';
        const selected = buybox.querySelector('[data-add-to-cart]');
        if (selected && variant) selected.dataset.variantId = variant.dataset.variantId;
    }

    function initProductTabs() {
        const tabs = document.querySelector('[data-tabs]');
        if (!tabs) return;
        const activate = name => {
            tabs.querySelectorAll('[data-tab]').forEach(button => {
                const active = button.dataset.tab === name;
                button.classList.toggle('active', active);
                button.setAttribute('aria-selected', String(active));
            });
            tabs.querySelectorAll('[data-tab-panel]').forEach(panel => {
                panel.hidden = panel.dataset.tabPanel !== name;
            });
        };
        tabs.addEventListener('click', event => {
            const button = event.target.closest('[data-tab]');
            if (button) activate(button.dataset.tab);
        });
        if (window.location.hash === '#reviews' || tabs.hasAttribute('data-show-reviews')) activate('reviews');
    }

    if (installment) {
        installment.addEventListener('click', event => {
            const row = event.target.closest('tr[data-month]');
            if (row && !event.target.matches('input')) {
                const radio = row.querySelector('input[name="installment"]');
                if (radio) { radio.checked = true; radio.dispatchEvent(new Event('change', {bubbles:true})); }
            }
        });
        installment.addEventListener('change', event => {
            if (event.target.matches('input[name="installment"]')) {
                installment.querySelectorAll('tr[data-month]').forEach(row => {
                    row.classList.toggle('active', row.contains(event.target));
                });
            }
        });
        updateInstallments();
    }
    initProductTabs();
    initHeaderSearch();

    document.addEventListener('click', async event => {
        const fav = event.target.closest('.fav-btn');
        if (fav) {
            event.preventDefault();
            const id = Number(fav.dataset.productId);
            const ids = readArray(favoritesKey).map(Number);
            const active = ids.includes(id);
            fav.classList.toggle('active', !active);
            localStorage.setItem(favoritesKey, JSON.stringify(active ? ids.filter(v => v !== id) : [...ids, id]));
            updateCounts();
            if (loggedIn) {
                try {
                    const response = await fetch('/favorites/' + id, {
                        method: active ? 'DELETE' : 'POST',
                        headers: {'X-CSRF-TOKEN': csrf || '', 'Accept':'application/json'}
                    });
                    if (!response.ok) throw new Error('HTTP ' + response.status);
                } catch {
                    localStorage.setItem(favoritesKey, JSON.stringify(ids));
                    fav.classList.toggle('active', active);
                    updateCounts();
                    notify('Əməliyyat yerinə yetirilmədi', 'error');
                    return;
                }
            }
            notify(active ? (window.parfumshopMessages?.favoriteRemoved || 'Seçilmişlərdən silindi') : (window.parfumshopMessages?.favoriteAdded || 'Seçilmişlərə əlavə edildi'));
            return;
        }

        const share = event.target.closest('.share-btn');
        if (share) {
            event.preventDefault();
            const url = share.dataset.url || window.location.href;
            if (navigator.share) {
                try { await navigator.share({title: share.dataset.title || document.title, url}); } catch {}
            } else {
                try { await navigator.clipboard.writeText(url); notify('Link kopyalandı'); }
                catch { window.prompt('Linki kopyalayın:', url); }
            }
            return;
        }

        const thumbnail = event.target.closest('.thumbs .t img');
        if (thumbnail) {
            const main = document.querySelector('.main-image > img');
            if (main) { main.src = thumbnail.src; main.alt = thumbnail.alt || main.alt; }
            return;
        }

        const size = event.target.closest('.size-pill');
        if (size) {
            document.querySelectorAll('.size-pill').forEach(el => el.classList.remove('active-size-amount'));
            size.classList.add('active-size-amount');
            updateInstallments();
            return;
        }

        const qtyButton = event.target.closest('[data-qty-action]');
        if (qtyButton) {
            const display = document.querySelector('[data-qty-value]');
            if (display) display.textContent = String(Math.max(1, Number(display.textContent || 1) + (qtyButton.dataset.qtyAction === 'plus' ? 1 : -1)));
            return;
        }

        const add = event.target.closest('[data-add-to-cart]');
        if (add) {
            const selected = document.querySelector('.size-pill.active-size-amount');
            if (!selected) return;
            const cart = readArray('parfumshop_cart');
            const id = Number(selected.dataset.variantId);
            const quantity = Number(document.querySelector('[data-qty-value]')?.textContent || 1);
            const item = cart.find(v => Number(v.variant_id) === id);
            if (item) {
                item.quantity = (Number(item.quantity) || 1) + quantity;
                item.product_id = Number(add.dataset.productId || buybox?.dataset.productId);
            }
            else cart.push({product_id: Number(add.dataset.productId || buybox?.dataset.productId), variant_id:id, quantity});
            localStorage.setItem('parfumshop_cart', JSON.stringify(cart));
            window.dispatchEvent(new CustomEvent('parfumshop:cart-updated', {detail:cart}));
            updateCounts();
            notify(window.parfumshopMessages?.cartAdded || 'Məhsul səbətə əlavə edildi');
            const label = add.textContent;
            add.textContent = 'Səbətə əlavə edildi ✓';
            setTimeout(() => { add.textContent = label; }, 1600);
            return;
        }

        const writeReview = event.target.closest('[data-review-open]');
        if (writeReview) {
            document.querySelector('[data-review-modal]')?.showModal();
            return;
        }
        const closeReview = event.target.closest('[data-review-close]');
        if (closeReview) {
            document.querySelector('[data-review-modal]')?.close();
            return;
        }
        const accordion = event.target.closest('[data-accordion-toggle]');
        if (accordion) {
            const root = accordion.closest('[data-accordion]');
            const open = root.classList.toggle('open');
            accordion.setAttribute('aria-expanded', String(open));
            root.querySelector('[data-accordion-body]').hidden = !open;
            return;
        }

        const card = event.target.closest('.card[data-href]');
        if (card && !event.target.closest('a, button, input, select')) window.location.href = card.dataset.href;
    });

    document.addEventListener('click', function (e) {
        const toggle = e.target.closest('[data-filter-toggle]');
        if (toggle) {
            const body = document.getElementById(toggle.getAttribute('aria-controls'));
            const isOpen = toggle.getAttribute('aria-expanded') === 'true';
            toggle.setAttribute('aria-expanded', String(!isOpen));
            body.hidden = isOpen;
            return;
        }

        const expandBtn = e.target.closest('[data-filter-expand]');
        if (expandBtn) {
            const more = expandBtn.nextElementSibling;
            more.hidden = !more.hidden;
            expandBtn.textContent = more.hidden ? 'Bütün qoxu ailələri' : 'Daha az göstər';
        }
    });

    document.addEventListener('click', function (e) {
        const panelToggle = e.target.closest('[data-panel-toggle]');
        if (panelToggle) {
            const panel = panelToggle.closest('[data-collapsible-panel]');
            const isOpen = panel.classList.toggle('is-open');
            panelToggle.setAttribute('aria-expanded', String(isOpen));
        }
    });

    document.querySelectorAll('[data-price-slider]').forEach(function (slider) {
        const minInput = slider.querySelector('[data-price-min-range]');
        const maxInput = slider.querySelector('[data-price-max-range]');
        const rangeEl = slider.querySelector('[data-price-range]');
        const wrapper = slider.parentElement;
        const minLabel = wrapper.querySelector('[data-price-min-label]');
        const maxLabel = wrapper.querySelector('[data-price-max-label]');
        const minHidden = wrapper.querySelector('[data-price-min-hidden]');
        const maxHidden = wrapper.querySelector('[data-price-max-hidden]');

        const min = parseFloat(slider.dataset.min);
        const max = parseFloat(slider.dataset.max);
        const minGap = parseFloat(slider.dataset.step) || 1;

        function update() {
            let minVal = parseFloat(minInput.value);
            let maxVal = parseFloat(maxInput.value);

            if (maxVal - minVal < minGap) {
                if (this === minInput) {
                    minVal = maxVal - minGap;
                    minInput.value = minVal;
                } else {
                    maxVal = minVal + minGap;
                    maxInput.value = maxVal;
                }
            }

            const minPercent = ((minVal - min) / (max - min)) * 100;
            const maxPercent = ((maxVal - min) / (max - min)) * 100;

            rangeEl.style.left = minPercent + '%';
            rangeEl.style.right = (100 - maxPercent) + '%';

            minLabel.textContent = minVal + ' ₼';
            maxLabel.textContent = maxVal + ' ₼';
            minHidden.value = minVal;
            maxHidden.value = maxVal;
        }

        minInput.addEventListener('input', update);
        maxInput.addEventListener('input', update);
        update();
    });



    const languageSwitcher = document.getElementById('languageSwitcher');

    if (languageSwitcher) {
        languageSwitcher.addEventListener('change', async function () {
            const selectedLocale = this.value;
            const previousLocale = this.dataset.currentLocale;
            this.disabled = true;

            try {
                const response = await fetch(this.dataset.changeUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({ locale: selectedLocale })
                });

                if (!response.ok) throw new Error('Language change failed');

                window.location.reload();
            } catch (error) {
                this.value = previousLocale;
                this.disabled = false;
                console.error(error);
            }
        });
    }

    document.addEventListener('auxclick', event => {
        const card = event.target.closest('.card[data-href]');
        if (event.button === 1 && card && !event.target.closest('button, a')) window.open(card.dataset.href, '_blank', 'noopener');
    });
    window.addEventListener('storage', updateCounts);
    window.addEventListener('parfumshop:cart-updated', updateCounts);
    updateCounts();
    syncFavorites();
    if (document.querySelector('[data-review-errors]')) document.querySelector('[data-review-modal]')?.showModal();

    (function initSidebarExtrasPlacement() {
        const extras = document.getElementById('sidebarExtras');
        const mobileSlot = document.getElementById('sidebarExtrasMobileSlot');
        if (!extras || !mobileSlot) return;

        const desktopParent = extras.parentElement;
        const desktopNextSibling = extras.nextSibling;
        const mq = window.matchMedia('(max-width: 1024px)');

        function place(isMobile) {
            if (isMobile) {
                if (!mobileSlot.contains(extras)) mobileSlot.appendChild(extras);
            } else {
                if (!desktopParent.contains(extras)) desktopParent.insertBefore(extras, desktopNextSibling);
            }
        }

        place(mq.matches);
        mq.addEventListener('change', e => place(e.matches));
    })();
})();
