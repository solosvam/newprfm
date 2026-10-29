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
        select.append(new Option(`Qovluqdan fayl (sounds/${type}.mp3)`, PS_SOUND_FILE));
        Object.entries(PS_SOUNDS).forEach(([key, sound]) => select.append(new Option(sound.label, key)));
        select.value = psNotifySounds[type] || info.default;
        const play = document.createElement('button');
        play.type = 'button';
        play.textContent = 'Dinlə';
        const note = document.createElement('div');
        note.className = 'note';
        // Qovluqdan fayl seçilibsə — fayl yerindədirmi
        const check = async () => {
            note.textContent = select.value === PS_SOUND_FILE && !(await psSoundFileExists(type))
                ? `sounds/${type}.mp3 tapılmadı — hazır ton çalınacaq. Faylı extension qovluğundakı sounds/ qovluğuna qoyun.` : '';
        };
        const preview = () => psPlaySound(type, select.value, Number($('volume').value));
        play.addEventListener('click', preview);
        select.addEventListener('change', () => {
            chrome.storage.local.get('psNotifySounds', ({ psNotifySounds: current = {} }) => {
                chrome.storage.local.set({ psNotifySounds: { ...current, [type]: select.value } }, flash);
            });
            check();
            preview();
        });
        row.append(label, select, play, note);
        $('types').append(row);
        check();
    });
});

$('enabled').addEventListener('change', () => chrome.storage.local.set({ psNotifyMuted: !$('enabled').checked }, flash));
$('volume').addEventListener('change', () => chrome.storage.local.set({ psNotifyVolume: Number($('volume').value) }, flash));

// Admin tablarını "Admin" qrupuna yığmaq (admin-tabs.js) — susmaya görə açıq
chrome.storage.local.get('psAdminGroup', ({ psAdminGroup }) => {
    $('adminGroup').checked = psAdminGroup !== false;
    $('adminGroup').addEventListener('change', () => chrome.storage.local.set({ psAdminGroup: $('adminGroup').checked }, flash));
});
