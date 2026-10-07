(() => {
    'use strict';
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    const loggedIn = document.body.dataset.auth === '1';
    const favoritesKey = 'parfumshop_favorites';

    // Serverdən gələn məlumat: layouts/app.blade.php → <script type="application/json" id="app-data">
    let appData = {};
    try { appData = JSON.parse(document.getElementById('app-data')?.textContent || '{}'); } catch (e) {}

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

                const price = [product.size, product.price ? product.price + '\u00A0₼' : null]
                    .filter(Boolean)
                    .join(' / ');

                const priceBox = text('span', 'search-result-price', price);
                // məhsul endirimi: faiz nişanı + köhnə qiymət üstündən xətt, yeni qiymət qırmızı
                if (product.discount && Number(product.regular_price) > Number(product.price)) {
                    priceBox.textContent = '';
                    if (product.size) priceBox.append(product.size + ' / ');
                    priceBox.append(text('s', 'price-old', product.regular_price), ' ', text('span', 'price-sale', product.price + '\u00A0₼'));
                    info.prepend(text('span', 'search-result-discount', '−' + product.discount + '%'));
                }

                link.append(thumb, info, priceBox);
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
    const flash = appData.flash || {};
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
        const cart = window.parfumshopCart.get();
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

    const favoriteSelector = '.fav-btn, .favorite-toggle';
    const activeFavoriteIcon = '/frontend/images/product-card-wishlist-active.svg';

    function paintFavorites(ids) {
        const selectedIds = ids.map(Number);

        document.querySelectorAll(favoriteSelector).forEach(button => {
            const active = selectedIds.includes(Number(button.dataset.productId));
            button.classList.toggle('active', active);
            button.classList.toggle('is-favorite', active);
            button.setAttribute('aria-pressed', String(active));

            const icon = button.querySelector('img');
            if (!icon) return;

            const outlineIcon = icon.dataset.outlineIcon || icon.getAttribute('src');
            icon.dataset.outlineIcon = outlineIcon;
            icon.src = active ? activeFavoriteIcon : outlineIcon;
        });
    }

    window.paintFavorites = paintFavorites;

    // Qonaq: "Tövsiyə olunanlar" — brauzerdə bəyəndiklərinə tərkibcə oxşar ətirlər (server: /recommendations)
    async function loadGuestRecommendations(ids) {
        const list = document.querySelector('[data-guest-recommendations]');
        if (!list || !ids.length) return;
        try {
            const response = await fetch('/recommendations?ids=' + ids.slice(-50).join(','), {headers: {'Accept': 'text/html'}});
            if (response.status !== 200) return;
            const html = (await response.text()).trim();
            if (html) list.innerHTML = html;
        } catch (error) {
            console.warn('Tövsiyələr yüklənmədi', error);
        }
    }

    // Məhsul kartı: hover-də ikinci şəkil. Yalnız siçanlı cihazda, ilk hover-də yüklənir;
    // şəkil gələnə qədər əsas şəkil qalır, sonra CSS yumşaq keçid edir (.has-alt).
    if (window.matchMedia('(hover: hover)').matches) {
        document.addEventListener('pointerover', event => {
            if (event.pointerType && event.pointerType !== 'mouse') return;
            const thumb = event.target.closest?.('.thumb[data-hover-src]');
            if (!thumb || thumb.dataset.hoverLoaded) return;
            thumb.dataset.hoverLoaded = '1';
            const img = new Image();
            img.className = 'thumb-alt';
            img.alt = '';
            img.decoding = 'async';
            img.onload = () => { thumb.appendChild(img); thumb.classList.add('has-alt'); };
            img.src = thumb.dataset.hoverSrc;
        });
    }

    async function syncFavorites() {
        if (!loggedIn) {
            const ids = readArray(favoritesKey).map(Number);
            paintFavorites(ids);
            updateCounts();
            loadGuestRecommendations(ids.filter(id => Number.isInteger(id) && id > 0));
            return;
        }

        try {
            const response = await fetch('/favorites/ids', {headers: {'Accept':'application/json'}});
            if (!response.ok) return;

            const ids = (await response.json()).ids.map(Number);
            localStorage.setItem(favoritesKey, JSON.stringify(ids));
            paintFavorites(ids);
            updateCounts();
        } catch (error) {
            console.warn('Seçilmişlər yüklənmədi', error);
        }
    }

    const buybox = document.querySelector('[data-buybox]');
    const installment = document.querySelector('[data-installment]');

    function updateInstallments() {
        if (!buybox || !installment) return;
        const variant = buybox.querySelector('.size-pill.active-size-amount');
        const price = Number(variant?.dataset.price || buybox.dataset.basePrice || 0);
        // endirim: köhnə qiymət üstündən xətt (data-regular-price), cari — endirimli
        const current = buybox.querySelector('[data-price-current]');
        if (current) current.textContent = price.toFixed(2) + ' ₼';
        const old = buybox.querySelector('[data-price-old]');
        const regular = Number(variant?.dataset.regularPrice || 0);
        if (old) {
            old.hidden = !(regular > price);
            old.textContent = regular.toFixed(2) + ' ₼';
        }

        // hissə-hissə ödənişə məhsul endirimi tətbiq olunmur — adi qiymətlə
        const creditPrice = Number(variant?.dataset.regularPrice || 0) || price;
        installment.querySelectorAll('tr[data-month]').forEach(row => {
            const months = Number(row.dataset.month);
            const rate = Number(row.dataset.rate || 0);
            const total = creditPrice * (1 + rate / 100);
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
            // müddət yalnız məbləğ onun minimumundan yuxarı olanda (CreditPeriod::availableFor)
            const min = appData.creditRule?.mins?.[months];
            row.hidden = min != null && creditPrice <= min;
        });
        const checked = installment.querySelector('tr[data-month]:not([hidden]) input[name="installment"]:checked');
        if (!checked) {
            const first = installment.querySelector('tr[data-month]:not([hidden]) input[name="installment"]');
            if (first) { first.checked = true; first.dispatchEvent(new Event('change', { bubbles: true })); }
        }

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
        const fav = event.target.closest(favoriteSelector);
        if (fav) {
            event.preventDefault();

            const id = Number(fav.dataset.productId);
            const ids = readArray(favoritesKey).map(Number);
            const active = ids.includes(id);
            const updatedIds = active ? ids.filter(value => value !== id) : [...ids, id];

            localStorage.setItem(favoritesKey, JSON.stringify(updatedIds));
            paintFavorites(updatedIds);
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
                    paintFavorites(ids);
                    updateCounts();
                    notify('Əməliyyat yerinə yetirilmədi', 'error');
                    return;
                }
            }

            if (active) {
                fav.closest('.wishlist-card')?.remove();
            }

            notify(active ? (appData.messages?.favoriteRemoved || 'Seçilmişlərdən silindi') : (appData.messages?.favoriteAdded || 'Seçilmişlərə əlavə edildi'));
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
            syncPriceAlert();
            return;
        }

        const alertButton = event.target.closest('[data-price-alert]');
        if (alertButton) {
            togglePriceAlert(alertButton);
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
            const id = Number(selected.dataset.variantId);
            const quantity = Number(document.querySelector('[data-qty-value]')?.textContent || 1);
            if (add.disabled) return;
            add.disabled = true;
            try {
                await window.parfumshopCart.change(id, 'add', quantity, Number(add.dataset.productId || buybox?.dataset.productId));
                updateCounts();
                notify(appData.messages?.cartAdded || 'Məhsul səbətə əlavə edildi');
                const label = add.textContent;
                add.textContent = 'Səbətə əlavə edildi ✓';
                setTimeout(() => { add.textContent = label; }, 1600);
            } catch (error) {
                notify(error.message, 'error');
            } finally {
                add.disabled = false;
            }
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

            if (max > min && maxVal - minVal < minGap) {
                if (this === minInput) {
                    minVal = maxVal - minGap;
                    minInput.value = minVal;
                } else {
                    maxVal = minVal + minGap;
                    maxInput.value = maxVal;
                }
            }

            const minPercent = max > min ? ((minVal - min) / (max - min)) * 100 : 0;
            const maxPercent = max > min ? ((maxVal - min) / (max - min)) * 100 : 100;

            rangeEl.style.left = minPercent + '%';
            rangeEl.style.right = (100 - maxPercent) + '%';

            minLabel.textContent = minVal.toFixed(2) + ' ₼';
            maxLabel.textContent = maxVal.toFixed(2) + ' ₼';
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

                // ünvanda ?lang= varsa (Google-dan gələn dil versiyası), o köhnə dili qaytarmasın
                const url = new URL(window.location.href);
                if (url.searchParams.has('lang')) {
                    url.searchParams.delete('lang');
                    window.location.replace(url.toString());
                } else {
                    window.location.reload();
                }
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

    // Header-in real hündürlüyü → --site-header-h (toastlar header-in altında çıxsın)
    (function trackHeaderHeight() {
        const header = document.querySelector('body > header');
        if (!header) return;
        const set = () => document.documentElement.style.setProperty('--site-header-h', header.offsetHeight + 'px');
        set();
        if ('ResizeObserver' in window) new ResizeObserver(set).observe(header);
    })();

    // Tema düyməsi (header)
    document.getElementById('theme-toggle')?.addEventListener('click', () => {
        const html = document.documentElement;
        const next = html.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
        html.setAttribute('data-theme', next);
        try { localStorage.setItem('theme', next); } catch (e) {}
    });

    // Select2 — placeholder data-placeholder atributundan, yoxdursa "Brend seç"
    if (window.jQuery?.fn?.select2) {
        window.jQuery(() => {
            window.jQuery('.select2').each(function () {
                window.jQuery(this).select2({
                    placeholder: this.dataset.placeholder || appData.messages?.selectBrand || '',
                    width: '100%'
                });
            });
        });
    }

    // Blade-dəki inline onchange/onclick/onsubmit əvəzinə data-atributlar.
    // jQuery ilə dinləyirik ki, select2-nin göndərdiyi change hadisəsi də tutulsun.
    if (window.jQuery) {
        const $doc = window.jQuery(document);
        // <select data-navigate-on-change>: seçilən dəyər URL-dir
        $doc.on('change', 'select[data-navigate-on-change]', function () {
            if (this.value) window.location.href = this.value;
        });
        // <select data-submit-on-change>: formu dərhal göndər
        $doc.on('change', 'select[data-submit-on-change]', function () {
            this.form?.submit();
        });
    }
    // <a data-history-back>: əvvəlki səhifəyə qayıt
    document.addEventListener('click', event => {
        const link = event.target.closest('[data-history-back]');
        if (!link) return;
        event.preventDefault();
        window.history.back();
    });
    // <form data-confirm="Mətn">: göndərməzdən əvvəl təsdiq soruş
    document.addEventListener('submit', event => {
        const form = event.target.closest('form[data-confirm]');
        if (form && !window.confirm(form.dataset.confirm)) event.preventDefault();
    });

    // Ünvan forması: "Ətraflı" — bina, blok, mərtəbə, mənzil sahələrini aç/bağla
    document.addEventListener('click', event => {
        const toggle = event.target.closest('[data-address-details-toggle]');
        if (!toggle) return;
        const details = document.getElementById(toggle.getAttribute('aria-controls'));
        if (!details) return;
        details.hidden = !details.hidden;
        toggle.setAttribute('aria-expanded', String(!details.hidden));
        if (!details.hidden) details.querySelector('input')?.focus();
    });

    // Üfüqi sürüşən pill menyular (kateqoriyalar, məlumat səhifələri, şəxsi kabinet):
    // sürüşdürülə bilirsə kənarları solğunlaşdır, aktiv bəndi görünən yerə gətir, sağda hərəkət edən ox
    function initScrollPills(nav, hint) {
        if (!nav) return;
        const update = () => {
            const max = nav.scrollWidth - nav.clientWidth;
            nav.classList.toggle('has-more-start', nav.scrollLeft > 4);
            nav.classList.toggle('has-more-end', max - nav.scrollLeft > 4);
        };
        const active = nav.querySelector('a.active, a.is-active, [aria-current="page"]');
        if (active) {
            // offsetLeft yox: link li içində ola bilər — ekran koordinatları ilə hesablanır
            const offset = active.getBoundingClientRect().left - nav.getBoundingClientRect().left + nav.scrollLeft;
            if (offset + active.offsetWidth > nav.clientWidth) {
                nav.style.scrollBehavior = 'auto';
                nav.scrollLeft = offset - (nav.clientWidth - active.offsetWidth) / 2;
                nav.style.scrollBehavior = '';
            }
        }
        update();
        nav.addEventListener('scroll', update, { passive: true });
        window.addEventListener('resize', update);

        // Sağda hərəkət edən ox + səhifə açılanda menyunun özü bir az sürüşüb qayıdır
        if (!hint) return;
        const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        let interacted = false;
        let nudged = false;

        const hideHint = () => {
            interacted = true;
            hint.classList.add('is-hidden');
            nav.classList.remove('is-peeking');
        };
        ['touchstart', 'mousedown', 'wheel'].forEach(type => nav.addEventListener(type, hideHint, { passive: true }));

        // "Peek": kateqoriyalar bir az sola gedib qayıdır. scrollTo əvəzinə CSS transform —
        // iOS Safari proqramla edilən smooth scroll-u səhifə yüklənərkən bəzən icra etmir.
        const nudge = () => {
            if (nudged || reduced || interacted || nav.scrollLeft > 0) return;
            nudged = true;
            setTimeout(() => {
                if (interacted) return;
                nav.classList.add('is-peeking');
                nav.addEventListener('animationend', () => nav.classList.remove('is-peeking'), { once: true });
            }, 700);
        };

        // Menyu sürüşdürülə bilirmi? Ekran ölçüsü dəyişəndə də yenidən yoxlanır
        // (məs. telefon çevriləndə və ya brauzerin eni kiçildiləndə)
        const syncHint = () => {
            const scrollable = nav.scrollWidth > nav.clientWidth + 4;
            hint.hidden = !scrollable;
            if (!scrollable) return;
            hint.classList.toggle('is-hidden', interacted || !nav.classList.contains('has-more-end'));
            nudge();
        };
        nav.addEventListener('scroll', () => {
            if (!interacted) hint.classList.toggle('is-hidden', !nav.classList.contains('has-more-end'));
        }, { passive: true });
        let resizeTimer;
        const onResize = () => {
            update();
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(syncHint, 150);
        };
        // Menyunun öz eni dəyişəndə (pəncərə, telefon çevrilməsi, devtools) — ResizeObserver daha etibarlıdır
        if ('ResizeObserver' in window) new ResizeObserver(onResize).observe(nav);
        else window.addEventListener('resize', onResize);

        // Oxa basanda bir az sağa sürüşdür
        hint.addEventListener('click', () => {
            nav.scrollBy({ left: nav.clientWidth * 0.6, behavior: 'smooth' });
            hideHint();
        });

        syncHint();
    }

    initScrollPills(document.querySelector('nav.cats'), document.querySelector('[data-cats-hint]'));
    // data-pill-scroll — sürüşən konteyner; ox onunla eyni valideyndə (data-pill-hint)
    document.querySelectorAll('[data-pill-scroll]').forEach(nav => {
        initScrollPills(nav, nav.parentElement.querySelector('[data-pill-hint]'));
    });

    // Brend zolağı: kənar solğunlaşması, desktopda oxlar və siçanla sürükləmə
    // (Windows-da touchpad olmadan üfüqi sürüşdürmək çətindir)
    document.querySelectorAll('[data-brands-strip]').forEach(strip => {
        const track = strip.querySelector('.brands');
        const prev = strip.querySelector('[data-brands-nav="-1"]');
        const next = strip.querySelector('[data-brands-nav="1"]');
        if (!track) return;

        const update = () => {
            const max = track.scrollWidth - track.clientWidth;
            const atStart = track.scrollLeft <= 4;
            const atEnd = max - track.scrollLeft <= 4;
            track.classList.toggle('has-more-start', !atStart);
            track.classList.toggle('has-more-end', !atEnd);
            if (prev) prev.hidden = atStart;
            if (next) next.hidden = atEnd;
        };
        update();
        track.addEventListener('scroll', update, { passive: true });
        if ('ResizeObserver' in window) new ResizeObserver(update).observe(track);
        track.querySelectorAll('img').forEach(img => img.complete || img.addEventListener('load', update, { once: true }));

        [prev, next].forEach(btn => btn?.addEventListener('click', () => {
            track.scrollBy({ left: Number(btn.dataset.brandsNav) * track.clientWidth * 0.8, behavior: 'smooth' });
        }));

        // Siçanla sürükləmə (yalnız siçan; toxunuşda brauzerin öz sürüşməsi işləyir)
        let startX = 0, startLeft = 0, dragging = false, moved = false;
        track.addEventListener('pointerdown', event => {
            if (event.pointerType !== 'mouse' || event.button !== 0) return;
            dragging = true; moved = false;
            startX = event.clientX; startLeft = track.scrollLeft;
        });
        window.addEventListener('pointermove', event => {
            if (!dragging) return;
            const dx = event.clientX - startX;
            if (!moved && Math.abs(dx) > 5) { moved = true; track.classList.add('is-dragging'); }
            if (moved) track.scrollLeft = startLeft - dx;
        });
        window.addEventListener('pointerup', () => {
            if (!dragging) return;
            dragging = false;
            // Sürükləmədən sonra linkə klik getməsin
            if (moved) setTimeout(() => track.classList.remove('is-dragging'), 0);
        });
        track.addEventListener('click', event => { if (moved) { event.preventDefault(); moved = false; } }, true);
    });

    // Kabinet menyusu (mobil): aktiv bəndi görünən yerə sürüşdür
    (function centerActiveAccountNav() {
        const nav = document.querySelector('.account-nav');
        const active = nav?.querySelector('a.active');
        if (nav && active && nav.scrollWidth > nav.clientWidth) {
            nav.scrollLeft = active.offsetLeft - (nav.clientWidth - active.offsetWidth) / 2;
        }
    })();

    // "Qiymət enəndə xəbər ver": seçilmiş ölçüyə abunəlik; qonaq — xəbərdarlıq (girişə yönləndirilmir)
    const priceAlert = document.querySelector('[data-price-alert]');
    const alertSubscribed = new Set((() => { try { return JSON.parse(priceAlert?.dataset.subscribed || '[]'); } catch (e) { return []; } })());
    const selectedVariantId = () => Number(document.querySelector('.size-pill.active-size-amount')?.dataset.variantId || 0);

    function syncPriceAlert() {
        if (!priceAlert) return;
        const on = alertSubscribed.has(selectedVariantId());
        priceAlert.classList.toggle('is-on', on);
        priceAlert.setAttribute('aria-pressed', String(on));
        priceAlert.querySelector('[data-price-alert-label]').textContent = on ? priceAlert.dataset.labelOn : priceAlert.dataset.labelOff;
    }

    async function togglePriceAlert(button) {
        if (button.dataset.auth !== '1') {
            notify(button.dataset.loginMessage, 'warning');
            return;
        }
        const variantId = selectedVariantId();
        if (!variantId || button.disabled) return;
        button.disabled = true;
        try {
            const response = await fetch(button.dataset.url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
                body: JSON.stringify({ variant_id: variantId }),
            });
            const data = await response.json().catch(() => ({}));
            if (!response.ok) throw new Error(data.message || 'Error');
            data.subscribed ? alertSubscribed.add(variantId) : alertSubscribed.delete(variantId);
            syncPriceAlert();
            notify(data.message, data.subscribed ? 'success' : 'info');
            // bildiriş push ilə gedir — icazə hələ verilməyibsə indi istənir
            if (data.subscribed) window.psPush?.prompt();
        } catch (e) {
            notify(e.message, 'error');
        } finally {
            button.disabled = false;
        }
    }
    syncPriceAlert();

    // Kartlarda geri sayım qutuları (gün / saat / dəq / san); bitəndə qutular gizlənir
    (() => {
        const boxes = [...document.querySelectorAll('[data-card-countdown]')];
        if (!boxes.length) return;
        const pad = (n) => String(n).padStart(2, '0');
        const tick = () => boxes.forEach((box) => {
            let s = Math.max(0, Math.floor((Number(box.dataset.cardCountdown) - Date.now()) / 1000));
            if (s === 0) { box.hidden = true; return; }
            const parts = { d: Math.floor(s / 86400), h: Math.floor(s % 86400 / 3600), m: Math.floor(s % 3600 / 60), s: s % 60 };
            box.querySelectorAll('[data-unit]').forEach((cell) => { cell.textContent = pad(parts[cell.dataset.unit]); });
        });
        tick();
        setInterval(tick, 1000);
    })();

    // Endirimin bitməsinə geri sayım ("2 gün 05:14:33"); bitəndə səhifə yenilənir — adi qiymət görünsün
    (() => {
        const timers = [...document.querySelectorAll('[data-countdown]')];
        if (!timers.length) return;
        const pad = (n) => String(n).padStart(2, '0');
        let reloaded = false;
        const tick = () => timers.forEach((el) => {
            let s = Math.max(0, Math.floor((Number(el.dataset.countdown) - Date.now()) / 1000));
            if (s === 0) {
                if (!reloaded) { reloaded = true; setTimeout(() => location.reload(), 1500); }
                return;
            }
            const d = Math.floor(s / 86400); s %= 86400;
            el.textContent = (d ? d + ' ' + (el.dataset.daysLabel || 'd') + ' ' : '') + pad(Math.floor(s / 3600)) + ':' + pad(Math.floor(s % 3600 / 60)) + ':' + pad(s % 60);
        });
        tick();
        setInterval(tick, 1000);
    })();

    // Şifrə inputları: göz düyməsi ilə şifrəni göstər/gizlət (saytdakı bütün type="password" sahələri)
    (() => {
        const labels = { show: appData.messages?.passwordShow || 'Show password', hide: appData.messages?.passwordHide || 'Hide password' };
        const eye = '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' +
            '<g class="pw-toggle__open"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></g>' +
            '<g class="pw-toggle__closed"><path d="M10.7 5.1A10.4 10.4 0 0 1 12 5c6.5 0 10 7 10 7a17.6 17.6 0 0 1-2.2 3.2"/><path d="M6.6 6.6C3.9 8.4 2 12 2 12s3.5 7 10 7a9.7 9.7 0 0 0 5.4-1.6"/><path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"/><path d="M2 2l20 20"/></g></svg>';

        document.querySelectorAll('input[type="password"]').forEach(input => {
            if (input.closest('.pw-field')) return;
            const wrap = document.createElement('span');
            wrap.className = 'pw-field';
            input.parentNode.insertBefore(wrap, input);
            wrap.appendChild(input);

            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'pw-toggle';
            button.innerHTML = eye;
            button.setAttribute('aria-label', labels.show);
            if (input.id) button.setAttribute('aria-controls', input.id);
            wrap.appendChild(button);

            button.addEventListener('click', () => {
                const visible = input.type === 'password';
                input.type = visible ? 'text' : 'password';
                button.classList.toggle('is-visible', visible);
                button.setAttribute('aria-label', visible ? labels.hide : labels.show);
                input.focus({ preventScroll: true });
            });
        });
    })();

    // Filtr: ölçü siyahısında axtarış
    document.addEventListener('input', event => {
        if (!event.target.matches('[data-filter-size-search]')) return;
        const query = event.target.value.trim().toLocaleLowerCase();
        const list = event.target.closest('.filter-section')?.querySelector('[data-filter-size-list]');
        list?.querySelectorAll('[data-filter-size-option]').forEach(option => {
            option.hidden = !option.textContent.trim().toLocaleLowerCase().includes(query);
        });
    });
})();
