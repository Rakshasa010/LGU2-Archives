// Updates sidebar storage status and optional donut chart in storage.php using storage_status.php
(function () {
  function $(id) { return document.getElementById(id); }

  function updateSidebar(data) {
    var mp = $('mobile-storage-percent');
    var mb = $('mobile-storage-bar');
    var mu = $('mobile-storage-used');
    var mt = $('mobile-storage-total');
    var dp = $('desktop-storage-percent');
    var db = $('desktop-storage-bar');
    var du = $('desktop-storage-used');
    var dt = $('desktop-storage-total');
    if (mp) mp.textContent = String(data.percent) + '%';
    if (mb) mb.style.width = String(data.percent) + '%';
    if (mu) mu.textContent = data.used_human;
    if (mt) mt.textContent = data.total_human;
    if (dp) dp.textContent = String(data.percent) + '%';
    if (db) db.style.width = String(data.percent) + '%';
    if (du) du.textContent = data.used_human;
    if (dt) dt.textContent = data.total_human;
  }

  function updateDonut(data) {
    var donut = $('donutProgress');
    var pctEl = $('storagePercentage');
    var usedEl = $('storageUsed');
    var totalEl = $('storageTotal');
    var dUsedEl = $('detailUsed');
    var dAvailEl = $('detailAvailable');
    var dTotalEl = $('detailTotal');
    if (!donut && !pctEl && !usedEl && !totalEl && !dUsedEl && !dAvailEl && !dTotalEl) return;
    var pct = data.percent;
    if (pctEl) pctEl.textContent = String(pct) + '%';
    if (usedEl) usedEl.textContent = data.used_human;
    if (totalEl) totalEl.textContent = 'of ' + data.total_human;
    if (dUsedEl) dUsedEl.textContent = data.used_human;
    if (dTotalEl) dTotalEl.textContent = data.total_human;
    if (dAvailEl) {
      try {
        var availBytes = Math.max(0, (data.total_bytes || 0) - (data.used_bytes || 0));
        dAvailEl.textContent = humanBytes(availBytes);
      } catch (_) {}
    }
    if (donut) {
      var r = 90;
      var circumference = 2 * Math.PI * r;
      donut.style.transition = 'stroke-dashoffset 1.2s cubic-bezier(0.4,0,0.2,1)';
      donut.setAttribute('stroke-dasharray', String(circumference));
      var offset = circumference - (pct / 100) * circumference;
      setTimeout(function(){ donut.style.strokeDashoffset = String(offset); }, 50);
      updateDonutColor(pct);
    }
    function humanBytes(bytes) {
      if (bytes <= 0) return '0 B';
      var units = ['B','KB','MB','GB','TB'];
      var e = Math.floor(Math.log(bytes) / Math.log(1024));
      e = Math.max(0, Math.min(e, units.length - 1));
      var val = bytes / Math.pow(1024, e);
      return (e >= 3 ? val.toFixed(1) : Math.round(val)) + ' ' + units[e];
    }
    function updateDonutColor(p) {
      var el = $('donutProgress');
      if (!el) return;
      if (p >= 90) {
        el.classList.remove('stroke-red-600', 'dark:stroke-red-500');
        el.classList.add('stroke-red-700');
      } else if (p >= 70) {
        el.classList.remove('stroke-red-600', 'dark:stroke-red-500');
        el.classList.add('stroke-orange-500');
      } else {
        el.classList.remove('stroke-red-700', 'stroke-orange-500');
        el.classList.add('stroke-red-600');
      }
    }
  }

  function fetchStatus() {
    fetch('storage_status.php', { credentials: 'same-origin' })
      .then(function(r){ return r.json(); })
      .then(function(d){
        if (!d || !d.success) return;
        updateSidebar(d);
        updateDonut(d);
      })
      .catch(function(){});
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', fetchStatus);
  } else {
    fetchStatus();
  }
  window.addEventListener('focus', fetchStatus);
})();
