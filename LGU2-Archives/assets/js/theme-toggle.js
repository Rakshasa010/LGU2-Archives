// Shared dark-mode toggle for Tailwind `dark:` classes.
// Supports multiple toggles:
// - `themeToggle` + `moonIcon`/`sunIcon`
// - `themeToggleUser` + `moonIconUser`/`sunIconUser`
// ...any id starting with "themeToggle" will be handled.
(function () {
  const KEY = 'theme';
  const LEGACY_KEY = 'archive-theme';
  const root = document.documentElement;

  function normalizeMode(value) {
    return value === 'dark' ? 'dark' : 'light';
  }

  function getStoredMode() {
    try {
      const stored = localStorage.getItem(KEY);
      if (stored === 'dark' || stored === 'light') return stored;

      const legacy = localStorage.getItem(LEGACY_KEY);
      if (legacy === 'dark' || legacy === 'light') {
        localStorage.setItem(KEY, legacy);
        return legacy;
      }
    } catch (_) {
      // ignore
    }
    return 'light';
  }

  function persistMode(mode) {
    const normalized = normalizeMode(mode);
    try {
      localStorage.setItem(KEY, normalized);
      // keep legacy key in sync for pages not yet updated
      localStorage.setItem(LEGACY_KEY, normalized);
    } catch (_) {
      // ignore
    }
  }

  function applyMode(mode) {
    const normalized = normalizeMode(mode);
    if (normalized === 'dark') root.classList.add('dark');
    else root.classList.remove('dark');
  }

  function setIconVisibility({ moon, sun, isDark }) {
    if (!moon || !sun) return;
    if (isDark) {
      moon.classList.remove('hidden');
      moon.classList.add('block');
      sun.classList.remove('block');
      sun.classList.add('hidden');
    } else {
      sun.classList.remove('hidden');
      sun.classList.add('block');
      moon.classList.remove('block');
      moon.classList.add('hidden');
    }
  }

  function updateAllToggleIcons() {
    const isDark = root.classList.contains('dark');
    const toggles = Array.from(document.querySelectorAll('[id^="themeToggle"]'));

    toggles.forEach((btn) => {
      const suffix = btn.id.slice('themeToggle'.length); // "" | "User" | ...
      const moon = document.getElementById(`moonIcon${suffix}`);
      const sun = document.getElementById(`sunIcon${suffix}`);
      setIconVisibility({ moon, sun, isDark });
      btn.title = isDark ? 'Switch to light mode' : 'Switch to dark mode';
      btn.setAttribute('aria-pressed', String(isDark));
    });
  }

  function broadcastChange(mode) {
    document.dispatchEvent(
      new CustomEvent('themechange', {
        detail: { mode: normalizeMode(mode) },
      }),
    );
  }

  function setMode(mode) {
    applyMode(mode);
    persistMode(mode);
    updateAllToggleIcons();
    broadcastChange(mode);

    // Save preference to the database
    fetch('save_theme.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ dark_mode: mode === 'dark' })
    }).catch(function (e) { console.error("Failed to save theme:", e); });
  }

  function attachHandlers() {
    const toggles = Array.from(document.querySelectorAll('[id^="themeToggle"]'));
    toggles.forEach((btn) => {
      btn.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        const next = root.classList.contains('dark') ? 'light' : 'dark';
        setMode(next);
      });
    });
  }

  // Initial sync
  setMode(getStoredMode());

  // Attach handlers when DOM is ready (in case this script is in <head> with defer).
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', attachHandlers);
  } else {
    attachHandlers();
  }

  // Cross-tab / other-window sync
  window.addEventListener('storage', (e) => {
    if (!e) return;
    if (e.key !== KEY && e.key !== LEGACY_KEY) return;
    if (!e.newValue) return;
    applyMode(e.newValue);
    updateAllToggleIcons();
    broadcastChange(e.newValue);
  });

  // Same-tab sync in case other scripts mutate storage directly
  window.addEventListener('focus', () => {
    const stored = getStoredMode();
    const applied = root.classList.contains('dark') ? 'dark' : 'light';
    if (normalizeMode(stored) !== normalizeMode(applied)) {
      applyMode(stored);
      updateAllToggleIcons();
      broadcastChange(stored);
    }
  });
})();
