// Apply saved theme on load and provide a toggle for elements with id 'themeToggle'
(function(){
    const KEY = 'theme';
    const root = document.documentElement;

    function applyTheme(mode){
        if (mode === 'dark') root.classList.add('dark'); else root.classList.remove('dark');
    }

    function updateIcons(){
        const moon = document.getElementById('moonIcon');
        const sun = document.getElementById('sunIcon');
        if (!moon || !sun) return;
        const isDark = root.classList.contains('dark');
        if (isDark){ moon.classList.remove('hidden'); moon.classList.add('block'); sun.classList.remove('block'); sun.classList.add('hidden'); }
        else { sun.classList.remove('hidden'); sun.classList.add('block'); moon.classList.remove('block'); moon.classList.add('hidden'); }
    }

    // initialize from storage
    try{ const stored = localStorage.getItem(KEY) || 'light'; applyTheme(stored); }catch(e){}
    // update any icons on load
    updateIcons();

    const btn = document.getElementById('themeToggle');
    if (btn){
        btn.addEventListener('click', function(e){
            e.preventDefault();
            const cur = root.classList.contains('dark') ? 'dark' : 'light';
            const next = cur === 'dark' ? 'light' : 'dark';
            applyTheme(next);
            try{ localStorage.setItem(KEY, next); }catch(e){}
            updateIcons();
        });
    }
})();
