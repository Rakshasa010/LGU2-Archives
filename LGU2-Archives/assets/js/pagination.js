/**
 * Reusable Pagination Controls
 * Renders prev/next arrows + page indicator matching the existing audit-logs style.
 *
 * Usage:
 *   var pg = new PaginationControls('container-id', {
 *       pageSize: 20,
 *       initialPage: 1,
 *       onPageChange: function(page) { ... }
 *   });
 *   pg.update(totalItems);   // call after data loads
 *   pg.getPage();            // returns current 1-based page number
 *   pg.reset();              // resets to page 1 and calls onPageChange
 */
function PaginationControls(containerId, options) {
    options = options || {};
    this.containerId = containerId;
    this.pageSize = options.pageSize || 20;
    this.currentPage = options.initialPage || 1;
    this.totalItems = 0;
    this.totalPages = 1;
    this.onPageChange = options.onPageChange || function () {};
    this._render();
}

PaginationControls.prototype._render = function () {
    var el = document.getElementById(this.containerId);
    if (!el) return;
    el.innerHTML =
        '<button class="pg-prev px-3 py-1.5 text-gray-500 hover:text-gray-800 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-slate-700 transition-colors border-r border-gray-200 dark:border-slate-600 disabled:opacity-40 disabled:cursor-not-allowed" type="button"><i class="bi bi-chevron-left text-xs"></i></button>' +
        '<span class="pg-info text-xs font-bold text-gray-700 dark:text-gray-300 px-3 py-1.5 select-none">1 / 1</span>' +
        '<button class="pg-next px-3 py-1.5 text-gray-500 hover:text-gray-800 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-slate-700 transition-colors border-l border-gray-200 dark:border-slate-600 disabled:opacity-40 disabled:cursor-not-allowed" type="button"><i class="bi bi-chevron-right text-xs"></i></button>';
    var self = this;
    el.querySelector('.pg-prev').addEventListener('click', function () {
        if (self.currentPage > 1) {
            self.currentPage--;
            self.onPageChange(self.currentPage);
            self._updateUI();
        }
    });
    el.querySelector('.pg-next').addEventListener('click', function () {
        if (self.currentPage < self.totalPages) {
            self.currentPage++;
            self.onPageChange(self.currentPage);
            self._updateUI();
        }
    });
    this._updateUI();
};

PaginationControls.prototype._updateUI = function () {
    var el = document.getElementById(this.containerId);
    if (!el) return;
    var info = el.querySelector('.pg-info');
    if (info) info.textContent = this.currentPage + ' / ' + this.totalPages;
    var prev = el.querySelector('.pg-prev');
    var next = el.querySelector('.pg-next');
    if (prev) prev.disabled = this.currentPage <= 1;
    if (next) next.disabled = this.currentPage >= this.totalPages;
    // Show/hide entire control if only 1 page
    if (this.totalPages <= 1) {
        el.style.display = 'none';
    } else {
        el.style.display = 'inline-flex';
    }
};

PaginationControls.prototype.update = function (totalItems) {
    this.totalItems = totalItems;
    this.totalPages = Math.max(1, Math.ceil(totalItems / this.pageSize));
    if (this.currentPage > this.totalPages) this.currentPage = this.totalPages;
    this._updateUI();
};

PaginationControls.prototype.getPage = function () {
    return this.currentPage;
};

PaginationControls.prototype.getPageSize = function () {
    return this.pageSize;
};

PaginationControls.prototype.reset = function () {
    this.currentPage = 1;
    this.onPageChange(1);
    this._updateUI();
};

PaginationControls.prototype.setPage = function (page) {
    this.currentPage = Math.max(1, Math.min(page, this.totalPages));
    this._updateUI();
};
