(function () {
    var LANDING_I18N = {
        nav_features: { en: "Features", tl: "Mga Tampok" },
        nav_about: { en: "About", tl: "Tungkol" },
        nav_security: { en: "Security", tl: "Seguridad" },
        nav_how: { en: "How It Works", tl: "Paano Ito Gumagana" },
        nav_faq: { en: "FAQ", tl: "FAQ" },
        nav_contact: { en: "Contact", tl: "Makipag-ugnayan" },
        nav_login: { en: "Login", tl: "Mag-login" },
        nav_register: { en: "Create Account", tl: "Gumawa ng Account" },

        hero_tagline: { en: "Preserving the legislative legacy of every Valenzueño.", tl: "Pinapanatili ang pambatasang pamana ng bawat Valenzueño." },
        hero_text: { en: "Welcome to the Legislative Archive System — the secure digital home for the city's ordinances, resolutions, and official records. Explore, request, and access important documents anytime, anywhere.", tl: "Maligayang pagdating sa Legislative Archive System — ang ligtas na digital na tahanan ng mga ordinansa, resolusyon, at opisyal na rekord ng lungsod. Tuklasin, hingin, at i-access ang mahahalagang dokumento anumang oras, kahit saan." },
        hero_login: { en: "Login to Get Started", tl: "Mag-login para Magsimula" },
        hero_explore: { en: "Explore Features", tl: "Tingnan ang mga Tampok" },
        hero_scroll: { en: "Scroll", tl: "I-scroll" },

        feat_eyebrow: { en: "Features & Capabilities", tl: "Mga Tampok at Kakayahan" },
        feat_title: { en: "Everything a modern legislative office needs", tl: "Lahat ng kailangan ng modernong opisina ng lehislatura" },
        feat_sub: { en: "Powerful tools to organize, preserve, and retrieve the records that shape Valenzuela City.", tl: "Makapangyarihang mga kasangkapan upang ayusin, pangalagaan, at hanapin ang mga rekord na humuhubog sa Lungsod ng Valenzuela." },
        feat1_t: { en: "Digital Records Archive", tl: "Digital na Archive ng mga Rekord" },
        feat1_d: { en: "Ordinances, resolutions, and official documents stored in one secure, organized system.", tl: "Mga ordinansa, resolusyon, at opisyal na dokumento na nakalagak sa isang ligtas at organisadong sistema." },
        feat2_t: { en: "Smart Search", tl: "Matalinong Paghahanap" },
        feat2_d: { en: "Find any record instantly by title, keyword, author, or folder.", tl: "Hanapin ang anumang rekord agad-agad ayon sa pamagat, keyword, may-akda, o folder." },
        feat3_t: { en: "Version Tracking", tl: "Pagsubaybay sa Bersyon" },
        feat3_d: { en: "View the full history of a document — every revision tracked and preserved.", tl: "Tingnan ang buong kasaysayan ng isang dokumento — bawat rebisyon ay nasusubaybayan at napapangalagaan." },
        feat4_t: { en: "Secure Downloads", tl: "Ligtas na Pag-download" },
        feat4_d: { en: "Request downloads protected by one-time password verification for peace of mind.", tl: "Hingin ang pag-download na protektado ng one-time password para sa kapayapaan ng isip." },
        feat5_t: { en: "Google Sign-In (OAuth 2.0)", tl: "Google Sign-In (OAuth 2.0)" },
        feat5_d: { en: "Log in securely with your Google account using OAuth 2.0 identity verification.", tl: "Mag-login nang ligtas gamit ang iyong Google account sa pamamagitan ng OAuth 2.0 identity verification." },
        feat6_t: { en: "IPFS Storage (Pinata)", tl: "IPFS Storage (Pinata)" },
        feat6_d: { en: "Records are pinned to the IPFS network via Pinata for permanent, decentralized backup.", tl: "Ang mga rekord ay naka-pin sa IPFS network sa pamamagitan ng Pinata para sa permanenteng, desentralisadong backup." },

        about_eyebrow: { en: "About the System", tl: "Tungkol sa Sistema" },
        about_title: { en: "Bringing Valenzuela's legislative records into the digital age", tl: "Dinadala ang mga pambatasang rekord ng Valenzuela sa digital na panahon" },
        about_text: { en: "The Legislative Archive System is the official digital repository of the City Government of Valenzuela. It safeguards the city's legislative history, making it easier for offices, researchers, and citizens to find and use the documents that guide the city forward.", tl: "Ang Legislative Archive System ay ang opisyal na digital repositoryo ng Pamahalaang Lungsod ng Valenzuela. Pinangangalagaan nito ang kasaysayang pambatas ng lungsod, na ginagawang mas madali para sa mga opisina, mananaliksik, at mamamayan na hanapin at gamitin ang mga dokumentong gumagabay sa pag-unlad ng lungsod." },
        about_mission_t: { en: "Our Mission", tl: "Ang Aming Misyon" },
        about_mission_d: { en: "To preserve, organize, and make accessible every legislative record of the city with accuracy and care.", tl: "Upang pangalagaan, ayusin, at gawing naa-access ang bawat pambatasang rekord ng lungsod nang may kawastuhan at malasakit." },
        about_vision_t: { en: "Our Vision", tl: "Ang Aming Bisyon" },
        about_vision_d: { en: "A transparent, efficient, and future-ready legislative office that serves every Valenzueño.", tl: "Isang transparent, mahusay, at handa sa hinaharap na opisina ng lehislatura na naglilingkod sa bawat Valenzueño." },
        about_caption_d: { en: "Serving the people of Valenzuela", tl: "Naglilingkod sa mga mamamayan ng Valenzuela" },

        sec_eyebrow: { en: "Trust & Security", tl: "Pagtitiwala at Seguridad" },
        sec_title: { en: "Your records, protected at every step", tl: "Ang iyong mga rekord, protektado sa bawat hakbang" },
        sec_sub: { en: "We treat every document as the public trust it is — secured by design.", tl: "Itinuturing namin ang bawat dokumento bilang tiwala ng publiko — ligtas sa pamamagitan ng disenyo." },
        sec1_t: { en: "OTP Verification", tl: "Pag-verify ng OTP" },
        sec1_d: { en: "Downloads are protected by a one-time password sent to your email.", tl: "Ang mga pag-download ay protektado ng one-time password na ipinapadala sa iyong email." },
        sec2_t: { en: "Role-Based Access", tl: "Access batay sa Tungkulin" },
        sec2_d: { en: "Admins, staff, and users only see what their role permits.", tl: "Ang mga admin, kawani, at gumagamit ay nakikita lamang ang pinapayagan ng kanilang tungkulin." },
        sec3_t: { en: "Session Security", tl: "Seguridad ng Session" },
        sec3_d: { en: "Automatic timeouts and lockouts stop unauthorized use.", tl: "Pinipigilan ng awtomatikong timeout at lockout ang hindi awtorisadong paggamit." },
        sec4_t: { en: "Audit Logs", tl: "Talaan ng Pag-audit" },
        sec4_d: { en: "Every action is logged for transparency and accountability.", tl: "Bawat aksyon ay naitatala para sa transparency at pananagutan." },

        num_eyebrow: { en: "Our Numbers", tl: "Ang Aming mga Bilang" },
        num_title: { en: "A growing archive, built for the city", tl: "Lumalaking archive, itinayo para sa lungsod" },
        stat1: { en: "Records Archived", tl: "Na-archive na Rekord" },
        stat2: { en: "Document Folders", tl: "Mga Folder ng Dokumento" },
        stat3: { en: "Registered Users", tl: "Nakarehistrong Gumagamit" },
        stat4: { en: "Downloads Tracked", tl: "Na-subaybayang Pag-download" },
        how_eyebrow: { en: "How It Works", tl: "Paano Ito Gumagana" },
        how_title: { en: "Getting started takes three simple steps", tl: "Tatlong simpleng hakbang lamang para magsimula" },
        step1_t: { en: "Create your account", tl: "Gumawa ng iyong account" },
        step1_d: { en: "Register and get approved by an administrator.", tl: "Magrehistro at maaprubahan ng isang administrator." },
        step2_t: { en: "Search & browse", tl: "Maghanap at mag-browse" },
        step2_d: { en: "Find the record you need by folder, title, or keyword.", tl: "Hanapin ang rekord na kailangan mo ayon sa folder, pamagat, o keyword." },
        step3_t: { en: "Download securely", tl: "Mag-download nang ligtas" },
        step3_d: { en: "Confirm with a one-time password and the document is yours.", tl: "Kumpirmahin gamit ang one-time password at sa iyo na ang dokumento." },

        faq_eyebrow: { en: "Frequently Asked Questions", tl: "Mga Madalas Itanong" },
        faq_title: { en: "Questions? We've got answers", tl: "May mga tanong? Nandito ang mga sagot" },
        faq1q: { en: "What is the Legislative Archive System?", tl: "Ano ang Legislative Archive System?" },
        faq1a: { en: "It is the official digital archive of the Valenzuela City Legislative Office. It stores, organizes, and protects the city's ordinances, resolutions, and official records in one secure system — replacing paper-based filing with a fast, searchable digital repository.", tl: "Ito ang opisyal na digital archive ng Opisina ng Lehislatura ng Lungsod ng Valenzuela. Iniimbak, inaayos, at pinoprotektahan nito ang mga ordinansa, resolusyon, at opisyal na rekord ng lungsod sa isang ligtas na sistema — pinapalitan ang papel na pagtatala ng mabilis at madaling hanapin na digital repositoryo." },
        faq2q: { en: "Who can access the system?", tl: "Sino ang maaaring gumamit ng sistema?" },
        faq2a: { en: "City employees and authorized users of the Legislative Office. New accounts are created through registration and must be approved by an administrator before first sign-in. Each account's access depends on the assigned role (admin, staff, or user).", tl: "Ang mga empleyado ng lungsod at awtorisadong gumagamit ng Opisina ng Lehislatura. Ang mga bagong account ay ginagawa sa pamamagitan ng rehistrasyon at dapat aprubahan ng administrator bago ang unang pag-sign in. Ang access ng bawat account ay nakabatay sa itinalagang tungkulin (admin, kawani, o gumagamit)." },
        faq3q: { en: "How do I request or download a document?", tl: "Paano ako humihingi o nagda-download ng dokumento?" },
        faq3a: { en: "Sign in, search or browse for the record you need, and open it. Click the download button and the system sends a one-time password (OTP) to your registered email — enter it to confirm and the document downloads securely. Downloads are limited to supported formats (PDF and Word documents).", tl: "Mag-sign in, maghanap o mag-browse para sa rekord na kailangan mo, at buksan ito. I-click ang pindutan ng pag-download at magpapadala ang sistema ng one-time password (OTP) sa iyong rehistradong email — ilagay ito upang kumpirmahin at ligtas na mada-download ang dokumento. Limitado ang mga pag-download sa suportadong mga format (PDF at Word documents)." },
        faq4q: { en: "Is my data secure?", tl: "Ligtas ba ang aking data?" },
        faq4a: { en: "Yes. The system protects records through OTP-verified downloads, role-based access control, automatic session timeouts, and complete audit logging. Every access and download is recorded for transparency and accountability.", tl: "Oo. Pinoprotektahan ng sistema ang mga rekord sa pamamagitan ng OTP-verified downloads, role-based access control, awtomatikong session timeout, at kumpletong audit logging. Bawat pag-access at pag-download ay naitatala para sa transparency at pananagutan." },
        faq5q: { en: "Can I see the history of a document?", tl: "Maaari ko bang makita ang kasaysayan ng isang dokumento?" },
        faq5a: { en: "Yes. Version tracking records every revision of a document. You can view past versions, see when they were added, and compare different versions side by side to follow changes over time.", tl: "Oo. Itinatala ng version tracking ang bawat rebisyon ng isang dokumento. Maaari mong tingnan ang mga nakaraang bersyon, makita kung kailan idinagdag ang mga ito, at ikumpara ang iba't ibang bersyon nang magkatabi upang masubaybayan ang mga pagbabago sa paglipas ng panahon." },
        faq6q: { en: "I forgot my password. What do I do?", tl: "Nakalimutan ko ang aking password. Ano ang gagawin ko?" },
        faq6a: { en: "Click the \"Forgot password\" link on the login page and enter your registered email. A secure password reset link will be sent to you. If you don't receive it, contact the system administrator for assistance.", tl: "I-click ang link na \"Forgot password\" sa login page at ilagay ang iyong rehistradong email. May ipapadalang ligtas na link para i-reset ang password. Kung hindi mo ito natanggap, makipag-ugnayan sa administrator ng sistema para sa tulong." },
        faq7q: { en: "How do I create an account?", tl: "Paano ako gagawa ng account?" },
        faq7a: { en: "Click \"Create Account\" on the login page and fill out the registration form. Your account will be reviewed and approved by an administrator. You'll then be able to sign in using the login page.", tl: "I-click ang \"Create Account\" sa login page at punan ang rehistrasyon. Susuriin at aaprubahan ng administrator ang iyong account. Pagkatapos nito, makakapag-sign in ka na gamit ang login page." },
        faq8q: { en: "What file formats are supported?", tl: "Anong mga file format ang suportado?" },
        faq8a: { en: "The system supports PDF and Word documents (PDF, DOC, DOCX) for download. Some documents can also be previewed directly in the browser.", tl: "Sinusuportahan ng sistema ang PDF at Word documents (PDF, DOC, DOCX) para sa pag-download. Ang ilang dokumento ay maaari ding i-preview nang direkta sa browser." },

        contact_eyebrow: { en: "Contact Us", tl: "Makipag-ugnayan" },
        contact_title: { en: "Reach the Legislative Office", tl: "Makipag-ugnayan sa Opisina ng Lehislatura" },
        contact_sub: { en: "Questions, requests, or feedback? Send us a message.", tl: "May mga tanong, kahilingan, o feedback? Padalhan kami ng mensahe." },
        c_address_l: { en: "Address", tl: "Address" },
        c_hours_l: { en: "Office Hours", tl: "Oras ng Opisina" },
        c_hours_v: { en: "Monday - Friday: 8:00 AM - 5:00 PM", tl: "Lunes - Biyernes: 8:00 AM - 5:00 PM" },
        c_phone_l: { en: "Phone", tl: "Telepono" },
        c_email_l: { en: "Email", tl: "Email" },
        form_name_l: { en: "Your Name", tl: "Ang Iyong Pangalan" },
        form_email_l: { en: "Email Address", tl: "Email Address" },
        form_dept_l: { en: "Office/Department (optional)", tl: "Opisina/Departmento (opsyonal)" },
        form_msg_l: { en: "Message", tl: "Mensahe" },
        form_name_ph: { en: "Enter your full name", tl: "Ilagay ang iyong buong pangalan" },
        form_email_ph: { en: "Enter your email address", tl: "Ilagay ang iyong email address" },
        form_dept_ph: { en: "e.g. Engineering Office", tl: "hal. Engineering Office" },
        form_msg_ph: { en: "Type your message here...", tl: "Isulat ang iyong mensahe dito..." },
        form_submit: { en: "Send Message", tl: "Magpadala ng Mensahe" },

        cta_title: { en: "Ready to explore Valenzuela's legislative records?", tl: "Handa ka nang tuklasin ang mga pambatasang rekord ng Valenzuela?" },
        cta_sub: { en: "Sign in to browse the archive, track documents, and download the records you need.", tl: "Mag-sign in upang mag-browse sa archive, subaybayan ang mga dokumento, at i-download ang mga rekord na kailangan mo." },
        cta_login: { en: "Login to Get Started", tl: "Mag-login para Magsimula" },
        cta_register: { en: "Create an Account", tl: "Gumawa ng Account" },

        foot_tagline: { en: "Preserving the legislative legacy of Valenzuela City — one record at a time.", tl: "Pinapanatili ang pambatasang pamana ng Lungsod ng Valenzuela — isang rekord sa bawat pagkakataon." },
        foot_quicklinks: { en: "Quick Links", tl: "Mga Mabilisang Link" },
        foot_home: { en: "Home", tl: "Home" },
        foot_features: { en: "Features", tl: "Mga Tampok" },
        foot_about: { en: "About", tl: "Tungkol" },
        foot_security: { en: "Security", tl: "Seguridad" },
        foot_faq: { en: "FAQ", tl: "FAQ" },
        foot_contact: { en: "Contact", tl: "Makipag-ugnayan" },
        foot_getstarted: { en: "Get Started", tl: "Magsimula" },
        foot_login: { en: "Login", tl: "Mag-login" },
        foot_register: { en: "Create Account", tl: "Gumawa ng Account" },
        foot_forgot: { en: "Forgot Password", tl: "Nakalimutang Password" },
        foot_terms: { en: "Terms & Conditions", tl: "Mga Tuntunin at Kundisyon" },
        foot_copy: { en: "City Government of Valenzuela. All rights reserved.", tl: "Pamahalaang Lungsod ng Valenzuela. Lahat ng karapatan ay nakalaan." }
    };

    var currentLang = 'en';
    try {
        var saved = localStorage.getItem('lang');
        if (saved === 'tl' || saved === 'en') currentLang = saved;
    } catch (_) {}

    function applyLang(lang) {
        currentLang = lang;
        document.documentElement.setAttribute('lang', lang);
        document.querySelectorAll('[data-i18n]').forEach(function (el) {
            var key = el.getAttribute('data-i18n');
            var entry = LANDING_I18N[key];
            if (entry && entry[lang]) el.textContent = entry[lang];
        });
        document.querySelectorAll('[data-i18n-placeholder]').forEach(function (el) {
            var key = el.getAttribute('data-i18n-placeholder');
            var entry = LANDING_I18N[key];
            if (entry && entry[lang]) el.setAttribute('placeholder', entry[lang]);
        });
        document.querySelectorAll('.lang-btn').forEach(function (btn) {
            btn.classList.toggle('active', btn.getAttribute('data-lang') === lang);
        });
        try { localStorage.setItem('lang', lang); } catch (_) {}
    }

    document.querySelectorAll('.lang-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            applyLang(btn.getAttribute('data-lang'));
        });
    });

    applyLang(currentLang);
})();
