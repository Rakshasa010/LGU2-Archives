        // Quick Reports & Analytics Charts (uses exact same pattern as report_analytics.php lines 1195-1217)
        // #region debug-point C:quick-reports-init
        const byType = {"Meeting":5,"Ordinance":14,"Public Hearing":5,"ordinances":4,"anything":2,"wild thoughts":1,"67":1};
        const seriesLabels = ["2026-07-16","2026-07-17","2026-07-18","2026-07-19","2026-07-20","2026-07-21","2026-07-22","2026-07-23","2026-07-24","2026-07-25","2026-07-26","2026-07-27","2026-07-28","2026-07-29"];
        const seriesDownloads = [0,0,0,0,0,0,0,0,0,0,0,0,0,0];
        const seriesUploads = [0,0,0,0,0,0,0,0,0,0,0,0,0,0];
        const seriesRecords = [0,0,0,0,0,0,0,0,0,0,0,0,0,0];
        const seriesRecordsMerged = [0,0,0,0,0,0,0,0,0,0,0,0,0,0];
        const fuLabels = ["67","anything","ordinances","wild thoughts"];
        const fuLast7 = [0,0,0,0];
        const fuPrev7 = [0,0,0,0];
        const fuEarlier = [0,0,0,0];
        const catLabels = [];
        const catLast7 = [];
        const catPrev7 = [];
        const catEarlier = [];
        const ffLabels = [];
        const ffValues = [];
        const dupLegLabels = ["ordinance,3722","ordinance,3719","Joel Tagay"];
        const dupLegCounts = [7,5,2];
        const dupFileLabels = [];
        const dupFileCounts = [];

        const labelsAndData = (obj) => { const labels = Object.keys(obj); const data = Object.values(obj); return { labels, data }; };
        const hideSk = (id) => { const el = document.getElementById(id); if (el) el.classList.add('hidden'); };
        const rbt = labelsAndData(byType);
        // Mini sparkline: Records
        const qaRecordsMiniCtx = document.getElementById('qaRecordsMini')?.getContext('2d');
        if (qaRecordsMiniCtx) {
            try {
                new Chart(qaRecordsMiniCtx, {
                    type: 'line',
                    data: { labels: seriesLabels, datasets: [{ data: seriesRecordsMerged, borderColor: '#dc2626', backgroundColor: 'rgba(220, 38, 38, 0.2)', fill: true, tension: 0.3 }] },
                    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { display: false }, y: { display: false } } }
                });
            } catch (err) {
                console.error('qaRecordsMini failed:', err);
            }
        }

        // Mini sparkline: Downloads
        const qaDownloadsMiniCtx = document.getElementById('qaDownloadsMini')?.getContext('2d');
        if (qaDownloadsMiniCtx) {
            new Chart(qaDownloadsMiniCtx, {
                type: 'line',
                data: { labels: seriesLabels, datasets: [{ data: seriesDownloads, borderColor: '#2563eb', backgroundColor: 'rgba(37, 99, 235, 0.2)', fill: true, tension: 0.3 }] },
                options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { display: false }, y: { display: false } } }
            });
        }

        // Mini sparkline: Uploads
        const qaUploadsMiniCtx = document.getElementById('qaUploadsMini')?.getContext('2d');
        if (qaUploadsMiniCtx) {
            new Chart(qaUploadsMiniCtx, {
                type: 'line',
                data: { labels: seriesLabels, datasets: [{ data: seriesUploads, borderColor: '#10b981', backgroundColor: 'rgba(16, 185, 129, 0.2)', fill: true, tension: 0.3 }] },
                options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { display: false }, y: { display: false } } }
            });
        }

        // Records Trend Line
        const qaRecordsLineCtx = document.getElementById('qaRecordsLine')?.getContext('2d');
        if (qaRecordsLineCtx) {
            try {
                new Chart(qaRecordsLineCtx, {
                    type: 'line',
                    data: { labels: seriesLabels, datasets: [{ label: 'Records', data: seriesRecords, tension: 0.3, borderColor: '#dc2626', backgroundColor: 'rgba(220, 38, 38, 0.2)', fill: true }] },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: false } },
                        scales: {
                            x: { ticks: { maxRotation: 0, autoSkip: true } },
                            y: { beginAtZero: true, precision: 0 }
                        }
                    }
                });
            } catch (err) {
                console.error('qaRecordsLine failed:', err);
            }
        }

        // Records by Type Doughnut (with ABS/% toggle - special pattern)
        let rbtChart = null;
        let rbtMode = 'abs';
        try { rbtMode = localStorage.getItem('rbtMode') || 'abs'; } catch(e) {}
        function renderRbt() {
            const qaRecordsByTypeCtx = document.getElementById('qaRecordsByType')?.getContext('2d');
            if (!qaRecordsByTypeCtx) return;
            const labels = rbt.labels;
            const values = rbt.data;
            const total = values.reduce((a, b) => a + b, 0) || 1;
            const data = (rbtMode === 'pct') ? values.map(v => +(v * 100 / total).toFixed(2)) : values;
            if (rbtChart) rbtChart.destroy();
            try {
                rbtChart = new Chart(qaRecordsByTypeCtx, {
                    type: 'doughnut',
                    data: {
                        labels: labels,
                        datasets: [{ data: data, backgroundColor: ['#dc2626','#f97316','#3b82f6','#10b981','#6b21a8','#f59e0b','#ef4444','#06b6d4','#84cc16'] }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { position: 'bottom' },
                            tooltip: {
                                callbacks: {
                                    label: function(ctx) {
                                        const idx = ctx.dataIndex;
                                        const raw = values[idx];
                                        const pct = (raw * 100 / total).toFixed(2) + '%';
                                        return labels[idx] + ': ' + (rbtMode === 'pct' ? pct : raw);
                                    }
                                }
                            }
                        }
                    }
                });
            } catch (err) {
                console.error('qaRecordsByType failed:', err);
            }
            const toggle = document.getElementById('rbt-toggle');
            if (toggle) toggle.textContent = (rbtMode === 'pct' ? '%' : 'ABS');
        }
        renderRbt();
        const rbtToggle = document.getElementById('rbt-toggle');
        if (rbtToggle) {
            rbtToggle.addEventListener('click', function() {
                rbtMode = (rbtMode === 'abs') ? 'pct' : 'abs';
                try { localStorage.setItem('rbtMode', rbtMode); } catch(e) {}
                renderRbt();
            });
        }

        // Uploads by Folder grouped bar
        const uploadsByFolderChartCtx = document.getElementById('uploadsByFolderChart')?.getContext('2d');
        if (uploadsByFolderChartCtx) {
            try {
                new Chart(uploadsByFolderChartCtx, {
                    type: 'bar',
                    data: {
                        labels: fuLabels,
                        datasets: [
                            { label: 'Last 7d', data: fuLast7, backgroundColor: '#2563eb' },
                            { label: 'Prev 7d', data: fuPrev7, backgroundColor: '#f97316' },
                            { label: '8-30d', data: fuEarlier, backgroundColor: '#6b7280' }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { position: 'bottom' } },
                        scales: { x: { stacked: false }, y: { beginAtZero: true, precision: 0 } }
                    }
                });
            } catch (err) {
                console.error('uploadsByFolderChart failed:', err);
            }
        }

        // Optional canvases (safe guards)
        const recordsByCategoryChartCtx = document.getElementById('recordsByCategoryChart')?.getContext('2d');
        if (recordsByCategoryChartCtx) {
            new Chart(recordsByCategoryChartCtx, {
                type: 'bar',
                data: {
                    labels: catLabels,
                    datasets: [
                        { label: 'Last 7d', data: catLast7, backgroundColor: '#dc2626' },
                        { label: 'Prev 7d', data: catPrev7, backgroundColor: '#f97316' },
                        { label: '8-30d', data: catEarlier, backgroundColor: '#6b7280' }
                    ]
                },
                options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } }, scales: { y: { beginAtZero: true, precision: 0 } } }
            });
        }

        const filesByFolderDonutCtx = document.getElementById('filesByFolderDonut')?.getContext('2d');
        if (filesByFolderDonutCtx) {
            new Chart(filesByFolderDonutCtx, {
                type: 'doughnut',
                data: { labels: ffLabels, datasets: [{ data: ffValues, backgroundColor: ['#dc2626','#f97316','#3b82f6','#10b981','#6b21a8','#f59e0b','#ef4444','#06b6d4','#84cc16'] }] },
                options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } }
            });
        }

        const dupLegBarCtx = document.getElementById('dupLegBar')?.getContext('2d');
        if (dupLegBarCtx) {
            new Chart(dupLegBarCtx, {
                type: 'bar',
                data: { labels: dupLegLabels.map(function(s){ return s.length>18 ? s.slice(0,18)+'ΓÇª' : s; }), datasets: [{ label: 'Count', data: dupLegCounts, backgroundColor: '#dc2626' }] },
                options: { responsive: true, maintainAspectRatio: false, indexAxis: 'y', plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true, precision: 0 } } }
            });
        }

        const dupFileBarCtx = document.getElementById('dupFileBar')?.getContext('2d');
        if (dupFileBarCtx) {
            new Chart(dupFileBarCtx, {
                type: 'bar',
                data: { labels: dupFileLabels.map(function(s){ return s.length>18 ? s.slice(0,18)+'ΓÇª' : s; }), datasets: [{ label: 'Count', data: dupFileCounts, backgroundColor: '#2563eb' }] },
                options: { responsive: true, maintainAspectRatio: false, indexAxis: 'y', plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true, precision: 0 } } }
            });
        }
