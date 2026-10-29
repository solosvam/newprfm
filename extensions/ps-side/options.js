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

// Tab qrupları (admin-tabs.js): yığmaq açıq/söndürülü + qaydalar (ad, rəng, ünvan)
const GROUP_COLORS = { grey: 'Boz', blue: 'Mavi', red: 'Qırmızı', yellow: 'Sarı', green: 'Yaşıl', pink: 'Çəhrayı', purple: 'Bənövşəyi', cyan: 'Firuzəyi', orange: 'Narıncı' };
const DEFAULT_GROUPS = [
    { title: 'Admin', color: 'red', url: PS_SITE + '/admin' },
    { title: 'Sayt', color: 'blue', url: PS_SITE },
];

function groupRow(rule = { title: '', color: 'grey', url: 'https://' }) {
    const row = document.createElement('div');
    row.className = 'group';
    const title = Object.assign(document.createElement('input'), { type: 'text', value: rule.title, placeholder: 'Ad' });
    title.dataset.field = 'title';
    const color = document.createElement('select');
    color.dataset.field = 'color';
    Object.entries(GROUP_COLORS).forEach(([value, label]) => color.append(new Option(label, value)));
    color.value = rule.color in GROUP_COLORS ? rule.color : 'grey';
    const url = Object.assign(document.createElement('input'), { type: 'text', value: rule.url, placeholder: 'https://… (ünvanın başlanğıcı)' });
    url.dataset.field = 'url';
    const remove = Object.assign(document.createElement('button'), { type: 'button', textContent: 'Sil', className: 'danger' });
    remove.addEventListener('click', () => row.remove());
    row.append(title, color, url, remove);
    return row;
}

chrome.storage.local.get(['psAdminGroup', 'psTabGroups'], ({ psAdminGroup, psTabGroups }) => {
    $('adminGroup').checked = psAdminGroup !== false;
    $('adminGroup').addEventListener('change', () => chrome.storage.local.set({ psAdminGroup: $('adminGroup').checked }, flash));
    const head = document.createElement('div');
    head.className = 'group group-head';
    ['Ad', 'Rəng', 'Ünvan', ''].forEach((text) => head.append(Object.assign(document.createElement('span'), { textContent: text })));
    $('groups').append(head);
    (Array.isArray(psTabGroups) ? psTabGroups : DEFAULT_GROUPS).forEach((rule) => $('groups').append(groupRow(rule)));
});

$('addGroup').addEventListener('click', () => $('groups').append(groupRow()));
$('saveGroups').addEventListener('click', () => {
    const rules = [...document.querySelectorAll('#groups .group:not(.group-head)')].map((row) => {
        const value = (field) => row.querySelector(`[data-field="${field}"]`).value.trim();
        return { title: value('title'), color: value('color'), url: value('url') };
    }).filter((rule) => rule.title && /^https?:\/\/./.test(rule.url));
    chrome.storage.local.set({ psTabGroups: rules }, flash);
});
