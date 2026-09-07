/* =====================================================================
   Le Comptable à Lunettes — JavaScript du site public
   Vanilla JS, sans dépendance. Chaque module est autonome : si l'un
   échoue, les autres continuent de fonctionner.
   ===================================================================== */
(function () {
    'use strict';

    /* ----------------------------------------------------- Configuration */
    var CONFIG = { lang: 'fr', token: '', endpoints: {}, strings: {}, motion: true };
    try {
        var raw = document.getElementById('app-config');
        if (raw) { CONFIG = Object.assign(CONFIG, JSON.parse(raw.textContent)); }
    } catch (e) { /* configuration illisible : on garde les valeurs par défaut */ }

    var REDUCED = window.matchMedia('(prefers-reduced-motion: reduce)').matches || CONFIG.motion === false;

    var $  = function (sel, ctx) { return (ctx || document).querySelector(sel); };
    var $$ = function (sel, ctx) { return Array.prototype.slice.call((ctx || document).querySelectorAll(sel)); };

    function on(el, type, handler, opts) { if (el) { el.addEventListener(type, handler, opts || false); } }

    /** Appel JSON avec jeton CSRF. */
    function post(url, payload) {
        return fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
            body: JSON.stringify(Object.assign({ _token: CONFIG.token, lang: CONFIG.lang }, payload))
        }).then(function (response) {
            return response.json().catch(function () { return { ok: false, error: 'bad_json' }; });
        });
    }

    function store(key, value, days) {
        try {
            if (value === undefined) { return window.localStorage.getItem('lcal_' + key); }
            window.localStorage.setItem('lcal_' + key, value);
            if (days) { window.localStorage.setItem('lcal_' + key + '_exp', String(Date.now() + days * 864e5)); }
        } catch (e) { return null; }
    }
    function storeValid(key) {
        try {
            var exp = window.localStorage.getItem('lcal_' + key + '_exp');
            if (exp && Date.now() > Number(exp)) {
                window.localStorage.removeItem('lcal_' + key);
                window.localStorage.removeItem('lcal_' + key + '_exp');
                return false;
            }
            return !!window.localStorage.getItem('lcal_' + key);
        } catch (e) { return false; }
    }

    /* ==================================================================
       1. Apparition des éléments au défilement
       ================================================================== */
    function initReveal() {
        var items = $$('[data-reveal]');
        if (!items.length) { return; }

        if (REDUCED || !('IntersectionObserver' in window)) {
            items.forEach(function (el) { el.classList.add('is-revealed'); });
            return;
        }

        var observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-revealed');
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.12, rootMargin: '0px 0px -8% 0px' });

        items.forEach(function (el) { observer.observe(el); });
    }

    /* ==================================================================
       2. Compteurs animés
       ================================================================== */
    function initCounters() {
        var counters = $$('[data-counter]');
        if (!counters.length) { return; }

        function run(el) {
            // L'élément ne contient que le nombre : préfixe et suffixe sont
            // rendus par le gabarit, donc jamais altérés par l'animation.
            var target = parseFloat(el.getAttribute('data-counter')) || 0;

            if (REDUCED) {
                el.textContent = String(target);
                return;
            }
            var start = performance.now();
            var duration = 1400;

            function frame(now) {
                var progress = Math.min((now - start) / duration, 1);
                var eased = 1 - Math.pow(1 - progress, 3);
                el.textContent = String(Math.round(target * eased));
                if (progress < 1) { requestAnimationFrame(frame); }
            }
            requestAnimationFrame(frame);
        }

        if (!('IntersectionObserver' in window)) { counters.forEach(run); return; }

        var observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) { run(entry.target); observer.unobserve(entry.target); }
            });
        }, { threshold: 0.5 });
        counters.forEach(function (el) { observer.observe(el); });
    }

    /* ==================================================================
       3. En-tête : ombre, masquage au défilement, jauge de progression
       ================================================================== */
    function initHeader() {
        var header = $('[data-header]');
        var progress = $('[data-scroll-progress]');
        var toTop = $('[data-to-top]');
        var last = window.scrollY;
        var ticking = false;

        function update() {
            var y = window.scrollY;
            if (header) {
                header.classList.toggle('is-stuck', y > 8);
                // On masque l'en-tête vers le bas, on le ramène vers le haut.
                var goingDown = y > last && y > 320;
                header.classList.toggle('is-hidden', goingDown && !document.body.classList.contains('is-locked'));
            }
            if (progress) {
                var max = document.documentElement.scrollHeight - window.innerHeight;
                progress.style.transform = 'scaleX(' + (max > 0 ? Math.min(y / max, 1) : 0) + ')';
            }
            if (toTop) { toTop.classList.toggle('is-visible', y > 700); }
            last = y;
            ticking = false;
        }

        on(window, 'scroll', function () {
            if (!ticking) { requestAnimationFrame(update); ticking = true; }
        }, { passive: true });
        update();

        on(toTop, 'click', function () {
            window.scrollTo({ top: 0, behavior: REDUCED ? 'auto' : 'smooth' });
        });
    }

    /* ==================================================================
       4. Navigation mobile
       ================================================================== */
    function initNav() {
        var toggle = $('[data-nav-toggle]');
        var nav = $('#primary-nav');
        var overlay = $('[data-nav-overlay]');
        if (!toggle || !nav) { return; }

        function setOpen(open) {
            nav.classList.toggle('is-open', open);
            toggle.setAttribute('aria-expanded', String(open));
            toggle.setAttribute('aria-label', open ? CONFIG.strings.menuClose : CONFIG.strings.menuOpen);
            document.body.classList.toggle('is-locked', open);
            if (overlay) { overlay.hidden = !open; }
            if (open) { var first = $('.nav__link', nav); if (first) { first.focus(); } }
        }

        on(toggle, 'click', function () { setOpen(!nav.classList.contains('is-open')); });
        on(overlay, 'click', function () { setOpen(false); });
        on(document, 'keydown', function (event) {
            if (event.key === 'Escape' && nav.classList.contains('is-open')) { setOpen(false); toggle.focus(); }
        });
        $$('.nav__link', nav).forEach(function (link) {
            on(link, 'click', function () { if (window.innerWidth <= 960) { setOpen(false); } });
        });
    }

    /* ==================================================================
       5. Sélecteur de langue
       ================================================================== */
    function initLangSwitcher() {
        var wrapper = $('[data-lang-switcher]');
        if (!wrapper) { return; }
        var toggle = $('.lang__toggle', wrapper);
        var menu = $('.lang__menu', wrapper);

        function setOpen(open) {
            menu.hidden = !open;
            toggle.setAttribute('aria-expanded', String(open));
        }

        on(toggle, 'click', function (event) { event.stopPropagation(); setOpen(menu.hidden); });
        on(document, 'click', function (event) { if (!wrapper.contains(event.target)) { setOpen(false); } });
        on(document, 'keydown', function (event) { if (event.key === 'Escape') { setOpen(false); } });

        // Mémorisation du choix de langue pour les visites suivantes.
        $$('.lang__option', menu).forEach(function (option) {
            on(option, 'click', function () {
                var code = option.getAttribute('data-lang');
                document.cookie = 'lcal_lang=' + encodeURIComponent(code) + ';path=/;max-age=31536000;samesite=lax';
            });
        });
    }

    /* ==================================================================
       6. Barre d'action collée en bas
       ================================================================== */
    function initStickyCta() {
        var bar = $('[data-sticky-cta]');
        if (!bar) { return; }
        // Apparition douce dès les premiers pixels de défilement.
        window.setTimeout(function () { bar.classList.add('is-visible'); }, 450);

        var height = bar.offsetHeight;
        if (height) { document.documentElement.style.setProperty('--sticky-h', height + 'px'); }

        on(window, 'resize', function () {
            var next = bar.offsetHeight;
            if (next) { document.documentElement.style.setProperty('--sticky-h', next + 'px'); }
        }, { passive: true });
    }

    /* ==================================================================
       7. Accordéons (FAQ) — ouverture animée à hauteur réelle
       ================================================================== */
    function initAccordions() {
        $$('[data-accordion]').forEach(function (group) {
            $$('.faq__trigger', group).forEach(function (trigger) {
                var panel = document.getElementById(trigger.getAttribute('aria-controls'));
                var item = trigger.closest('.faq__item');
                if (!panel) { return; }

                on(trigger, 'click', function () {
                    var isOpen = trigger.getAttribute('aria-expanded') === 'true';

                    // Un seul panneau ouvert à la fois.
                    $$('.faq__trigger', group).forEach(function (other) {
                        if (other === trigger) { return; }
                        var otherPanel = document.getElementById(other.getAttribute('aria-controls'));
                        other.setAttribute('aria-expanded', 'false');
                        if (otherPanel) { collapse(otherPanel); }
                        var otherItem = other.closest('.faq__item');
                        if (otherItem) { otherItem.classList.remove('is-open'); }
                    });

                    trigger.setAttribute('aria-expanded', String(!isOpen));
                    if (item) { item.classList.toggle('is-open', !isOpen); }
                    if (isOpen) { collapse(panel); } else { expand(panel); }
                });
            });
        });

        function expand(panel) {
            panel.hidden = false;
            if (REDUCED) { panel.style.height = 'auto'; return; }
            var height = panel.scrollHeight;
            panel.style.height = '0px';
            panel.style.transition = 'height .42s cubic-bezier(.16,1,.3,1)';
            requestAnimationFrame(function () {
                panel.style.height = height + 'px';
                window.setTimeout(function () { panel.style.height = 'auto'; }, 430);
            });
        }
        function collapse(panel) {
            if (panel.hidden) { return; }
            if (REDUCED) { panel.hidden = true; return; }
            panel.style.height = panel.scrollHeight + 'px';
            requestAnimationFrame(function () {
                panel.style.height = '0px';
                window.setTimeout(function () { panel.hidden = true; panel.style.height = ''; }, 430);
            });
        }
    }

    /* ==================================================================
       8. Formulaire de contact
       ================================================================== */
    function initContactForm() {
        $$('[data-contact-form]').forEach(function (form) {
            var feedback = $('[data-form-feedback]', form);
            var submit = form.querySelector('button[type="submit"]');
            var label = submit ? $('.btn__label', submit) : null;
            var original = label ? label.textContent : '';

            on(form, 'submit', function (event) {
                event.preventDefault();
                clearErrors(form);

                if (!validate(form)) { return; }

                var payload = {};
                new FormData(form).forEach(function (value, key) { payload[key] = value; });
                payload.consent = form.querySelector('[name="consent"]').checked;

                if (submit) { submit.disabled = true; }
                if (label) { label.textContent = CONFIG.strings.sending; }
                setFeedback(feedback, '', '');

                post(CONFIG.endpoints.contact, payload).then(function (response) {
                    if (response.ok) {
                        form.reset();
                        setFeedback(feedback, response.message || 'Merci !', 'is-ok');
                        // La demande envoyée, la relance de sortie n'a plus lieu d'être.
                        store('exit_done', '1', 30);
                    } else if (response.errors) {
                        showErrors(form, response.errors);
                        setFeedback(feedback, '', '');
                    } else {
                        setFeedback(feedback, response.message || CONFIG.strings.error, 'is-error');
                    }
                }).catch(function () {
                    setFeedback(feedback, CONFIG.strings.error, 'is-error');
                }).then(function () {
                    if (submit) { submit.disabled = false; }
                    if (label) { label.textContent = original; }
                });
            });
        });

        function validate(form) {
            var valid = true;
            $$('[required]', form).forEach(function (field) {
                var empty = field.type === 'checkbox' ? !field.checked : !field.value.trim();
                var badEmail = field.type === 'email' && field.value && !/^[^@\s]+@[^@\s]+\.[a-z]{2,}$/i.test(field.value);
                if (empty || badEmail) {
                    valid = false;
                    field.classList.add('is-invalid');
                    var slot = form.querySelector('[data-error-for="' + field.name + '"]');
                    if (slot) { slot.textContent = CONFIG.strings.required; }
                }
            });
            return valid;
        }
        function clearErrors(form) {
            $$('.is-invalid', form).forEach(function (f) { f.classList.remove('is-invalid'); });
            $$('[data-error-for]', form).forEach(function (s) { s.textContent = ''; });
        }
        function showErrors(form, errors) {
            Object.keys(errors).forEach(function (name) {
                var field = form.querySelector('[name="' + name + '"]');
                var slot = form.querySelector('[data-error-for="' + name + '"]');
                if (field) { field.classList.add('is-invalid'); }
                if (slot) { slot.textContent = errors[name]; }
            });
        }
        function setFeedback(node, message, className) {
            if (!node) { return; }
            node.textContent = message;
            node.className = 'contact__feedback' + (className ? ' ' + className : '');
        }
    }

    /* ==================================================================
       9. Assistant IA (Gemini)
       ================================================================== */
    function initChatbot() {
        var root = $('[data-chatbot]');
        if (!root) { return; }

        var panel = $('[data-chat-panel]', root);
        var log = $('[data-chat-log]', root);
        var form = $('[data-chat-form]', root);
        var input = $('.chatbot__input', root);
        var openBtn = $('[data-chat-open]', root);
        var closeBtn = $('[data-chat-close]', root);
        var history = [];
        var busy = false;

        function setOpen(open) {
            panel.hidden = !open;
            root.classList.toggle('is-open', open);
            openBtn.setAttribute('aria-expanded', String(open));
            if (open) {
                window.setTimeout(function () { input.focus(); }, 120);
                store('chat_seen', '1', 30);
            } else {
                openBtn.focus();
            }
        }

        on(openBtn, 'click', function () { setOpen(true); });
        on(closeBtn, 'click', function () { setOpen(false); });
        on(document, 'keydown', function (event) {
            if (event.key === 'Escape' && !panel.hidden) { setOpen(false); }
        });

        $$('[data-chat-suggestion]', root).forEach(function (chip) {
            on(chip, 'click', function () {
                input.value = chip.textContent.trim();
                form.dispatchEvent(new Event('submit', { cancelable: true }));
            });
        });

        on(form, 'submit', function (event) {
            event.preventDefault();
            var message = input.value.trim();
            if (!message || busy) { return; }

            busy = true;
            input.value = '';
            addMessage('user', message);
            history.push({ role: 'user', content: message });

            var typing = addTyping();

            post(CONFIG.endpoints.chat, { message: message, history: history.slice(-6) })
                .then(function (response) {
                    typing.remove();
                    var reply = response.reply || CONFIG.strings.unavailable;
                    addMessage('bot', reply, response.sources || []);
                    history.push({ role: 'assistant', content: reply });
                })
                .catch(function () {
                    typing.remove();
                    addMessage('bot', CONFIG.strings.unavailable);
                })
                .then(function () { busy = false; input.focus(); });
        });

        function addMessage(role, text, sources) {
            var wrap = document.createElement('div');
            wrap.className = 'chat-msg chat-msg--' + (role === 'user' ? 'user' : 'bot');

            var bubble = document.createElement('div');
            bubble.className = 'chat-msg__bubble';
            bubble.textContent = text;

            if (sources && sources.length) {
                var list = document.createElement('div');
                list.className = 'chat-msg__sources';
                sources.forEach(function (source) {
                    if (!source.url) { return; }
                    var link = document.createElement('a');
                    link.className = 'chat-msg__source';
                    link.href = source.url;
                    link.textContent = source.title;
                    list.appendChild(link);
                });
                if (list.childNodes.length) { bubble.appendChild(list); }
            }

            wrap.appendChild(bubble);
            log.appendChild(wrap);
            log.scrollTop = log.scrollHeight;
            return wrap;
        }

        function addTyping() {
            var wrap = document.createElement('div');
            wrap.className = 'chat-msg chat-msg--bot';
            wrap.innerHTML = '<div class="chat-msg__bubble"><span class="chat-typing">'
                + '<span></span><span></span><span></span></span></div>';
            log.appendChild(wrap);
            log.scrollTop = log.scrollHeight;
            return wrap;
        }
    }

    /* ==================================================================
       10. Fenêtre d'intention de sortie
       ================================================================== */
    function initExitPopup() {
        var popup = $('[data-exit-popup]');
        if (!popup) { return; }

        var frequency = parseInt(popup.getAttribute('data-frequency'), 10) || 7;
        var delay = parseInt(popup.getAttribute('data-delay'), 10) || 1200;
        var mobileTimeout = parseInt(popup.getAttribute('data-mobile-timeout'), 10) || 45;

        if (storeValid('exit_shown') || storeValid('exit_done')) { return; }

        var armed = false;
        var shown = false;
        var lastFocus = null;

        // On n'arme le déclencheur qu'après un temps de lecture minimal.
        window.setTimeout(function () { armed = true; }, delay);

        function show() {
            if (shown || !armed) { return; }
            shown = true;
            lastFocus = document.activeElement;
            popup.hidden = false;
            document.body.classList.add('is-locked');
            store('exit_shown', '1', frequency);
            var dialog = $('.exit-popup__dialog', popup);
            if (dialog) { dialog.focus(); }
        }

        function hide() {
            popup.hidden = true;
            document.body.classList.remove('is-locked');
            if (lastFocus && lastFocus.focus) { lastFocus.focus(); }
        }

        // Ordinateur : sortie du curseur par le haut de la fenêtre.
        on(document, 'mouseout', function (event) {
            if (!event.relatedTarget && !event.toElement && event.clientY <= 8) { show(); }
        });

        // Repli mobile : inactivité prolongée ou retour arrière.
        if (window.matchMedia('(pointer: coarse)').matches) {
            window.setTimeout(show, mobileTimeout * 1000);
            var lastY = window.scrollY;
            on(window, 'scroll', function () {
                var y = window.scrollY;
                if (lastY - y > 220 && y < 260) { show(); }
                lastY = y;
            }, { passive: true });
        }

        $$('[data-exit-close]', popup).forEach(function (btn) { on(btn, 'click', hide); });
        on(document, 'keydown', function (event) { if (event.key === 'Escape' && !popup.hidden) { hide(); } });

        // Piège de tabulation dans la fenêtre modale.
        on(popup, 'keydown', function (event) {
            if (event.key !== 'Tab') { return; }
            var focusable = $$('button, a[href], input, [tabindex]:not([tabindex="-1"])', popup)
                .filter(function (el) { return el.offsetParent !== null; });
            if (!focusable.length) { return; }
            var first = focusable[0];
            var last = focusable[focusable.length - 1];
            if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
            else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
        });

        var form = $('[data-exit-form]', popup);
        if (!form) { return; }
        var feedback = $('[data-exit-feedback]', form);

        on(form, 'submit', function (event) {
            event.preventDefault();
            var email = form.querySelector('[name="email"]').value.trim();
            if (!/^[^@\s]+@[^@\s]+\.[a-z]{2,}$/i.test(email)) {
                feedback.textContent = CONFIG.strings.required;
                feedback.className = 'exit-popup__feedback is-error';
                return;
            }
            var button = form.querySelector('button[type="submit"]');
            if (button) { button.disabled = true; }

            post(CONFIG.endpoints.lead, {
                email: email,
                source: 'exit_popup',
                page: window.location.pathname,
                website: form.querySelector('[name="website"]').value
            }).then(function (response) {
                if (response.ok) {
                    feedback.textContent = response.message || 'Merci !';
                    feedback.className = 'exit-popup__feedback is-ok';
                    store('exit_done', '1', 90);
                    window.setTimeout(hide, 1800);
                } else {
                    feedback.textContent = response.message || CONFIG.strings.error;
                    feedback.className = 'exit-popup__feedback is-error';
                }
            }).catch(function () {
                feedback.textContent = CONFIG.strings.error;
                feedback.className = 'exit-popup__feedback is-error';
            }).then(function () { if (button) { button.disabled = false; } });
        });
    }

    /* ==================================================================
       11. Défilement doux vers les ancres
       ================================================================== */
    function initAnchors() {
        $$('a[href^="#"]').forEach(function (link) {
            on(link, 'click', function (event) {
                var id = link.getAttribute('href');
                if (id.length < 2) { return; }
                var target = document.querySelector(id);
                if (!target) { return; }
                event.preventDefault();
                var header = $('[data-header]');
                var offset = header ? header.offsetHeight + 12 : 12;
                var top = target.getBoundingClientRect().top + window.scrollY - offset;
                window.scrollTo({ top: top, behavior: REDUCED ? 'auto' : 'smooth' });
                target.setAttribute('tabindex', '-1');
                target.focus({ preventScroll: true });
            });
        });
    }

    /* ==================================================================
       Amorçage
       ================================================================== */
    function boot() {
        [initReveal, initCounters, initHeader, initNav, initLangSwitcher, initStickyCta,
         initAccordions, initContactForm, initChatbot, initExitPopup, initAnchors]
            .forEach(function (module) {
                try { module(); } catch (error) {
                    if (window.console) { console.warn('[LCAL] module en échec :', error); }
                }
            });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
