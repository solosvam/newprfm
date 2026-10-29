// Bildiriş səsləri: hazır tonlar (notların tezliyi, Hz) və növ üzrə susmaya görə seçim.
// options.html (seçim + dinləmə) və offscreen.html (çalma) istifadə edir.
const PS_SOUNDS = {
    ding: { label: 'Bir ton', notes: [660] },
    double: { label: 'İki qısa yüksək', notes: [880, 1175] },
    rising: { label: 'Pilləli yuxarı', notes: [523, 659, 784] },
    falling: { label: 'Pilləli aşağı', notes: [784, 659, 523] },
    bell: { label: 'Zəng', notes: [1047, 1319, 1047] },
    alarm: { label: 'Həyəcan (4 dəfə)', notes: [880, 660, 880, 660] },
    soft: { label: 'Yumşaq aşağı', notes: [392, 330] },
};
const PS_SOUND_TYPES = {
    one_click: { label: 'Yeni asan sifariş', default: 'double' },
    credit: { label: 'Hissə-hissə müraciəti', default: 'rising' },
    site_order: { label: 'Sayt sifarişi', default: 'ding' },
};

let psAudio = null;
function psPlayNotes(notes, volume = 0.3) {
    psAudio ??= new AudioContext();
    if (psAudio.state === 'suspended') psAudio.resume();
    notes.forEach((frequency, index) => {
        const start = psAudio.currentTime + index * 0.18;
        const osc = psAudio.createOscillator();
        const gain = psAudio.createGain();
        osc.type = 'sine';
        osc.frequency.value = frequency;
        gain.gain.setValueAtTime(0.0001, start);
        gain.gain.exponentialRampToValueAtTime(Math.max(0.0002, volume), start + 0.02);
        gain.gain.exponentialRampToValueAtTime(0.0001, start + 0.16);
        osc.connect(gain).connect(psAudio.destination);
        osc.start(start);
        osc.stop(start + 0.17);
    });
}
