(function () {
    // Inject styles
    const style = document.createElement('style');
    style.innerHTML = `
        #command-palette-backdrop {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.5);
            backdrop-filter: blur(4px);
            z-index: 9998;
            align-items: flex-start;
            justify-content: center;
            padding-top: 10vh;
        }
        #command-palette-backdrop.open {
            display: flex;
        }
        #command-palette {
            background: var(--bg-color, white);
            width: 100%;
            max-width: 600px;
            border-radius: 16px;
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5);
            overflow: hidden;
            transform: scale(0.95);
            opacity: 0;
            transition: all 0.2s ease;
            z-index: 9999;
            border: 1px solid rgba(156, 163, 175, 0.2);
        }
        .dark #command-palette {
            background: #1e293b;
            color: #f1f5f9;
            border-color: rgba(255, 255, 255, 0.1);
        }
        #command-palette-backdrop.open #command-palette {
            transform: scale(1);
            opacity: 1;
        }
        #cp-input {
            width: 100%;
            padding: 20px 24px;
            font-size: 1.125rem;
            border: none;
            outline: none;
            background: transparent;
            color: inherit;
            border-bottom: 1px solid rgba(156, 163, 175, 0.2);
        }
        #cp-results {
            max-height: 350px;
            overflow-y: auto;
            padding: 8px 0;
        }
        .cp-item {
            padding: 12px 24px;
            display: flex;
            align-items: center;
            gap: 12px;
            cursor: pointer;
            transition: background 0.1s;
            color: #4b5563;
        }
        .dark .cp-item {
            color: #94a3b8;
        }
        .cp-item:hover, .cp-item.active {
            background: rgba(220, 38, 38, 0.1);
            color: #dc2626;
        }
        .dark .cp-item:hover, .dark .cp-item.active {
            background: rgba(220, 38, 38, 0.2);
            color: #fca5a5;
        }
        .cp-icon {
            font-size: 1.25rem;
        }
        .cp-shortcut {
            margin-left: auto;
            font-size: 0.75rem;
            background: rgba(156, 163, 175, 0.2);
            padding: 2px 6px;
            border-radius: 4px;
            opacity: 0.7;
        }
    `;
    document.head.appendChild(style);

    // Command links
    const commands = [
        { name: 'Dashboard', url: 'archives-landing.php', icon: 'bi-speedometer2', keys: 'G D' },
        { name: 'Storage Archives', url: 'storage.php', icon: 'bi-folder', keys: 'G S' },
        { name: 'Export Data', url: 'export.php', icon: 'bi-cloud-upload', keys: 'G E' },
        { name: 'Version Tracking', url: 'version_tracking.php', icon: 'bi-book', keys: 'G V' },
        { name: 'Reports & Analytics', url: 'report_analytics.php', icon: 'bi-graph-up', keys: 'G R' },
        { name: 'User Management', url: 'user_management.php', icon: 'bi-people', keys: 'G U' },
        { name: 'Audit Logs', url: 'audit-logs.php', icon: 'bi-shield-check', keys: 'G A' },
        { name: 'Account Settings', url: 'profile_management.php', icon: 'bi-gear', keys: 'G P' },
        { name: 'Sign Out', url: 'logout.php', icon: 'bi-box-arrow-right', keys: '⌘ Q' }
    ];

    // Inject HTML
    const backdrop = document.createElement('div');
    backdrop.id = 'command-palette-backdrop';
    backdrop.innerHTML = `
        <div id="command-palette">
            <input type="text" id="cp-input" placeholder="Search for pages or commands..." autocomplete="off" spellcheck="false" />
            <div id="cp-results"></div>
        </div>
    `;
    document.body.appendChild(backdrop);

    const input = document.getElementById('cp-input');
    const resultsContainer = document.getElementById('cp-results');
    let activeIndex = 0;
    let filtered = [...commands];

    function render() {
        resultsContainer.innerHTML = '';
        if (filtered.length === 0) {
            resultsContainer.innerHTML = '<div class="px-6 py-4 text-sm opacity-50 text-center">No commands found.</div>';
            return;
        }
        filtered.forEach((cmd, i) => {
            const div = document.createElement('div');
            div.className = 'cp-item' + (i === activeIndex ? ' active' : '');
            div.innerHTML = `
                <i class="bi ${cmd.icon} cp-icon"></i>
                <span style="font-weight: 500">${cmd.name}</span>
                <span class="cp-shortcut">${cmd.keys}</span>
            `;
            div.onclick = () => { window.location.href = cmd.url; };
            div.onmouseover = () => {
                activeIndex = i;
                Array.from(resultsContainer.children).forEach((c, idx) => c.classList.toggle('active', idx === activeIndex));
            };
            resultsContainer.appendChild(div);
        });
    }

    function togglePalette() {
        const isOpen = backdrop.classList.contains('open');
        if (isOpen) {
            backdrop.classList.remove('open');
            input.blur();
        } else {
            backdrop.classList.add('open');
            input.value = '';
            filtered = [...commands];
            activeIndex = 0;
            render();
            setTimeout(() => input.focus(), 50);
        }
    }

    input.addEventListener('input', (e) => {
        const query = e.target.value.toLowerCase();
        filtered = commands.filter(c => c.name.toLowerCase().includes(query));
        activeIndex = 0;
        render();
    });

    input.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') togglePalette();
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            activeIndex = (activeIndex + 1) % filtered.length;
            render();
            const activeEl = resultsContainer.children[activeIndex];
            if (activeEl) activeEl.scrollIntoView({ block: 'nearest' });
        }
        if (e.key === 'ArrowUp') {
            e.preventDefault();
            activeIndex = (activeIndex - 1 + filtered.length) % filtered.length;
            render();
            const activeEl = resultsContainer.children[activeIndex];
            if (activeEl) activeEl.scrollIntoView({ block: 'nearest' });
        }
        if (e.key === 'Enter') {
            e.preventDefault();
            if (filtered[activeIndex]) window.location.href = filtered[activeIndex].url;
        }
    });

    backdrop.addEventListener('click', (e) => {
        if (e.target === backdrop) togglePalette();
    });

    // Global shortcut Ctrl+K or Cmd+K
    // And global "G" + key sequence? Too complex for simple setup, let's stick to Ctrl+K
    document.addEventListener('keydown', (e) => {
        if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
            e.preventDefault();
            togglePalette();
        }
    });

})();
