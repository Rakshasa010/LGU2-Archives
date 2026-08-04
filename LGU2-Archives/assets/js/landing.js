(function () {
    var header = document.getElementById('site-header');
    var navToggle = document.getElementById('nav-toggle');
    var mobileMenu = document.getElementById('mobile-menu');

    function onScroll() {
        if (header) header.classList.toggle('scrolled', window.scrollY > 40);
        if (backToTop) backToTop.classList.toggle('visible', window.scrollY > 500);
    }
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();

    var backToTop = document.getElementById('back-to-top');
    if (backToTop) {
        backToTop.addEventListener('click', function () {
            window.scrollTo({ top: 0 });
        });
    }

    if (navToggle && mobileMenu) {
        navToggle.addEventListener('click', function () {
            var hidden = mobileMenu.classList.toggle('hidden');
            navToggle.setAttribute('aria-expanded', hidden ? 'false' : 'true');
            navToggle.innerHTML = hidden ? '<i class="bi bi-list"></i>' : '<i class="bi bi-x-lg"></i>';
        });
        mobileMenu.querySelectorAll('a').forEach(function (a) {
            a.addEventListener('click', function () {
                mobileMenu.classList.add('hidden');
                navToggle.setAttribute('aria-expanded', 'false');
                navToggle.innerHTML = '<i class="bi bi-list"></i>';
            });
        });
    }

    var io = new IntersectionObserver(function (entries) {
        entries.forEach(function (e) {
            if (e.isIntersecting) {
                e.target.classList.add('visible');
                io.unobserve(e.target);
            }
        });
    }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });
    document.querySelectorAll('.reveal').forEach(function (el) {
        io.observe(el);
    });

    document.querySelectorAll('.faq-item > button').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var item = btn.parentElement;
            var wasOpen = item.classList.contains('open');
            document.querySelectorAll('.faq-item.open').forEach(function (i) {
                i.classList.remove('open');
            });
            if (!wasOpen) item.classList.add('open');
        });
    });

    var ci = new IntersectionObserver(function (entries) {
        entries.forEach(function (e) {
            if (e.isIntersecting) {
                var el = e.target;
                var target = parseFloat(el.getAttribute('data-count') || '0') || 0;
                var dur = 1400;
                var start = null;
                function step(ts) {
                    if (!start) start = ts;
                    var p = Math.min((ts - start) / dur, 1);
                    var val = Math.round(target * (1 - Math.pow(1 - p, 3)));
                    el.textContent = val.toLocaleString();
                    if (p < 1) requestAnimationFrame(step);
                }
                requestAnimationFrame(step);
                ci.unobserve(el);
            }
        });
    }, { threshold: 0.4 });
    document.querySelectorAll('[data-count]').forEach(function (el) {
        ci.observe(el);
    });

    var themeBtns = document.querySelectorAll('#theme-toggle, #theme-toggle-mobile');
    function applyTheme(t) {
        document.documentElement.classList.toggle('dark', t === 'dark');
        try { localStorage.setItem('theme', t); } catch (_) {}
        var icon = t === 'dark' ? '<i class="bi bi-sun"></i>' : '<i class="bi bi-moon-stars"></i>';
        themeBtns.forEach(function (b) { b.innerHTML = icon; });
    }
    function toggleTheme() {
        var isDark = document.documentElement.classList.contains('dark');
        applyTheme(isDark ? 'light' : 'dark');
    }
    if (themeBtns.length) {
        applyTheme(document.documentElement.classList.contains('dark') ? 'dark' : 'light');
        themeBtns.forEach(function (b) {
            b.addEventListener('click', toggleTheme);
        });
    }

    var heroCarousel = document.getElementById('hero-carousel');
    if (heroCarousel) {
        var heroImages = [];
        try { heroImages = JSON.parse(heroCarousel.getAttribute('data-hero-images') || '[]'); } catch (_) {}
        heroImages = heroImages.filter(function (s) { return typeof s === 'string' && s; });
        var heroSlides = [];
        var heroIndex = 0;
        var heroTimer = null;
        heroImages.forEach(function (src) {
            var div = document.createElement('div');
            div.className = 'hero-slide';
            div.style.backgroundImage = "url('" + src + "')";
            heroCarousel.appendChild(div);
            heroSlides.push(div);
        });
        function heroGo(i) {
            heroIndex = (i + heroImages.length) % heroImages.length;
            heroSlides.forEach(function (s, k) { s.classList.toggle('active', k === heroIndex); });
        }
        if (heroSlides.length > 1) {
            heroTimer = setInterval(function () { heroGo(heroIndex + 1); }, 6000);
            heroCarousel.addEventListener('mouseenter', function () { if (heroTimer) clearInterval(heroTimer); });
            heroCarousel.addEventListener('mouseleave', function () {
                if (heroTimer) clearInterval(heroTimer);
                heroTimer = setInterval(function () { heroGo(heroIndex + 1); }, 6000);
            });
        }
        heroGo(0);
    }

    var carouselEl = document.getElementById('about-carousel');
    if (carouselEl) {
        var images = [];
        try { images = JSON.parse(carouselEl.getAttribute('data-images') || '[]'); } catch (_) {}
        images = images.filter(function (s) { return typeof s === 'string' && s; });
        var track = carouselEl.querySelector('.carousel-track');
        var dotsWrap = carouselEl.querySelector('.carousel-dots');
        var prev = carouselEl.querySelector('.carousel-prev');
        var next = carouselEl.querySelector('.carousel-next');
        var slides = [];
        var index = 0;
        var timer = null;
        var count = images.length;

        if (count > 0 && track) {
            images.forEach(function (src) {
                var div = document.createElement('div');
                div.className = 'carousel-slide';
                div.style.backgroundImage = "url('" + src + "')";
                track.appendChild(div);
                slides.push(div);
            });
            function go(i) {
                index = (i + count) % count;
                slides.forEach(function (s, k) { s.classList.toggle('active', k === index); });
                var dots = dotsWrap.querySelectorAll('.carousel-dot');
                dots.forEach(function (d, k) { d.classList.toggle('active', k === index); });
            }
            function restart() {
                if (count < 2) return;
                if (timer) clearInterval(timer);
                timer = setInterval(function () { go(index + 1); }, 5000);
            }
            if (count > 1) {
                images.forEach(function (_, i) {
                    var d = document.createElement('button');
                    d.className = 'carousel-dot' + (i === 0 ? ' active' : '');
                    d.setAttribute('type', 'button');
                    d.setAttribute('aria-label', 'Photo ' + (i + 1));
                    d.addEventListener('click', function () { go(i); restart(); });
                    dotsWrap.appendChild(d);
                });
            }
            if (prev) prev.addEventListener('click', function () { go(index - 1); restart(); });
            if (next) next.addEventListener('click', function () { go(index + 1); restart(); });
            if (prev) prev.classList.toggle('hidden', count < 2);
            if (next) next.classList.toggle('hidden', count < 2);
            carouselEl.addEventListener('mouseenter', function () { if (timer) clearInterval(timer); });
            carouselEl.addEventListener('mouseleave', restart);
            var startX = 0;
            carouselEl.addEventListener('touchstart', function (e) { startX = e.touches[0].clientX; }, { passive: true });
            carouselEl.addEventListener('touchend', function (e) {
                var dx = e.changedTouches[0].clientX - startX;
                if (Math.abs(dx) > 40) { go(dx < 0 ? index + 1 : index - 1); restart(); }
            }, { passive: true });
            go(0);
            restart();
        } else {
            carouselEl.style.display = 'none';
        }
    }

    var contactForm = document.getElementById('contact-form');
    if (contactForm) {
        contactForm.addEventListener('submit', function (e) {
            e.preventDefault();
            var status = document.getElementById('contact-status');
            var btn = document.getElementById('contact-submit');
            var btnHtml = btn.innerHTML;
            var name = (document.getElementById('cf-name') || {}).value;
            var email = (document.getElementById('cf-email') || {}).value;
            var msg = (document.getElementById('cf-msg') || {}).value;
            if (!name || !String(name).trim() || !email || !String(email).trim() || !msg || !String(msg).trim()) {
                if (status) {
                    status.className = 'form-status error';
                    status.textContent = 'Please fill in your name, email, and message.';
                }
                return;
            }
            if (status) { status.className = 'form-status'; status.textContent = ''; }
            btn.disabled = true;
            btn.innerHTML = '<span class="inline-flex items-center gap-2"><i class="bi bi-arrow-repeat animate-spin"></i> Sending...</span>';
            var fd = new FormData(contactForm);
            fetch(contactForm.getAttribute('action'), { method: 'POST', body: fd })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (status) {
                        status.className = 'form-status ' + (data.success ? 'success' : 'error');
                        status.textContent = data.message || (data.success ? 'Sent.' : 'Something went wrong.');
                    }
                    if (data.success) contactForm.reset();
                })
                .catch(function () {
                    if (status) {
                        status.className = 'form-status error';
                        status.textContent = 'Unable to send. Please try again later.';
                    }
                })
                .finally(function () {
                    btn.disabled = false;
                    btn.innerHTML = btnHtml;
                });
        });
    }
})();
