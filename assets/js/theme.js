/* ==========================================================================
   PPSS Theme controller
   - Applies the saved theme before paint (loaded synchronously in <head>).
   - Persists the preference in localStorage under "ppss-theme".
   - Falls back to the operating-system preference.
   ========================================================================== */

(function () {
    'use strict';

    var STORAGE_KEY = 'ppss-theme';
    var root = document.documentElement;
    var media = window.matchMedia ? window.matchMedia('(prefers-color-scheme: dark)') : null;

    function readStoredTheme() {
        try {
            var value = window.localStorage.getItem(STORAGE_KEY);
            return value === 'light' || value === 'dark' ? value : null;
        } catch (error) {
            return null;
        }
    }

    function systemTheme() {
        return media && media.matches ? 'dark' : 'light';
    }

    function storeTheme(theme) {
        try {
            window.localStorage.setItem(STORAGE_KEY, theme);
        } catch (error) {
            /* Private browsing / storage disabled: theme still applies for this page. */
        }
    }

    function currentTheme() {
        return root.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
    }

    function updateToggleButtons(theme) {
        var isDark = theme === 'dark';
        var label = isDark ? 'Switch to light mode' : 'Switch to dark mode';

        document.querySelectorAll('.theme-toggle').forEach(function (button) {
            button.setAttribute('aria-pressed', isDark ? 'true' : 'false');
            button.setAttribute('aria-label', label);
            button.setAttribute('title', label);

            var icon = button.querySelector('.theme-toggle-icon');
            if (icon) {
                icon.textContent = isDark ? 'light_mode' : 'dark_mode';
            }

            var text = button.querySelector('.theme-toggle-text');
            if (text) {
                text.textContent = isDark ? 'Light' : 'Dark';
            }
        });
    }

    function applyTheme(theme, persist) {
        var next = theme === 'dark' ? 'dark' : 'light';
        root.setAttribute('data-theme', next);
        root.style.colorScheme = next;

        if (persist) {
            storeTheme(next);
        }

        updateToggleButtons(next);

        window.dispatchEvent(new CustomEvent('themechange', {
            detail: { theme: next }
        }));
    }

    // Apply immediately, before first paint, to avoid a flash of the wrong theme.
    root.setAttribute('data-theme', readStoredTheme() || systemTheme());

    window.toggleTheme = function () {
        applyTheme(currentTheme() === 'dark' ? 'light' : 'dark', true);
    };

    window.setTheme = function (theme) {
        applyTheme(theme, true);
    };

    window.getTheme = currentTheme;

    function init() {
        updateToggleButtons(currentTheme());

        if (media) {
            var onSystemChange = function (event) {
                // Only follow the OS when the user has not chosen a theme.
                if (!readStoredTheme()) {
                    applyTheme(event.matches ? 'dark' : 'light', false);
                }
            };

            if (typeof media.addEventListener === 'function') {
                media.addEventListener('change', onSystemChange);
            } else if (typeof media.addListener === 'function') {
                media.addListener(onSystemChange);
            }
        }

        // Keep multiple open tabs in sync.
        window.addEventListener('storage', function (event) {
            if (event.key === STORAGE_KEY && (event.newValue === 'light' || event.newValue === 'dark')) {
                applyTheme(event.newValue, false);
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
