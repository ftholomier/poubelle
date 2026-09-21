/**
 * Back-office : éditeur de texte riche et confirmations.
 * L'éditeur repose sur contenteditable ; le HTML produit est renettoyé côté
 * serveur par Sanitizer avant d'être écrit (liste blanche stricte).
 */
(function () {
  'use strict';

  var $  = function (s, r) { return (r || document).querySelector(s); };
  var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };

  function initEditor() {
    var form = $('[data-editor]');
    if (!form) { return; }

    var area = $('[data-editor-area]', form);
    var input = $('[data-editor-input]', form);
    var status = $('[data-editor-status]', form);
    if (!area || !input) { return; }

    var savedAt = Date.now();
    var dirty = false;

    function sync() {
      input.value = area.innerHTML;
    }

    $$('[data-cmd]', form).forEach(function (btn) {
      btn.addEventListener('click', function () {
        var cmd = btn.getAttribute('data-cmd');
        var value = btn.getAttribute('data-value') || null;

        if (cmd === 'createLink') {
          value = window.prompt('Adresse du lien', 'https://');
          if (!value) { return; }
        }
        if (cmd === 'insertImage') {
          value = window.prompt('Adresse de l’image', '/assets/img/');
          if (!value) { return; }
        }
        if (cmd === 'formatBlock') { value = '<' + value + '>'; }

        area.focus();
        try { document.execCommand(cmd, false, value); } catch (e) { /* navigateur récalcitrant */ }
        sync();
      });
    });

    area.addEventListener('input', function () { dirty = true; sync(); });

    // Le collage n'apporte que du texte : pas de HTML étranger dans l'éditeur.
    area.addEventListener('paste', function (e) {
      e.preventDefault();
      var text = (e.clipboardData || window.clipboardData).getData('text/plain');
      document.execCommand('insertText', false, text);
      sync();
    });

    form.addEventListener('submit', function () { dirty = false; sync(); });

    window.addEventListener('beforeunload', function (e) {
      if (!dirty) { return undefined; }
      e.preventDefault();
      e.returnValue = '';
      return '';
    });

    if (status) {
      var template = status.textContent.trim();
      setInterval(function () {
        var seconds = Math.round((Date.now() - savedAt) / 1000);
        status.textContent = template.replace(/^Sauvegarde auto il y a \d+ s/, 'Sauvegarde auto il y a ' + seconds + ' s');
      }, 5000);
    }

    sync();
  }

  function initConfirms() {
    $$('[data-confirm]').forEach(function (el) {
      el.addEventListener('click', function (e) {
        if (!window.confirm(el.getAttribute('data-confirm'))) { e.preventDefault(); }
      });
    });
  }

  function boot() { initEditor(); initConfirms(); }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else { boot(); }
})();
