/* =========================================================================
   Back-office iOiO — interactions. JavaScript natif, aucune dépendance.
   Tout reste utilisable sans JS : les formulaires postent normalement.
   ========================================================================= */
(function () {
  'use strict';

  function $$(selector, scope) { return Array.prototype.slice.call((scope || document).querySelectorAll(selector)); }

  /* Confirmation avant une suppression. */
  $$('form[data-confirm]').forEach(function (form) {
    form.addEventListener('submit', function (e) {
      if (!window.confirm(form.getAttribute('data-confirm'))) { e.preventDefault(); }
    });
  });

  /* Sélecteur de couleur relié au champ texte. */
  $$('[data-color-picker]').forEach(function (picker) {
    var text = picker.parentNode.querySelector('[data-color-text]');
    if (!text) { return; }
    picker.addEventListener('input', function () { text.value = picker.value.toUpperCase(); });
    text.addEventListener('input', function () {
      if (/^#[0-9A-Fa-f]{6}$/.test(text.value)) { picker.value = text.value; }
    });
  });

  /* Choix des photos : l'ordre de sélection est l'ordre d'affichage. */
  $$('[data-media-picker]').forEach(function (picker) {
    var input = picker.parentNode.querySelector('[data-media-value]');
    if (!input) { return; }
    var max = parseInt(input.getAttribute('data-media-max') || '0', 10);

    function paint() {
      var selected = input.value.split(',').filter(Boolean);
      $$('[data-media-path]', picker).forEach(function (button) {
        var index = selected.indexOf(button.getAttribute('data-media-path'));
        button.classList.toggle('is-picked', index > -1);
        var badge = button.querySelector('.media-pick__n');
        if (index > -1) {
          if (!badge) {
            badge = document.createElement('span');
            badge.className = 'media-pick__n';
            button.appendChild(badge);
          }
          badge.textContent = String(index + 1);
        } else if (badge) {
          badge.remove();
        }
      });
    }

    $$('[data-media-path]', picker).forEach(function (button) {
      button.addEventListener('click', function () {
        var path = button.getAttribute('data-media-path');
        var selected = input.value.split(',').filter(Boolean);
        var index = selected.indexOf(path);
        if (index > -1) {
          selected.splice(index, 1);
        } else if (max === 1) {
          selected = [path];
        } else {
          selected.push(path);
        }
        input.value = selected.join(',');
        paint();
      });
    });
    paint();
  });

  /* Éditeur WYSIWYG : la zone visible alimente un champ caché, assaini au serveur. */
  $$('[data-wysiwyg]').forEach(function (wrap) {
    var area = wrap.querySelector('[data-wysiwyg-area]');
    var input = wrap.querySelector('[data-wysiwyg-input]');
    if (!area || !input) { return; }

    function sync() { input.value = area.innerHTML.trim(); }

    $$('.tool', wrap).forEach(function (tool) {
      tool.addEventListener('click', function () {
        var command = tool.getAttribute('data-cmd') || '';
        area.focus();
        if (command.indexOf('formatBlock:') === 0) {
          document.execCommand('formatBlock', false, command.split(':')[1]);
        } else if (command === 'createLink') {
          var url = window.prompt('Adresse du lien (https://…)');
          if (url) { document.execCommand('createLink', false, url); }
        } else {
          document.execCommand(command, false, null);
        }
        sync();
      });
    });

    area.addEventListener('input', sync);
    area.addEventListener('blur', sync);
    // Collage en texte brut : on n'importe jamais la mise en forme de Word.
    area.addEventListener('paste', function (e) {
      e.preventDefault();
      var text = (e.clipboardData || window.clipboardData).getData('text/plain');
      document.execCommand('insertText', false, text);
      sync();
    });
    var form = wrap.closest('form');
    if (form) { form.addEventListener('submit', sync); }
    sync();
  });

  /* Un enregistrement en cours ne doit pas être perdu par un clic ailleurs. */
  var dirty = false;
  $$('form .field, form .title-input, form [data-wysiwyg-area]').forEach(function (el) {
    el.addEventListener('input', function () { dirty = true; });
  });
  $$('form').forEach(function (form) {
    form.addEventListener('submit', function () { dirty = false; });
  });
  window.addEventListener('beforeunload', function (e) {
    if (!dirty) { return; }
    e.preventDefault();
    e.returnValue = '';
  });
})();
