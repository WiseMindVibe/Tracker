(function () {
    var KEY = 'tracker-theme';

    function currentTheme() {
        var t = document.documentElement.getAttribute('data-theme');
        return t === 'light' || t === 'dark' ? t : 'dark';
    }

    function applyAria(btn) {
        var t = currentTheme();
        btn.setAttribute('aria-pressed', t === 'light' ? 'true' : 'false');
        btn.setAttribute(
            'aria-label',
            t === 'light' ? 'Switch to dark theme' : 'Switch to light theme'
        );
    }

    function toggle() {
        var next = currentTheme() === 'light' ? 'dark' : 'light';
        document.documentElement.setAttribute('data-theme', next);
        try {
            localStorage.setItem(KEY, next);
        } catch (e) {
            /* ignore */
        }
        var btn = document.getElementById('topbar-theme-btn');
        if (btn) {
            applyAria(btn);
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        var btn = document.getElementById('topbar-theme-btn');
        if (!btn) {
            return;
        }
        applyAria(btn);
        btn.addEventListener('click', toggle);
    });
})();
