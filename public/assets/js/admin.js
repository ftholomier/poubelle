/* =====================================================================
   Back-office — JavaScript (vanilla, sans dépendance)
   Éditeur WYSIWYG, constructeur de blocs, champs répétables.
   ===================================================================== */
(function () {
    'use strict';

    var CONFIG = { token: '' };
    try {
        var node = document.getElementById('admin-config');
        if (node) { CONFIG = Object.assign(CONFIG, JSON.parse(node.textContent)); }
    } catch (e) { /* valeurs par défaut */ }

    var $  = function (s, c) { return (c || document).querySelector(s); };
    var $$ = function (s, c) { return Array.prototype.slice.call((c || document).querySelectorAll(s)); };
    function on(el, type, fn, opts) { if (el) { el.addEventListener(type, fn, opts || false); } }

    /* ==================================================================
       1. Onglets de langue des champs multilingues
       ================================================================== */
    function initMultilang(scope) {
        $$('[data-multilang]', scope).forEach(function (field) {
            if (field.dataset.mlReady) { return; }
            field.dataset.mlReady = '1';

            $$('[data-ml-tab]', field).forEach(function (tab) {
                on(tab, 'click', function () {
                    var lang = tab.getAttribute('data-ml-tab');
                    $$('[data-ml-tab]', field).forEach(function (other) {
                        var active = other === tab;
                        other.classList.toggle('is-active', active);
                        other.setAttribute('aria-selected', String(active));
                    });
                    $$('[data-ml-pane]', field).forEach(function (pane) {
                        pane.classList.toggle('is-active', pane.getAttribute('data-ml-pane') === lang);
                    });
                });
            });
        });
    }

    /* ==================================================================
       2. Éditeur WYSIWYG
       ================================================================== */
    function initEditors(scope) {
        $$('[data-editor]', scope).forEach(function (editor) {
            if (editor.dataset.edReady) { return; }
            editor.dataset.edReady = '1';

            var area = $('[data-editor-area]', editor);
            var source = $('[data-editor-source]', editor);
            if (!area || !source) { return; }

            function sync() { source.value = clean(area.innerHTML); }

            on(area, 'input', sync);
            on(area, 'blur', sync);

            // Collage : on ne garde que du texte, la mise en forme se refait à la main.
            on(area, 'paste', function (event) {
                event.preventDefault();
                var text = (event.clipboardData || window.clipboardData).getData('text/plain');
                document.execCommand('insertText', false, text);
                sync();
            });

            $$('button[data-cmd]', editor).forEach(function (button) {
                on(button, 'click', function (event) {
                    event.preventDefault();
                    var cmd = button.getAttribute('data-cmd');
                    var value = button.getAttribute('data-value') || null;

                    if (cmd === 'source') {
                        var showing = editor.classList.toggle('is-source');
                        button.classList.toggle('is-active', showing);
                        if (showing) {
                            sync();
                            source.hidden = false;
                        } else {
                            source.hidden = true;
                            area.innerHTML = clean(source.value);
                        }
                        return;
                    }
                    if (cmd === 'createLink') {
                        var url = window.prompt('Adresse du lien (https://… ou /page) :', 'https://');
                        if (!url) { return; }
                        if (!/^(https?:\/\/|\/|mailto:|tel:|#)/i.test(url)) {
                            window.alert('Adresse refusée : utilisez https://, /page, mailto: ou tel:');
                            return;
                        }
                        value = url;
                    }
                    area.focus();
                    document.execCommand(cmd, false, value);
                    sync();
                    refreshState(editor, area);
                });
            });

            on(area, 'keyup', function () { refreshState(editor, area); });
            on(area, 'mouseup', function () { refreshState(editor, area); });

            // Sécurité : synchronisation forcée avant tout envoi de formulaire.
            var form = editor.closest('form');
            if (form && !form.dataset.edHooked) {
                form.dataset.edHooked = '1';
                on(form, 'submit', function () {
                    $$('[data-editor]', form).forEach(function (ed) {
                        var edArea = $('[data-editor-area]', ed);
                        var edSource = $('[data-editor-source]', ed);
                        if (edArea && edSource && !ed.classList.contains('is-source')) {
                            edSource.value = clean(edArea.innerHTML);
                        }
                    });
                });
            }
        });

        function refreshState(editor, area) {
            $$('button[data-cmd]', editor).forEach(function (button) {
                var cmd = button.getAttribute('data-cmd');
                if (['bold', 'italic', 'underline', 'insertUnorderedList', 'insertOrderedList'].indexOf(cmd) === -1) { return; }
                try { button.classList.toggle('is-active', document.queryCommandState(cmd)); } catch (e) { /* ignoré */ }
            });
        }

        /** Nettoyage côté client ; le serveur ré-assainit systématiquement. */
        function clean(html) {
            var holder = document.createElement('div');
            holder.innerHTML = html;
            $$('script, style, iframe, object, embed, link, meta', holder).forEach(function (el) { el.remove(); });
            $$('*', holder).forEach(function (el) {
                Array.prototype.slice.call(el.attributes).forEach(function (attr) {
                    var name = attr.name.toLowerCase();
                    if (name.indexOf('on') === 0 || name === 'style' || name === 'srcset') {
                        el.removeAttribute(attr.name);
                    }
                    if ((name === 'href' || name === 'src') && /^\s*javascript:/i.test(attr.value)) {
                        el.removeAttribute(attr.name);
                    }
                });
            });
            return holder.innerHTML.trim();
        }
    }

    /* ==================================================================
       3. Blocs de page : ouverture, réordonnancement, ajout, suppression
       ================================================================== */
    function initBlocks() {
        var list = $('[data-blocks]');
        if (!list) { return; }

        // Ouvrir / replier
        list.addEventListener('click', function (event) {
            var bar = event.target.closest('.ad-block__bar');
            if (bar && !event.target.closest('.ad-block__tools') && !event.target.closest('.ad-block__handle')) {
                bar.closest('.ad-block').classList.toggle('is-open');
            }

            var remove = event.target.closest('[data-block-remove]');
            if (remove) {
                event.preventDefault();
                if (window.confirm('Supprimer ce bloc ? La page devra être enregistrée pour valider.')) {
                    remove.closest('.ad-block').remove();
                    renumber();
                }
            }

            var move = event.target.closest('[data-block-move]');
            if (move) {
                event.preventDefault();
                var block = move.closest('.ad-block');
                var dir = move.getAttribute('data-block-move');
                if (dir === 'up' && block.previousElementSibling) {
                    list.insertBefore(block, block.previousElementSibling);
                } else if (dir === 'down' && block.nextElementSibling) {
                    list.insertBefore(block.nextElementSibling, block);
                }
                renumber();
            }
        });

        // Glisser-déposer
        $$('.ad-block', list).forEach(makeDraggable);

        function makeDraggable(block) {
            var handle = $('.ad-block__handle', block);
            if (!handle) { return; }
            handle.setAttribute('draggable', 'true');

            on(handle, 'dragstart', function (event) {
                block.classList.add('is-dragging');
                event.dataTransfer.effectAllowed = 'move';
                event.dataTransfer.setData('text/plain', '');
            });
            on(handle, 'dragend', function () {
                block.classList.remove('is-dragging');
                renumber();
            });
        }

        on(list, 'dragover', function (event) {
            event.preventDefault();
            var dragging = $('.ad-block.is-dragging', list);
            if (!dragging) { return; }
            var after = getDragAfter(list, event.clientY);
            if (after == null) { list.appendChild(dragging); }
            else { list.insertBefore(dragging, after); }
        });

        function getDragAfter(container, y) {
            var items = $$('.ad-block:not(.is-dragging)', container);
            var closest = { offset: Number.NEGATIVE_INFINITY, element: null };
            items.forEach(function (child) {
                var box = child.getBoundingClientRect();
                var offset = y - box.top - box.height / 2;
                if (offset < 0 && offset > closest.offset) { closest = { offset: offset, element: child }; }
            });
            return closest.element;
        }

        /** Réindexe les noms de champs pour refléter l'ordre affiché. */
        function renumber() {
            $$('.ad-block', list).forEach(function (block, index) {
                $$('[name]', block).forEach(function (field) {
                    field.name = field.name.replace(/^blocks\[\d+\]/, 'blocks[' + index + ']');
                });
                var position = $('[data-block-position]', block);
                if (position) { position.textContent = String(index + 1); }
            });
        }

        // Ajout d'un bloc : le gabarit vient du serveur (source unique de vérité).
        var adder = $('[data-block-add]');
        if (adder) {
            on(adder, 'change', function () {
                var type = adder.value;
                if (!type) { return; }
                adder.disabled = true;

                fetch('/admin/api/block-template', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                    body: JSON.stringify({ _token: CONFIG.token, type: type })
                })
                    .then(function (r) { return r.json(); })
                    .then(function (response) {
                        if (!response.ok) { throw new Error('template'); }
                        // On recharge la page avec le bloc demandé : le serveur
                        // génère le formulaire complet, aucun rendu dupliqué en JS.
                        var form = $('[data-page-form]');
                        var hidden = document.createElement('input');
                        hidden.type = 'hidden';
                        hidden.name = 'add_block';
                        hidden.value = type;
                        form.appendChild(hidden);
                        form.submit();
                    })
                    .catch(function () {
                        window.alert('Ajout impossible. Rechargez la page et réessayez.');
                        adder.disabled = false;
                        adder.value = '';
                    });
            });
        }

        renumber();
    }

    /* ==================================================================
       4. Champs répétables (services, étapes, FAQ, avis…)
       ================================================================== */
    function initRepeaters() {
        document.addEventListener('click', function (event) {
            var add = event.target.closest('[data-repeat-add]');
            if (add) {
                event.preventDefault();
                var container = document.getElementById(add.getAttribute('data-repeat-add'));
                if (!container) { return; }
                var template = container.querySelector('template');
                if (!template) { return; }

                var index = container.querySelectorAll('.ad-repeat__item').length;
                var html = template.innerHTML.replace(/__INDEX__/g, String(index));
                var holder = document.createElement('div');
                holder.innerHTML = html;
                var item = holder.firstElementChild;
                container.insertBefore(item, template);

                initMultilang(item);
                initEditors(item);
                var firstInput = item.querySelector('input, textarea, select');
                if (firstInput) { firstInput.focus(); }
            }

            var remove = event.target.closest('[data-repeat-remove]');
            if (remove) {
                event.preventDefault();
                var line = remove.closest('.ad-repeat__item');
                if (line && window.confirm('Supprimer cet élément ?')) {
                    var parent = line.parentElement;
                    line.remove();
                    reindex(parent);
                }
            }
        });

        function reindex(container) {
            if (!container) { return; }
            $$('.ad-repeat__item', container).forEach(function (item, index) {
                $$('[name]', item).forEach(function (field) {
                    field.name = field.name.replace(/\[(\d+)\](?!.*\[\d+\])/, '[' + index + ']');
                });
            });
        }
    }

    /* ==================================================================
       5. Téléversement (glisser-déposer)
       ================================================================== */
    function initDropzone() {
        $$('[data-drop]').forEach(function (zone) {
            var input = $('input[type="file"]', zone.closest('form'));
            if (!input) { return; }

            ['dragenter', 'dragover'].forEach(function (type) {
                on(zone, type, function (event) { event.preventDefault(); zone.classList.add('is-over'); });
            });
            ['dragleave', 'drop'].forEach(function (type) {
                on(zone, type, function (event) { event.preventDefault(); zone.classList.remove('is-over'); });
            });
            on(zone, 'drop', function (event) {
                if (event.dataTransfer.files.length) {
                    input.files = event.dataTransfer.files;
                    zone.closest('form').submit();
                }
            });
            on(zone, 'click', function () { input.click(); });
            on(input, 'change', function () { if (input.files.length) { zone.closest('form').submit(); } });
        });
    }

    /* ==================================================================
       6. Divers : menu latéral mobile, confirmations, copie d'URL, couleurs
       ================================================================== */
    function initMisc() {
        var toggle = $('[data-sidebar-toggle]');
        on(toggle, 'click', function () { $('#ad-sidebar').classList.toggle('is-open'); });

        document.addEventListener('submit', function (event) {
            var form = event.target;
            var message = form.getAttribute('data-confirm');
            if (message && !window.confirm(message)) { event.preventDefault(); }
        });

        $$('[data-copy]').forEach(function (field) {
            on(field, 'focus', function () { field.select(); });
            on(field, 'click', function () { field.select(); });
        });

        // Synchronisation sélecteur de couleur ↔ champ hexadécimal.
        $$('.ad-color').forEach(function (row) {
            var picker = $('input[type="color"]', row);
            var hex = $('.ad-color__hex', row);
            if (!picker || !hex) { return; }
            on(picker, 'input', function () { hex.value = picker.value.toUpperCase(); });
            on(hex, 'input', function () {
                if (/^#[0-9A-Fa-f]{6}$/.test(hex.value)) { picker.value = hex.value; }
            });
        });

        // Génération d'un slug depuis le titre, tant qu'il n'a pas été saisi.
        var slug = $('[data-slug]');
        var title = $('[data-slug-source]');
        if (slug && title && slug.value === '') {
            on(title, 'input', function () {
                slug.value = title.value
                    .toLowerCase()
                    .normalize('NFD').replace(/[̀-ͯ]/g, '')
                    .replace(/[^a-z0-9]+/g, '-')
                    .replace(/^-+|-+$/g, '');
            });
        }
    }

    function boot() {
        [function () { initMultilang(document); },
         function () { initEditors(document); },
         initBlocks, initRepeaters, initDropzone, initMisc]
            .forEach(function (fn) {
                try { fn(); } catch (error) {
                    if (window.console) { console.warn('[admin]', error); }
                }
            });
    }

    if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', boot); }
    else { boot(); }
})();
