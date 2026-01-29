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

    function setButtonState(btn, status) {
        if (!btn) return;
        // reset
        btn.classList.remove(
            'bg-red-600','hover:bg-red-700','text-white','border-red-700',
            'bg-white','dark:bg-slate-700','text-gray-700','dark:text-gray-200',
            'border-gray-200','dark:border-slate-600'
        );

        if (status === 'unread') {
            btn.classList.add('bg-red-600','hover:bg-red-700','text-white','border-red-700');
            btn.textContent = 'Mark Read';
        } else {
            btn.classList.add('bg-white','dark:bg-slate-700','text-gray-700','dark:text-gray-200','border-gray-200','dark:border-slate-600');
            btn.textContent = 'Read';
        }
    }

    function updateRowStatus(id, status) {
        var tr = document.querySelector('[data-id="'+id+'"]');
        if (!tr) return;
        tr.setAttribute('data-status', status);
        var btn = tr.querySelector('.mark-read-btn');
        if (status === 'read') {
            tr.classList.remove('bg-yellow-50');
            setButtonState(btn, 'read');
        } else {
            tr.classList.add('bg-yellow-50');
            setButtonState(btn, 'unread');
        }
    }

    // Initialize UI from current DOM state first
    document.querySelectorAll('#notesBody tr').forEach(function(tr){
        var id = tr.getAttribute('data-id');
        var status = tr.getAttribute('data-status') || 'unread';
        updateRowStatus(id, status);
    });

    // Then override with any locally stored read/unread toggles
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
                setButtonState(btn, 'read');
            } else {
                tr.classList.add('highlight');
                setButtonState(btn, 'unread');
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
    // Dark mode is handled centrally by `assets/js/theme-head.js` + `assets/js/theme-toggle.js`.
})();
