// Theme animation hooks (MVP)
(function () {
  var root = document.documentElement;
  var timer = null;

  function markStorageCards() {
    var cards = [];
    var mobileBar = document.getElementById('mobile-storage-bar');
    var desktopBar = document.getElementById('desktop-storage-bar');
    var dashboard = document.getElementById('storage-overview');

    function findCard(el) {
      var cur = el;
      var limit = 0;
      while (cur && limit < 8) {
        if (cur.classList && cur.classList.contains('rounded-xl')) return cur;
        cur = cur.parentElement;
        limit++;
      }
      return null;
    }

    if (mobileBar) cards.push(findCard(mobileBar));
    if (desktopBar) cards.push(findCard(desktopBar));
    if (dashboard) cards.push(dashboard);

    cards.forEach(function (c) {
      if (c && c.classList) c.classList.add('storage-overview-card');
    });
  }

  function animateTheme() {
    if (timer) clearTimeout(timer);
    root.classList.add('theme-animating');
    timer = setTimeout(function () {
      root.classList.remove('theme-animating');
    }, 650);
  }

  document.addEventListener('themechange', animateTheme);

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', markStorageCards);
  } else {
    markStorageCards();
  }
})();
