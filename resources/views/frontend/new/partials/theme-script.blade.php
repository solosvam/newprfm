<script defer src="{{ asset('frontend/new/js/main.js') }}"></script>
<script>
    document.getElementById('theme-toggle').addEventListener('click', function () {
        var html = document.documentElement;
        var next = html.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
        html.setAttribute('data-theme', next);
        localStorage.setItem('theme', next);
    });
</script>
