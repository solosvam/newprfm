/**
 * Səsli axtarış: brauzerin nitq tanıması (Web Speech API — Chrome, Edge, Safari, Android).
 * Dəstəklənməyən brauzerdə (Firefox) mikrofon düyməsi görünmür. Dil — en-US (brend adları üçün; nav.blade.php).
 * Danışarkən mətn axtarış sahəsinə yazılır — main.js-in AJAX təklifləri açılır; səhifə göndərilmir,
 * müştəri təklifi seçir və ya Enter basır. Davamlı rejim: sözlər arası fasilə tanımanı bitirmir —
 * 1.5 san susduqdan sonra (və ya ən çox 12 san) dayanır.
 */
(function () {
    'use strict';

    const button = document.getElementById('searchVoice');
    const input = document.getElementById('searchInput');
    const Recognition = window.SpeechRecognition || window.webkitSpeechRecognition;
    if (!button || !input || !Recognition) return;

    button.hidden = false;
    let recognition = null;
    let silenceTimer = null;
    let limitTimer = null;
    const SILENCE_MS = 1500;
    const MAX_MS = 12000;
    const placeholder = input.placeholder;
    const say = (message, type) => (window.ParfumNotify ? window.ParfumNotify(message, type) : null);

    function stop() {
        clearTimeout(silenceTimer);
        clearTimeout(limitTimer);
        button.classList.remove('is-listening');
        button.setAttribute('aria-pressed', 'false');
        input.placeholder = placeholder;
        recognition = null;
    }

    button.addEventListener('click', () => {
        if (recognition) { recognition.stop(); return; }

        recognition = new Recognition();
        recognition.lang = button.dataset.lang || 'en-US';
        recognition.interimResults = true;
        recognition.continuous = true;   // sözlər arası fasilədə dayanmasın
        recognition.maxAlternatives = 1;
        let finalText = '';
        const current = recognition;
        const restartSilence = () => {
            clearTimeout(silenceTimer);
            silenceTimer = setTimeout(() => current.stop(), SILENCE_MS);
        };

        recognition.onstart = () => {
            button.classList.add('is-listening');
            button.setAttribute('aria-pressed', 'true');
            input.value = '';
            input.placeholder = button.dataset.listening;
            // heç danışmasa da, uzun danışsa da — dayansın
            limitTimer = setTimeout(() => current.stop(), MAX_MS);
        };
        recognition.onresult = (event) => {
            let text = '';
            let finals = '';
            for (const result of event.results) {
                text += result[0].transcript;
                if (result.isFinal) finals += result[0].transcript;
            }
            finalText = finals;
            restartSilence();
            input.value = text.trim();
            // təkliflər (main.js) — yazan kimi
            input.dispatchEvent(new Event('input', { bubbles: true }));
        };
        recognition.onerror = (event) => {
            if (event.error === 'not-allowed' || event.error === 'service-not-allowed') say(button.dataset.denied, 'error');
            else if (event.error === 'no-speech') say(button.dataset.noSpeech, 'info');
        };
        recognition.onend = () => {
            stop();
            const query = (finalText || input.value).trim();
            if (!query) return;
            // göndərmirik — təkliflər (AJAX) açıq qalır, müştəri seçir və ya Enter basır
            input.value = query;
            input.focus();
            input.dispatchEvent(new Event('input', { bubbles: true }));
        };

        try { recognition.start(); } catch (e) { stop(); }
    });
})();
