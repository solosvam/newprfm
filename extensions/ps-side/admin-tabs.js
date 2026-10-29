// Tab qrupları: ayarlarda yaradılan qaydalar (ad, rəng, ünvan) — ünvanı həmin başlanğıcla olan tab o qrupa düşür.
// Bir neçə qayda uyğundursa ən uzun ünvanlı seçilir (məs. /admin → "Admin", saytın qalanı → "Sayt").
// Tab başqa (qaydalarda olmayan) qrupdadırsa və ya sabitlənibsə toxunulmur. Ayarlarda hamısını söndürmək olar (psAdminGroup = false).
const TAB_GROUP_COLORS = ['grey', 'blue', 'red', 'yellow', 'green', 'pink', 'purple', 'cyan', 'orange'];
const DEFAULT_TAB_GROUPS = [
    { title: 'Admin', color: 'red', url: PS_SITE + '/admin' },
    { title: 'Sayt', color: 'blue', url: PS_SITE },
];

async function tabGroupRules() {
    const { psAdminGroup, psTabGroups } = await chrome.storage.local.get(['psAdminGroup', 'psTabGroups']);
    if (psAdminGroup === false) return [];
    return (Array.isArray(psTabGroups) ? psTabGroups : DEFAULT_TAB_GROUPS).filter((rule) => rule.title && rule.url);
}

function matchRule(rules, url) {
    if (!url) return null;
    return rules.filter((rule) => url.startsWith(rule.url)).sort((a, b) => b.url.length - a.url.length)[0] || null;
}

async function groupParfumshopTab(tab) {
    if (!tab?.id || tab.pinned) return;
    const rules = await tabGroupRules();
    const rule = matchRule(rules, tab.url);
    if (!rule) return;
    if (tab.groupId !== chrome.tabGroups.TAB_GROUP_ID_NONE) {
        // Bizim qaydalardan birinin qrupundadırsa və uyğun qrup başqadırsa — keçir; istifadəçinin öz qrupuna toxunmuruq
        const current = await chrome.tabGroups.get(tab.groupId).catch(() => null);
        if (!rules.some((r) => r.title === current?.title) || current.title === rule.title) return;
    }
    const color = TAB_GROUP_COLORS.includes(rule.color) ? rule.color : 'grey';
    const [existing] = await chrome.tabGroups.query({ windowId: tab.windowId, title: rule.title });
    if (existing) {
        await chrome.tabs.group({ tabIds: tab.id, groupId: existing.id }).catch(() => {});
        if (existing.color !== color) await chrome.tabGroups.update(existing.id, { color }).catch(() => {});
        return;
    }
    const groupId = await chrome.tabs.group({ tabIds: tab.id, createProperties: { windowId: tab.windowId } }).catch(() => null);
    if (groupId !== null) await chrome.tabGroups.update(groupId, { title: rule.title, color }).catch(() => {});
}

function groupAllParfumshopTabs() {
    chrome.tabs.query({}, (tabs) => tabs.forEach(groupParfumshopTab));
}

// Ünvan dəyişəndə — qrupla
chrome.tabs.onUpdated.addListener((tabId, info, tab) => {
    if (info.url || info.status === 'complete') groupParfumshopTab(tab);
});
chrome.runtime.onStartup.addListener(groupAllParfumshopTabs);
// Ayarlar dəyişəndə (qayda əlavə olundu / dəyişdi, yenidən açıldı) — açıq tabları da qrupla
chrome.storage.onChanged.addListener((changes, area) => {
    if (area === 'local' && (changes.psTabGroups || (changes.psAdminGroup && changes.psAdminGroup.newValue !== false))) groupAllParfumshopTabs();
});
