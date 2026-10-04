/* Back-office · site de l'association : dépôt des documents publics (statuts, comptes rendus…). */
(() => {
  'use strict';
  const BO = window.BO;
  if (!BO) return;
  document.addEventListener('click', async (e) => {
    const btn = e.target.closest('[data-asso-upload]');
    if (!btn) return;
    const box = btn.closest('[data-asso-file]');
    const input = document.createElement('input');
    input.type = 'file';
    input.accept = '.pdf,.jpg,.jpeg,.png,.webp,.zip';
    input.addEventListener('change', async () => {
      const file = input.files && input.files[0];
      if (!file) return;
      if (file.size > 20 * 1024 * 1024) { BO.toast('Fichier trop lourd : 20 Mo au plus.', true); return; }
      const fd = new FormData();
      fd.append('file', file);
      btn.disabled = true;
      const label = btn.textContent;
      btn.textContent = 'Envoi…';
      const r = await BO.post('/admin/association/documents/envoi', fd, false);
      btn.disabled = false;
      btn.textContent = label;
      if (!r.ok) { BO.toast(r.error || 'Envoi impossible', true); return; }
      BO.setValue(box.querySelector('[data-asso-file-value]'), r.file);
      box.querySelector('[data-asso-file-name]').textContent = r.file + ' · ' + r.size + ' (enregistrez pour le publier)';
      btn.textContent = 'Remplacer…';
      BO.toast('Fichier déposé');
    });
    input.click();
  });
})();
