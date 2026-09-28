// <head>-də, CSS-dən əvvəl, defer-siz yüklənir: səhifə açılanda
// tema yanlış rəngdə "yanıb-sönməsin" deyə data-theme dərhal qoyulur.
(function () {
    var saved = null;
    try { saved = localStorage.getItem('theme'); } catch (e) {}
    var theme = saved || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
    document.documentElement.setAttribute('data-theme', theme);
})();
