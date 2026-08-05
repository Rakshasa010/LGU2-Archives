// Dark mode is handled centrally by `assets/js/theme-head.js` + `assets/js/theme-toggle.js`.
// This file still listens to `themechange` for UI updates (charts, etc.).

// Modal functionality
const modal = document.getElementById('createFolderModal');

function closeModal() {
    modal.classList.add('hidden');
}

// Initialize modal event listeners
document.addEventListener('DOMContentLoaded', function() {
    // Modal open button
    const openModalBtn = document.getElementById('openCreateFolderModal');
    if (openModalBtn) {
        openModalBtn.addEventListener('click', function() {
            modal.classList.remove('hidden');
        });
    }

    // Create folder button
    const createBtn = document.getElementById('createBtn');
    if (createBtn) {
        createBtn.addEventListener('click', function() {
            const folderName = document.getElementById('folderName').value;
            if (folderName.trim() !== '') {
                UI_ENH.toast('Folder "' + folderName + '" would be created here', {background:'linear-gradient(90deg,#4ade80,#16a34a)'});
                document.getElementById('folderName').value = '';
                closeModal();
            } else {
                UI_ENH.toast('Please enter a folder name', {background:'linear-gradient(90deg,#dc2626,#c53030)'});
            }
        });
    }

    // Search functionality
    const legislativeSearchBtn = document.getElementById('legislativeSearchBtn');
    const legislativeSearchInput = document.getElementById('legislativeSearchInput');
    const clearSearchBtn = document.getElementById('clearSearchBtn');
    const searchTermDisplay = document.getElementById('searchTermDisplay');
    const searchTermText = document.getElementById('searchTermText');

    function escapeHtml(str) {
        return str
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function performSearch() {
        const searchTerm = legislativeSearchInput.value.trim();

        if (searchTerm === '') {
            clearSearch();
            return;
        }

        // Show the term that is being searched
        if (searchTermDisplay && searchTermText) {
            searchTermText.textContent = searchTerm;
            searchTermDisplay.classList.remove('hidden');
        }

        // Show loading state
        const legislativeSearchResults = document.getElementById('legislativeSearchResults');
        const searchResultsList = document.getElementById('searchResultsList');
        const legislativeEmptyState = document.getElementById('legislativeEmptyState');

        searchResultsList.innerHTML = `
            <div class="text-center py-12">
                <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto mb-4"></div>
                <div class="text-gray-600 dark:text-gray-400">Searching...</div>
            </div>
        `;
        legislativeSearchResults.classList.remove('hidden');
        legislativeEmptyState.classList.add('hidden');

        // Fetch search results
        fetch('search_records.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'search=' + encodeURIComponent(searchTerm)
        })
        .then(response => response.json())
        .then(data => {
            displaySearchResults(data.results, searchTerm);
            if (data.related) displaySearchFacets(data.related);
        })
        .catch(error => {
            console.error('Search error:', error);
            searchResultsList.innerHTML = `
                <div class="text-center py-12">
                    <svg class="w-16 h-16 mx-auto mb-4 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <div class="text-xl font-semibold text-gray-800 dark:text-gray-200 mb-2">Search Error</div>
                    <div class="text-gray-600 dark:text-gray-400">Unable to perform search. Please try again.</div>
                </div>
            `;
        });
    }

    function displaySearchFacets(facets) {
        const container = document.getElementById('searchRelated');
        const chips = document.getElementById('searchRelatedChips');
        if (!container || !chips) return;
        if (!facets || facets.length === 0) {
            container.classList.add('hidden');
            chips.innerHTML = '';
            return;
        }
        chips.innerHTML = facets.map(f => {
            return `<button data-query="${escapeHtml(f.query)}" class="px-3 py-1 text-xs rounded-full bg-gray-100 dark:bg-slate-700 text-gray-800 dark:text-gray-200 hover:bg-gray-200 dark:hover:bg-slate-600 transition-colors">${escapeHtml(f.label)}</button>`;
        }).join('');
        container.classList.remove('hidden');
        chips.querySelectorAll('button[data-query]').forEach(btn => {
            btn.addEventListener('click', function(){
                const q = this.getAttribute('data-query');
                if (q) {
                    legislativeSearchInput.value = q;
                    performSearch();
                }
            });
        });
    }

    function displaySearchResults(results, searchTerm) {
        const searchResultsList = document.getElementById('searchResultsList');
        const searchResultsCount = document.getElementById('searchResultsCount');

        // Start with a summary card that always shows what was searched
        let contentHtml = `
            <div class="bg-white dark:bg-slate-800 rounded-lg p-4 border border-gray-200 dark:border-slate-600 shadow-sm mb-3">
                <div class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1">Searched Keyword</div>
                <div class="text-lg font-semibold text-gray-900 dark:text-gray-100 break-words">${escapeHtml(searchTerm)}</div>
            </div>
        `;

        if (results.length === 0) {
            contentHtml += `
                <div class="text-center py-12">
                    <svg class="w-16 h-16 mx-auto mb-4 text-gray-400 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                    <div class="text-xl font-semibold text-gray-800 dark:text-gray-200 mb-2">No results found</div>
                    <div class="text-gray-600 dark:text-gray-400">Try different keywords</div>
                </div>
            `;
            searchResultsCount.textContent = '0 results found';
        } else {
            searchResultsCount.textContent = `${results.length} result${results.length !== 1 ? 's' : ''} found`;

            const resultsCards = results.map(record => {
                let highlightedTitle = record.title;
                if (searchTerm) {
                    const regex = new RegExp(`(${searchTerm})`, 'gi');
                    highlightedTitle = record.title.replace(regex, '<mark class="bg-yellow-200 dark:bg-yellow-900">$1</mark>');
                }

                return `
                    <div class="bg-white dark:bg-slate-800 rounded-lg p-4 hover:shadow-lg transition-all border border-gray-200 dark:border-slate-700 cursor-pointer search-result-item group"
                         data-record-id="${record.id}"
                         data-title="${record.title.replace(/"/g, '&quot;')}"
                         data-type="${record.type.replace(/"/g, '&quot;')}"
                         data-month="${record.month.replace(/"/g, '&quot;')}"
                         data-year="${record.year}"
                         data-author="${record.author.replace(/"/g, '&quot;')}"
                         data-source="${record.source}"
                         data-kind="${record.kind || ''}"
                         data-folder-id="${record.folder_id || ''}"
                         data-file-path="${(record.file_path || '').replace(/"/g, '&quot;')}">
                        <div class="flex items-start justify-between">
                            <div class="flex items-start space-x-4 flex-1">
                                <div class="flex-shrink-0 p-2 bg-gray-100 dark:bg-slate-700 rounded-lg group-hover:bg-red-50 dark:group-hover:bg-red-900/20 transition-colors">
                                    ${getTypeIcon(record.type)}
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="font-bold text-gray-800 dark:text-gray-100 mb-1 group-hover:text-red-600 dark:group-hover:text-red-400 transition-colors truncate pr-4">
                                        ${highlightedTitle}
                                    </div>
                                    <div class="flex flex-wrap items-center text-sm text-gray-600 dark:text-gray-400 gap-y-1">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300 mr-2">
                                            ${record.type}
                                        </span>
                                        <span class="flex items-center mr-3">
                                            <i class="bi bi-calendar3 mr-1.5 text-xs"></i>
                                            ${record.month} ${record.year}
                                        </span>
                                        <span class="flex items-center truncate max-w-[150px]" title="${record.author}">
                                            <i class="bi bi-person mr-1.5 text-xs"></i>
                                            ${record.author}
                                        </span>
                                        ${record.folder_name ? `
                                        <span class="flex items-center">
                                            <i class="bi bi-folder mr-1.5 text-xs"></i>
                                            ${record.folder_name}
                                        </span>
                                        ` : ''}
                                    </div>
                                </div>
                            </div>
                            <div class="flex-shrink-0 self-center ml-4">
                                <button class="p-2 rounded-full text-gray-400 hover:text-red-600 dark:hover:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 transition-all">
                                    <svg class="w-6 h-6 transform group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                `;
            }).join('');

            contentHtml += resultsCards;
        }

        searchResultsList.innerHTML = contentHtml;

        // Add click handlers for search results
        document.querySelectorAll('.search-result-item').forEach(item => {
            item.addEventListener('click', function() {
                const recordId = this.getAttribute('data-record-id');
                const type = this.getAttribute('data-type');
                const source = this.getAttribute('data-source');
                const kind = this.getAttribute('data-kind');
                const folderId = this.getAttribute('data-folder-id');
                const filePath = this.getAttribute('data-file-path');
                
                try {
                    const title = this.getAttribute('data-title') || '';
                    const month = this.getAttribute('data-month') || '';
                    const year = this.getAttribute('data-year') || '';
                    const author = this.getAttribute('data-author') || '';
                    if (window.RecentViews && typeof window.RecentViews.add === 'function') {
                        window.RecentViews.add({ id: recordId, title, type, month, year, author });
                    }
                } catch (_) {}
                
                visitFile(recordId);
                
                // Determine where to redirect
                if (kind === 'folder') {
                    // Clicked on an archive folder: go to folder view
                    window.location.href = `folder_view.php?id=${folderId}`;
                } else if (source === 'archive') {
                    // Archive file: go to folder view with highlight
                    window.location.href = `folder_view.php?id=${folderId}&highlight=${recordId}`;
                } else {
                    // Legislative file: go to folder view for the legislative folder
                    window.location.href = `folder_view.php?id=${folderId}&highlight=${recordId}`;
                }
            });
        });
    }

    function visitFile(recordId) {
        // Update the access timestamp
        fetch('update_access.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'record_id=' + encodeURIComponent(recordId)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Show a brief success message or just log it
                console.log('File access recorded');
                // Optionally show a toast or update UI
            } else {
                console.error('Failed to record file access:', data.message);
            }
        })
        .catch(error => {
            console.error('Error recording file access:', error);
        });
    }

    function clearSearch() {
        legislativeSearchInput.value = '';
        document.getElementById('legislativeSearchResults').classList.add('hidden');
        document.getElementById('legislativeEmptyState').classList.remove('hidden');
        // hide related facets
        const rel = document.getElementById('searchRelated');
        if (rel) rel.classList.add('hidden');

        // Hide the displayed search term
        if (searchTermDisplay && searchTermText) {
            searchTermText.textContent = '';
            searchTermDisplay.classList.add('hidden');
        }
    }

    function getTypeIcon(type) {
        const icons = {
            'Ordinance': '<svg class="w-8 h-8 text-orange-600 dark:text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>',
            'Resolution': '<svg class="w-8 h-8 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>',
            'Public Hearing': '<svg class="w-8 h-8 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z"/></svg>',
            'Meeting': '<svg class="w-8 h-8 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>',
            'Billing': '<svg class="w-8 h-8 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>',
            'Legislative Session': '<svg class="w-8 h-8 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>'
        };
        return icons[type] || '<svg class="w-8 h-8 text-gray-600 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>';
    }

    // Attach event listeners
    if (legislativeSearchBtn) {
        legislativeSearchBtn.addEventListener('click', performSearch);
    }

    if (legislativeSearchInput) {
        let searchTimeout;
        legislativeSearchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(performSearch, 300);
        });

        legislativeSearchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                performSearch();
            }
        });
    }

    if (clearSearchBtn) {
        clearSearchBtn.addEventListener('click', clearSearch);
    }
});

// Enhanced Donut Chart with SVG rendering
function initDonutChart() {
    const donut = document.getElementById('uploadedDonut');
    const tooltip = document.getElementById('donutTooltip');
    const legendContainer = document.getElementById('donutLegend');
    if (!donut || !tooltip || !legendContainer) {
        console.warn('Donut chart elements not found');
        return;
    }

    const segments = JSON.parse(donut.getAttribute('data-segments') || '[]');
    const viewBox = 280;
    const center = viewBox / 2;
    const radius = 100;
    const innerRadius = 70;
    const strokeWidth = radius - innerRadius;

    let currentAngle = -90;
    const segmentPaths = [];
    const segmentData = [];

    segments.forEach((segment, index) => {
        const percent = segment.percent;
        const angle = (percent / 100) * 360;
        const startAngle = currentAngle;
        const endAngle = currentAngle + angle;

        const startRad = (startAngle * Math.PI) / 180;
        const endRad = (endAngle * Math.PI) / 180;

        const x1 = center + radius * Math.cos(startRad);
        const y1 = center + radius * Math.sin(startRad);
        const x2 = center + radius * Math.cos(endRad);
        const y2 = center + radius * Math.sin(endRad);
        const x3 = center + innerRadius * Math.cos(endRad);
        const y3 = center + innerRadius * Math.sin(endRad);
        const x4 = center + innerRadius * Math.cos(startRad);
        const y4 = center + innerRadius * Math.sin(startRad);

        const largeArc = angle > 180 ? 1 : 0;
        const path = `M ${x1} ${y1} A ${radius} ${radius} 0 ${largeArc} 1 ${x2} ${y2} L ${x3} ${y3} A ${innerRadius} ${innerRadius} 0 ${largeArc} 0 ${x4} ${y4} Z`;

        segmentPaths.push({ path, color: segment.color || '#dc2626', name: segment.name, percent: percent, index: index, startAngle: startAngle, endAngle: endAngle });
        segmentData.push({ name: segment.name, percent: percent, color: segment.color || '#dc2626', startAngle: startAngle, endAngle: endAngle });

        currentAngle = endAngle;
    });

    segmentPaths.forEach((seg, index) => {
        const pathElement = document.createElementNS('http://www.w3.org/2000/svg', 'path');
        pathElement.setAttribute('d', seg.path);
        pathElement.setAttribute('fill', seg.color);
        pathElement.setAttribute('class', 'donut-segment cursor-pointer transition-all');
        pathElement.setAttribute('data-index', index);
        pathElement.style.transition = 'all 0.3s cubic-bezier(0.4, 0, 0.2, 1)';
        donut.appendChild(pathElement);
    });

    segmentPaths.forEach((seg, index) => {
        if (seg.percent < 5) return;

        const midAngle = (seg.startAngle + seg.endAngle) / 2;
        const midRad = (midAngle * Math.PI) / 180;
        const labelRadius = (radius + innerRadius) / 2;
        const x = center + labelRadius * Math.cos(midRad);
        const y = center + labelRadius * Math.sin(midRad);

        const textElement = document.createElementNS('http://www.w3.org/2000/svg', 'text');
        textElement.setAttribute('x', x);
        textElement.setAttribute('y', y);
        textElement.setAttribute('text-anchor', 'middle');
        textElement.setAttribute('dominant-baseline', 'middle');
        textElement.setAttribute('fill', document.documentElement.classList.contains('dark') ? '#e5e7eb' : '#1f2937');
        textElement.setAttribute('font-size', '14');
        textElement.setAttribute('font-weight', '600');
        textElement.textContent = seg.percent + '%';
        donut.appendChild(textElement);
    });

    segments.forEach((segment, index) => {
        const legendItem = document.createElement('div');
        legendItem.className = 'flex items-center space-x-3 p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-slate-700 transition-colors';
        legendItem.innerHTML = `
            <div class="w-4 h-4 rounded flex-shrink-0 border border-gray-300 dark:border-gray-600" style="background-color: ${segment.color}; box-shadow: 0 2px 4px rgba(0,0,0,0.1);"></div>
            <div class="flex-1">
                <div class="text-sm font-medium text-gray-800 dark:text-gray-200">${segment.name}</div>
                <div class="text-xs text-gray-600 dark:text-gray-400">${segment.percent}%</div>
            </div>
        `;
        legendContainer.appendChild(legendItem);
    });

    const segmentElements = donut.querySelectorAll('.donut-segment');
    const legendItems = legendContainer.querySelectorAll('div');

    function highlightSegment(index, highlight = true) {
        const segment = segmentElements[index];
        if (highlight) {
            segment.style.transform = 'scale(1.05)';
            segment.style.filter = 'brightness(1.15)';
        } else {
            segment.style.transform = 'scale(1)';
            segment.style.filter = 'brightness(1)';
        }
    }

    function showTooltip(e, segment) {
        tooltip.innerHTML = `
            <div class="font-bold">${segment.name}</div>
            <div class="text-lg">${segment.percent}%</div>
            <div class="text-xs opacity-75">of total documents</div>
        `;
        tooltip.style.left = e.pageX + 'px';
        tooltip.style.top = (e.pageY - 10) + 'px';
        tooltip.classList.remove('opacity-0');
        tooltip.classList.add('opacity-100');
    }

    function hideTooltip() {
        tooltip.classList.add('opacity-0');
        tooltip.classList.remove('opacity-100');
    }

    segmentElements.forEach((segment, index) => {
        segment.addEventListener('mouseenter', function(e) {
            highlightSegment(index, true);
            const segData = segmentData[index];
            showTooltip(e, segData);
        });
        segment.addEventListener('mousemove', function(e) {
            const segData = segmentData[index];
            showTooltip(e, segData);
        });
        segment.addEventListener('mouseleave', function() {
            highlightSegment(index, false);
            hideTooltip();
        });
    });
}

// Initialize donut chart when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initDonutChart);
} else {
    initDonutChart();
}

// Update donut chart text color when theme changes
document.addEventListener('themechange', function() {
    const textElements = document.querySelectorAll('#uploadedDonut text');
    textElements.forEach(text => {
        if (text.getAttribute('font-weight') === '600') {
            text.setAttribute('fill', document.documentElement.classList.contains('dark') ? '#e5e7eb' : '#1f2937');
        }
    });
});

// Burger Menu Toggle (for mobile sidebar)
function initBurgerMenu() {
    const burger = document.getElementById('burgerMenu');
    const sidebar = document.getElementById('sidebar');
    const backdrop = document.getElementById('sidebarBackdrop');
    if (!burger || !sidebar) return;

    burger.addEventListener('click', function() {
        sidebar.classList.toggle('hidden');
        backdrop.classList.toggle('hidden');
    });
}

function closeSidebar() {
    const sidebar = document.getElementById('sidebar');
    const backdrop = document.getElementById('sidebarBackdrop');
    sidebar.classList.add('hidden');
    backdrop.classList.add('hidden');
}

// Settings Menu Toggle
function initSettingsMenu() {
    const settings = document.getElementById('settingsMenu');
    const dropdown = document.getElementById('settingsDropdown');
    if (!settings || !dropdown) return;

    settings.addEventListener('click', function(e) {
        e.stopPropagation();
        dropdown.classList.toggle('hidden');
    });

    // Close dropdown when clicking outside
    document.addEventListener('click', function(e) {
        if (!settings.contains(e.target) && !dropdown.contains(e.target)) {
            dropdown.classList.add('hidden');
        }
    });
}

// Initialize menus when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function() {
        initBurgerMenu();
        initSettingsMenu();
    });
} else {
    initBurgerMenu();
    initSettingsMenu();
}
