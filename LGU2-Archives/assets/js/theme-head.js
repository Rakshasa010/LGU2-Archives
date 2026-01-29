// Tailwind dark-mode preflight (no flicker)
// - Reads canonical key: "theme" (values: "dark" | "light")
// - Migrates legacy key: "plv-theme" -> "theme"
(function () {
  const root = document.documentElement;

  function getStoredTheme() {
    try {
      const theme = localStorage.getItem('theme');
      if (theme === 'dark' || theme === 'light') return theme;

      const legacy = localStorage.getItem('plv-theme');
      if (legacy === 'dark' || legacy === 'light') {
        localStorage.setItem('theme', legacy);
        return legacy;
      }
    } catch (_) {
      // ignore storage errors (privacy mode / blocked storage)
    }
    return 'light';
  }

  if (getStoredTheme() === 'dark') root.classList.add('dark');
})();

