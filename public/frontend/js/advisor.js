/**
 * AI ətir seçici — chat (/advisor).
 *  - İlk iki mesaj səhifədədir (#advisorStart). Müştəri cavab yazır və ya hazır cavaba basır →
 *    bütün söhbət POST /advisor-a gedir → AI növbəti sualı + hazır cavabları qaytarır.
 *  - Söhbət bitəndə (AI qərar verir; server ən çox MAX_ANSWERS cavabdan sonra bitirir) — seçilən ətirlərin kartları.
 *  - Söhbət localStorage-də saxlanır (7 gün), səhifə yenilənəndə davam edir; nəticə 24 saat (qiymətlər dəyişə bilər).
 */
(function () {
    'use strict';

    const chat = document.getElementById('advisorChat');
    if (!chat) return;

    const KEY = 'ps_advisor_chat_v1:' + chat.dataset.locale;
    const CHAT_TTL = 7 * 24 * 3600 * 1000;
    const RESULT_TTL = 24 * 3600 * 1000;

    const log = document.getElementById('advisorLog');
    const typing = document.getElementById('advisorTyping');
    const repliesBox = document.getElementById('advisorReplies');
    const form = document.getElementById('advisorForm');
    const input = document.getElementById('advisorInput');
    const restartBtn = document.getElementById('advisorRestart');
    const results = document.getElementById('advisorResults');
    const intro = document.getElementById('advisorIntro');
    const body = document.getElementById('advisorBody');
    const start = JSON.parse(document.getElementById('advisorStart').textContent);

    let state = load() || fresh();
    let busy = false;

    function fresh() {
        return { at: Date.now(), messages: start.map((m) => ({ role: m.role, text: m.text })), replies: start[start.length - 1].replies || [], done: false, result: null };
    }

    function load() {
        try {
            const saved = JSON.parse(localStorage.getItem(KEY) || 'null');
            if (!saved || !Array.isArray(saved.messages) || Date.now() - saved.at > CHAT_TTL) return null;
            if (saved.result && Date.now() - saved.result.at > RESULT_TTL) { saved.result = null; saved.done = false; }
            return saved;
        } catch { return null; }
    }

    function save() {
        state.at = Date.now();
        try { localStorage.setItem(KEY, JSON.stringify(state)); } catch { /* yaddaş bağlıdır — söhbət yalnız bu səhifədə qalır */ }
    }

    const answers = () => state.messages.filter((m) => m.role === 'user').length;

    function bubble(message) {
        const row = document.createElement('div');
        row.className = 'advisor-msg advisor-msg--' + message.role;
        if (message.role === 'assistant') {
            const img = document.createElement('img');
            img.src = chat.dataset.bot; img.alt = ''; img.width = 32; img.height = 32; img.className = 'advisor-msg__bot';
            row.appendChild(img);
        }
        const text = document.createElement('div');
        text.className = 'advisor-msg__text';
        text.textContent = message.text;
        row.appendChild(text);
        log.appendChild(row);
    }

    function render() {
        log.replaceChildren();
        state.messages.forEach(bubble);
        renderReplies();
        const finished = state.done;
        form.hidden = finished;
        restartBtn.hidden = answers() < 1;
        if (state.result) showResult(state.result, false);
        else results.hidden = true;
    }

    function renderReplies() {
        repliesBox.replaceChildren();
        if (state.done || busy) return;
        (state.replies || []).forEach((label) => {
            const b = document.createElement('button');
            b.type = 'button';
            b.className = 'advisor-chip advisor-chip--reply';
            b.textContent = label;
            b.addEventListener('click', () => send(label));
            repliesBox.appendChild(b);
        });
    }

    function setBusy(on) {
        busy = on;
        typing.hidden = !on;
        input.disabled = on;
        form.querySelector('button').disabled = on;
        renderReplies();
        if (on) typing.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    function showResult(result, scroll) {
        intro.textContent = result.intro || result.empty || '';
        body.innerHTML = result.html || '';
        results.hidden = false;
        document.dispatchEvent(new CustomEvent('parfum:cards-added'));
        if (scroll) results.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    async function ask() {
        setBusy(true);
        try {
            const response = await fetch(chat.dataset.url, {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': chat.dataset.token, 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
                body: JSON.stringify({ messages: state.messages.slice(-30) }),
            });
            const data = await response.json().catch(() => ({}));
            if (!response.ok) throw new Error(data.message || chat.dataset.error);

            if (data.reply) state.messages.push({ role: 'assistant', text: data.reply });
            state.replies = data.quick_replies || [];
            state.done = !!data.done;
            if (data.done) state.result = { at: Date.now(), intro: data.intro || '', html: data.html || '', empty: data.empty || '' };
            save();
            setBusy(false);
            render();
            if (data.done) showResult(state.result, true);
            else input.focus({ preventScroll: true });
        } catch (error) {
            setBusy(false);
            render();
            const say = window.ParfumNotify || ((m) => window.alert(m));
            say(error.message || chat.dataset.error, 'error');
        }
    }

    function send(text) {
        text = (text || '').trim().slice(0, 300);
        if (!text || busy || state.done) return;
        state.messages.push({ role: 'user', text });
        state.replies = [];
        input.value = '';
        save();
        render();
        ask();
    }

    form.addEventListener('submit', (e) => { e.preventDefault(); send(input.value); });
    restartBtn.addEventListener('click', () => {
        state = fresh();
        try { localStorage.removeItem(KEY); } catch { /* */ }
        render();
        chat.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });

    render();
    // Son cavab AI-yə çatmayıbsa (səhifə bağlanıb) — yenidən soruşulur
    const last = state.messages[state.messages.length - 1];
    if (!state.done && last && last.role === 'user') ask();
})();
