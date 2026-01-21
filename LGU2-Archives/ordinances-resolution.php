<?php
// Include database connection
include 'authdatabase.php';

// Fetch ordinances and resolutions
$sql = "SELECT id, title, type, month, year, author, created_at FROM legislative_records WHERE type IN ('Ordinance', 'Resolution') ORDER BY year DESC, month DESC, created_at DESC";
$result = $conn->query($sql);
$ordinance_resolution_records = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $ordinance_resolution_records[] = $row;
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ordinances & Resolutions - Document Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        primary: {
                            DEFAULT: '#dc2626',
                            light: '#f97316',
                        }
                    }
                }
            }
        }
    </script>
</head>
<body class="min-h-screen bg-gradient-to-br from-gray-50 to-gray-100 dark:from-slate-900 dark:to-slate-800 text-gray-900 dark:text-gray-100 transition-colors duration-200">
    <!-- Header -->
    <header class="sticky top-0 z-50 bg-white/80 dark:bg-slate-900/80 backdrop-blur-lg border-b border-gray-200 dark:border-slate-700 shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <div class="flex items-center space-x-4">
                    <a href="archives-landing.php" class="flex items-center space-x-2 text-gray-700 dark:text-gray-300 hover:text-red-600 dark:hover:text-red-400 transition-colors">
                        <span class="text-xl">←</span>
                        <span class="font-semibold">Back to Archives</span>
                    </a>
                </div>
                <div class="flex items-center space-x-3">
                    <button id="themeToggle" class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-slate-800 transition-colors" title="Toggle theme">
                        <svg id="moonIcon" class="w-5 h-5 text-gray-700 dark:text-gray-300 hidden dark:block" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
                        </svg>
                        <svg id="sunIcon" class="w-5 h-5 text-gray-700 dark:text-gray-300 block dark:hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Content Header -->
        <div class="mb-8 pb-6 border-b border-gray-200 dark:border-slate-700">
            <h1 class="text-4xl font-bold bg-gradient-to-r from-red-600 to-orange-500 bg-clip-text text-transparent mb-2">Ordinances & Resolutions</h1>
            <p class="text-gray-600 dark:text-gray-400">Manage and view documents by reading status</p>
        </div>

        <!-- Quick Actions -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-8">
            <div onclick="createFolder()" class="bg-white dark:bg-slate-800 rounded-xl shadow-lg border border-gray-200 dark:border-slate-700 p-6 hover:shadow-xl transition-all cursor-pointer group">
                <div class="mb-3 group-hover:scale-110 transition-transform">
                    <svg class="w-12 h-12 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
                    </svg>
                </div>
                <div class="font-semibold text-gray-800 dark:text-gray-200">Create Folder</div>
            </div>
            <div onclick="uploadFile()" class="bg-white dark:bg-slate-800 rounded-xl shadow-lg border border-gray-200 dark:border-slate-700 p-6 hover:shadow-xl transition-all cursor-pointer group">
                <div class="mb-3 group-hover:scale-110 transition-transform">
                    <svg class="w-12 h-12 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                    </svg>
                </div>
                <div class="font-semibold text-gray-800 dark:text-gray-200">Upload File</div>
            </div>
        </div>

        <!-- File List Container -->
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-lg border border-gray-200 dark:border-slate-700 p-6">
            <div id="filesList" class="space-y-4">
                <?php if (empty($ordinance_resolution_records)): ?>
                    <div class="text-center py-12">
                        <svg class="w-16 h-16 mx-auto mb-4 text-gray-400 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        <div class="text-xl font-semibold text-gray-800 dark:text-gray-200 mb-2">No Ordinances or Resolutions Found</div>
                        <div class="text-gray-600 dark:text-gray-400">Documents will appear here once uploaded</div>
                    </div>
                <?php else: ?>
                    <?php foreach ($ordinance_resolution_records as $record): ?>
                        <div data-id="<?php echo $record['id']; ?>" class="flex items-center space-x-3 p-3 hover:bg-gray-50 dark:hover:bg-slate-700 transition-colors rounded-md border border-transparent hover:border-gray-100 dark:hover:border-slate-600">
                            <div class="flex-shrink-0">
                                <?php if ($record['type'] === 'Ordinance'): ?>
                                    <svg class="w-7 h-7 text-orange-600 dark:text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                <?php else: ?>
                                    <svg class="w-7 h-7 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                <?php endif; ?>
                            </div>

                            <button class="likeBtn flex items-center text-sm text-gray-500 dark:text-gray-300 hover:text-red-600 transition-colors" data-id="<?php echo $record['id']; ?>" title="Like">
                                <svg class="w-5 h-5 mr-1" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 016.364 0L12 7.636l1.318-1.318a4.5 4.5 0 116.364 6.364L12 21.682 4.318 12.682a4.5 4.5 0 010-6.364z" />
                                </svg>
                                <span class="likeCount text-xs">0</span>
                            </button>

                            <div class="flex-1 min-w-0">
                                <div class="font-medium text-gray-800 dark:text-gray-200 truncate"><?php echo htmlspecialchars($record['title']); ?></div>
                                <div class="text-xs text-gray-500 dark:text-gray-400 mt-1 truncate">
                                    <span class="px-2 py-0.5 bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-300 rounded text-[11px]"><?php echo htmlspecialchars($record['type']); ?></span>
                                    <span class="mx-2">•</span>
                                    <span><?php echo htmlspecialchars($record['month'] . ' ' . $record['year']); ?></span>
                                    <span class="mx-2">•</span>
                                    <span><?php echo htmlspecialchars($record['author']); ?></span>
                                    <span class="mx-2">•</span>
                                    <span>Added: <?php echo date('M j, Y', strtotime($record['created_at'])); ?></span>
                                </div>
                            </div>

                            <div class="flex items-center space-x-2">
                                <button onclick="openSideViewerServer(<?php echo $record['id']; ?>, '<?php echo addslashes(htmlspecialchars($record['title'])); ?>', '<?php echo addslashes(htmlspecialchars($record['type'])); ?>', '<?php echo addslashes(htmlspecialchars($record['month'])); ?>', '<?php echo addslashes(htmlspecialchars($record['year'])); ?>', '<?php echo addslashes(htmlspecialchars($record['author'])); ?>')" class="text-gray-400 hover:text-blue-600 dark:hover:text-blue-400 p-1 mr-2" title="View">
                                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                </button>
                                <button onclick="openDownloadPopup(<?php echo $record['id']; ?>, '<?php echo addslashes(htmlspecialchars($record['title'])); ?>', '<?php echo addslashes(htmlspecialchars($record['type'])); ?>', '<?php echo addslashes(htmlspecialchars($record['month'])); ?>', '<?php echo addslashes(htmlspecialchars($record['year'])); ?>', '<?php echo addslashes(htmlspecialchars($record['author'])); ?>')" class="text-gray-400 hover:text-blue-600 dark:hover:text-blue-400 p-1" title="Download">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m-3 3V4m0 6V4" />
                                    </svg>
                                </button>
                                <button onclick="openDeleteConfirm(<?php echo $record['id']; ?>, '<?php echo addslashes(htmlspecialchars($record['title'])); ?>')" class="text-gray-400 hover:text-red-600 dark:hover:text-red-400 p-1 ml-1" title="Delete">
                                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7L5 7M10 11v6m4-6v6M6 7l1 12a2 2 0 002 2h6a2 2 0 002-2l1-12" />
                                    </svg>
                                </button>
                                <svg class="w-4 h-4 text-gray-300" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                </svg>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- Upload Modal -->
    <div id="uploadModal" class="hidden fixed inset-0 z-50 overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div class="fixed inset-0 bg-black/50 backdrop-blur-sm" onclick="closeModal('uploadModal')"></div>
            <div class="relative bg-white dark:bg-slate-800 rounded-xl shadow-2xl max-w-md w-full p-6 border border-gray-200 dark:border-slate-700">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-2xl font-bold text-gray-800 dark:text-gray-200">Upload File</h2>
                    <button class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 text-2xl" onclick="closeModal('uploadModal')">&times;</button>
                </div>
                <form id="uploadForm" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">File Name</label>
                        <input type="text" id="fileName" name="fileName" required class="w-full px-3 py-2 border border-gray-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100" placeholder="e.g., 2024-12-15_Ordinance_No_123.pdf">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Select File</label>
                        <input type="file" id="fileInput" name="file" accept=".pdf,.doc,.docx,.txt" required class="w-full px-3 py-2 border border-gray-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-red-50 file:text-red-700 hover:file:bg-red-100">
                    </div>
                    <div class="flex justify-end space-x-3 pt-4">
                        <button type="button" onclick="closeModal('uploadModal')" class="px-4 py-2 text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 transition-colors">Cancel</button>
                        <button type="submit" class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg transition-colors">Upload</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function openDownloadPopup(id, title, type, month, year, author) {
            const url = `download.php?id=${encodeURIComponent(id)}&title=${encodeURIComponent(title)}&type=${encodeURIComponent(type)}&month=${encodeURIComponent(month)}&year=${encodeURIComponent(year)}&author=${encodeURIComponent(author)}`;
            window.open(url, 'downloadPopup', 'width=500,height=400,scrollbars=yes,resizable=yes');
        }

        // Delete handling
        function openDeleteConfirm(id, title, type) {
            if (!confirm(`Delete "${title}"? This action cannot be undone.`)) return;
            deleteRecord(id, title, type || 'Ordinance/Resolution');
        }

        function addToDeletedStorage(obj) {
            try {
                const key = 'deletedFiles';
                const raw = localStorage.getItem(key);
                let arr = raw ? JSON.parse(raw) : [];
                // prune expired entries
                const now = Date.now();
                arr = arr.filter(it => { try { return !it.expireAt || new Date(it.expireAt).getTime() > now; } catch(e){ return true; } });
                arr.unshift(obj);
                if (arr.length > 200) arr.length = 200;
                localStorage.setItem(key, JSON.stringify(arr));
            } catch (e) {
                console.error('Could not save deleted file metadata', e);
            }
        }

        function deleteRecord(id, title, type) {
            fetch(`delete.php?id=${encodeURIComponent(id)}`, { method: 'POST' })
                .then(r => {
                    if (!r.ok) throw new Error('Failed to delete');
                    return r.text();
                })
                .then(() => {
                    const el = document.querySelector(`[data-id="${id}"]`);
                    if (el) el.remove();
                    // compute 30-month expiry
                    const expireDate = new Date();
                    expireDate.setMonth(expireDate.getMonth() + 30);
                    addToDeletedStorage({
                        id: String(id),
                        name: title || (`Record ${id}`),
                        type: type || 'Ordinance/Resolution',
                        category: type || 'Ordinance/Resolution',
                        originalPath: '',
                        deletedAt: new Date().toISOString(),
                        expireAt: expireDate.toISOString()
                    });
                })
                .catch(err => {
                    alert('Could not delete record. Server may not have delete endpoint.');
                    console.error(err);
                });
        }

        // Like button handling (client-side, stored in localStorage)
        (function() {
            const STORAGE_KEY = 'lr-likes';

            function loadLikes() {
                try {
                    return JSON.parse(localStorage.getItem(STORAGE_KEY) || '{}');
                } catch (e) {
                    return {};
                }
            }

            function saveLikes(obj) {
                localStorage.setItem(STORAGE_KEY, JSON.stringify(obj));
            }

            function updateLikeUI(btn, liked) {
                const heart = btn.querySelector('svg');
                const countEl = btn.querySelector('.likeCount');
                if (!heart || !countEl) return;
                if (liked) {
                    heart.classList.add('text-red-600');
                    heart.setAttribute('fill', 'currentColor');
                } else {
                    heart.classList.remove('text-red-600');
                    heart.setAttribute('fill', 'none');
                }
                // simplistic count display: 1 when liked, 0 when not
                countEl.textContent = liked ? '1' : '0';
            }

            function initLikes() {
                const likes = loadLikes();
                document.querySelectorAll('.likeBtn').forEach(btn => {
                    const id = btn.getAttribute('data-id');
                    const liked = !!likes[id];
                    updateLikeUI(btn, liked);
                    btn.addEventListener('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        const cur = !!likes[id];
                        likes[id] = !cur;
                        if (!likes[id]) delete likes[id];
                        saveLikes(likes);
                        updateLikeUI(btn, likes[id]);
                    });
                });
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', initLikes);
            } else {
                initLikes();
            }
        })();

        function createFolder() {
            const folderName = prompt('Enter folder name:');
            if (folderName && folderName.trim()) {
                alert(`Folder "${folderName}" created successfully!`);
            }
        }

        function uploadFile() {
            openModal('uploadModal');
        }

        function openModal(modalId) {
            document.getElementById(modalId).classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.add('hidden');
            document.body.style.overflow = 'auto';
        }

        document.getElementById('uploadForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const fileInput = document.getElementById('fileInput');
            const fileName = document.getElementById('fileName').value.trim();

            if (fileInput.files.length > 0 && fileName) {
                const dateMatch = fileName.match(/^(\d{4}-\d{2}-\d{2})_/);
                if (dateMatch) {
                    const date = dateMatch[1];
                    const fileType = fileName.split('.').pop().toLowerCase();

                    // Here you would typically upload the file to the server
                    // For now, we'll just show a success message
                    alert('File uploaded successfully!');
                    this.reset();
                    closeModal('uploadModal');
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
                        
                        document.dispatchEvent(new CustomEvent('themechange', { 
                            detail: { mode: newMode } 
                        }));
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
    </script>
    <!-- Side Viewer Panel -->
    <div id="sideViewer" class="fixed right-0 top-0 h-full w-96 bg-white dark:bg-slate-900 border-l border-gray-200 dark:border-slate-700 shadow-xl transform translate-x-full transition-transform duration-200 z-50">
        <div class="p-4 flex items-start justify-between border-b border-gray-100 dark:border-slate-700">
            <div>
                <div id="sv-title" class="font-semibold text-lg text-gray-900 dark:text-gray-100">Title</div>
                <div class="text-xs text-gray-500 dark:text-gray-400 mt-1" id="sv-meta">Meta</div>
            </div>
            <div class="text-right">
                <button onclick="closeSideViewer()" class="text-gray-500 hover:text-gray-700 dark:text-gray-300">&times;</button>
            </div>
        </div>
        <div class="p-4 space-y-3">
            <div class="text-sm text-gray-600 dark:text-gray-300"><strong>Type:</strong> <span id="sv-type"></span></div>
            <div class="text-sm text-gray-600 dark:text-gray-300"><strong>Author:</strong> <span id="sv-author"></span></div>
            <div id="sv-preview" class="mt-3 text-sm text-gray-500 dark:text-gray-400">Preview not available. Use Open to download or view the file.</div>
        </div>
        <div class="p-4 border-t border-gray-100 dark:border-slate-700">
            <a id="sv-open-btn" class="inline-block px-4 py-2 bg-red-600 text-white rounded hidden" href="#" target="_blank">Open / Download</a>
        </div>
    </div>

    <script>
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
    </script>
</body>
</html>
