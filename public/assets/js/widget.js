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

  // Compte à rebours : jours, heures, minutes jusqu'à la date (horodatage du musée).
  var cd = document.querySelector('[data-countdown]');
  if (cd) {
    var two = function (n) { return (n < 10 ? '0' : '') + n; };
    var upd = function () {
      var left = Math.max(0, +cd.getAttribute('data-countdown') * 1000 - Date.now());
      var put = function (k, v) { var b = cd.querySelector('[data-cd="' + k + '"]'); if (b) b.textContent = v; };
      put('j', Math.floor(left / 864e5)); put('h', two(Math.floor(left / 36e5) % 24)); put('m', two(Math.floor(left / 6e4) % 60));
    };
    upd();
    setInterval(upd, 15000);
  }

  // Recherche : ouvre la recherche du musée dans un nouvel onglet (l'iframe du script
  // d'intégration n'a pas le droit d'envoyer un formulaire, mais peut ouvrir un onglet).
  var sf = document.querySelector('[data-search]');
  if (sf) sf.addEventListener('submit', function (e) {
    var v = sf.querySelector('input').value.trim();
    if (!v) return;
    e.preventDefault();
    var tpl = sf.getAttribute('data-search');
    var enc = encodeURIComponent(v);
    window.open(tpl.replace('__Q__', tpl.indexOf('/w/clic/') >= 0 ? encodeURIComponent(enc) : enc), '_blank', 'noopener');
  });
  // Boutique : la carte se retourne toutes les 5 s pour montrer l'objet suivant (pause au survol
  // ou au clavier ; mouvement réduit : simple fondu, voir le CSS du cadre).
  var flip = document.querySelector('[data-flip]');
  if (flip) {
    var cards = [].slice.call(flip.children), at = 0, hold = false;
    var pause = function () { hold = true; }, play = function () { hold = false; };
    flip.addEventListener('mouseenter', pause); flip.addEventListener('mouseleave', play);
    flip.addEventListener('focusin', pause); flip.addEventListener('focusout', play);
    setInterval(function () {
      if (hold || document.hidden) return;
      flip.classList.add('is-turning');
      setTimeout(function () {
        cards[at].classList.remove('is-on'); cards[at].setAttribute('tabindex', '-1'); cards[at].setAttribute('aria-hidden', 'true');
        at = (at + 1) % cards.length;
        cards[at].classList.add('is-on'); cards[at].removeAttribute('tabindex'); cards[at].removeAttribute('aria-hidden');
        flip.classList.remove('is-turning');
      }, matchMedia('(prefers-reduced-motion: reduce)').matches ? 400 : 280);
    }, 5000);
  }

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
