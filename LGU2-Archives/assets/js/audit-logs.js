(function () {
    var selected = null;
    try {
        var body = document.body;
        if (body && body.dataset && body.dataset.auditSelected) {
            selected = body.dataset.auditSelected || null;
            if (selected === '') selected = null;
        }
    } catch (e) { }
    try { if (selected === null && typeof window._audit_selected_from_php !== 'undefined') selected = window._audit_selected_from_php; } catch (e) { }
    if (selected !== null) {
        var el = document.getElementById('note-' + selected);
        if (el) {
            el.scrollIntoView({ behavior: 'smooth', block: 'center' });
            el.classList.add('ring-2', 'ring-red-300');
            setTimeout(function () { el.classList.remove('ring-2', 'ring-red-300'); }, 2200);
        }
    }
    var stored = {};
    try { stored = JSON.parse(localStorage.getItem('audit_read') || '{}'); } catch (e) { stored = {}; }

    var timeTimer = null;
    function buildTimestamp(dateStr, timeStr) {
        var ymd = (dateStr || '').split('-');
        var y = parseInt(ymd[0] || '0', 10);
        var m = parseInt(ymd[1] || '0', 10);
        var d = parseInt(ymd[2] || '0', 10);
        var match = (timeStr || '').match(/(\d{1,2}):(\d{2})\s*(AM|PM)/i);
        var hh = 0, mm = 0, ap = 'AM';
        if (match) {
            hh = parseInt(match[1], 10);
            mm = parseInt(match[2], 10);
            ap = match[3].toUpperCase();
        }
        if (ap === 'PM' && hh !== 12) hh += 12;
        if (ap === 'AM' && hh === 12) hh = 0;
        var dt = new Date(y, (m > 0 ? m - 1 : 0), d, hh, mm, 0);
        return dt.getTime();
    }
    function formatRelative(ms) {
        var now = Date.now();
        var diff = Math.floor((now - ms) / 1000);
        if (diff < 0) diff = 0;
        if (diff < 60) return diff + 's ago';
        var mins = Math.floor(diff / 60);
        if (mins < 60) return mins + 'm ago';
        var hrs = Math.floor(mins / 60);
        if (hrs < 24) return hrs + 'h ago';
        var days = Math.floor(hrs / 24);
        return days + 'd ago';
    }
    function updateRelativeTimes() {
        var nodes = document.querySelectorAll('.note-time');
        nodes.forEach(function (el) {
            var ms = parseInt(el.dataset.ts || '0', 10);
            var base = el.dataset.base || '';
            if (!isNaN(ms) && base) {
                el.innerHTML = base + ' <span class="text-gray-500">' + formatRelative(ms) + '</span>';
            }
        });
    }
    function ensureTimer() {
        if (timeTimer) return;
        timeTimer = setInterval(updateRelativeTimes, 60000);
    }

    function setButtonState(btn, status) {
        if (!btn) return;
        // reset
        btn.classList.remove(
            'bg-red-600', 'hover:bg-red-700', 'text-white', 'border-red-700',
            'bg-white', 'dark:bg-slate-700', 'text-gray-700', 'dark:text-gray-200',
            'border-gray-200', 'dark:border-slate-600'
        );

        if (status === 'unread') {
            btn.classList.add('bg-red-600', 'hover:bg-red-700', 'text-white', 'border-red-700');
            btn.textContent = 'Mark Read';
        } else {
            btn.classList.add('bg-white', 'dark:bg-slate-700', 'text-gray-700', 'dark:text-gray-200', 'border-gray-200', 'dark:border-slate-600');
            btn.textContent = 'Read';
        }
    }

    function updateRowStatus(id, status) {
        status = (status || 'unread').toLowerCase();
        var tr = document.querySelector('[data-id="' + id + '"]');
        if (!tr) return;
        tr.setAttribute('data-status', status);
        var btn = tr.querySelector('.mark-read-btn');
        var actionCell = tr.querySelector('td:nth-child(6)');
        var contentEl = tr.querySelector('td:nth-child(4) a, td:nth-child(4) span');
        if (status === 'read') {
            tr.classList.remove('highlight');
            if (actionCell) {
                actionCell.innerHTML = '<span class="inline-flex items-center px-3 py-1.5 text-xs font-semibold rounded-lg border bg-white dark:bg-slate-700 text-gray-700 dark:text-gray-200 border-gray-200 dark:border-slate-600">Read</span>';
            }
            if (contentEl) {
                contentEl.classList.remove('font-semibold');
                contentEl.classList.add('font-medium');
            }
        } else {
            if (actionCell) {
                actionCell.innerHTML = '<button class="mark-read-btn px-3 py-1.5 text-xs font-semibold rounded-lg border bg-red-600 hover:bg-red-700 text-white border-red-700 transition-colors" type="button">Mark Read</button>';
            }
            if (contentEl) {
                contentEl.classList.remove('font-medium');
                contentEl.classList.add('font-semibold');
            }
            attachRowHandlers();
        }
    }

    function renderRows(items) {
        var tbody = document.getElementById('notesBody');
        if (!tbody) return;
        var html = items.map(function (note) {
            var linkHtml = '';
            if (note.link) linkHtml = '<a href="' + note.link + '" class="text-gray-800 dark:text-gray-100 hover:underline block">' + note.content + '</a>';
            else linkHtml = '<span class="text-gray-800 dark:text-gray-100 block">' + note.content + '</span>';
            var baseMs = NaN;
            if (typeof note.age_seconds === 'number') {
                baseMs = Date.now() - (note.age_seconds * 1000);
            } else if (note.created_at) {
                var iso = String(note.created_at).replace(' ', 'T');
                var parsed = Date.parse(iso);
                if (!isNaN(parsed)) baseMs = parsed;
            }
            if (isNaN(baseMs)) {
                baseMs = buildTimestamp(String(note.date || ''), String(note.time || ''));
            }
            var actionHtml = (String((note.status || 'unread')).toLowerCase() === 'read')
                ? '<span class="inline-flex items-center px-3 py-1.5 text-xs font-semibold rounded-lg border bg-white dark:bg-slate-700 text-gray-700 dark:text-gray-200 border-gray-200 dark:border-slate-600">Read</span>'
                : '<button class="mark-read-btn px-3 py-1.5 text-xs font-semibold rounded-lg border bg-red-600 hover:bg-red-700 text-white border-red-700 transition-colors" type="button">Mark Read</button>';
            return '<tr id="note-' + note.id + '" data-id="' + note.id + '" data-status="' + note.status + '" class="border-t">' +
                '<td class="px-3 py-2 text-sm text-gray-700 dark:text-gray-200">' + note.id + '</td>' +
                '<td class="px-3 py-2 text-sm"><span class="note-time" data-ts="' + baseMs + '" data-base="' + String(note.time || '') + '" title="Created: ' + String(note.created_at || note.date) + ' • Status: ' + String(note.status || '') + '">' + String(note.time || '') + ' <span class="text-gray-500">' + formatRelative(baseMs) + '</span></span></td>' +
                '<td class="px-3 py-2 text-sm">' + note.date + '</td>' +
                '<td class="px-3 py-2 text-sm">' + linkHtml + '</td>' +
                '<td class="px-3 py-2 text-sm text-gray-600 dark:text-gray-300">' + note.about + '</td>' +
                '<td class="px-3 py-2 text-sm">' + actionHtml + '</td>' +
                '</tr>';
        }).join('');
        tbody.innerHTML = html;
        // apply initial styles per item status
        items.forEach(function (note) { updateRowStatus(note.id, note.status || 'unread'); });
        attachRowHandlers();
        updateUnreadCount();
        logShown();
        updateRelativeTimes();
        ensureTimer();
    }
    document.querySelectorAll('#notesBody tr').forEach(function (tr) {
        var id = tr.getAttribute('data-id');
        var status = (tr.getAttribute('data-status') || 'unread').toLowerCase();
        updateRowStatus(id, status);
    });
    attachRowHandlers();

    // Mark read on row click (excluding the explicit toggle button)
    if (notesBody) {
        notesBody.addEventListener('click', function (e) {
            var btn = e.target.closest('.mark-read-btn');
            if (btn) {
                var trBtn = btn.closest('tr');
                if (!trBtn) return;
                var idBtn = trBtn.getAttribute('data-id');
                var nextBtn = 'read';
                try {
                    fetch('notifications_update.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: 'id=' + encodeURIComponent(idBtn) + '&status=' + encodeURIComponent(nextBtn)
                    }).then(function () { });
                } catch (e) { }
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
                        body: 'id=' + encodeURIComponent(id) + '&status=read'
                    }).then(function () { });
                } catch (e) { }
                updateRowStatus(id, 'read');
                updateUnreadCount();
            }
            // if content link clicked, allow navigation normally after updating
            if (anchor) {
                // no preventDefault; update already performed
            }
        });
    }

    function fetchNotifications() {
        var status = document.getElementById('filter-status')?.value || '';
        var about = document.getElementById('filter-about')?.value || '';
        var from = document.getElementById('filter-from')?.value || '';
        var to = document.getElementById('filter-to')?.value || '';
        var pageSize = parseInt(document.getElementById('page-size')?.value || '10', 10);
        var pageInfo = document.getElementById('page-info');
        var pagePrev = document.getElementById('page-prev');
        var pageNext = document.getElementById('page-next');
        var url = 'notifications_fetch.php?status=' + encodeURIComponent(status) + '&about=' + encodeURIComponent(about) + '&from=' + encodeURIComponent(from) + '&to=' + encodeURIComponent(to) + '&page=' + encodeURIComponent(fetchNotifications.page || 1) + '&page_size=' + encodeURIComponent(pageSize);
        fetch(url).then(function (r) { return r.json(); }).then(function (data) {
            if (!data || !data.success) return;
            renderRows(data.items || []);
            var aboutSel = document.getElementById('filter-about');
            if (aboutSel) {
                // Clear existing dynamic options (keep the first "About" option)
                while (aboutSel.options.length > 1) {
                    aboutSel.remove(1);
                }
                var staticAbout = ['Approval', 'Backup', 'Comment', 'Document Upload', 'Import', 'Message', 'Metadata', 'Permissions', 'Profile Update', 'Security', 'System Maintenance', 'User Management', 'User Registration'];
                var combined = Array.from(new Set(staticAbout.concat(data.about_options || [])));
                combined.sort();
                combined.forEach(function (opt) {
                    if (!opt) return;
                    var o = document.createElement('option');
                    o.value = opt;
                    o.textContent = opt;
                    aboutSel.appendChild(o);
                });

                // Restore selected value if valid
                var params = new URLSearchParams(window.location.search);
                var currentAbout = params.get('about');
                if (currentAbout) aboutSel.value = currentAbout;
            }
            if (pageInfo) pageInfo.textContent = String(data.page) + ' / ' + Math.max(1, Math.ceil((data.total || 0) / (data.page_size || 10)));
            var maxPage = Math.max(1, Math.ceil((data.total || 0) / (data.page_size || 10)));
            var cur = data.page || 1;
            if (pagePrev) { pagePrev.disabled = cur <= 1; pagePrev.onclick = function () { fetchNotifications.page = Math.max(1, cur - 1); fetchNotifications(); updateUrlParams(); }; }
            if (pageNext) { pageNext.disabled = cur >= maxPage; pageNext.onclick = function () { fetchNotifications.page = Math.min(maxPage, cur + 1); fetchNotifications(); updateUrlParams(); }; }
        }).catch(function () { });
    }
    fetchNotifications.page = 1;
    Object.keys(stored).forEach(function (k) { var val = stored[k]; updateRowStatus(k, val); });

    function attachRowHandlers() {
        document.querySelectorAll('.mark-read-btn').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.stopPropagation();
                var tr = btn.closest('tr');
                var id = tr.getAttribute('data-id');
                var next = 'read';
                updateRowStatus(id, next);
                updateUnreadCount();
                try { logEvent('alert_dismissed', [id]); } catch (e) { }
                fetch('notifications_update.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'id=' + encodeURIComponent(id) + '&status=' + encodeURIComponent(next)
                }).then(function () { }).catch(function () { });
            });
        });
    }

    var notesBody = document.getElementById('notesBody');
    var filterAll = document.getElementById('filter-all');
    var filterUnread = document.getElementById('filter-unread');
    var searchInput = document.getElementById('searchInput');
    var unreadCountEl = document.getElementById('unread-count');

    function updateUnreadCount() {
        var unread = 0;
        document.querySelectorAll('#notesBody tr').forEach(function (r) { if ((r.getAttribute('data-status') || '').toLowerCase() === 'unread') unread++; });
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
        } catch (e) { }
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
        } catch (e) { }
    }
    restoreFromUrl();
    if (filterAll) filterAll.addEventListener('click', function () {
        var sel = document.getElementById('filter-status'); if (sel) sel.value = '';
        fetchNotifications.page = 1; fetchNotifications(); updateUrlParams();
    });
    if (filterUnread) filterUnread.addEventListener('click', function () {
        var sel = document.getElementById('filter-status'); if (sel) sel.value = 'unread';
        fetchNotifications.page = 1; fetchNotifications(); updateUrlParams();
    });
    if (searchInput) searchInput.addEventListener('input', function () { var q = (this.value || '').toLowerCase(); document.querySelectorAll('#notesBody tr').forEach(function (r) { var text = r.textContent.toLowerCase(); r.style.display = text.indexOf(q) !== -1 ? '' : 'none'; }); });

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
    document.getElementById('date-preset-today')?.addEventListener('click', function () {
        var today = getTodayDate();
        setDateRange(today, today);
    });

    document.getElementById('date-preset-week')?.addEventListener('click', function () {
        var weekStart = getWeekStartDate();
        var today = getTodayDate();
        setDateRange(weekStart, today);
    });

    document.getElementById('date-preset-month')?.addEventListener('click', function () {
        var monthStart = getMonthStartDate();
        var today = getTodayDate();
        setDateRange(monthStart, today);
    });

    document.getElementById('filter-status')?.addEventListener('change', function () { fetchNotifications.page = 1; fetchNotifications(); updateUrlParams(); });
    document.getElementById('filter-about')?.addEventListener('change', function () { fetchNotifications.page = 1; fetchNotifications(); updateUrlParams(); });
    document.getElementById('filter-from')?.addEventListener('change', function () { fetchNotifications.page = 1; fetchNotifications(); updateUrlParams(); });
    document.getElementById('filter-to')?.addEventListener('change', function () { fetchNotifications.page = 1; fetchNotifications(); updateUrlParams(); });
    document.getElementById('page-size')?.addEventListener('change', function () { fetchNotifications.page = 1; fetchNotifications(); updateUrlParams(); });
    updateUnreadCount();
    function logEvent(type, ids) {
        try {
            fetch('notifications_log.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'event_type=' + encodeURIComponent(type) + '&ids=' + encodeURIComponent(JSON.stringify(ids || []))
            }).then(function () { });
        } catch (e) { }
    }
    function logShown() {
        var ids = Array.from(document.querySelectorAll('#notesBody tr')).map(function (r) { return r.getAttribute('data-id'); });
        logEvent('alert_shown', ids);
    }
    var markAll = document.getElementById('mark-all-read');
    if (markAll) markAll.addEventListener('click', function () {
        var rows = Array.from(document.querySelectorAll('#notesBody tr')).filter(function (r) {
            var st = (r.getAttribute('data-status') || 'unread').toLowerCase();
            var hidden = false;
            try { hidden = window.getComputedStyle(r).display === 'none'; } catch (e) { }
            return st === 'unread' && !hidden;
        });
        var ids = rows.map(function (r) { return r.getAttribute('data-id'); }).filter(Boolean);
        if (ids.length === 0) return;

        // Apply visual transition
        rows.forEach(function (r) {
            r.style.transition = 'all 0.5s ease';
            r.style.opacity = '0.5';
            setTimeout(function () { r.style.opacity = '1'; }, 500);
            updateRowStatus(r.getAttribute('data-id'), 'read');
        });

        updateUnreadCount();
        try { logEvent('alert_dismissed', ids); } catch (e) { }

        // Single batch fetch
        fetch('notifications_update.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'ids=' + encodeURIComponent(ids.join(',')) + '&status=read'
        }).then(function () { }).catch(function () { });
    });
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { fetchNotifications(); });
    } else {
        fetchNotifications();
    }
})();
