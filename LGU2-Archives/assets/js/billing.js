function openModal(modalId) {
    document.getElementById(modalId).classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.add('hidden');
    document.body.style.overflow = 'auto';
}

function createFolder() {
    const folderName = prompt('Enter folder name:');
    if (folderName && folderName.trim()) {
        alert(`Folder "${folderName}" created successfully!`);
    }
}

function uploadFile() {
    openModal('uploadModal');
}

document.getElementById('uploadForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    const fileInput = document.getElementById('fileInput');
    const fileName = document.getElementById('fileName').value.trim();

    if (fileInput && fileInput.files.length > 0 && fileName) {
        const dateMatch = fileName.match(/^(\d{4}-\d{2}-\d{2})_/);
        if (dateMatch) {
            const date = dateMatch[1];
            const fileType = fileName.split('.').pop().toLowerCase();

            try {
                if (!window.files) window.files = [];
                window.files.push({ name: fileName, date: date, type: fileType });
                if (typeof renderFiles === 'function') renderFiles();
            } catch(e){}

            this.reset();
            closeModal('uploadModal');
            alert('File uploaded successfully!');
        } else {
            alert('File name must start with YYYY-MM-DD_ format');
        }
    }
});

window.addEventListener('click', function(event) {
    const modals = document.querySelectorAll('[id$="Modal"]');
    modals.forEach(modal => {
        if (event.target === modal) {
            modal.classList.add('hidden');
            document.body.style.overflow = 'auto';
        }
    });
});

document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        const modals = document.querySelectorAll('[id$="Modal"]');
        modals.forEach(modal => {
            if (!modal.classList.contains('hidden')) {
                modal.classList.add('hidden');
                document.body.style.overflow = 'auto';
            }
        });
    }
});

document.addEventListener('DOMContentLoaded', function() {
    if (typeof renderFiles === 'function') renderFiles();
});

function openDownloadPopup(id, title, type, month, year, author) {
    const url = `download.php?id=${encodeURIComponent(id)}&title=${encodeURIComponent(title)}&type=${encodeURIComponent(type)}&month=${encodeURIComponent(month)}&year=${encodeURIComponent(year)}&author=${encodeURIComponent(author)}`;
    window.open(url, 'downloadPopup', 'width=500,height=400,scrollbars=yes,resizable=yes');
}

// Dark Mode Toggle - Updated for Tailwind
(function() {
    const root = document.documentElement;
    const STORAGE_KEY = 'plv-theme';
    
    function applyTheme(mode) {
        if (mode === 'dark') {
            root.classList.add('dark');
        } else {
            root.classList.remove('dark');
        }
    }
    
    const stored = localStorage.getItem(STORAGE_KEY) || 'light';
    applyTheme(stored);

    function initDarkMode() {
        const toggleBtn = document.getElementById('themeToggle');
        
        if (toggleBtn) {
            updateToggleIcon();
            
            toggleBtn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                const currentMode = root.classList.contains('dark') ? 'dark' : 'light';
                const newMode = currentMode === 'dark' ? 'light' : 'dark';
                
                applyTheme(newMode);
                localStorage.setItem(STORAGE_KEY, newMode);
                updateToggleIcon();
                
                document.dispatchEvent(new CustomEvent('themechange', { detail: { mode: newMode } }));
            });
        }
        
        function updateToggleIcon() {
            const moonIcon = document.getElementById('moonIcon');
            const sunIcon = document.getElementById('sunIcon');
            const toggleBtn = document.getElementById('themeToggle');
            if (!toggleBtn || !moonIcon || !sunIcon) return;
            
            const isDark = root.classList.contains('dark');
            if (isDark) {
                moonIcon.classList.remove('hidden');
                moonIcon.classList.add('block');
                sunIcon.classList.remove('block');
                sunIcon.classList.add('hidden');
            } else {
                sunIcon.classList.remove('hidden');
                sunIcon.classList.add('block');
                moonIcon.classList.remove('block');
                moonIcon.classList.add('hidden');
            }
            toggleBtn.title = isDark ? 'Switch to light mode' : 'Switch to dark mode';
        }
        
        window.addEventListener('storage', function(e) {
            if (e.key === STORAGE_KEY && e.newValue) {
                applyTheme(e.newValue);
                updateToggleIcon();
            }
        });
        
        window.addEventListener('focus', function() {
            const currentStored = localStorage.getItem(STORAGE_KEY) || 'light';
            const currentApplied = root.classList.contains('dark') ? 'dark' : 'light';
            if (currentStored !== currentApplied) {
                applyTheme(currentStored);
                updateToggleIcon();
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initDarkMode);
    } else {
        initDarkMode();
    }
})();

function openSideViewerServer(id, title, type, month, year, author) {
    const url = `download.php?id=${encodeURIComponent(id)}&title=${encodeURIComponent(title)}&type=${encodeURIComponent(type)}&month=${encodeURIComponent(month)}&year=${encodeURIComponent(year)}&author=${encodeURIComponent(author)}`;
    openSideViewer({ title, type, month, year, author, downloadUrl: url });
}

function openSideViewer(data) {
    const panel = document.getElementById('sideViewer');
    if (!panel) return;
    document.getElementById('sv-title').textContent = data.title || 'Untitled';
    document.getElementById('sv-type').textContent = data.type || '';
    document.getElementById('sv-meta').textContent = `${data.month || ''} ${data.year || ''}`.trim();
    document.getElementById('sv-author').textContent = data.author || '';
    const openBtn = document.getElementById('sv-open-btn');
    const preview = document.getElementById('sv-preview');

    const pdfUrl = (data.rawUrl && typeof data.rawUrl === 'string') ? data.rawUrl : data.downloadUrl;
    if (pdfUrl && pdfUrl.toLowerCase().endsWith('.pdf')) {
        preview.innerHTML = `<iframe class="w-full h-[60vh] border" src="${pdfUrl}" sandbox="allow-same-origin allow-scripts allow-popups"></iframe>`;
    } else {
        preview.textContent = data.previewText || 'Preview not available. Use Open to download or view the file.';
    }

    if (data.downloadUrl) {
        openBtn.href = data.downloadUrl;
        openBtn.classList.remove('hidden');
    } else {
        openBtn.classList.add('hidden');
    }
    panel.classList.remove('translate-x-full');
    panel.classList.add('translate-x-0');
    document.body.style.overflow = 'hidden';
}

function closeSideViewer() {
    const panel = document.getElementById('sideViewer');
    if (!panel) return;
    panel.classList.remove('translate-x-0');
    panel.classList.add('translate-x-full');
    document.body.style.overflow = 'auto';
}
