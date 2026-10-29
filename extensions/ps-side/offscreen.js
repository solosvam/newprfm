// Bildiriş səsi (notifications.js göndərir): ayarlarda növ üçün seçilən ton və ya qovluqdakı fayl, səs gücü (options.html)
chrome.runtime.onMessage.addListener((message) => {
    if (message?.type !== 'ps-notify-sound') return;
    chrome.storage.local.get(['psNotifySounds', 'psNotifyVolume'], ({ psNotifySounds = {}, psNotifyVolume = 0.3 }) => {
        const key = psNotifySounds[message.sound] || PS_SOUND_TYPES[message.sound]?.default || 'ding';
        psPlaySound(message.sound, key, psNotifyVolume);
    });
});
