(function(){
    var selected = null;
    try {
        var body = document.body;
        if (body && body.dataset && body.dataset.auditSelected) {
            selected = body.dataset.auditSelected || null;
            if (selected === '') selected = null;
        }
    } catch (e) {}
    try { if (selected === null && typeof window._audit_selected_from_php !== 'undefined') selected = window._audit_selected_from_php; } catch(e){}
    if (selected !== null) {
        var el = document.getElementById('note-' + selected);
        if (el) {
            el.scrollIntoView({ behavior: 'smooth', block: 'center' });
            el.classList.add('ring-2', 'ring-red-300');
            setTimeout(function(){ el.classList.remove('ring-2', 'ring-red-300'); }, 2200);
        }
    }
    // initialize read state from localStorage
    var stored = {};
    try { stored = JSON.parse(localStorage.getItem('audit_read') || '{}'); } catch(e){ stored = {}; }

    function updateRowStatus(id, status) {
        var tr = document.querySelector('[data-id="'+id+'"]');
        if (!tr) return;
        tr.setAttribute('data-status', status);
        if (status === 'read') {
            tr.classList.remove('bg-yellow-50');
            var btn = tr.querySelector('.mark-read-btn'); if (btn) btn.textContent = 'Mark Read';
        } else {
            tr.classList.add('bg-yellow-50');
        }
    }

    Object.keys(stored).forEach(function(k){ var val = stored[k]; updateRowStatus(k, val); });

    document.querySelectorAll('.mark-read-btn').forEach(function(btn){
        btn.addEventListener('click', function(e){
            e.stopPropagation();
            var tr = btn.closest('tr');
            var id = tr.getAttribute('data-id');
            var cur = tr.getAttribute('data-status') || 'unread';
            var next = (cur === 'unread') ? 'read' : 'unread';
            stored[id] = next;
            localStorage.setItem('audit_read', JSON.stringify(stored));
            tr.setAttribute('data-status', next);
            if (next === 'read') {
                tr.classList.remove('highlight');
                tr.style.opacity = '0.9';
                btn.textContent = 'Mark Read';
            } else {
                tr.classList.add('highlight');
                btn.textContent = 'Mark Read';
            }
            updateUnreadCount();
        });
    });

    var notesBody = document.getElementById('notesBody');
    var filterAll = document.getElementById('filter-all');
    var filterUnread = document.getElementById('filter-unread');
    var searchInput = document.getElementById('searchInput');
    var unreadCountEl = document.getElementById('unread-count');

    function updateUnreadCount(){
        var unread = 0;
        document.querySelectorAll('#notesBody tr').forEach(function(r){ if (r.getAttribute('data-status') === 'unread') unread++; });
        unreadCountEl.textContent = unread + ' unread';
    }

    filterAll.addEventListener('click', function(){ document.querySelectorAll('#notesBody tr').forEach(function(r){ r.style.display = ''; }); });
    filterUnread.addEventListener('click', function(){ document.querySelectorAll('#notesBody tr').forEach(function(r){ r.style.display = (r.getAttribute('data-status') === 'unread') ? '' : 'none'; }); });
    searchInput.addEventListener('input', function(){ var q = (this.value || '').toLowerCase(); document.querySelectorAll('#notesBody tr').forEach(function(r){ var text = r.textContent.toLowerCase(); r.style.display = text.indexOf(q) !== -1 ? '' : 'none'; }); });

    updateUnreadCount();
    function applyTheme(t){ if (t==='dark') document.documentElement.classList.add('dark'); else document.documentElement.classList.remove('dark'); }
    var savedTheme = localStorage.getItem('theme') || 'light'; applyTheme(savedTheme);
    var themeBtn = document.getElementById('themeToggleAudit');
    themeBtn?.addEventListener('click', function(){ var cur = document.documentElement.classList.contains('dark') ? 'dark' : 'light'; var next = (cur==='dark') ? 'light' : 'dark'; applyTheme(next); localStorage.setItem('theme', next); });
})();
