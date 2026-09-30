// Kuryer ekranı: formalar iki dəfə göndərilməsin (zəif internetdə təkrar basma)
document.querySelectorAll('form[data-once]').forEach(form => form.addEventListener('submit', () =>
    form.querySelectorAll('button').forEach(b => { b.disabled = true; })));
