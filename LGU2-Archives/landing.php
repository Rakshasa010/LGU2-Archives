<?php
session_start();

if (isset($_SESSION['otp_pending']) && $_SESSION['otp_pending'] === true) {
    header("Location: verify-otp.php");
    exit();
}
if (isset($_SESSION['user_id'])) {
    header("Location: archives-landing.php");
    exit();
}

$LOGO = 'Images/Val-logo/valenzuela logo.webp';

$IMG_PHOTOS = glob(__DIR__ . '/Images/Photos/*.{jpg,jpeg,png,webp,JPG,JPEG,PNG,WEBP}', GLOB_BRACE);
if (!is_array($IMG_PHOTOS) || count($IMG_PHOTOS) === 0) {
    $IMG_PHOTOS = ['Images/Photos/val1.jpg'];
} else {
    $IMG_PHOTOS = array_values(array_map(function ($p) {
        return 'Images/Photos/' . basename($p);
    }, $IMG_PHOTOS));
}
sort($IMG_PHOTOS, SORT_STRING);

// Distribution of the Photos folder across the page:
//   Hero carousel        -> all photos
//   About carousel       -> all photos, reversed (different opening image)
//   CTA background       -> last photo in the folder
$IMG_HERO_SLIDES = $IMG_PHOTOS;
$IMG_HERO  = $IMG_HERO_SLIDES[0];
$IMG_ABOUT = $IMG_HERO_SLIDES[count($IMG_HERO_SLIDES) - 1];
$IMG_CTA   = $IMG_HERO_SLIDES[count($IMG_HERO_SLIDES) - 1];
$hero_images_json = htmlspecialchars(json_encode($IMG_HERO_SLIDES), ENT_QUOTES);
$carousel_images_json = htmlspecialchars(json_encode(array_reverse($IMG_HERO_SLIDES)), ENT_QUOTES);

// Rotating captions for the hero carousel (cycles regardless of photo filename)
$IMG_CAPTION_POOL = [
    ['Legislative Archive System', 'Official digital repository of Valenzuela City'],
    ['Ordinances & Resolutions', 'Every law that shapes the city, preserved'],
    ['Records You Can Trust', 'Secure, organized, and always accessible'],
    ['Version Tracking', 'Every revision of every document, tracked'],
    ['Smart Search', 'Find any record instantly by title, author, or folder'],
    ['Reports & Analytics', 'Insights on records, activity, and storage usage'],
    ['Serving Valenzueños', "Preserving the city's legislative legacy"]
];
$IMG_CAPTIONS = [];
foreach ($IMG_HERO_SLIDES as $k => $_src) {
    $IMG_CAPTIONS[] = $IMG_CAPTION_POOL[$k % count($IMG_CAPTION_POOL)];
}
$hero_captions_json = htmlspecialchars(json_encode($IMG_CAPTIONS), ENT_QUOTES);
$stat_records = 0;
$stat_folders = 0;
$stat_users = 0;
$stat_downloads = 0;
if (file_exists(__DIR__ . '/authdatabase.php')) {
    try {
        require __DIR__ . '/authdatabase.php';
        if (isset($conn) && $conn instanceof mysqli) {
            $r = $conn->query("SELECT (SELECT COUNT(*) FROM archive_files) + (SELECT COUNT(*) FROM legislative_records WHERE parent_version_id IS NULL) AS c");
            if ($r && $row = $r->fetch_assoc()) $stat_records = (int)$row['c'];
            $r = $conn->query("SELECT (SELECT COUNT(*) FROM archive_folders) + (SELECT COUNT(DISTINCT CASE type WHEN 'Ordinance' THEN 'Ordinances & Resolutions' WHEN 'Resolution' THEN 'Ordinances & Resolutions' WHEN 'Public Hearing' THEN 'Public Hearings' WHEN 'Meeting' THEN 'Meeting Records' END) FROM legislative_folders WHERE parent_id IS NULL) AS c");
            if ($r && $row = $r->fetch_assoc()) $stat_folders = (int)$row['c'];
            $r = $conn->query("SELECT COUNT(*) AS c FROM users");
            if ($r && $row = $r->fetch_assoc()) $stat_users = (int)$row['c'];
            if ($conn->query("SHOW TABLES LIKE 'analytics_events'")->num_rows > 0) {
                $r = $conn->query("SELECT COUNT(*) AS c FROM analytics_events");
                if ($r && $row = $r->fetch_assoc()) $stat_downloads = (int)$row['c'];
            }
        }
    } catch (Throwable $e) {
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Legislative Archive System - City Government of Valenzuela</title>
    <meta name="description" content="Welcome to the Legislative Archive System of Valenzuela City - a secure digital home for ordinances, resolutions, and official legislative records.">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        primary: {
                            DEFAULT: '#dc2626',
                            light: '#f97316'
                        }
                    }
                }
            }
        };
    </script>
    <script>
        (function () {
            var t = null;
            try { t = localStorage.getItem('theme') || localStorage.getItem('archive-theme'); } catch (_) {}
            if (t === 'dark') document.documentElement.classList.add('dark');
        })();
    </script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/landing.css">
    <link rel="apple-touch-icon" href="<?php echo $LOGO; ?>">
    <link rel="icon" type="image/png" href="<?php echo $LOGO; ?>">
</head>
<body class="bg-white dark:bg-slate-950 text-gray-900 dark:text-gray-100 antialiased overflow-x-hidden">

    <header id="site-header" class="fixed top-0 inset-x-0 z-50">
        <nav class="max-w-7xl mx-auto flex items-center justify-between px-4 sm:px-6 py-3">
            <a href="#home" class="flex items-center gap-3">
                <img src="<?php echo $LOGO; ?>" alt="City Government of Valenzuela" class="w-11 h-11 object-contain rounded-full bg-white shadow">
                <span class="hidden sm:block">
                    <span class="block text-sm font-extrabold tracking-tight text-white dark:text-white nav-brand">Legislative Archive System</span>
                    <span class="block text-[11px] text-gray-200 dark:text-gray-300 nav-brand-sub">City Government of Valenzuela</span>
                </span>
            </a>
            <div class="hidden lg:flex items-center gap-5 text-sm font-semibold">
                <a href="#features" class="nav-link" data-i18n="nav_features">Features</a>
                <a href="#about" class="nav-link" data-i18n="nav_about">About</a>
                <a href="#security" class="nav-link" data-i18n="nav_security">Security</a>
                <a href="#how-it-works" class="nav-link" data-i18n="nav_how">How It Works</a>
                <a href="#faq" class="nav-link" data-i18n="nav_faq">FAQ</a>
                <a href="#contact" class="nav-link" data-i18n="nav_contact">Contact</a>
            </div>
            <div class="hidden lg:flex items-center gap-3">
                <div class="lang-toggle" role="group" aria-label="Language">
                    <button type="button" class="lang-btn" data-lang="en">EN</button>
                    <button type="button" class="lang-btn" data-lang="tl">FIL</button>
                </div>
                <button id="theme-toggle" type="button" aria-label="Toggle theme" class="nav-link text-xl w-10 h-10 flex items-center justify-center rounded-full hover:bg-white/10 transition-colors"></button>
                <a href="login.php" class="text-sm font-semibold text-white dark:text-white nav-login px-4 py-2.5 rounded-lg transition-colors" data-i18n="nav_login">Login</a>
                <a href="registering.php" class="text-sm font-semibold text-white bg-gradient-to-r from-red-600 to-orange-500 hover:from-red-700 hover:to-orange-600 px-4 py-2.5 rounded-lg shadow-lg shadow-red-900/30 transition-all nav-register" data-i18n="nav_register">Create Account</a>
            </div>
            <button id="nav-toggle" type="button" aria-label="Toggle menu" class="lg:hidden text-2xl text-white dark:text-white w-11 h-11 flex items-center justify-center rounded-lg hover:bg-white/10 transition-colors">
                <i class="bi bi-list"></i>
            </button>
        </nav>
        <div id="mobile-menu" class="hidden lg:hidden mx-4 mb-3 rounded-2xl bg-white/95 dark:bg-slate-900/95 backdrop-blur border border-gray-200 dark:border-slate-700 shadow-2xl overflow-hidden">
            <div class="flex flex-col p-4 gap-1">
                <a href="#features" class="mobile-link px-4 py-3 rounded-lg text-gray-800 dark:text-gray-200 font-semibold hover:bg-red-50 dark:hover:bg-red-900/20" data-i18n="nav_features">Features</a>
                <a href="#about" class="mobile-link px-4 py-3 rounded-lg text-gray-800 dark:text-gray-200 font-semibold hover:bg-red-50 dark:hover:bg-red-900/20" data-i18n="nav_about">About</a>
                <a href="#security" class="mobile-link px-4 py-3 rounded-lg text-gray-800 dark:text-gray-200 font-semibold hover:bg-red-50 dark:hover:bg-red-900/20" data-i18n="nav_security">Security</a>
                <a href="#how-it-works" class="mobile-link px-4 py-3 rounded-lg text-gray-800 dark:text-gray-200 font-semibold hover:bg-red-50 dark:hover:bg-red-900/20" data-i18n="nav_how">How It Works</a>
                <a href="#faq" class="mobile-link px-4 py-3 rounded-lg text-gray-800 dark:text-gray-200 font-semibold hover:bg-red-50 dark:hover:bg-red-900/20" data-i18n="nav_faq">FAQ</a>
                <a href="#contact" class="mobile-link px-4 py-3 rounded-lg text-gray-800 dark:text-gray-200 font-semibold hover:bg-red-50 dark:hover:bg-red-900/20" data-i18n="nav_contact">Contact</a>
                <div class="flex items-center justify-between mt-3 border-t border-gray-200 dark:border-slate-700 pt-3">
                    <div class="lang-toggle" role="group" aria-label="Language">
                        <button type="button" class="lang-btn lang-btn-mobile" data-lang="en">EN</button>
                        <button type="button" class="lang-btn lang-btn-mobile" data-lang="tl">FIL</button>
                    </div>
                    <button id="theme-toggle-mobile" type="button" aria-label="Toggle theme" class="text-xl w-10 h-10 flex items-center justify-center rounded-full text-gray-800 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-slate-800 transition-colors"></button>
                </div>
                <div class="grid grid-cols-2 gap-3 mt-3">
                    <a href="login.php" class="text-center py-3 rounded-lg border-2 border-gray-300 dark:border-slate-600 text-gray-800 dark:text-gray-200 font-semibold" data-i18n="nav_login">Login</a>
                    <a href="registering.php" class="text-center py-3 rounded-lg bg-gradient-to-r from-red-600 to-orange-500 text-white font-semibold" data-i18n="nav_register">Create Account</a>
                </div>
            </div>
        </div>
    </header>

    <section id="home" class="relative min-h-screen flex items-center justify-center overflow-hidden">
        <div id="hero-carousel" class="absolute inset-0" data-hero-images='<?php echo $hero_images_json; ?>' data-hero-captions='<?php echo $hero_captions_json; ?>'></div>
        <div class="absolute inset-0 bg-gradient-to-b from-black/70 via-black/60 to-black/80"></div>
        <div id="hero-caption" class="hero-caption">
            <p class="hero-caption-title"></p>
            <p class="hero-caption-sub"></p>
        </div>
        <div id="hero-dots" class="hero-dots" role="group" aria-label="Photo slides"></div>
        <div id="hero-progress" class="hero-progress"><div id="hero-progress-bar" class="hero-progress-bar"></div></div>
        <button type="button" id="hero-prev" class="hero-nav-btn hero-nav-prev" aria-label="Previous photo"><i class="bi bi-chevron-left"></i></button>
        <button type="button" id="hero-next" class="hero-nav-btn hero-nav-next" aria-label="Next photo"><i class="bi bi-chevron-right"></i></button>
        <div class="relative z-10 max-w-4xl mx-auto text-center px-6 pt-32 pb-24">
            <span class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full border border-white/25 bg-white/10 backdrop-blur text-white text-xs font-bold uppercase tracking-widest">
                <span class="w-1.5 h-1.5 rounded-full bg-orange-400 animate-pulse"></span>
                Valenzuela City Legislative Office
            </span>
            <h1 class="mt-6 text-4xl sm:text-6xl lg:text-7xl font-extrabold tracking-tight text-white leading-tight">
                Tayo na, <span class="grad-text">Valenzuela!</span>
            </h1>
            <p class="mt-4 text-lg sm:text-2xl font-semibold text-orange-300" data-i18n="hero_tagline">Preserving the legislative legacy of every Valenzueño.</p>
            <p class="mt-5 text-sm sm:text-base text-gray-200 max-w-2xl mx-auto leading-relaxed" data-i18n="hero_text">
                Welcome to the Legislative Archive System — the secure digital home for the city's ordinances,
                resolutions, and official records. Explore, request, and access important documents anytime, anywhere.
            </p>
            <div class="mt-9 flex flex-col sm:flex-row items-center justify-center gap-4">
                <a href="login.php" class="w-full sm:w-auto px-8 py-4 rounded-xl bg-gradient-to-r from-red-600 to-orange-500 hover:from-red-700 hover:to-orange-600 text-white font-bold text-lg shadow-xl shadow-red-900/40 hover:-translate-y-0.5 transition-all">
                    <span data-i18n="hero_login">Login to Get Started</span> <i class="bi bi-arrow-right ml-1"></i>
                </a>
                <a href="#features" class="w-full sm:w-auto px-8 py-4 rounded-xl border-2 border-white/40 text-white font-bold text-lg hover:bg-white/10 backdrop-blur transition-all" data-i18n="hero_explore">
                    Explore Features
                </a>
            </div>
        </div>
        <a href="#features" class="absolute bottom-7 left-1/2 -translate-x-1/2 z-10 flex flex-col items-center gap-2 text-white/80 hover:text-white transition-colors" aria-label="Scroll to features">
            <span class="text-[11px] font-semibold uppercase tracking-widest" data-i18n="hero_scroll">Scroll</span>
            <span class="mouse">
                <span class="mouse-wheel"></span>
            </span>
        </a>
    </section>

    <section id="features" class="py-20 sm:py-28 bg-gray-50 dark:bg-slate-900">
        <div class="max-w-7xl mx-auto px-4 sm:px-6">
            <div class="text-center max-w-2xl mx-auto reveal reveal-left">
                <span class="section-eyebrow" data-i18n="feat_eyebrow">Features &amp; Capabilities</span>
                <h2 class="mt-3 text-3xl sm:text-4xl font-extrabold tracking-tight" data-i18n="feat_title">Everything a modern legislative office needs</h2>
                <p class="mt-4 text-gray-600 dark:text-gray-400" data-i18n="feat_sub">Powerful tools to organize, preserve, and retrieve the records that shape Valenzuela City.</p>
            </div>
            <div class="mt-14 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 stagger-grid">
                <div class="feature-card reveal reveal-left">
                    <div class="feature-icon"><i class="bi bi-archive"></i></div>
                    <h3 class="text-lg font-bold" data-i18n="feat1_t">Digital Records Archive</h3>
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-400 leading-relaxed" data-i18n="feat1_d">Ordinances, resolutions, and official documents stored in one secure, organized system.</p>
                </div>
                <div class="feature-card reveal">
                    <div class="feature-icon"><i class="bi bi-search"></i></div>
                    <h3 class="text-lg font-bold" data-i18n="feat2_t">Smart Search</h3>
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-400 leading-relaxed" data-i18n="feat2_d">Find any record instantly by title, keyword, author, or folder.</p>
                </div>
                <div class="feature-card reveal reveal-right">
                    <div class="feature-icon"><i class="bi bi-clock-history"></i></div>
                    <h3 class="text-lg font-bold" data-i18n="feat3_t">Version Tracking</h3>
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-400 leading-relaxed" data-i18n="feat3_d">View the full history of a document — every revision tracked and preserved.</p>
                </div>
                <div class="feature-card reveal reveal-left">
                    <div class="feature-icon"><i class="bi bi-shield-check"></i></div>
                    <h3 class="text-lg font-bold" data-i18n="feat4_t">Secure Downloads</h3>
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-400 leading-relaxed" data-i18n="feat4_d">Request downloads protected by one-time password verification for peace of mind.</p>
                </div>
                <div class="feature-card reveal">
                    <div class="feature-icon"><i class="bi bi-google"></i></div>
                    <h3 class="text-lg font-bold" data-i18n="feat5_t">Google Auth</h3>
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-400 leading-relaxed" data-i18n="feat5_d">One-click sign in and registration with your Google account via secure OAuth 2.0.</p>
                </div>
                <div class="feature-card reveal reveal-right">
                    <div class="feature-icon"><i class="bi bi-hdd-network"></i></div>
                    <h3 class="text-lg font-bold" data-i18n="feat6_t">IPFS Pinata</h3>
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-400 leading-relaxed" data-i18n="feat6_d">Documents pinned to IPFS via Pinata for tamper-proof, decentralized storage.</p>
                </div>
            </div>
        </div>
    </section>

    <section id="about" class="py-20 sm:py-28 bg-white dark:bg-slate-950">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 grid lg:grid-cols-2 gap-12 lg:gap-16 items-center">
            <div class="reveal reveal-left">
                <span class="section-eyebrow" data-i18n="about_eyebrow">About the System</span>
                <h2 class="mt-3 text-3xl sm:text-4xl font-extrabold tracking-tight" data-i18n="about_title">Bringing Valenzuela's legislative records into the digital age</h2>
                <p class="mt-5 text-gray-600 dark:text-gray-400 leading-relaxed" data-i18n="about_text">
                    The Legislative Archive System is the official digital repository of the City Government of Valenzuela.
                    It safeguards the city's legislative history, making it easier for offices, researchers, and citizens
                    to find and use the documents that guide the city forward.
                </p>
                <div class="mt-8 grid sm:grid-cols-2 gap-4 stagger-grid">
                    <div class="p-5 rounded-2xl border border-gray-200 dark:border-slate-700 bg-gray-50 dark:bg-slate-900 reveal reveal-left">
                        <div class="text-red-600 dark:text-orange-400 text-2xl"><i class="bi bi-bullseye"></i></div>
                        <h4 class="mt-2 font-bold" data-i18n="about_mission_t">Our Mission</h4>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400" data-i18n="about_mission_d">To preserve, organize, and make accessible every legislative record of the city with accuracy and care.</p>
                    </div>
                    <div class="p-5 rounded-2xl border border-gray-200 dark:border-slate-700 bg-gray-50 dark:bg-slate-900 reveal reveal-right">
                        <div class="text-red-600 dark:text-orange-400 text-2xl"><i class="bi bi-eye"></i></div>
                        <h4 class="mt-2 font-bold" data-i18n="about_vision_t">Our Vision</h4>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400" data-i18n="about_vision_d">A transparent, efficient, and future-ready legislative office that serves every Valenzueño.</p>
                    </div>
                </div>
            </div>
            <div class="reveal reveal-right relative">
                <div id="about-carousel" class="carousel relative rounded-3xl overflow-hidden shadow-2xl shadow-red-900/20 ring-1 ring-gray-200 dark:ring-slate-700" data-images='<?php echo $carousel_images_json; ?>'>
                    <div class="carousel-track"></div>
                    <div class="absolute inset-0 bg-gradient-to-t from-black/60 to-transparent pointer-events-none"></div>
                    <button class="carousel-btn carousel-prev" type="button" aria-label="Previous photo"><i class="bi bi-chevron-left"></i></button>
                    <button class="carousel-btn carousel-next" type="button" aria-label="Next photo"><i class="bi bi-chevron-right"></i></button>
                    <div class="carousel-dots"></div>
                    <div class="absolute bottom-5 left-5 right-5 flex items-center gap-4 rounded-2xl bg-white/95 dark:bg-slate-900/95 backdrop-blur px-5 py-4 shadow-lg pointer-events-none">
                        <div class="w-12 h-12 rounded-full bg-gradient-to-br from-red-600 to-orange-500 flex items-center justify-center text-white text-xl flex-shrink-0"><i class="bi bi-buildings"></i></div>
                        <div>
                            <p class="font-bold text-sm">City Government of Valenzuela</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400" data-i18n="about_caption_d">Serving the people of Valenzuela</p>
                        </div>
                    </div>
                </div>
                <div id="about-carousel-thumbs" class="carousel-thumbs" aria-label="Photo thumbnails"></div>
            </div>
        </div>
    </section>

    <section id="security" class="py-20 sm:py-28 bg-slate-900 dark:bg-slate-950">
        <div class="max-w-7xl mx-auto px-4 sm:px-6">
            <div class="text-center max-w-2xl mx-auto reveal reveal-right">
                <span class="section-eyebrow section-eyebrow-dark" data-i18n="sec_eyebrow">Trust &amp; Security</span>
                <h2 class="mt-3 text-3xl sm:text-4xl font-extrabold tracking-tight text-white" data-i18n="sec_title">Your records, protected at every step</h2>
                <p class="mt-4 text-gray-400" data-i18n="sec_sub">We treat every document as the public trust it is — secured by design.</p>
            </div>
            <div class="mt-14 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 stagger-grid">
                <div class="security-card reveal reveal-left">
                    <div class="security-icon"><i class="bi bi-envelope-check"></i></div>
                    <h3 class="text-base font-bold text-white" data-i18n="sec1_t">OTP Verification</h3>
                    <p class="mt-2 text-sm text-gray-400 leading-relaxed" data-i18n="sec1_d">Downloads are protected by a one-time password sent to your email.</p>
                </div>
                <div class="security-card reveal">
                    <div class="security-icon"><i class="bi bi-shield-lock"></i></div>
                    <h3 class="text-base font-bold text-white" data-i18n="sec2_t">Role-Based Access</h3>
                    <p class="mt-2 text-sm text-gray-400 leading-relaxed" data-i18n="sec2_d">Admins, staff, and users only see what their role permits.</p>
                </div>
                <div class="security-card reveal">
                    <div class="security-icon"><i class="bi bi-stopwatch"></i></div>
                    <h3 class="text-base font-bold text-white" data-i18n="sec3_t">Session Security</h3>
                    <p class="mt-2 text-sm text-gray-400 leading-relaxed" data-i18n="sec3_d">Automatic timeouts and lockouts stop unauthorized use.</p>
                </div>
                <div class="security-card reveal reveal-right">
                    <div class="security-icon"><i class="bi bi-journal-text"></i></div>
                    <h3 class="text-base font-bold text-white" data-i18n="sec4_t">Audit Logs</h3>
                    <p class="mt-2 text-sm text-gray-400 leading-relaxed" data-i18n="sec4_d">Every action is logged for transparency and accountability.</p>
                </div>
            </div>
        </div>
    </section>

    <section id="how-it-works" class="py-20 sm:py-28 bg-white dark:bg-slate-900">
        <div class="max-w-7xl mx-auto px-4 sm:px-6">
            <div class="text-center max-w-2xl mx-auto reveal reveal-left">
                <span class="section-eyebrow" data-i18n="num_eyebrow">Our Numbers</span>
                <h2 class="mt-3 text-3xl sm:text-4xl font-extrabold tracking-tight" data-i18n="num_title">A growing archive, built for the city</h2>
            </div>
            <div class="mt-12 grid grid-cols-2 lg:grid-cols-4 gap-6 stagger-grid">
                <div class="stat-card reveal reveal-left">
                    <div class="stat-value" data-count="<?php echo $stat_records; ?>">0</div>
                    <div class="stat-label" data-i18n="stat1">Records Archived</div>
                </div>
                <div class="stat-card reveal">
                    <div class="stat-value" data-count="<?php echo $stat_folders; ?>">0</div>
                    <div class="stat-label" data-i18n="stat2">Document Folders</div>
                </div>
                <div class="stat-card reveal">
                    <div class="stat-value" data-count="<?php echo $stat_users; ?>">0</div>
                    <div class="stat-label" data-i18n="stat3">Registered Users</div>
                </div>
                <div class="stat-card reveal reveal-right">
                    <div class="stat-value" data-count="<?php echo $stat_downloads; ?>">0</div>
                    <div class="stat-label" data-i18n="stat4">Downloads Tracked</div>
                </div>
            </div>

            <div class="mt-24 text-center max-w-2xl mx-auto reveal reveal-right">
                <span class="section-eyebrow" data-i18n="how_eyebrow">How It Works</span>
                <h2 class="mt-3 text-3xl sm:text-4xl font-extrabold tracking-tight" data-i18n="how_title">Getting started takes three simple steps</h2>
            </div>
            <div class="mt-12 grid lg:grid-cols-3 gap-6 stagger-grid">
                <div class="step-card reveal reveal-left">
                    <div class="step-number">1</div>
                    <div class="step-icon"><i class="bi bi-person-plus"></i></div>
                    <h3 class="text-lg font-bold" data-i18n="step1_t">Create your account</h3>
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-400 leading-relaxed" data-i18n="step1_d">Register and get approved by an administrator.</p>
                </div>
                <div class="step-card reveal">
                    <div class="step-number">2</div>
                    <div class="step-icon"><i class="bi bi-folder2-open"></i></div>
                    <h3 class="text-lg font-bold" data-i18n="step2_t">Search &amp; browse</h3>
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-400 leading-relaxed" data-i18n="step2_d">Find the record you need by folder, title, or keyword.</p>
                </div>
                <div class="step-card reveal reveal-right">
                    <div class="step-number">3</div>
                    <div class="step-icon"><i class="bi bi-download"></i></div>
                    <h3 class="text-lg font-bold" data-i18n="step3_t">Download securely</h3>
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-400 leading-relaxed" data-i18n="step3_d">Confirm with a one-time password and the document is yours.</p>
                </div>
            </div>
        </div>
    </section>

    <section id="faq" class="py-20 sm:py-28 bg-gray-50 dark:bg-slate-950">
        <div class="max-w-3xl mx-auto px-4 sm:px-6">
            <div class="text-center reveal reveal-left">
                <span class="section-eyebrow" data-i18n="faq_eyebrow">Frequently Asked Questions</span>
                <h2 class="mt-3 text-3xl sm:text-4xl font-extrabold tracking-tight" data-i18n="faq_title">Questions? We've got answers</h2>
            </div>
            <div class="mt-12 space-y-3">
                <div class="faq-item reveal">
                    <button type="button" class="faq-question">
                        <span data-i18n="faq1q">What is the Legislative Archive System?</span>
                        <i class="bi bi-plus-lg faq-icon text-red-600 dark:text-orange-400"></i>
                    </button>
                    <div class="faq-answer">
                        <p class="px-6 pb-5 text-sm text-gray-600 dark:text-gray-400 leading-relaxed" data-i18n="faq1a">It is the official digital archive of the Valenzuela City Legislative Office. It stores, organizes, and protects the city's ordinances, resolutions, and official records in one secure system &mdash; replacing paper-based filing with a fast, searchable digital repository.</p>
                    </div>
                </div>
                <div class="faq-item reveal">
                    <button type="button" class="faq-question">
                        <span data-i18n="faq2q">Who can access the system?</span>
                        <i class="bi bi-plus-lg faq-icon text-red-600 dark:text-orange-400"></i>
                    </button>
                    <div class="faq-answer">
                        <p class="px-6 pb-5 text-sm text-gray-600 dark:text-gray-400 leading-relaxed" data-i18n="faq2a">City employees and authorized users of the Legislative Office. New accounts are created through registration and must be approved by an administrator before first sign-in. Each account's access depends on the assigned role (admin, staff, or user).</p>
                    </div>
                </div>
                <div class="faq-item reveal">
                    <button type="button" class="faq-question">
                        <span data-i18n="faq3q">How do I request or download a document?</span>
                        <i class="bi bi-plus-lg faq-icon text-red-600 dark:text-orange-400"></i>
                    </button>
                    <div class="faq-answer">
                        <p class="px-6 pb-5 text-sm text-gray-600 dark:text-gray-400 leading-relaxed" data-i18n="faq3a">Sign in, search or browse for the record you need, and open it. Click the download button and the system sends a one-time password (OTP) to your registered email &mdash; enter it to confirm and the document downloads securely. Downloads are limited to supported formats (PDF and Word documents).</p>
                    </div>
                </div>
                <div class="faq-item reveal">
                    <button type="button" class="faq-question">
                        <span data-i18n="faq4q">Is my data secure?</span>
                        <i class="bi bi-plus-lg faq-icon text-red-600 dark:text-orange-400"></i>
                    </button>
                    <div class="faq-answer">
                        <p class="px-6 pb-5 text-sm text-gray-600 dark:text-gray-400 leading-relaxed" data-i18n="faq4a">Yes. The system protects records through OTP-verified downloads, role-based access control, automatic session timeouts, and complete audit logging. Every access and download is recorded for transparency and accountability.</p>
                    </div>
                </div>
                <div class="faq-item reveal">
                    <button type="button" class="faq-question">
                        <span data-i18n="faq5q">Can I see the history of a document?</span>
                        <i class="bi bi-plus-lg faq-icon text-red-600 dark:text-orange-400"></i>
                    </button>
                    <div class="faq-answer">
                        <p class="px-6 pb-5 text-sm text-gray-600 dark:text-gray-400 leading-relaxed" data-i18n="faq5a">Yes. Version tracking records every revision of a document. You can view past versions, see when they were added, and compare different versions side by side to follow changes over time.</p>
                    </div>
                </div>
                <div class="faq-item reveal">
                    <button type="button" class="faq-question">
                        <span data-i18n="faq6q">I forgot my password. What do I do?</span>
                        <i class="bi bi-plus-lg faq-icon text-red-600 dark:text-orange-400"></i>
                    </button>
                    <div class="faq-answer">
                        <p class="px-6 pb-5 text-sm text-gray-600 dark:text-gray-400 leading-relaxed" data-i18n="faq6a">Click the "Forgot password" link on the login page and enter your registered email. A secure password reset link will be sent to you. If you don't receive it, contact the system administrator for assistance.</p>
                    </div>
                </div>
                <div class="faq-item reveal">
                    <button type="button" class="faq-question">
                        <span data-i18n="faq7q">How do I create an account?</span>
                        <i class="bi bi-plus-lg faq-icon text-red-600 dark:text-orange-400"></i>
                    </button>
                    <div class="faq-answer">
                        <p class="px-6 pb-5 text-sm text-gray-600 dark:text-gray-400 leading-relaxed" data-i18n="faq7a">Click "Create Account" on the login page and fill out the registration form. Your account will be reviewed and approved by an administrator. You'll then be able to sign in using the login page.</p>
                    </div>
                </div>
                <div class="faq-item reveal">
                    <button type="button" class="faq-question">
                        <span data-i18n="faq8q">What file formats are supported?</span>
                        <i class="bi bi-plus-lg faq-icon text-red-600 dark:text-orange-400"></i>
                    </button>
                    <div class="faq-answer">
                        <p class="px-6 pb-5 text-sm text-gray-600 dark:text-gray-400 leading-relaxed" data-i18n="faq8a">The system supports PDF and Word documents (PDF, DOC, DOCX) for download. Some documents can also be previewed directly in the browser.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section id="contact" class="py-20 sm:py-28 bg-white dark:bg-slate-900">
        <div class="max-w-7xl mx-auto px-4 sm:px-6">
            <div class="text-center max-w-2xl mx-auto reveal reveal-right">
                <span class="section-eyebrow" data-i18n="contact_eyebrow">Contact Us</span>
                <h2 class="mt-3 text-3xl sm:text-4xl font-extrabold tracking-tight" data-i18n="contact_title">Reach the Legislative Office</h2>
                <p class="mt-4 text-gray-600 dark:text-gray-400" data-i18n="contact_sub">Questions, requests, or feedback? Send us a message.</p>
            </div>
            <div class="mt-14 grid lg:grid-cols-2 gap-10 items-start">
                <div class="space-y-4 reveal reveal-left">
                    <div class="contact-card">
                        <div class="contact-icon"><i class="bi bi-geo-alt"></i></div>
                        <div>
                            <p class="text-xs font-bold uppercase tracking-widest text-gray-400" data-i18n="c_address_l">Address</p>
                            <p class="mt-1 text-sm font-semibold">Valenzuela City Legislative Office, Poblacion, City of Valenzuela, Metro Manila</p>
                        </div>
                    </div>
                    <div class="contact-card">
                        <div class="contact-icon"><i class="bi bi-clock"></i></div>
                        <div>
                            <p class="text-xs font-bold uppercase tracking-widest text-gray-400" data-i18n="c_hours_l">Office Hours</p>
                            <p class="mt-1 text-sm font-semibold" data-i18n="c_hours_v">Monday - Friday: 8:00 AM - 5:00 PM</p>
                        </div>
                    </div>
                    <div class="contact-card">
                        <div class="contact-icon"><i class="bi bi-telephone"></i></div>
                        <div>
                            <p class="text-xs font-bold uppercase tracking-widest text-gray-400" data-i18n="c_phone_l">Phone</p>
                            <p class="mt-1 text-sm font-semibold">(02) 8-XXX-XXXX</p>
                        </div>
                    </div>
                    <div class="contact-card">
                        <div class="contact-icon"><i class="bi bi-envelope"></i></div>
                        <div>
                            <p class="text-xs font-bold uppercase tracking-widest text-gray-400" data-i18n="c_email_l">Email</p>
                            <p class="mt-1 text-sm font-semibold">legislative@valenzuela.gov.ph</p>
                        </div>
                    </div>
                </div>
                <div class="reveal reveal-right">
                    <form id="contact-form" class="contact-form" method="POST" action="api/contact-submit.php" novalidate>
                        <input type="text" name="website" class="hidden" tabindex="-1" autocomplete="off" aria-hidden="true">
                        <div class="space-y-4">
                            <div>
                                <label for="cf-name" data-i18n="form_name_l">Your Name</label>
                                <input type="text" id="cf-name" name="name" required maxlength="150" autocomplete="name" data-i18n-placeholder="form_name_ph" placeholder="Enter your full name">
                            </div>
                            <div>
                                <label for="cf-email" data-i18n="form_email_l">Email Address</label>
                                <input type="email" id="cf-email" name="email" required maxlength="200" autocomplete="email" data-i18n-placeholder="form_email_ph" placeholder="Enter your email address">
                            </div>
                            <div>
                                <label for="cf-dept" data-i18n="form_dept_l">Office/Department (optional)</label>
                                <input type="text" id="cf-dept" name="department" maxlength="200" data-i18n-placeholder="form_dept_ph" placeholder="e.g. Engineering Office">
                            </div>
                            <div>
                                <label for="cf-msg" data-i18n="form_msg_l">Message</label>
                                <textarea id="cf-msg" name="message" rows="5" required maxlength="5000" data-i18n-placeholder="form_msg_ph" placeholder="Type your message here..."></textarea>
                            </div>
                            <div id="contact-status" class="form-status" role="status" aria-live="polite"></div>
                            <button type="submit" id="contact-submit" class="w-full py-3.5 rounded-xl bg-gradient-to-r from-red-600 to-orange-500 hover:from-red-700 hover:to-orange-600 text-white font-bold transition-all">
                                <span data-i18n="form_submit">Send Message</span> <i class="bi bi-send ml-1"></i>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </section>

    <section id="cta" class="relative py-24 sm:py-32 overflow-hidden">
        <div class="absolute inset-0 bg-cover bg-center" style="background-image:url('<?php echo $IMG_CTA; ?>')"></div>
        <div class="absolute inset-0 bg-black/70"></div>
        <div class="relative z-10 max-w-3xl mx-auto text-center px-6 reveal">
            <span class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full border border-white/25 bg-white/10 backdrop-blur text-white text-xs font-bold uppercase tracking-widest">
                <i class="bi bi-stars"></i> Tayo na, Valenzuela
            </span>
            <h2 class="mt-6 text-3xl sm:text-5xl font-extrabold tracking-tight text-white" data-i18n="cta_title">Ready to explore Valenzuela's legislative records?</h2>
            <p class="mt-4 text-gray-300" data-i18n="cta_sub">Sign in to browse the archive, track documents, and download the records you need.</p>
            <div class="mt-9 flex flex-col sm:flex-row items-center justify-center gap-4">
                <a href="login.php" class="w-full sm:w-auto px-8 py-4 rounded-xl bg-gradient-to-r from-red-600 to-orange-500 hover:from-red-700 hover:to-orange-600 text-white font-bold text-lg shadow-xl shadow-red-900/40 hover:-translate-y-0.5 transition-all">
                    <span data-i18n="cta_login">Login to Get Started</span> <i class="bi bi-arrow-right ml-1"></i>
                </a>
                <a href="registering.php" class="w-full sm:w-auto px-8 py-4 rounded-xl border-2 border-white/40 text-white font-bold text-lg hover:bg-white/10 backdrop-blur transition-all" data-i18n="cta_register">
                    Create an Account
                </a>
            </div>
        </div>
    </section>

    <footer class="bg-slate-900 dark:bg-slate-950 text-gray-300">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 py-14 grid gap-10 md:grid-cols-3">
            <div>
                <div class="flex items-center gap-3">
                    <img src="<?php echo $LOGO; ?>" alt="City Government of Valenzuela" class="w-12 h-12 object-contain rounded-full bg-white">
                    <div>
                        <p class="font-extrabold text-white">Legislative Archive System</p>
                        <p class="text-xs text-gray-400">City Government of Valenzuela</p>
                    </div>
                </div>
                <p class="mt-4 text-sm text-gray-400 leading-relaxed" data-i18n="foot_tagline">Preserving the legislative legacy of Valenzuela City — one record at a time.</p>
            </div>
            <div>
                <p class="font-bold text-white uppercase tracking-widest text-xs mb-4" data-i18n="foot_quicklinks">Quick Links</p>
                <ul class="space-y-2 text-sm">
                    <li><a href="#home" class="hover:text-orange-400 transition-colors" data-i18n="foot_home">Home</a></li>
                    <li><a href="#features" class="hover:text-orange-400 transition-colors" data-i18n="foot_features">Features</a></li>
                    <li><a href="#about" class="hover:text-orange-400 transition-colors" data-i18n="foot_about">About</a></li>
                    <li><a href="#security" class="hover:text-orange-400 transition-colors" data-i18n="foot_security">Security</a></li>
                    <li><a href="#faq" class="hover:text-orange-400 transition-colors" data-i18n="foot_faq">FAQ</a></li>
                    <li><a href="#contact" class="hover:text-orange-400 transition-colors" data-i18n="foot_contact">Contact</a></li>
                </ul>
            </div>
            <div>
                <p class="font-bold text-white uppercase tracking-widest text-xs mb-4" data-i18n="foot_getstarted">Get Started</p>
                <ul class="space-y-2 text-sm">
                    <li><a href="login.php" class="hover:text-orange-400 transition-colors" data-i18n="foot_login">Login</a></li>
                    <li><a href="registering.php" class="hover:text-orange-400 transition-colors" data-i18n="foot_register">Create Account</a></li>
                    <li><a href="forgot-password.php" class="hover:text-orange-400 transition-colors" data-i18n="foot_forgot">Forgot Password</a></li>
                    <li><a href="login.php" class="hover:text-orange-400 transition-colors" data-i18n="foot_terms">Terms &amp; Conditions</a></li>
                </ul>
            </div>
        </div>
        <div class="border-t border-white/10">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 py-5 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-gray-400">
                <p>&copy; <?php echo date('Y'); ?> <span data-i18n="foot_copy">City Government of Valenzuela. All rights reserved.</span></p>
                <p>Legislative Archive System <span class="mx-1">|</span> Metropolitan Manila</p>
            </div>
        </div>
    </footer>

    <button id="back-to-top" class="back-to-top" type="button" aria-label="Back to top">
        <i class="bi bi-arrow-up"></i>
    </button>

    <script src="assets/js/landing-i18n.js"></script>
    <script src="assets/js/landing.js"></script>
</body>
</html>
