/* ============================================
   MOBILE RESPONSIVE JS
   LGU2-Archives System
   - Body scroll lock when sidebar is open
   - Close sidebar on resize to desktop
   - Touch-friendly interactions
   ============================================ */

(function () {
    var MOBILE_BP = 768;

    function isMobile() {
        return window.innerWidth < MOBILE_BP;
    }

    /* ---- BODY SCROLL LOCK when sidebar is open ---- */
    function initScrollLock() {
        var sidebar = document.getElementById('mobile-sidebar');
        if (!sidebar) return;

        var observer = new MutationObserver(function () {
            if (isMobile() && !sidebar.classList.contains('-translate-x-full')) {
                document.body.style.overflow = 'hidden';
            } else {
                document.body.style.overflow = '';
            }
        });

        observer.observe(sidebar, { attributes: true, attributeFilter: ['class'] });
    }

    /* ---- CLOSE SIDEBAR ON RESIZE TO DESKTOP ---- */
    function initResizeClose() {
        var resizeTimer;
        window.addEventListener('resize', function () {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(function () {
                if (!isMobile()) {
                    var sidebar = document.getElementById('mobile-sidebar');
                    var overlay = document.getElementById('sidebar-overlay');
                    if (sidebar) {
                        sidebar.classList.add('-translate-x-full');
                    }
                    if (overlay) {
                        overlay.classList.add('opacity-0', 'pointer-events-none');
                        overlay.classList.remove('opacity-100', 'pointer-events-auto');
                    }
                    document.body.style.overflow = '';
                }
            }, 150);
        });
    }

    /* ---- INIT ---- */
    document.addEventListener('DOMContentLoaded', function () {
        initScrollLock();
        initResizeClose();
    });
})();
