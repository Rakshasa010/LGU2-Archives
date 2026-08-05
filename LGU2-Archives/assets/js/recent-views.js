// Recent Views helper (per-user, per-browser; stored in localStorage)
(function () {
  var KEY = 'recentViews';
  var MAX_ITEMS = 50;

  function safeParse(json, fallback) {
    try {
      var val = JSON.parse(json);
      return Array.isArray(val) ? val : fallback;
    } catch (_) {
      return fallback;
    }
  }

  function nowMs() {
    return Date.now();
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
    } catch (_) {}
  }

  function normalize(item) {
    var o = {
      id: item && item.id != null ? String(item.id) : '',
      title: item && item.title ? String(item.title) : '',
      type: item && item.type ? String(item.type) : '',
      month: item && item.month ? String(item.month) : '',
      year: item && item.year ? String(item.year) : '',
      author: item && item.author ? String(item.author) : ''
    };
    return o;
  }

  function dedupeAppend(list, item) {
    var id = item.id || '';
    var title = item.title || '';
    var type = item.type || '';
    var month = item.month || '';
    var year = item.year || '';
    var author = item.author || '';
    var filtered = [];
    for (var i = 0; i < list.length; i++) {
      var it = list[i] || {};
      if (
        (id && it.id === id) ||
        (title && it.title === title && it.type === type && it.month === month && String(it.year) === String(year))
      ) {
        continue;
      }
      filtered.push(it);
    }
    filtered.unshift(item);
    if (filtered.length > MAX_ITEMS) filtered.length = MAX_ITEMS;
    return filtered;
  }

  function iconForType(type) {
    var t = String(type || '').toLowerCase();
    if (t === 'ordinance') return '<svg class="w-6 h-6 text-orange-600 dark:text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>';
    if (t === 'resolution') return '<svg class="w-6 h-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>';
    if (t === 'public hearing') return '<svg class="w-6 h-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z"/></svg>';
    if (t === 'meeting') return '<svg class="w-6 h-6 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>';
    return '<svg class="w-6 h-6 text-gray-600 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>';
  }

  function formatItem(item) {
    var title = item.title || 'Untitled';
    var type = item.type || '';
    var month = item.month || '';
    var year = item.year || '';
    var author = item.author || '';
    var meta = [month && String(month), year && String(year)].filter(Boolean).join(' ');
    
    var isImg = /\.(jpg|jpeg|png|gif|webp)$/i.test(title);
    var previewFn = isImg ? ' onclick="if(window.openImagePreview) { window.openImagePreview(\'uploads/archives/\' + \'' + title + '\', \'' + title + '\'); } return false;"' : '';
    
    var dropdownHtml = 
      '<div class="relative ml-auto">' +
        '<button onclick="var menu = this.nextElementSibling; document.querySelectorAll(\'.rv-dropdown\').forEach(function(el){ if(el !== menu) el.classList.add(\'hidden\'); }); menu.classList.toggle(\'hidden\'); if(event) event.stopPropagation();" class="p-1 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 focus:outline-none rounded-full hover:bg-gray-200 dark:hover:bg-slate-600">' +
          '<svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"/></svg>' +
        '</button>' +
        '<div class="rv-dropdown hidden absolute right-0 mt-1 w-32 bg-white dark:bg-slate-800 rounded-md shadow-lg border border-gray-200 dark:border-slate-700 z-10 py-1">' +
          '<a href="#" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-slate-700">Open</a>' +
          (isImg ? '<a href="#"' + previewFn + ' class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-slate-700">Preview Layout</a>' : '') +
          '<a href="#" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-slate-700">Download</a>' +
        '</div>' +
      '</div>';

    return (
      '<div class="bg-gray-50 dark:bg-slate-700 rounded-lg p-3 border border-gray-200 dark:border-slate-600 flex items-center space-x-3">' +
      '<div class="flex-shrink-0">' + iconForType(type) + '</div>' +
      '<div class="flex-1 min-w-0" onclick="document.querySelectorAll(\'.rv-dropdown\').forEach(function(el){ el.classList.add(\'hidden\'); });">' +
      '<div class="font-semibold text-gray-800 dark:text-gray-200 truncate pr-2">' + title + '</div>' +
      '<div class="text-xs text-gray-600 dark:text-gray-400 truncate mt-1">' +
      (type ? '<span class="px-2 py-0.5 bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-300 rounded">' + type + '</span>' : '') +
      (meta ? '<span class="mx-2 truncate">•</span><span class="truncate">' + meta + '</span>' : '') +
      (author ? '<span class="mx-2 truncate">•</span><span class="truncate">' + author + '</span>' : '') +
      '</div>' +
      '</div>' + dropdownHtml +
      '</div>'
    );
  }

  function render(container) {
    var el = typeof container === 'string' ? document.getElementById(container) : container;
    if (!el) return;
    var items = load().slice().sort(function (a, b) { return (b.ts || 0) - (a.ts || 0); });
    if (!items.length) {
      el.innerHTML = '<div class="text-sm text-gray-600 dark:text-gray-400">No recent files</div>';
      return;
    }
    var html = items.slice(0, 10).map(formatItem).join('');
    el.innerHTML = html;
  }

  function add(item) {
    var it = normalize(item || {});
    it.ts = nowMs();
    var list = load();
    list = dedupeAppend(list, it);
    save(list);
  }

  window.RecentViews = {
    add: add,
    list: function () {
      return load().slice().sort(function (a, b) { return (b.ts || 0) - (a.ts || 0); });
    },
    renderTo: render
  };

  try {
    window.addEventListener('storage', function (e) {
      if (e && e.key === KEY) {
        var target = document.getElementById('latestFilesList');
        if (target) render(target);
      }
    });
    window.addEventListener('visibilitychange', function () {
      if (!document.hidden) {
        var target = document.getElementById('latestFilesList');
        if (target) render(target);
      }
    });
  } catch (_) {}
})();

