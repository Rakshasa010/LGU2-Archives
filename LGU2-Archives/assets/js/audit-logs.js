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
        status = (status || 'unread').toLowerCase();
        var tr = document.querySelector('[data-id="'+id+'"]');
        if (!tr) return;
        tr.setAttribute('data-status', status);
        var btn = tr.querySelector('.mark-read-btn');
        var contentEl = tr.querySelector('td:nth-child(4) a, td:nth-child(4) span');
        if (status === 'read') {
            tr.classList.remove('highlight');
            // Remove red highlight - CSS will handle default styling via data-status="read"
            setButtonState(btn, 'read');
            if (contentEl) {
                contentEl.classList.remove('font-semibold');
                contentEl.classList.add('font-medium');
            }
        } else {
            // Unread status - CSS will apply red highlight via data-status="unread"
            setButtonState(btn, 'unread');
            if (contentEl) {
                contentEl.classList.remove('font-medium');
                contentEl.classList.add('font-semibold');
            }
        }
    }

    function renderRows(items){
        var tbody = document.getElementById('notesBody');
        if (!tbody) return;
        var html = items.map(function(note){
            var linkHtml = '';
            if (note.link) linkHtml = '<a href="'+note.link+'" class="text-gray-800 dark:text-gray-100 hover:underline block">'+note.content+'</a>';
            else linkHtml = '<span class="text-gray-800 dark:text-gray-100 block">'+note.content+'</span>';
            return '<tr id="note-'+note.id+'" data-id="'+note.id+'" data-status="'+note.status+'" class="border-t">'+
                   '<td class="px-3 py-2 text-sm text-gray-700 dark:text-gray-200">'+note.id+'</td>'+
                   '<td class="px-3 py-2 text-sm">'+note.time+'</td>'+
                   '<td class="px-3 py-2 text-sm">'+note.date+'</td>'+
                   '<td class="px-3 py-2 text-sm">'+linkHtml+'</td>'+
                   '<td class="px-3 py-2 text-sm text-gray-600 dark:text-gray-300">'+note.about+'</td>'+
                   '<td class="px-3 py-2 text-sm"><button class="mark-read-btn px-3 py-1.5 text-xs font-semibold rounded-lg border bg-red-600 hover:bg-red-700 text-white border-red-700 transition-colors" type="button">Mark Read</button></td>'+
                   '</tr>';
        }).join('');
        tbody.innerHTML = html;
        // apply initial styles per item status
        items.forEach(function(note){ updateRowStatus(note.id, note.status || 'unread'); });
        attachRowHandlers();
        updateUnreadCount();
        logShown();
    }
    document.querySelectorAll('#notesBody tr').forEach(function(tr){
        var id = tr.getAttribute('data-id');
        var status = (tr.getAttribute('data-status') || 'unread').toLowerCase();
        updateRowStatus(id, status);
    });
    attachRowHandlers();

    // Mark read on row click (excluding the explicit toggle button)
    if (notesBody) {
        notesBody.addEventListener('click', function(e){
            var btn = e.target.closest('.mark-read-btn');
            if (btn) {
                var trBtn = btn.closest('tr');
                if (!trBtn) return;
                var idBtn = trBtn.getAttribute('data-id');
                var curBtn = trBtn.getAttribute('data-status') || 'unread';
                var nextBtn = (curBtn === 'unread') ? 'read' : 'unread';
                try {
                    fetch('notifications_update.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: 'id='+encodeURIComponent(idBtn)+'&status='+encodeURIComponent(nextBtn)
                    }).then(function(){});
                } catch(e){}
                updateRowStatus(idBtn, nextBtn);
                updateUnreadCount();
                return;
            }
            var anchor = e.target.closest('a');
            var tr = e.target.closest('tr');
            if (!tr) return;
            var id = tr.getAttribute('data-id');
            var cur = (tr.getAttribute('data-status') || 'unread').toLowerCase();
            if (cur === 'unread' && id) {
                try {
                    fetch('notifications_update.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: 'id='+encodeURIComponent(id)+'&status=read'
                    }).then(function(){});
                } catch(e){}
                updateRowStatus(id, 'read');
                updateUnreadCount();
            }
            // if content link clicked, allow navigation normally after updating
            if (anchor) {
                // no preventDefault; update already performed
            }
        });
    }

    function fetchNotifications(){
        var status = document.getElementById('filter-status')?.value || '';
        var about = document.getElementById('filter-about')?.value || '';
        var from = document.getElementById('filter-from')?.value || '';
        var to = document.getElementById('filter-to')?.value || '';
        var pageSize = parseInt(document.getElementById('page-size')?.value || '10', 10);
        var pageInfo = document.getElementById('page-info');
        var pagePrev = document.getElementById('page-prev');
        var pageNext = document.getElementById('page-next');
        var url = 'notifications_fetch.php?status='+encodeURIComponent(status)+'&about='+encodeURIComponent(about)+'&from='+encodeURIComponent(from)+'&to='+encodeURIComponent(to)+'&page='+encodeURIComponent(fetchNotifications.page||1)+'&page_size='+encodeURIComponent(pageSize);
        fetch(url).then(function(r){ return r.json(); }).then(function(data){
            if (!data || !data.success) return;
            renderRows(data.items||[]);
            var aboutSel = document.getElementById('filter-about');
            if (aboutSel && (aboutSel.options.length <= 1)) {
                (data.about_options||[]).forEach(function(opt){
                    var o = document.createElement('option'); o.value = opt; o.textContent = opt; aboutSel.appendChild(o);
                });
            }
            if (pageInfo) pageInfo.textContent = String(data.page)+' / '+Math.max(1, Math.ceil((data.total||0)/(data.page_size||10)));
            var maxPage = Math.max(1, Math.ceil((data.total||0)/(data.page_size||10)));
            var cur = data.page||1;
            if (pagePrev) { pagePrev.disabled = cur<=1; pagePrev.onclick = function(){ fetchNotifications.page = Math.max(1, cur-1); fetchNotifications(); updateUrlParams(); }; }
            if (pageNext) { pageNext.disabled = cur>=maxPage; pageNext.onclick = function(){ fetchNotifications.page = Math.min(maxPage, cur+1); fetchNotifications(); updateUrlParams(); }; }
        }).catch(function(){});
    }
    fetchNotifications.page = 1;
    Object.keys(stored).forEach(function(k){ var val = stored[k]; updateRowStatus(k, val); });

    function attachRowHandlers(){
        document.querySelectorAll('.mark-read-btn').forEach(function(btn){
            btn.addEventListener('click', function(e){
                e.stopPropagation();
                var tr = btn.closest('tr');
                var id = tr.getAttribute('data-id');
                var cur = (tr.getAttribute('data-status') || 'unread').toLowerCase();
                var next = (cur === 'unread') ? 'read' : 'unread';
                updateRowStatus(id, next);
                updateUnreadCount();
                try { logEvent(next==='read'?'alert_dismissed':'notification_mark_unread',[id]); } catch(e){}
                fetch('notifications_update.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'id='+encodeURIComponent(id)+'&status='+encodeURIComponent(next)
                }).then(function(){ }).catch(function(){});
            });
        });
    }

    var notesBody = document.getElementById('notesBody');
    var filterAll = document.getElementById('filter-all');
    var filterUnread = document.getElementById('filter-unread');
    var searchInput = document.getElementById('searchInput');
    var unreadCountEl = document.getElementById('unread-count');

    function updateUnreadCount(){
        var unread = 0;
        document.querySelectorAll('#notesBody tr').forEach(function(r){ if ((r.getAttribute('data-status') || '').toLowerCase() === 'unread') unread++; });
        unreadCountEl.textContent = unread + ' unread';
    }

    function updateUrlParams() {
        try {
            var params = new URLSearchParams(window.location.search);
            var s = document.getElementById('filter-status')?.value || '';
            var a = document.getElementById('filter-about')?.value || '';
            var f = document.getElementById('filter-from')?.value || '';
            var t = document.getElementById('filter-to')?.value || '';
            var ps = document.getElementById('page-size')?.value || '10';
            var p = String(fetchNotifications.page || 1);
            if (s) params.set('status', s); else params.delete('status');
            if (a) params.set('about', a); else params.delete('about');
            if (f) params.set('from', f); else params.delete('from');
            if (t) params.set('to', t); else params.delete('to');
            if (ps) params.set('page_size', ps);
            if (p) params.set('page', p);
            var u = window.location.pathname + '?' + params.toString();
            history.replaceState(null, '', u);
        } catch(e){}
    }
    function restoreFromUrl() {
        try {
            var params = new URLSearchParams(window.location.search);
            var s = params.get('status') || '';
            var a = params.get('about') || '';
            var f = params.get('from') || '';
            var t = params.get('to') || '';
            var ps = params.get('page_size') || '';
            var p = parseInt(params.get('page') || '1', 10);
            var selS = document.getElementById('filter-status'); if (selS) selS.value = s;
            var selA = document.getElementById('filter-about'); if (selA) selA.value = a;
            var inpF = document.getElementById('filter-from'); if (inpF) inpF.value = f;
            var inpT = document.getElementById('filter-to'); if (inpT) inpT.value = t;
            var selPS = document.getElementById('page-size'); if (selPS && ps) selPS.value = ps;
            if (!isNaN(p)) fetchNotifications.page = p;
        } catch(e){}
    }
    restoreFromUrl();
    if (filterAll) filterAll.addEventListener('click', function(){
        var sel = document.getElementById('filter-status'); if (sel) sel.value = '';
        fetchNotifications.page = 1; fetchNotifications(); updateUrlParams();
    });
    if (filterUnread) filterUnread.addEventListener('click', function(){
        var sel = document.getElementById('filter-status'); if (sel) sel.value = 'unread';
        fetchNotifications.page = 1; fetchNotifications(); updateUrlParams();
    });
    if (searchInput) searchInput.addEventListener('input', function(){ var q = (this.value || '').toLowerCase(); document.querySelectorAll('#notesBody tr').forEach(function(r){ var text = r.textContent.toLowerCase(); r.style.display = text.indexOf(q) !== -1 ? '' : 'none'; }); });

    // Date range preset functions
    function setDateRange(fromDate, toDate) {
        var fromInput = document.getElementById('filter-from');
        var toInput = document.getElementById('filter-to');
        if (fromInput) fromInput.value = fromDate;
        if (toInput) toInput.value = toDate;
        fetchNotifications.page = 1;
        fetchNotifications();
        updateUrlParams();
    }

    function getTodayDate() {
        var today = new Date();
        return today.toISOString().split('T')[0];
    }

    function getWeekStartDate() {
        var today = new Date();
        var day = today.getDay();
        // Adjust so Monday is the start of the week (day 0 = Sunday, so Monday = 1)
        var diff = today.getDate() - (day === 0 ? 6 : day - 1);
        var weekStart = new Date(today);
        weekStart.setDate(diff);
        return weekStart.toISOString().split('T')[0];
    }

    function getMonthStartDate() {
        var today = new Date();
        return new Date(today.getFullYear(), today.getMonth(), 1).toISOString().split('T')[0];
    }

    // Date preset button handlers
    document.getElementById('date-preset-today')?.addEventListener('click', function(){
        var today = getTodayDate();
        setDateRange(today, today);
    });

    document.getElementById('date-preset-week')?.addEventListener('click', function(){
        var weekStart = getWeekStartDate();
        var today = getTodayDate();
        setDateRange(weekStart, today);
    });

    document.getElementById('date-preset-month')?.addEventListener('click', function(){
        var monthStart = getMonthStartDate();
        var today = getTodayDate();
        setDateRange(monthStart, today);
    });

    document.getElementById('filter-status')?.addEventListener('change', function(){ fetchNotifications.page = 1; fetchNotifications(); updateUrlParams(); });
    document.getElementById('filter-about')?.addEventListener('change', function(){ fetchNotifications.page = 1; fetchNotifications(); updateUrlParams(); });
    document.getElementById('filter-from')?.addEventListener('change', function(){ fetchNotifications.page = 1; fetchNotifications(); updateUrlParams(); });
    document.getElementById('filter-to')?.addEventListener('change', function(){ fetchNotifications.page = 1; fetchNotifications(); updateUrlParams(); });
    document.getElementById('page-size')?.addEventListener('change', function(){ fetchNotifications.page = 1; fetchNotifications(); updateUrlParams(); });
    updateUnreadCount();
    function logEvent(type, ids){
        try {
            fetch('notifications_log.php', {
                method:'POST',
                headers:{'Content-Type':'application/x-www-form-urlencoded'},
                body:'event_type='+encodeURIComponent(type)+'&ids='+encodeURIComponent(JSON.stringify(ids||[]))
            }).then(function(){});
        } catch(e){}
    }
    function logShown(){
        var ids = Array.from(document.querySelectorAll('#notesBody tr')).map(function(r){ return r.getAttribute('data-id'); });
        logEvent('alert_shown', ids);
    }
    var markAll = document.getElementById('mark-all-read');
    if (markAll) markAll.addEventListener('click', function(){
        var rows = Array.from(document.querySelectorAll('#notesBody tr')).filter(function(r){
            var st = (r.getAttribute('data-status') || 'unread').toLowerCase();
            var hidden = false;
            try { hidden = window.getComputedStyle(r).display === 'none'; } catch(e){}
            return st === 'unread' && !hidden;
        });
        var ids = rows.map(function(r){ return r.getAttribute('data-id'); }).filter(Boolean);
        if (ids.length === 0) return;
        ids.forEach(function(id){ updateRowStatus(id, 'read'); });
        updateUnreadCount();
        try { logEvent('alert_dismissed', ids); } catch(e){}
        Promise.all(ids.map(function(id){
            return fetch('notifications_update.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'id='+encodeURIComponent(id)+'&status=read'
            }).then(function(){});
        })).catch(function(){});
    });
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function(){ fetchNotifications(); });
    } else {
        fetchNotifications();
    }
})();
