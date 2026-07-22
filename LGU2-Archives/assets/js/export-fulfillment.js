/**
 * Export Request Fulfillment Flow
 * Asynchronous AJAX implementation with multi-modal workflow
 */

(function () {
    // ==================== DOM Elements ====================
    const requestItems = document.querySelectorAll('.request-item');
    const requestGrid = document.getElementById('request-grid');
    const requestEmpty = document.getElementById('request-empty');
    const searchInput = document.getElementById('request-search');
    const requestModal = document.getElementById('request-modal');
    const openRequestBtn = document.getElementById('open-request-modal');
    const requestCancel = document.getElementById('request-cancel');
    
    // Detail Modal (Modal #1)
    const detailModal = document.getElementById('detail-modal');
    const detailClose = document.getElementById('detail-close');
    const detailOpenStorageBtn = document.getElementById('detail-open-storage');
    const detailExportBtn = document.getElementById('detail-export-btn');
    const stagedAttachmentContainer = document.getElementById('staged-attachment-container');
    
    // Storage Modal (Modal #2)
    const storageModal = document.getElementById('storage-modal');
    const storageClose = document.getElementById('storage-close');
    const storageCancel = document.getElementById('storage-cancel');
    const storageSearch = document.getElementById('storage-search');
    const storageFilesContainer = document.getElementById('storage-files-container');
    const storageFolders = document.getElementById('storage-folders');

    // ==================== State Management ====================
    let currentDetailRequest = null;
    let currentDetailRequestData = null;
    let currentStagedFile = null;
    let currentFilter = {
        type: 'all',
        status: 'all',
        search: '',
        groupBy: 'daily',
        sortBy: 'date',
        sortDirection: 'desc'
    };

    // ==================== Modal Open/Close Functions ====================
    function openDetailModal(requestId) {
        currentDetailRequest = requestId;
        currentDetailRequestData = null;
        currentStagedFile = null;
        stagedAttachmentContainer.classList.add('hidden');
        detailExportBtn.disabled = true;
        
        detailModal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';

        // Fetch request details via AJAX
        fetchRequestDetails(requestId);
    }

    function closeDetailModal() {
        detailModal.classList.add('hidden');
        document.body.style.overflow = '';
        currentDetailRequest = null;
        currentDetailRequestData = null;
    }

    function openStorageModal() {
        if (!currentDetailRequest) return;
        storageModal.classList.remove('hidden');
        // Load files on open
        loadStorageFiles();
    }

    function closeStorageModal() {
        storageModal.classList.add('hidden');
        storageFilesContainer.innerHTML = '';
        storageSearch.value = '';
    }

    function openRequestModal() {
        if (!requestModal) return;
        requestModal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }

    function closeRequestModal() {
        if (!requestModal) return;
        requestModal.classList.add('hidden');
        document.body.style.overflow = '';
    }

    // ==================== Event Listeners - Modals ====================
    if (detailClose) detailClose.addEventListener('click', closeDetailModal);
    if (storageClose) storageClose.addEventListener('click', closeStorageModal);
    if (storageCancel) storageCancel.addEventListener('click', closeStorageModal);
    if (requestCancel) requestCancel.addEventListener('click', closeRequestModal);
    if (openRequestBtn) openRequestBtn.addEventListener('click', openRequestModal);

    if (detailOpenStorageBtn) {
        detailOpenStorageBtn.addEventListener('click', openStorageModal);
    }

    if (detailExportBtn) {
        detailExportBtn.addEventListener('click', processExport);
    }

    // Close modals on backdrop click
    detailModal.addEventListener('click', (e) => {
        if (e.target === detailModal.querySelector('div:first-child')) {
            closeDetailModal();
        }
    });

    storageModal.addEventListener('click', (e) => {
        if (e.target === storageModal.querySelector('div:first-child')) {
            closeStorageModal();
        }
    });

    // ==================== AJAX API Functions ====================
    function fetchRequestDetails(requestId) {
        fetch(`api/fetch-request-details.php?request_id=${requestId}`)
            .then(response => {
                if (!response.ok) throw new Error('Failed to fetch request details');
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    currentDetailRequestData = data.data;
                    populateDetailModal(data.data);
                } else {
                    showError('Failed to load request details: ' + data.error);
                }
            })
            .catch(error => {
                console.error('Error fetching request details:', error);
                showError('Error loading request details');
            });
    }

    function populateDetailModal(requestData) {
        document.getElementById('detail-title').textContent = requestData.document_title;
        document.getElementById('detail-status').textContent = requestData.status;
        document.getElementById('detail-requester').textContent = requestData.requester_name;
        document.getElementById('detail-department').textContent = requestData.department || 'N/A';
        document.getElementById('detail-version').textContent = requestData.requested_version;
        document.getElementById('detail-needed').textContent = requestData.needed_by_date || 'Not specified';
        document.getElementById('detail-purpose').textContent = requestData.purpose || 'Not provided';
        document.getElementById('detail-note').textContent = requestData.notes || 'No notes';
        document.getElementById('detail-submitted-date').textContent = requestData.date_requested.split(' ')[0];
        document.getElementById('detail-submitted-time').textContent = requestData.date_requested.split(' ')[1] || 'N/A';

        // Update staged attachment status if available
        if (requestData.staged_file_id && requestData.staged_file_name) {
            currentStagedFile = {
                id: requestData.staged_file_id,
                name: requestData.staged_file_name,
                size: requestData.staged_file_size
            };
            document.getElementById('staged-file-name').textContent = requestData.staged_file_name;
            document.getElementById('staged-file-size').textContent = formatFileSize(requestData.staged_file_size);
            stagedAttachmentContainer.classList.remove('hidden');
            detailExportBtn.disabled = false;
            detailExportBtn.classList.remove('opacity-50', 'cursor-not-allowed');
            detailExportBtn.classList.add('bg-emerald-600', 'hover:bg-emerald-700', 'text-white');
        }
    }

    function loadStorageFiles(folderIdParam = null, searchQuery = '') {
        let url = 'api/fetch-storage-files.php?page=1';
        if (folderIdParam) url += '&folder_id=' + folderIdParam;
        if (searchQuery) url += '&search=' + encodeURIComponent(searchQuery);

        storageFilesContainer.innerHTML = '<div class="text-center py-8 text-gray-500 dark:text-gray-400"><i class="bi bi-hourglass-split text-3xl mb-2 block"></i><p class="text-sm">Loading files...</p></div>';

        console.log('[StorageAPI] Fetching: ' + url);

        fetch(url)
            .then(response => {
                console.log('[StorageAPI] Response status:', response.status);
                if (!response.ok) throw new Error('HTTP ' + response.status);
                return response.json();
            })
            .then(data => {
                console.log('[StorageAPI] Response data:', data);
                if (data.success) {
                    renderStorageContent(data.data);
                } else {
                    showError('Failed to load storage: ' + (data.error || 'Unknown error'));
                    console.error('[StorageAPI] Error response:', data);
                    storageFilesContainer.innerHTML = '<div class="text-center py-8 text-red-500"><p class="text-sm">Error: ' + (data.error || 'Failed to load files') + '</p></div>';
                }
            })
            .catch(error => {
                console.error('[StorageAPI] Exception:', error);
                showError('Error loading storage files: ' + error.message);
                storageFilesContainer.innerHTML = '<div class="text-center py-8 text-red-500"><p class="text-sm">Error: ' + error.message + '</p></div>';
            });
    }

    function renderStorageContent(data) {
        // Render folders
        storageFolders.innerHTML = '';
        if (data.folders && data.folders.length > 0) {
            data.folders.forEach(folder => {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'px-3 py-1.5 text-xs font-medium rounded-lg border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-slate-600 transition-colors';
                btn.textContent = folder.name;
                btn.addEventListener('click', () => loadStorageFiles(folder.id));
                storageFolders.appendChild(btn);
            });
        }

        // Render files
        storageFilesContainer.innerHTML = '';
        if (data.files && data.files.length > 0) {
            data.files.forEach(file => {
                const fileRow = createFileRow(file);
                storageFilesContainer.appendChild(fileRow);
            });
        } else {
            storageFilesContainer.innerHTML = '<div class="text-center py-8 text-gray-500 dark:text-gray-400"><p class="text-sm">No files found</p></div>';
        }
    }

    function createFileRow(file) {
        const row = document.createElement('div');
        row.className = 'flex items-center justify-between p-3 bg-gray-50 dark:bg-slate-700/50 rounded-lg hover:bg-gray-100 dark:hover:bg-slate-700 transition-colors group';

        // File icon and name
        const fileInfo = document.createElement('div');
        fileInfo.className = 'flex items-center gap-3 flex-1 min-w-0';
        
        const fileIcon = document.createElement('div');
        fileIcon.className = 'flex-shrink-0';
        fileIcon.innerHTML = getFileIcon(file.file_type);
        
        const nameContainer = document.createElement('div');
        nameContainer.className = 'flex-1 min-w-0';
        nameContainer.innerHTML = `
            <p class="text-sm font-medium text-gray-900 dark:text-gray-100 truncate">${escapeHtml(file.name)}</p>
            <p class="text-xs text-gray-500 dark:text-gray-400">${file.size_formatted}</p>
        `;

        fileInfo.appendChild(fileIcon);
        fileInfo.appendChild(nameContainer);

        // Three-dot menu button
        const menuBtn = document.createElement('button');
        menuBtn.type = 'button';
        menuBtn.className = 'p-2 text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-400 hover:bg-white dark:hover:bg-slate-600 rounded-md transition-colors opacity-0 group-hover:opacity-100';
        menuBtn.innerHTML = '<i class="bi bi-three-dots-vertical"></i>';
        menuBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            showFileContextMenu(file, menuBtn);
        });

        row.appendChild(fileInfo);
        row.appendChild(menuBtn);

        return row;
    }

    function getFileIcon(fileType) {
        let iconClass = 'bi-file';
        let bgClass = 'bg-gray-100 dark:bg-slate-600';
        let textClass = 'text-gray-600 dark:text-gray-400';

        if (fileType === 'pdf' || fileType.includes('pdf')) {
            iconClass = 'bi-file-earmark-pdf-fill';
            bgClass = 'bg-red-100 dark:bg-red-900/30';
            textClass = 'text-red-600 dark:text-red-400';
        } else if (fileType.includes('word') || fileType.includes('doc')) {
            iconClass = 'bi-file-earmark-word-fill';
            bgClass = 'bg-blue-100 dark:bg-blue-900/30';
            textClass = 'text-blue-600 dark:text-blue-400';
        } else if (fileType.includes('sheet') || fileType.includes('xls') || fileType.includes('csv')) {
            iconClass = 'bi-file-earmark-spreadsheet-fill';
            bgClass = 'bg-emerald-100 dark:bg-emerald-900/30';
            textClass = 'text-emerald-600 dark:text-emerald-400';
        }

        return `<div class="w-10 h-10 rounded-lg ${bgClass} flex items-center justify-center"><i class="bi ${iconClass} text-lg ${textClass}"></i></div>`;
    }

    function showFileContextMenu(file, triggerBtn) {
        // Remove existing menu if present
        const existingMenu = document.querySelector('.file-context-menu');
        if (existingMenu) existingMenu.remove();

        const menu = document.createElement('div');
        menu.className = 'file-context-menu absolute bg-white dark:bg-slate-800 rounded-lg shadow-lg border border-gray-200 dark:border-slate-700 z-40 min-w-max';
        
        const menuItem = document.createElement('button');
        menuItem.type = 'button';
        menuItem.className = 'w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-slate-700 transition-colors flex items-center gap-2';
        menuItem.innerHTML = '<i class="bi bi-files text-red-600"></i>Make Copy for Export';
        menuItem.addEventListener('click', () => {
            stageExportCopy(file);
            menu.remove();
        });

        menu.appendChild(menuItem);
        document.body.appendChild(menu);

        // Position menu near trigger button
        const rect = triggerBtn.getBoundingClientRect();
        menu.style.top = (rect.bottom + 5) + 'px';
        menu.style.left = (rect.right - menu.offsetWidth) + 'px';

        // Close menu on click outside
        setTimeout(() => {
            document.addEventListener('click', function closeMenu(e) {
                if (!menu.contains(e.target) && e.target !== triggerBtn) {
                    menu.remove();
                    document.removeEventListener('click', closeMenu);
                }
            });
        }, 0);
    }

    function stageExportCopy(file) {
        if (!currentDetailRequest) {
            showError('No request selected');
            return;
        }

        // Show loading state
        const originalText = 'Make Copy for Export';
        showInfo('Staging file copy...');

        const payload = {
            file_id: file.id,
            request_id: currentDetailRequest
        };

        fetch('api/stage-export-copy.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(payload)
        })
            .then(response => {
                if (!response.ok) throw new Error('Failed to stage file');
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    currentStagedFile = {
                        id: data.data.staged_file_id,
                        name: data.data.file_name,
                        size: data.data.file_size
                    };

                    // Update detail modal with staged file info
                    document.getElementById('staged-file-name').textContent = data.data.file_name;
                    document.getElementById('staged-file-size').textContent = data.data.file_size_formatted;
                    stagedAttachmentContainer.classList.remove('hidden');

                    // Enable export button
                    detailExportBtn.disabled = false;
                    detailExportBtn.classList.remove('opacity-50', 'cursor-not-allowed');
                    detailExportBtn.classList.add('bg-emerald-600', 'hover:bg-emerald-700', 'text-white');

                    // Close storage modal and show detail modal
                    closeStorageModal();
                    
                    showSuccess('File staged successfully!');
                } else {
                    showError('Failed to stage file: ' + data.error);
                }
            })
            .catch(error => {
                console.error('Error staging file:', error);
                showError('Error staging file copy');
            });
    }

    function processExport() {
        if (!currentDetailRequest || !currentStagedFile) {
            showError('Invalid export state');
            return;
        }

        const originalText = detailExportBtn.textContent;
        detailExportBtn.disabled = true;
        detailExportBtn.innerHTML = '<i class="bi bi-hourglass-split animate-spin mr-2"></i>Processing...';

        const payload = {
            request_id: currentDetailRequest
        };

        fetch('api/process-export.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(payload)
        })
            .then(response => {
                if (!response.ok) throw new Error('Failed to process export');
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    showSuccess('Export request fulfilled successfully!');
                    
                    // Update UI state
                    detailExportBtn.innerHTML = '<i class="bi bi-check-circle-fill mr-2"></i>Exported';
                    detailExportBtn.classList.add('bg-emerald-600', 'text-white');
                    
                    // Close modal after short delay
                    setTimeout(() => {
                        closeDetailModal();
                        // Refresh request list
                        location.reload();
                    }, 2000);
                } else {
                    showError('Failed to process export: ' + data.error);
                    detailExportBtn.disabled = false;
                    detailExportBtn.textContent = originalText;
                }
            })
            .catch(error => {
                console.error('Error processing export:', error);
                showError('Error processing export');
                detailExportBtn.disabled = false;
                detailExportBtn.textContent = originalText;
            });
    }

    // ==================== Request Grid Filtering ====================
    requestItems.forEach(item => {
        item.addEventListener('click', (e) => {
            if (!e.target.closest('.item-menu-btn')) {
                const requestId = item.getAttribute('data-id');
                openDetailModal(requestId);
            }
        });
    });

    searchInput.addEventListener('input', (e) => {
        currentFilter.search = e.target.value.toLowerCase();
        applyFiltersAndSort();
    });

    storageSearch.addEventListener('input', (e) => {
        const searchQuery = e.target.value;
        loadStorageFiles(null, searchQuery);
    });

    // Filter buttons
    document.querySelectorAll('.type-filter-option').forEach(option => {
        option.addEventListener('click', () => {
            currentFilter.type = option.getAttribute('data-type');
            document.getElementById('filter-type-menu').classList.add('hidden');
            document.getElementById('filter-type-btn').setAttribute('aria-expanded', 'false');
            applyFiltersAndSort();
        });
    });

    document.querySelectorAll('.status-filter-option').forEach(option => {
        option.addEventListener('click', () => {
            currentFilter.status = option.getAttribute('data-status');
            document.getElementById('filter-status-menu').classList.add('hidden');
            document.getElementById('filter-status-btn').setAttribute('aria-expanded', 'false');
            applyFiltersAndSort();
        });
    });

    document.querySelectorAll('.sort-option').forEach(option => {
        option.addEventListener('click', () => {
            currentFilter.sortBy = option.getAttribute('data-sort');
            document.getElementById('sort-label').textContent = option.textContent;
            document.getElementById('sort-menu').classList.add('hidden');
            document.getElementById('sort-btn').setAttribute('aria-expanded', 'false');
            applyFiltersAndSort();
        });
    });

    document.getElementById('sort-direction-btn')?.addEventListener('click', () => {
        currentFilter.sortDirection = currentFilter.sortDirection === 'asc' ? 'desc' : 'asc';
        applyFiltersAndSort();
    });

    function applyFiltersAndSort() {
        let items = Array.from(requestItems);

        items = items.filter(item => {
            const typeMatch = currentFilter.type === 'all' || item.getAttribute('data-type') === currentFilter.type;
            const statusMatch = currentFilter.status === 'all' || item.getAttribute('data-status') === currentFilter.status;
            const searchMatch = currentFilter.search === '' || item.getAttribute('data-content').toLowerCase().includes(currentFilter.search);
            return typeMatch && statusMatch && searchMatch;
        });

        items.forEach(item => requestGrid.appendChild(item));
        requestItems.forEach(item => {
            item.style.display = items.includes(item) ? '' : 'none';
        });

        if (items.length === 0) {
            requestGrid.classList.add('hidden');
            requestEmpty.classList.remove('hidden');
        } else {
            requestGrid.classList.remove('hidden');
            requestEmpty.classList.add('hidden');
        }
    }

    // ==================== Utility Functions ====================
    function formatFileSize(bytes) {
        if (typeof bytes !== 'number') bytes = parseInt(bytes, 10);
        const units = ['B', 'KB', 'MB', 'GB'];
        const pow = Math.floor(Math.max(0, Math.log(bytes || 0) / Math.log(1024)));
        bytes /= Math.pow(1024, pow);
        return Math.round(bytes * 100) / 100 + ' ' + units[pow];
    }

    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, m => map[m]);
    }

    function showSuccess(message) {
        showNotification(message, 'success');
    }

    function showError(message) {
        showNotification(message, 'error');
    }

    function showInfo(message) {
        showNotification(message, 'info');
    }

    function showNotification(message, type) {
        // Use browser's built-in alert or a toast if available
        console.log(`[${type}] ${message}`);
        try {
            if (typeof UI_ENH !== 'undefined' && UI_ENH.toast) {
                const bgGradient = type === 'success' ? 'linear-gradient(90deg,#4ade80,#10b981)' :
                                   type === 'error' ? 'linear-gradient(90deg,#ef4444,#dc2626)' :
                                   'linear-gradient(90deg,#3b82f6,#1d4ed8)';
                UI_ENH.toast(message, { background: bgGradient });
            }
        } catch (e) {}
    }

    // Initialize on page load
    applyFiltersAndSort();
})();
