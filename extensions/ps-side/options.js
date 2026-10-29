// Ayarlar: səs açıq/söndürülü, səs gücü, hər bildiriş növü üçün ton (chrome.storage.local — notifications.js / offscreen.js oxuyur)
const $ = (id) => document.getElementById(id);
const saved = $('saved');
const flash = () => { saved.hidden = false; clearTimeout(flash.t); flash.t = setTimeout(() => { saved.hidden = true; }, 1200); };

chrome.storage.local.get(['psNotifyMuted', 'psNotifyVolume', 'psNotifySounds'], ({ psNotifyMuted = false, psNotifyVolume = 0.3, psNotifySounds = {} }) => {
    $('enabled').checked = !psNotifyMuted;
    $('volume').value = psNotifyVolume;

    Object.entries(PS_SOUND_TYPES).forEach(([type, info]) => {
        const row = document.createElement('div');
        row.className = 'row';
        const label = document.createElement('label');
        label.textContent = info.label;
        label.htmlFor = 'sound-' + type;
        const select = document.createElement('select');
        select.id = 'sound-' + type;
        Object.entries(PS_SOUNDS).forEach(([key, sound]) => select.append(new Option(sound.label, key)));
        select.value = psNotifySounds[type] || info.default;
        const play = document.createElement('button');
        play.type = 'button';
        play.textContent = 'Dinlə';
        const preview = () => psPlayNotes(PS_SOUNDS[select.value].notes, Number($('volume').value));
        play.addEventListener('click', preview);
        select.addEventListener('change', () => {
            chrome.storage.local.get('psNotifySounds', ({ psNotifySounds: current = {} }) => {
                chrome.storage.local.set({ psNotifySounds: { ...current, [type]: select.value } }, flash);
            });
            preview();
        });
        row.append(label, select, play);
        $('types').append(row);
    });
});

$('enabled').addEventListener('change', () => chrome.storage.local.set({ psNotifyMuted: !$('enabled').checked }, flash));
$('volume').addEventListener('change', () => chrome.storage.local.set({ psNotifyVolume: Number($('volume').value) }, flash));
