/* Back-office — champs répétables, garde-fou de sortie, slug auto. */
(function () {
  'use strict';

  const $  = (s, c) => (c || document).querySelector(s);
  const $$ = (s, c) => Array.from((c || document).querySelectorAll(s));

  /* --------------------------------------- Listes répétables */

  /** Réindexe name="data[items][0][title]" après ajout/suppression/tri. */
  function reindex(wrap) {
    const base = wrap.dataset.name;
    $$('[data-repeat-item]', wrap).forEach((item, i) => {
      $$('[name]', item).forEach((input) => {
        input.name = input.name.replace(
          new RegExp('^' + base.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '\\[[^\\]]*\\]'),
          base + '[' + i + ']'
        );
      });
      const n = $('.repeat__n', item);
      if (n) n.textContent = 'Élément ' + (i + 1);
    });
  }

  $$('[data-repeat]').forEach((wrap) => {
    const template = wrap.parentElement.querySelector('[data-repeat-template]');

    wrap.addEventListener('click', (e) => {
      const item = e.target.closest('[data-repeat-item]');
      if (!item) return;

      if (e.target.closest('[data-repeat-remove]')) {
        if (!confirm('Supprimer cet élément ?')) return;
        item.remove();
        reindex(wrap);
      } else if (e.target.closest('[data-repeat-up]') && item.previousElementSibling) {
        wrap.insertBefore(item, item.previousElementSibling);
        reindex(wrap);
      } else if (e.target.closest('[data-repeat-down]') && item.nextElementSibling) {
        wrap.insertBefore(item.nextElementSibling, item);
        reindex(wrap);
      }
    });

    const add = wrap.parentElement.querySelector('[data-repeat-add]');
    if (add && template) {
      add.addEventListener('click', () => {
        const html = template.innerHTML.replace(/__INDEX__/g, String($$('[data-repeat-item]', wrap).length));
        const holder = document.createElement('div');
        holder.innerHTML = html;
        const node = holder.firstElementChild;
        wrap.appendChild(node);
        reindex(wrap);
        const first = $('input, textarea, select', node);
        first && first.focus();
      });
    }

    reindex(wrap);
  });

  /* ------------------------------- Avertissement si non enregistré */

  $$('form[data-dirty-guard]').forEach((form) => {
    let dirty = false;
    form.addEventListener('input', () => { dirty = true; });
    form.addEventListener('submit', () => { dirty = false; });
    window.addEventListener('beforeunload', (e) => {
      if (!dirty) return;
      e.preventDefault();
      e.returnValue = '';
    });
  });

  /* ---------------------------------------------- Slug automatique */

  const title = $('#title');
  const slug = $('#slug');
  if (title && slug) {
    title.addEventListener('blur', () => {
      if (slug.value.trim() !== '') return;
      slug.value = title.value
        .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
        .toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
    });
  }
})();
