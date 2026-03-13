// Tailwind configuration for login page
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
; (function () {
    try {
        if (!document.getElementById('theme-animations-css')) {
            var link = document.createElement('link');
            link.id = 'theme-animations-css';
            link.rel = 'stylesheet';
            link.href = 'assets/css/theme-animations.css';
            document.head.appendChild(link);
        }
        if (!document.getElementById('skeletons-css')) {
            var sk = document.createElement('link');
            sk.id = 'skeletons-css';
            sk.rel = 'stylesheet';
            sk.href = 'assets/css/skeletons.css';
            document.head.appendChild(sk);
        }
        if (!document.getElementById('theme-animations-js')) {
            var script = document.createElement('script');
            script.id = 'theme-animations-js';
            script.src = 'assets/js/theme-animations.js';
            script.defer = true;
            document.head.appendChild(script);
        }
        if (!document.getElementById('skeleton-auto-js')) {
            var skjs = document.createElement('script');
            skjs.id = 'skeleton-auto-js';
            skjs.src = 'assets/js/skeleton-auto.js';
            skjs.defer = true;
            document.head.appendChild(skjs);
        }
    } catch (_) { }
})();
