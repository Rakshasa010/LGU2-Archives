/* UI enhancements: skeleton management, lazy loading, charts, preview modal (MVP) */
(function(){
  const UI = {
    init(){
      document.addEventListener('DOMContentLoaded', ()=>{
        this.setLazyImages();
        this.hydrateFetchContainers();
        this.initCharts();
        this.attachPreviewDelegation();
        this.initDarkModeToggle();
        this.attachContextMenu();
      });
    },

    setLazyImages(){
      try{
        document.querySelectorAll('img.doc-thumb, img[data-lazy]').forEach(img=>{
          if(!img.getAttribute('loading')) img.setAttribute('loading','lazy');
        });
      }catch(e){console.warn('setLazyImages',e)}
    },

    hydrateFetchContainers(){
      // find elements with data-fetch-url and keep skeleton until replaced
      document.querySelectorAll('[data-fetch-url]').forEach(container=>{
        const url = container.getAttribute('data-fetch-url');
        if(!url) return;
        fetch(url, { credentials: 'same-origin' })
          .then(r=>{
            const ct = r.headers.get('content-type') || '';
            if (ct.indexOf('application/json') !== -1) {
              // avoid inserting raw JSON into the DOM; let page-specific JS handle JSON endpoints
              container.classList.add('loaded', 'json-response');
              return null;
            }
            return r.text();
          })
          .then(html=>{
            if (!html) return;
            container.innerHTML = html;
            container.classList.add('loaded');
            UI.setLazyImages();
            UI.initCharts(container);
          }).catch(err=>{
            console.error('fetch container failed', url, err);
            container.classList.add('loaded');
          });
      });
    },

    initCharts(scope=document){
      // canvases with data-chart-config containing JSON
      scope.querySelectorAll('canvas[data-chart-config]').forEach(canvas=>{
        try{
          const cfgText = canvas.getAttribute('data-chart-config');
          const cfg = JSON.parse(cfgText);
          if(window.Chart && cfg){
            // avoid double-init
            if(canvas.__chart) { canvas.__chart.destroy(); }
            canvas.__chart = new Chart(canvas.getContext('2d'), cfg);
          }
        }catch(e){console.warn('initCharts parse', e)}
      });
    },

    attachPreviewDelegation(){
      document.body.addEventListener('click', e=>{
        const t = e.target.closest('[data-preview-url]');
        if(!t) return;
        e.preventDefault();
        const url = t.getAttribute('data-preview-url');
        const type = t.getAttribute('data-preview-type') || '';
        UI.openPreview(url, type);
      });
    },

    attachContextMenu(){
      // simple right-click context menu for previewable items
      document.addEventListener('contextmenu', function(e){
        const t = e.target.closest('[data-preview-url], .file-card, [data-preview-type]');
        if (!t) return;
        e.preventDefault();
        const url = t.getAttribute('data-preview-url') || t.getAttribute('href') || t.dataset.url || '';
        const type = t.getAttribute('data-preview-type') || '';
        // remove existing menu
        const old = document.getElementById('ui-context-menu'); if (old) old.remove();
        const menu = document.createElement('div'); menu.id='ui-context-menu';
        menu.style.position='fixed'; menu.style.zIndex=999999; menu.style.left=(e.clientX)+'px'; menu.style.top=(e.clientY)+'px';
        menu.style.background='#fff'; menu.style.border='1px solid rgba(0,0,0,0.08)'; menu.style.boxShadow='0 6px 18px rgba(0,0,0,0.12)'; menu.style.borderRadius='8px'; menu.style.padding='6px';
        menu.innerHTML = `
          <button data-act="preview" style="display:block;padding:8px 12px;border:none;background:transparent;width:100%;text-align:left;">Preview</button>
          <button data-act="download" style="display:block;padding:8px 12px;border:none;background:transparent;width:100%;text-align:left;">Download</button>
        `;
        document.body.appendChild(menu);
        menu.addEventListener('click', function(ev){
          const act = ev.target.closest('button')?.getAttribute('data-act');
          if (!act) return;
          if (act === 'preview') { if (url) UI.openPreview(url, type); }
          if (act === 'download') { if (url) window.open(url, '_blank'); }
          menu.remove();
        });
        document.addEventListener('click', function _c(){ const m = document.getElementById('ui-context-menu'); if (m) m.remove(); document.removeEventListener('click', _c); });
      });
    },

    openPreview(url, type=''){
      // create lightbox modal
      const overlay = document.createElement('div');
      overlay.style.position = 'fixed'; overlay.style.left=0; overlay.style.top=0; overlay.style.right=0; overlay.style.bottom=0;
      overlay.style.background='rgba(0,0,0,0.75)'; overlay.style.zIndex=99999; overlay.style.display='flex'; overlay.style.alignItems='center'; overlay.style.justifyContent='center';
      overlay.innerHTML = `<div style="width:90%;max-width:1100px;height:85%;background:#fff;border-radius:6px;overflow:hidden;position:relative;">
          <button id="ui-close-preview" style="position:absolute;z-index:3;right:8px;top:8px;padding:6px 10px;border:none;background:#111;color:#fff;border-radius:4px;">Close</button>
          <div id="ui-preview-body" style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;background:#f6f7fb"></div>
        </div>`;
      document.body.appendChild(overlay);
      const body = overlay.querySelector('#ui-preview-body');
      const close = overlay.querySelector('#ui-close-preview');
      close.addEventListener('click', ()=> overlay.remove());

      const lc = url.toLowerCase();
      if(type==='video' || lc.match(/\.(mp4|webm|ogg)(\?|$)/)){
        const vid = document.createElement('video'); vid.controls = true; vid.style.maxWidth='100%'; vid.style.maxHeight='100%'; vid.src = url; vid.preload='metadata'; body.appendChild(vid);
      } else if(type==='pdf' || lc.endsWith('.pdf')){
        const embed = document.createElement('iframe'); embed.src = url; embed.style.width='100%'; embed.style.height='100%'; embed.frameBorder=0; body.appendChild(embed);
      } else {
        // fallback to embed if possible
        const iframe = document.createElement('iframe'); iframe.src = url; iframe.style.width='100%'; iframe.style.height='100%'; iframe.frameBorder=0; body.appendChild(iframe);
      }
    }
    ,
    // Dark mode utilities (CSS-variable based)
    initDarkModeToggle(){
      try{
        const stored = localStorage.getItem('ui_theme');
        if(stored) document.documentElement.setAttribute('data-theme', stored);
        // attach toggles by id
        document.querySelectorAll('[data-theme-toggle]').forEach(btn=>{
          btn.addEventListener('click', ()=>{
            const cur = document.documentElement.getAttribute('data-theme') || '';
            const next = (cur === 'dark') ? 'light' : 'dark';
            document.documentElement.setAttribute('data-theme', next === 'dark' ? 'dark' : '');
            localStorage.setItem('ui_theme', next === 'dark' ? 'dark' : '');
          });
        });
      }catch(e){console.warn('dark mode init',e)}
    },
    // Toast helper using Toastify
    toast(message, opts={}){
      if (window.Toastify){
        Toastify(Object.assign({text:message,duration:opts.duration||3000,gravity:opts.gravity||'top',position:opts.position||'right',backgroundColor:opts.background||'linear-gradient(90deg,#4b5563,#111827)'} , opts)).showToast();
      } else { try{ alert(message); }catch(e){} }
    }
  };

  window.UI_ENH = UI;
  UI.init();
})();
