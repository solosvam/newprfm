// Admin panelinin tabları (PS_SITE/admin...) pəncərədəki rəngli "Admin" qrupuna düşür — sayt tablarından seçilsin.
// Tab artıq başqa qrupdadırsa (istifadəçi özü qoyub) toxunulmur. Ayarlarda söndürmək olar (psAdminGroup = false).
const ADMIN_GROUP = { title: 'Admin', color: 'red' };

async function adminGroupEnabled() {
    const { psAdminGroup } = await chrome.storage.local.get('psAdminGroup');
    return psAdminGroup !== false;
}

const isAdminUrl = (url) => !!url && url.startsWith(PS_SITE + '/admin');

async function groupAdminTab(tab) {
    if (!tab?.id || tab.groupId !== chrome.tabGroups.TAB_GROUP_ID_NONE || !isAdminUrl(tab.url) || tab.pinned) return;
    if (!(await adminGroupEnabled())) return;
    const [existing] = await chrome.tabGroups.query({ windowId: tab.windowId, title: ADMIN_GROUP.title });
    if (existing) {
        await chrome.tabs.group({ tabIds: tab.id, groupId: existing.id }).catch(() => {});
        return;
    }
    const groupId = await chrome.tabs.group({ tabIds: tab.id, createProperties: { windowId: tab.windowId } }).catch(() => null);
    if (groupId !== null) await chrome.tabGroups.update(groupId, ADMIN_GROUP).catch(() => {});
}

function groupAllAdminTabs() {
    chrome.tabs.query({ url: PS_SITE + '/admin*' }, (tabs) => tabs.forEach(groupAdminTab));
}

// Ünvan dəyişəndə (admin səhifəsi açıldı) — qrupla
chrome.tabs.onUpdated.addListener((tabId, info, tab) => {
    if (info.url || info.status === 'complete') groupAdminTab(tab);
});
chrome.runtime.onStartup.addListener(groupAllAdminTabs);
// Ayarda yenidən açılanda — artıq açıq admin tablarını da qrupla
chrome.storage.onChanged.addListener((changes, area) => {
    if (area === 'local' && changes.psAdminGroup?.newValue !== false && changes.psAdminGroup) groupAllAdminTabs();
});
