document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('downloadModal');
    const card = document.getElementById('modalCard');
    const closeX = document.getElementById('closeX');
    const cancelBtn = document.getElementById('cancelDownload');
    const downloadBtns = document.querySelectorAll('.download-format');

    // entrance animation
    requestAnimationFrame(() => {
        setTimeout(() => {
            card.classList.remove('scale-95', 'opacity-0');
            card.classList.add('scale-100', 'opacity-100');
        }, 20);
    });

    function closeWindow() {
        try { window.close(); } catch (e) { window.history.back(); }
    }

    closeX.addEventListener('click', closeWindow);
    cancelBtn.addEventListener('click', closeWindow);

    // clicking backdrop closes
    modal.addEventListener('click', function(e) {
        if (e.target === modal) closeWindow();
    });

    // Handle format selection
    downloadBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const format = this.getAttribute('data-format');
            btn.disabled = true;
            btn.classList.add('opacity-80');
            downloadDocument(<?php echo json_encode($record); ?>, format);
        });
    });

    async function downloadDocument(record, format) {
        const btns = Array.from(document.querySelectorAll('.download-format'));
        try {
            const formData = new FormData();
            formData.append('id', record.id);
            formData.append('title', record.title);
            formData.append('type', record.type);
            formData.append('month', record.month);
            formData.append('year', record.year);
            formData.append('author', record.author);
            formData.append('format', format);

            btns.forEach(b => b.disabled = true);

            const resp = await fetch(window.location.href, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            });

            if (!resp.ok) throw new Error('Server error: ' + resp.status);

            const blob = await resp.blob();

            let filename = '';
            const cd = resp.headers.get('Content-Disposition') || resp.headers.get('content-disposition');
            if (cd) {
                const match = cd.match(/filename\*=UTF-8''([^;]+)|filename="?([^";]+)"?/i);
                if (match) filename = decodeURIComponent(match[1] || match[2]);
            }

            if (!filename) {
                const extMap = { pdf: 'pdf', docx: 'doc', xml: 'xml' };
                const safeTitle = (record.title || 'document').replace(/[^a-zA-Z0-9\-_\. ]/g, '_');
                const ext = extMap[format] || format || 'bin';
                filename = `${safeTitle}_${record.id}.${ext}`;
            }

            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = filename;
            a.style.display = 'none';
            document.body.appendChild(a);
            a.click();

            setTimeout(() => {
                URL.revokeObjectURL(url);
                if (a.parentNode) a.parentNode.removeChild(a);
                try { window.close(); } catch (e) { /* ignore */ }
            }, 700);

        } catch (err) {
            alert('Download failed: ' + (err.message || err));
            btns.forEach(b => b.disabled = false);
        }
    }
});
