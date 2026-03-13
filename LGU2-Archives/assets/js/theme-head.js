// Tailwind dark-mode preflight (no flicker)
// - Reads canonical key: "theme" (values: "dark" | "light")
// - Migrates legacy key: "archive-theme" -> "theme"
(function () {
  const root = document.documentElement;
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
  } catch (_) {}

  function getStoredTheme() {
    try {
      const theme = localStorage.getItem('theme');
      if (theme === 'dark' || theme === 'light') return theme;

      const legacy = localStorage.getItem('archive-theme');
      if (legacy === 'dark' || legacy === 'light') {
        localStorage.setItem('theme', legacy);
        return legacy;
      }
    } catch (_) {
      // ignore storage errors (privacy mode / blocked storage)
    }
    return 'light';
  }

  if (getStoredTheme() === 'dark') root.classList.add('dark');
})();
;(function(){
  try {
    var path = (location.pathname || '');
    if (path.indexOf('login.php') !== -1) return;
    var TIMEOUT = 5 * 60 * 1000;
    var WARNING = 60 * 1000;
    var warningEl = null;
    var warnInterval = null;
    var warningVisible = false;
    var warnBtn = null;
    var warnText = null;
    var warnRemain = 0;
    var warningTimer = null;
    var expireTimer = null;
    function createWarning(){
      if (warningEl) return;
      var overlay = document.createElement('div');
      overlay.id = 'session-warning';
      overlay.className = 'fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm';
      var box = document.createElement('div');
      box.className = 'bg-white dark:bg-slate-800 rounded-xl shadow-2xl border border-gray-200 dark:border-slate-700 p-6 w-[92%] max-w-md';
      var title = document.createElement('div');
      title.className = 'text-lg font-bold text-gray-800 dark:text-gray-100';
      title.textContent = 'Session will expire soon';
      var text = document.createElement('div');
      text.className = 'mt-2 text-sm text-gray-700 dark:text-gray-300';
      text.textContent = 'You will be logged out in 60 seconds.';
      var actions = document.createElement('div');
      actions.className = 'mt-4 flex justify-end space-x-3';
      var stay = document.createElement('button');
      stay.className = 'px-4 py-2 bg-gradient-to-r from-red-600 to-orange-500 text-white rounded-lg font-semibold hover:from-red-700 hover:to-orange-600 transition-all';
      stay.textContent = 'Stay Logged In';
      stay.addEventListener('click', function(e){ e.preventDefault(); resetTimers(); });
      actions.appendChild(stay);
      box.appendChild(title);
      box.appendChild(text);
      box.appendChild(actions);
      overlay.appendChild(box);
      warningEl = overlay;
      warnBtn = stay;
      warnText = text;
      document.addEventListener('keydown', function(ev){ if (warningVisible) resetTimers(); });
    }
    function showWarning(){
      createWarning();
      if (!warningEl.parentNode) document.body.appendChild(warningEl);
      warningVisible = true;
      warnRemain = WARNING;
      updateWarnText();
      if (warnInterval) clearInterval(warnInterval);
      warnInterval = setInterval(function(){ warnRemain -= 1000; updateWarnText(); }, 1000);
    }
    function hideWarning(){
      warningVisible = false;
      if (warnInterval) { clearInterval(warnInterval); warnInterval = null; }
      if (warningEl && warningEl.parentNode) warningEl.parentNode.removeChild(warningEl);
    }
    function updateWarnText(){
      if (!warnText) return;
      var sec = Math.max(0, Math.ceil(warnRemain/1000));
      warnText.textContent = 'You will be logged out in ' + sec + ' seconds.';
    }
    function clearTimers(){
      if (warningTimer) { clearTimeout(warningTimer); warningTimer = null; }
      if (expireTimer) { clearTimeout(expireTimer); expireTimer = null; }
    }
    function schedule(){
      clearTimers();
      hideWarning();
      warningTimer = setTimeout(showWarning, TIMEOUT - WARNING);
      expireTimer = setTimeout(function(){ location.href = 'login.php?expired=1'; }, TIMEOUT);
    }
    function resetTimers(){
      schedule();
    }
    ['click','keydown','mousemove','touchstart','wheel'].forEach(function(ev){
      window.addEventListener(ev, function(){ resetTimers(); }, { passive: true });
    });
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', schedule);
    } else {
      schedule();
    }
  } catch(_){}
})();

