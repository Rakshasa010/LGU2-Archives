// Shared Recently Deleted storage helper (30-day retention)
// Stores items under localStorage key "deletedFiles" for `recent_deleted.php`.
(function () {
  const KEY = 'deletedFiles';
  const RETENTION_DAYS = 30;
  const MAX_ITEMS = 200;

  function safeParse(json, fallback) {
    try {
      const val = JSON.parse(json);
      return Array.isArray(val) ? val : fallback;
    } catch (_) {
      return fallback;
    }
  }

  function nowMs() {
    return Date.now();
  }

  function toIso(ms) {
    return new Date(ms).toISOString();
  }

  function load() {
    try {
      return safeParse(localStorage.getItem(KEY) || '[]', []);
    } catch (_) {
      return [];
    }
  }

  function save(items) {
    try {
      localStorage.setItem(KEY, JSON.stringify(items));
    } catch (_) {
      // ignore
    }
  }

  function prune(items, atMs = nowMs()) {
    return items.filter((it) => {
      if (!it) return false;
      if (!it.expireAt) return true;
      const exp = Date.parse(it.expireAt);
      return Number.isFinite(exp) ? exp > atMs : true;
    });
  }

  function add(entry) {
    const t = nowMs();
    const expMs = t + RETENTION_DAYS * 24 * 60 * 60 * 1000;

    const item = {
      id: String(entry?.id ?? `${t}`),
      name: String(entry?.name ?? 'Untitled'),
      type: String(entry?.type ?? '').toUpperCase() || 'FILE',
      category: String(entry?.category ?? entry?.type ?? 'Other'),
      originalPath: String(entry?.originalPath ?? ''),
      deletedAt: String(entry?.deletedAt ?? toIso(t)),
      expireAt: String(entry?.expireAt ?? toIso(expMs)),
    };

    const items = prune(load(), t);
    items.unshift(item);
    if (items.length > MAX_ITEMS) items.length = MAX_ITEMS;
    save(items);
    return item;
  }

  window.DeletedFiles = {
    KEY,
    RETENTION_DAYS,
    load,
    save,
    prune,
    add,
  };
})();

