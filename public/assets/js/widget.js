/* Widgets du musée (mode iframe) : la page annonce sa hauteur à la page qui l'accueille, pour
   une iframe à la bonne taille (script d'intégration /embed.js, ou quelques lignes chez le
   partenaire). Message : {type: "musee-widget", widget, height}. Aucune donnée personnelle.
   Quiz du jour : le choix est vérifié par le musée (POST …/quiz-du-jour/verifier), la bonne
   réponse n'étant pas dans la page ; rien n'est gardé (ni cookie ni stockage). */
(function () {
  var box = document.querySelector('.w-box');
  var key = (document.body.className.match(/\bw--([a-z0-9-]+)/) || [])[1] || '';
  var last = 0;
  function send() {
    if (window.parent === window) return;
    var h = Math.ceil((box || document.body).getBoundingClientRect().height) + 2;
    if (h === last) return;
    last = h;
    window.parent.postMessage({ type: 'musee-widget', widget: key, height: h, src: location.pathname + location.search }, '*');
  }
  if ('ResizeObserver' in window && box) new ResizeObserver(send).observe(box);
  window.addEventListener('load', send);
  document.addEventListener('change', send);
  send();

  var qz = document.querySelector('[data-quiz]');
  if (!qz || !window.fetch) return;
  var btns = qz.querySelectorAll('.w-qz__c');
  var out = qz.querySelector('.w-qz__r');
  var fact = qz.querySelector('.w-qz__f');
  var pkey = new URLSearchParams(location.search).get('key') || '';
  function pick(b) {
    var i = +b.getAttribute('data-i');
    for (var k = 0; k < btns.length; k++) btns[k].disabled = true;
    fetch(qz.getAttribute('data-verify'), {
      method: 'POST', credentials: 'omit', cache: 'no-store',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ date: qz.getAttribute('data-date'), choix: i, lang: qz.getAttribute('data-lang'), key: pkey })
    }).then(function (r) { return r.json(); }).then(function (d) {
      if (typeof d.bonne !== 'number') throw new Error(d.error || '');
      btns[d.bonne].classList.add('is-good');
      if (!d.correct) b.classList.add('is-bad');
      out.textContent = d.correct ? qz.getAttribute('data-ok') : qz.getAttribute('data-ko') + ' ' + d.reponse;
      out.hidden = false;
      if (d.explication) { fact.textContent = d.explication; fact.hidden = false; }
      send();
    }).catch(function (e) {
      for (var k = 0; k < btns.length; k++) btns[k].disabled = false;
      out.textContent = (e && e.message) || qz.getAttribute('data-err');
      out.hidden = false;
      send();
    });
  }
  for (var k = 0; k < btns.length; k++) btns[k].addEventListener('click', function () { pick(this); });
})();
