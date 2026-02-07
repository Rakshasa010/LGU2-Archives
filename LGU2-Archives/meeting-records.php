<?php
// Include database connection
include 'authdatabase.php';

// Fetch meeting records
$sql = "SELECT id, title, type, month, year, author, created_at FROM legislative_records WHERE type = 'Meeting' ORDER BY year DESC, month DESC, created_at DESC";
$result = $conn->query($sql);
$meeting_records = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $meeting_records[] = $row;
    }
}
$conn->close();
?>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meeting/Sessions Records - Document Management</title>
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
    <script src="assets/js/theme-head.js"></script>
    <script src="assets/js/deleted-files.js"></script>
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
            <h1 class="text-4xl font-bold bg-gradient-to-r from-red-600 to-orange-500 bg-clip-text text-transparent mb-2">Meeting/Sessions Records</h1>
            <p class="text-gray-600 dark:text-gray-400">Manage and view meeting and session documents</p>
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
                <?php if (empty($meeting_records)): ?>
                    <div class="text-center py-12">
                        <svg class="w-16 h-16 mx-auto mb-4 text-gray-400 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                        <div class="text-xl font-semibold text-gray-800 dark:text-gray-200 mb-2">No Meeting Records Found</div>
                        <div class="text-gray-600 dark:text-gray-400">Meeting documents will appear here once uploaded</div>
                    </div>
                <?php else: ?>
                    <?php foreach ($meeting_records as $record): ?>
                        <div class="bg-gray-50 dark:bg-slate-700 rounded-lg p-4 hover:shadow-md transition-shadow border border-gray-200 dark:border-slate-600">
                            <div class="flex items-start space-x-4">
                                <div class="flex-shrink-0">
                                    <svg class="w-8 h-8 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                                    </svg>
                                </div>
                                <div class="flex-1">
                                    <div class="font-semibold text-gray-800 dark:text-gray-200 mb-1"><?php echo htmlspecialchars($record['title']); ?></div>
                                    <div class="text-sm text-gray-600 dark:text-gray-400">
                                        <span class="px-2 py-1 bg-indigo-100 dark:bg-indigo-900/30 text-indigo-800 dark:text-indigo-300 rounded"><?php echo htmlspecialchars($record['type']); ?></span>
                                        <span class="mx-2">•</span>
                                        <span><?php echo htmlspecialchars($record['month'] . ' ' . $record['year']); ?></span>
                                        <span class="mx-2">•</span>
                                        <span><?php echo htmlspecialchars($record['author']); ?></span>
                                    </div>
                                    <div class="text-xs text-gray-500 dark:text-gray-500 mt-1">
                                        Added: <?php echo date('M j, Y', strtotime($record['created_at'])); ?>
                                    </div>
                                </div>
                                <div class="flex-shrink-0">
                                    <button onclick="openDownloadPopup(<?php echo $record['id']; ?>, '<?php echo addslashes(htmlspecialchars($record['title'])); ?>', '<?php echo addslashes(htmlspecialchars($record['type'])); ?>', '<?php echo addslashes(htmlspecialchars($record['month'])); ?>', '<?php echo addslashes(htmlspecialchars($record['year'])); ?>', '<?php echo addslashes(htmlspecialchars($record['author'])); ?>')" class="text-gray-400 hover:text-blue-600 dark:hover:text-blue-400 p-1" title="Download">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m-3 3V4m0 6V4" />
                                        </svg>
                                    </button>
                                </div>
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
                        <label for="fileInput" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Select File:</label>
                        <input type="file" id="fileInput" accept=".pdf,.doc,.docx,.txt" required
                               class="w-full px-4 py-2 border border-gray-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-red-500 focus:border-transparent">
                    </div>
                    <div>
                        <label for="fileName" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">File Name (YYYY-MM-DD_FileName.ext):</label>
                        <input type="text" id="fileName" placeholder="e.g., 2024-01-15_Meeting_Minutes.pdf" required
                               class="w-full px-4 py-2 border border-gray-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-red-500 focus:border-transparent">
                    </div>
                    <div class="flex justify-end space-x-3 pt-4">
                        <button type="button" onclick="closeModal('uploadModal')" class="px-4 py-2 text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-slate-700 rounded-lg hover:bg-gray-200 dark:hover:bg-slate-600 transition-colors">Cancel</button>
                        <button type="submit" class="px-4 py-2 bg-gradient-to-r from-red-600 to-orange-500 text-white rounded-lg font-semibold hover:from-red-700 hover:to-orange-600 transition-all">Upload</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Sample files data
        let files = [
            { name: "2024-12-15_Regular_Session_Minutes.pdf", date: "2024-12-15", type: "pdf" },
            { name: "2024-11-20_Special_Meeting_Agenda.pdf", date: "2024-11-20", type: "pdf" },
            { name: "2024-10-25_Budget_Review_Session.pdf", date: "2024-10-25", type: "pdf" },
            { name: "2024-09-10_Emergency_Response_Planning.pdf", date: "2024-09-10", type: "pdf" }
        ];

        function sortFilesByDate() {
            files.sort((a, b) => new Date(a.date) - new Date(b.date));
        }

        function renderFiles() {
            sortFilesByDate();
            const filesList = document.getElementById('filesList');
            filesList.innerHTML = files.map((file, index) => `
                <div class="flex items-center justify-between p-4 bg-gray-50 dark:bg-slate-700/50 rounded-lg border border-gray-200 dark:border-slate-600 hover:shadow-md transition-all">
                    <div class="flex items-center space-x-4 flex-1">
                        <svg class="w-10 h-10 text-red-600 dark:text-red-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        <div class="flex-1">
                            <div class="font-semibold text-gray-800 dark:text-gray-200 mb-1">${file.name}</div>
                            <div class="text-sm text-gray-600 dark:text-gray-400">Uploaded: ${formatDate(file.date)}</div>
                        </div>
                    </div>
                    <div class="flex items-center space-x-2">
                        <button onclick="previewFile('${file.name}')" class="px-4 py-2 text-sm font-semibold text-gray-700 dark:text-gray-300 bg-white dark:bg-slate-700 border border-gray-300 dark:border-slate-600 rounded-lg hover:bg-gray-50 dark:hover:bg-slate-600 transition-colors flex items-center space-x-1">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                            <span>View</span>
                        </button>
                        <button onclick="downloadFile('${file.name}')" class="px-4 py-2 text-sm font-semibold text-gray-700 dark:text-gray-300 bg-white dark:bg-slate-700 border border-gray-300 dark:border-slate-600 rounded-lg hover:bg-gray-50 dark:hover:bg-slate-600 transition-colors flex items-center space-x-1">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg>
                            <span>Download</span>
                        </button>
                        <button onclick="deleteFile(${index}, '${file.name}')" class="px-3 py-2 bg-gradient-to-br from-red-50 to-orange-50 dark:from-red-900/20 dark:to-orange-900/20 border border-red-200 dark:border-red-800 rounded-lg hover:from-red-100 hover:to-orange-100 dark:hover:from-red-900/30 dark:hover:to-orange-900/30 transition-all" title="Delete file">
                            <svg class="w-5 h-5 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                        </button>
                    </div>
                </div>
            `).join('');
        }

        function formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
        }

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

        document.getElementById('uploadForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const fileInput = document.getElementById('fileInput');
            const fileName = document.getElementById('fileName').value.trim();

            if (fileInput.files.length > 0 && fileName) {
                const dateMatch = fileName.match(/^(\d{4}-\d{2}-\d{2})_/);
                if (dateMatch) {
                    const date = dateMatch[1];
                    const fileType = fileName.split('.').pop().toLowerCase();

                    files.push({
                        name: fileName,
                        date: date,
                        type: fileType
                    });

                    renderFiles();
                    this.reset();
                    closeModal('uploadModal');
                    alert('File uploaded successfully!');
                } else {
                    alert('File name must start with YYYY-MM-DD_ format');
                }
            }
        });

        function previewFile(fileName) {
            const match = fileName.match(/^([\d]{4}-[\d]{2}-[\d]{2})_(.+)\.(\w+)$/);
            let title = fileName;
            let month = '';
            let year = '';
            let author = '';
            let fileType = '';
            let contentType = '';
            if (match) {
                const dateStr = match[1];
                title = match[2].replace(/_/g, ' ');
                fileType = match[3].toLowerCase();
                const d = new Date(dateStr);
                if (!isNaN(d)) {
                    month = d.toLocaleString('en-US', { month: 'long' });
                    year = d.getFullYear();
                }
            }
            if (fileType) {
                const ct = { pdf: 'application/pdf', doc: 'application/msword', docx: 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', pptx: 'application/vnd.openxmlformats-officedocument.presentationml.presentation', xlsx: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', txt: 'text/plain' };
                contentType = ct[fileType] || '';
            }
            const url = `download.php?id=0&title=${encodeURIComponent(title)}&type=${encodeURIComponent(fileType)}&month=${encodeURIComponent(month)}&year=${encodeURIComponent(year)}&author=${encodeURIComponent(author)}`;
            openSideViewer({ title, type: fileType, month, year, author, fileType, contentType, downloadUrl: url });
        }

        function downloadFile(fileName) {
            // Parse filename like YYYY-MM-DD_Title.ext to extract metadata
            const match = fileName.match(/^(\d{4}-\d{2}-\d{2})_(.+)\.(\w+)$/);
            let id = 0;
            let title = fileName;
            let type = '';
            let month = '';
            let year = '';
            let author = '';

            if (match) {
                const dateStr = match[1];
                title = match[2].replace(/_/g, ' ');
                type = match[3];
                const d = new Date(dateStr);
                if (!isNaN(d)) {
                    month = d.toLocaleString('en-US', { month: 'long' });
                    year = d.getFullYear();
                }
            }

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

        function deleteFile(index, fileName) {
            if (!confirm(`Are you sure you want to delete "${fileName}"?\n\nThis file will be moved to Recently Deleted for 30 days.`)) {
                return;
            }

            const file = files[index];
            const fileType = file.type.toUpperCase();

            window.DeletedFiles?.add({
                id: `meeting_${Date.now()}_${index}`,
                name: fileName,
                type: fileType,
                category: 'Meeting/Sessions Records',
                originalPath: 'Meeting/Sessions Records'
            });

            files.splice(index, 1);
            renderFiles();

            alert(`"${fileName}" has been moved to Recently Deleted.\n\nYou can restore it from the Recently Deleted page within 30 days.`);
        }

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
            renderFiles();
        });

        // Side viewer functions for previewing metadata / opening download
        function openSideViewer(data) {
            const panel = document.getElementById('sideViewer');
            if (!panel) return;
            document.getElementById('sv-title').textContent = data.title || 'Untitled';
            document.getElementById('sv-type').textContent = data.type || '';
            document.getElementById('sv-meta').textContent = `${data.month || ''} ${data.year || ''}`.trim();
            document.getElementById('sv-author').textContent = data.author || '';
            document.getElementById('sv-d-title').textContent = data.title || '';
            document.getElementById('sv-d-authors').textContent = data.author || '';
            document.getElementById('sv-d-size').textContent = data.size ? data.size : 'Unknown';
            document.getElementById('sv-d-modified').textContent = data.createdAt ? new Date(data.createdAt).toLocaleString() : 'Unknown';
            document.getElementById('sv-d-ctype').textContent = data.contentType || 'Unknown';
            document.getElementById('sv-d-saved').textContent = data.lastSaved ? new Date(data.lastSaved).toLocaleString() : 'Unknown';
            document.getElementById('sv-d-ftype').textContent = data.fileType || 'Unknown';
            const openBtn = document.getElementById('sv-open-btn');
            const preview = document.getElementById('sv-preview');

            // Determine PDF URL: prefer rawUrl if provided, otherwise downloadUrl
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
            <div class="mt-2">
                <div class="text-sm font-semibold text-gray-800 dark:text-gray-200 mb-2">Details</div>
                <div class="space-y-1 text-sm text-gray-700 dark:text-gray-300">
                    <div class="flex justify-between"><span>Title</span><span id="sv-d-title"></span></div>
                    <div class="flex justify-between"><span>Authors</span><span id="sv-d-authors"></span></div>
                    <div class="flex justify-between"><span>Size</span><span id="sv-d-size"></span></div>
                    <div class="flex justify-between"><span>Date modified</span><span id="sv-d-modified"></span></div>
                    <div class="flex justify-between"><span>Content type</span><span id="sv-d-ctype"></span></div>
                    <div class="flex justify-between"><span>Date last saved</span><span id="sv-d-saved"></span></div>
                    <div class="flex justify-between"><span>File type</span><span id="sv-d-ftype"></span></div>
                </div>
            </div>
            <div id="sv-preview" class="mt-3 text-sm text-gray-500 dark:text-gray-400">Preview not available. Use Open to download or view the file.</div>
        </div>
        <div class="p-4 border-t border-gray-100 dark:border-slate-700">
            <a id="sv-open-btn" class="inline-block px-4 py-2 bg-gradient-to-r from-red-600 to-orange-500 text-white rounded hidden" href="#" target="_blank">Open / Download</a>
        </div>
    </div>
    <script src="assets/js/theme-toggle.js"></script>
</body>
</html>
