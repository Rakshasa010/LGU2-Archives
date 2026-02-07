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
                   '<td class="px-3 py-2 text-sm"><button class="mark-read-btn px-3 py-1.5 text-xs font-semibold rounded-lg border transition-colors" type="button">Mark Read</button></td>'+
                   '</tr>';
        }).join('');
        tbody.innerHTML = html;
        attachRowHandlers();
        updateUnreadCount();
        logShown();
    }
    document.querySelectorAll('#notesBody tr').forEach(function(tr){
        var id = tr.getAttribute('data-id');
        var status = tr.getAttribute('data-status') || 'unread';
        updateRowStatus(id, status);
    });

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
            if (pagePrev) { pagePrev.disabled = cur<=1; pagePrev.onclick = function(){ fetchNotifications.page = Math.max(1, cur-1); fetchNotifications(); }; }
            if (pageNext) { pageNext.disabled = cur>=maxPage; pageNext.onclick = function(){ fetchNotifications.page = Math.min(maxPage, cur+1); fetchNotifications(); }; }
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
                var cur = tr.getAttribute('data-status') || 'unread';
                var next = (cur === 'unread') ? 'read' : 'unread';
                fetch('notifications_update.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'id='+encodeURIComponent(id)+'&status='+encodeURIComponent(next)
                }).then(function(r){ return r.json(); }).then(function(d){
                    if (d && d.success) {
                        updateRowStatus(id, next);
                        updateUnreadCount();
                        logEvent(next==='read'?'alert_dismissed':'notification_mark_unread',[id]);
                    }
                }).catch(function(){});
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
        document.querySelectorAll('#notesBody tr').forEach(function(r){ if (r.getAttribute('data-status') === 'unread') unread++; });
        unreadCountEl.textContent = unread + ' unread';
    }

    filterAll.addEventListener('click', function(){ document.querySelectorAll('#notesBody tr').forEach(function(r){ r.style.display = ''; }); });
    filterUnread.addEventListener('click', function(){ document.querySelectorAll('#notesBody tr').forEach(function(r){ r.style.display = (r.getAttribute('data-status') === 'unread') ? '' : 'none'; }); });
    searchInput.addEventListener('input', function(){ var q = (this.value || '').toLowerCase(); document.querySelectorAll('#notesBody tr').forEach(function(r){ var text = r.textContent.toLowerCase(); r.style.display = text.indexOf(q) !== -1 ? '' : 'none'; }); });

    document.getElementById('filter-status')?.addEventListener('change', function(){ fetchNotifications.page = 1; fetchNotifications(); });
    document.getElementById('filter-about')?.addEventListener('change', function(){ fetchNotifications.page = 1; fetchNotifications(); });
    document.getElementById('filter-from')?.addEventListener('change', function(){ fetchNotifications.page = 1; fetchNotifications(); });
    document.getElementById('filter-to')?.addEventListener('change', function(){ fetchNotifications.page = 1; fetchNotifications(); });
    document.getElementById('page-size')?.addEventListener('change', function(){ fetchNotifications.page = 1; fetchNotifications(); });
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
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function(){ fetchNotifications(); });
    } else {
        fetchNotifications();
    }
})();
