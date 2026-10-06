/* Championnat du club-house : entrer au championnat (e-mail + pseudo, lien sécurisé) ou changer de pseudo. */
(function () {
  'use strict';
  var root = document.querySelector('[data-qc]');
  var form = root && root.querySelector('[data-qc-form]');
  if (!form) return;
  var T = JSON.parse(document.getElementById('qc-i18n').textContent);
  var lang = root.getAttribute('data-lang') || 'fr';
  var msg = form.querySelector('[data-qc-msg]');
  form.addEventListener('submit', function (e) {
    e.preventDefault();
    var btn = form.querySelector('button');
    btn.disabled = true;
    msg.textContent = '';
    var body = { action: 'championnat', lang: lang, name: form.name.value, email: form.email ? form.email.value : '' };
    fetch((lang === 'en' ? '/en' : '') + '/api/quiz-live', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body), credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (j) {
        btn.disabled = false;
        if (!j.ok) { msg.textContent = j.error || T.error; return; }
        if (j.mailed && !j.member) { msg.textContent = T.mailed; form.reset(); return; }
        if (j.mailed) { msg.textContent = T.mailed; }
        else { msg.textContent = T.saved; }
        setTimeout(function () { location.reload(); }, 1600);
      })
      .catch(function () { btn.disabled = false; msg.textContent = T.error; });
  });
})();
