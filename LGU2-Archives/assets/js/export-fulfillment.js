/**
 * Export Request Fulfillment Flow
 * Asynchronous AJAX implementation with multi-modal workflow
 */

// IMMEDIATELY VERIFY FILE LOADED
console.log('[EXPORT-FULFILLMENT] ✅ Script loaded successfully at:', new Date().toISOString());
console.log('[EXPORT-FULFILLMENT] Version: CACHE_BUST_v' + Math.random());

// GLOBAL FILE COPY HANDLER (Outside IIFE for accessibility)
window.copyStorageFile = function(fileIdStr, fileNameStr, fileSizeStr, filePathStr) {
    console.log('[GLOBAL] Copy triggered:', fileIdStr, fileNameStr);
    
    // Find the file object or reconstruct it
    const fileObj = {
        id: fileIdStr,
        name: fileNameStr,
        size_formatted: fileSizeStr || 'Unknown size',
        path: filePathStr || ''
    };
    
    // Call the staging function
    if (window.exportFulfillment && window.exportFulfillment.stageFile) {
        window.exportFulfillment.stageFile(fileObj);
    } else {
        console.error('[GLOBAL] Export fulfillment object not found!');
        alert('Error: Export system not ready. Please refresh the page.');
    }
};

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
                console.log('[StorageAPI] Files count:', data.data?.files?.length || 0);
                if (data.success) {
                    renderStorageContent(data.data);
                    
                    // Verify buttons are clickable after render
                    setTimeout(() => {
                        const buttons = document.querySelectorAll('#storage-files-container button[data-file-id]');
                        console.log('[StorageAPI] Total buttons rendered:', buttons.length);
                        buttons.forEach((btn, idx) => {
                            console.log(`[StorageAPI] Button ${idx + 1}:`, {
                                fileId: btn.getAttribute('data-file-id'),
                                fileName: btn.getAttribute('data-file-name'),
                                clickable: btn.style.pointerEvents,
                                visible: btn.offsetHeight > 0
                            });
                        });
                    }, 100);
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
                
                // Add color coding based on folder type
                let bgClass = 'bg-white dark:bg-slate-700';
                let hoverClass = 'hover:bg-gray-100 dark:hover:bg-slate-600';
                
                if (folder.color) {
                    if (folder.color === 'orange') {
                        bgClass = 'bg-orange-50 dark:bg-orange-900/20 border-orange-300 dark:border-orange-700';
                        hoverClass = 'hover:bg-orange-100 dark:hover:bg-orange-900/30';
                    } else if (folder.color === 'blue') {
                        bgClass = 'bg-blue-50 dark:bg-blue-900/20 border-blue-300 dark:border-blue-700';
                        hoverClass = 'hover:bg-blue-100 dark:hover:bg-blue-900/30';
                    } else if (folder.color === 'indigo') {
                        bgClass = 'bg-indigo-50 dark:bg-indigo-900/20 border-indigo-300 dark:border-indigo-700';
                        hoverClass = 'hover:bg-indigo-100 dark:hover:bg-indigo-900/30';
                    }
                }
                
                btn.className = `px-3 py-1.5 text-xs font-medium rounded-lg border border-gray-300 dark:border-slate-600 ${bgClass} text-gray-700 dark:text-gray-200 ${hoverClass} transition-colors flex items-center gap-2`;
                btn.innerHTML = `<i class="bi ${folder.icon || 'bi-folder-fill'}"></i>${escapeHtml(folder.name)}`;
                
                // Store folder ID and type as data attributes
                btn.setAttribute('data-folder-id', folder.id);
                btn.setAttribute('data-folder-type', folder.folder_type || 'archive');
                
                btn.addEventListener('click', () => {
                    console.log('[StorageAPI] Opening folder:', folder.name, folder.id);
                    loadStorageFiles(folder.id);
                });
                
                storageFolders.appendChild(btn);
            });
        }

        // Render files in a grid layout
        storageFilesContainer.innerHTML = '';
        if (data.files && data.files.length > 0) {
            // Create grid container
            const gridContainer = document.createElement('div');
            gridContainer.className = 'grid grid-cols-1 md:grid-cols-2 gap-4';
            
            data.files.forEach(file => {
                const fileCard = createFileRow(file);
                gridContainer.appendChild(fileCard);
            });
            
            storageFilesContainer.appendChild(gridContainer);
        } else {
            storageFilesContainer.innerHTML = '<div class="text-center py-12 text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-slate-900/50 rounded-lg border-2 border-dashed border-gray-300 dark:border-slate-700"><i class="bi bi-inbox text-4xl mb-3 block"></i><p class="text-sm font-medium">No files found</p><p class="text-xs mt-1">Try selecting a different folder or adjusting your search</p></div>';
        }
    }

    function createFileRow(file) {
        console.log('[FileCard] Creating card for:', file.name, file.id);
        
        const row = document.createElement('div');
        row.className = 'file-card-item bg-white dark:bg-slate-800 rounded-lg border-2 border-gray-200 dark:border-slate-700 hover:border-red-400 dark:hover:border-red-600 hover:shadow-lg p-4';
        row.style.cssText = 'position: relative; transition: all 0.2s;';
        
        // Build the HTML content directly
        const fileTypeIcon = getFileIcon(file.file_type);
        
        row.innerHTML = `
            <div class="file-info" style="pointer-events: none; margin-bottom: 12px;">
                <div style="display: flex; align-items: start; gap: 12px;">
                    <div style="flex-shrink: 0;">
                        ${fileTypeIcon}
                    </div>
                    <div style="flex: 1; min-width: 0;">
                        <p style="font-size: 14px; font-weight: 600; color: #111827; margin-bottom: 4px;" class="dark:text-gray-100">${escapeHtml(file.name)}</p>
                        <p style="font-size: 12px; color: #6b7280;" class="dark:text-gray-400">${file.size_formatted}</p>
                        <p style="font-size: 11px; color: #9ca3af; margin-top: 4px;" class="dark:text-gray-500">${file.uploaded_at || 'Unknown date'}</p>
                    </div>
                </div>
            </div>
            <button 
                type="button" 
                class="copy-file-btn-action"
                data-file-id="${escapeHtml(file.id)}"
                data-file-name="${escapeHtml(file.name)}"
                data-file-size="${escapeHtml(file.size_formatted)}"
                data-file-path="${escapeHtml(file.path || '')}"
                style="
                    position: relative;
                    width: 100%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    gap: 8px;
                    padding: 10px 16px;
                    background-color: #dc2626;
                    color: white;
                    border: none;
                    border-radius: 8px;
                    font-weight: 600;
                    font-size: 14px;
                    cursor: pointer;
                    z-index: 100;
                    pointer-events: auto;
                    transition: background-color 0.2s;
                "
                onmouseover="this.style.backgroundColor='#b91c1c'"
                onmouseout="this.style.backgroundColor='#dc2626'"
                onmousedown="this.style.backgroundColor='#991b1b'"
                onmouseup="this.style.backgroundColor='#b91c1c'">
                <i class="bi bi-files"></i>
                <span>Make a Copy</span>
            </button>
        `;
        
        // Attach event listener AFTER adding to DOM
        setTimeout(() => {
            const btn = row.querySelector('.copy-file-btn-action');
            if (btn) {
                // Store file object reference
                btn.__fileData = file;
                
                btn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    e.preventDefault();
                    
                    console.log('[FileCopy] ========================================');
                    console.log('[FileCopy] ✅ Button clicked!');
                    console.log('[FileCopy] File name:', this.__fileData.name);
                    console.log('[FileCopy] File ID:', this.__fileData.id);
                    console.log('[FileCopy] File data:', this.__fileData);
                    console.log('[FileCopy] ========================================');
                    
                    // Call staging function
                    if (window.exportFulfillment && window.exportFulfillment.stageFile) {
                        console.log('[FileCopy] Calling window.exportFulfillment.stageFile...');
                        window.exportFulfillment.stageFile(this.__fileData);
                    } else {
                        console.log('[FileCopy] Calling stageExportCopy directly...');
                        stageExportCopy(this.__fileData);
                    }
                    return false;
                });
                
                console.log('[FileCard] Event listener attached to button for:', file.name);
            } else {
                console.error('[FileCard] ❌ Button not found in row!');
            }
        }, 0);
        
        console.log('[FileCard] Card created');
        return row;
    }
    
    // Expose staging function to global scope
    window.exportFulfillment = window.exportFulfillment || {};
    window.exportFulfillment.stageFile = function(file) {
        console.log('[ExportFulfillment] ========================================');
        console.log('[ExportFulfillment] Global staging function called!');
        console.log('[ExportFulfillment] File object:', file);
        console.log('[ExportFulfillment] File name:', file.name);
        console.log('[ExportFulfillment] File ID:', file.id);
        console.log('[ExportFulfillment] About to call stageExportCopy...');
        console.log('[ExportFulfillment] ========================================');
        stageExportCopy(file);
    };
    
    console.log('[EXPORT-FULFILLMENT] ✅ window.exportFulfillment.stageFile exposed successfully');
    console.log('[EXPORT-FULFILLMENT] Test it with: window.exportFulfillment.stageFile({id:"test", name:"test.pdf"})');

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

    function stageExportCopy(file) {
        console.log('[StageExport] Starting staging for:', file);
        console.log('[StageExport] Current request ID:', currentDetailRequest);
        
        if (!currentDetailRequest) {
            console.error('[StageExport] ❌ No request selected!');
            showError('No request selected');
            return;
        }

        console.log('[StageExport] Showing loading toast...');
        showInfo('Staging file copy...');

        const payload = {
            file_id: file.id,
            request_id: currentDetailRequest
        };
        
        console.log('[StageExport] Payload:', payload);
        console.log('[StageExport] Calling API: api/stage-export-copy.php');

        fetch('api/stage-export-copy.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(payload)
        })
            .then(response => {
                console.log('[StageExport] API Response status:', response.status);
                if (!response.ok) throw new Error('Failed to stage file - HTTP ' + response.status);
                return response.json();
            })
            .then(data => {
                console.log('[StageExport] API Response data:', data);
                
                if (data.success) {
                    console.log('[StageExport] ✅ Success! File staged:', data.data.file_name);
                    
                    currentStagedFile = {
                        id: data.data.staged_file_id,
                        name: data.data.file_name,
                        size: data.data.file_size
                    };

                    // Update detail modal with staged file info
                    console.log('[StageExport] Updating detail modal...');
                    document.getElementById('staged-file-name').textContent = data.data.file_name;
                    document.getElementById('staged-file-size').textContent = data.data.file_size_formatted;
                    stagedAttachmentContainer.classList.remove('hidden');

                    // Enable export button
                    console.log('[StageExport] Enabling export button...');
                    detailExportBtn.disabled = false;
                    detailExportBtn.classList.remove('opacity-50', 'cursor-not-allowed', 'bg-white', 'dark:bg-slate-700');
                    detailExportBtn.classList.add('bg-emerald-600', 'hover:bg-emerald-700', 'text-white', 'font-semibold');
                    detailExportBtn.innerHTML = '<i class="bi bi-box-arrow-up mr-2"></i>Export Package';

                    // Close storage modal and show detail modal
                    console.log('[StageExport] Closing storage modal...');
                    closeStorageModal();
                    
                    console.log('[StageExport] Showing success message...');
                    showSuccess('File staged successfully! You can now export.');
                } else {
                    console.error('[StageExport] ❌ API returned error:', data.error);
                    showError('Failed to stage file: ' + data.error);
                }
            })
            .catch(error => {
                console.error('[StageExport] ❌ Exception:', error);
                console.error('[StageExport] Error details:', error.message);
                showError('Error staging file copy: ' + error.message);
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
        console.log('[StorageSearch] Searching for:', searchQuery);
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
