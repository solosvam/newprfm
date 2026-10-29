// Bildiriş səsi (notifications.js göndərir). Offscreen səhifədə yalnız chrome.runtime var (chrome.storage yoxdur) —
// seçilən səs (key) və güc (volume) ayarlardan fon prosesində oxunub mesajla gəlir.
chrome.runtime.onMessage.addListener((message) => {
    if (message?.type !== 'ps-notify-sound') return;
    psPlaySound(message.sound, message.key, message.volume);
});
