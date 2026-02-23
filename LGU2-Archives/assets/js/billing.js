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
        const fileType = fileName.split('.').pop().toLowerCase();

        try {
            if (!window.files) window.files = [];
            window.files.push({ name: fileName, date: new Date().toISOString().split('T')[0], type: fileType });
            if (typeof renderFiles === 'function') renderFiles();
        } catch(e){}

        this.reset();
        closeModal('uploadModal');
        alert('File uploaded successfully!');
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
    const w = 520;
    const h = 520;
    const dualScreenLeft = window.screenLeft ?? window.screenX ?? 0;
    const dualScreenTop = window.screenTop ?? window.screenY ?? 0;
    const width = window.innerWidth || document.documentElement.clientWidth || screen.width;
    const height = window.innerHeight || document.documentElement.clientHeight || screen.height;
    const left = Math.round(dualScreenLeft + (width - w) / 2);
    const top = Math.round(dualScreenTop + (height - h) / 2);
    window.open(url, 'downloadPopup', `width=${w},height=${h},left=${left},top=${top},scrollbars=yes,resizable=yes`);
}

// Dark mode is handled centrally by `assets/js/theme-head.js` + `assets/js/theme-toggle.js`.

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
