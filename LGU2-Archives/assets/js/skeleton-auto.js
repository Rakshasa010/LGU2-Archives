// Global skeleton loading for common sections (MVP)
(function () {
  var selectors = [
    '#storage-overview',
    '#export-list',
    '#filesList',
    '#notesBody',
    '#auditTable',
    '#folder-grid',
    '.cards-grid',
    '.dataTable',
    'table'
  ];

  function addSkeleton(el) {
    if (!el || el.classList.contains('skeleton-shell')) return;
    el.classList.add('skeleton-shell');
  }

  function findTableShell(table) {
    var cur = table;
    var limit = 0;
    while (cur && limit < 4) {
      if (cur.classList && cur.classList.contains('overflow-x-auto')) return cur;
      cur = cur.parentElement;
      limit++;
    }
    return table;
  }

  function applySkeletons() {
    selectors.forEach(function (sel) {
      document.querySelectorAll(sel).forEach(function (el) {
        if (el.tagName && el.tagName.toLowerCase() === 'table') {
          addSkeleton(findTableShell(el));
        } else {
          addSkeleton(el);
        }
      });
    });
  }

  function clearSkeletons() {
    document.querySelectorAll('.skeleton-shell').forEach(function (el) {
      el.classList.remove('skeleton-shell');
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () {
      applySkeletons();
      setTimeout(clearSkeletons, 700);
    });
  } else {
    applySkeletons();
    setTimeout(clearSkeletons, 700);
  }
})();
