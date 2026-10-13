/* Script d'intégration des widgets du musée (servi en /embed.js).
   Chez le partenaire :
     <div data-musee-widget="ce-jour-la" data-theme="sombre" data-lang="fr" data-key="mk_…"></div>
     <script src="https://<musée>/embed.js" async></script>
   Chaque <div> devient une iframe isolée (sandbox, aucun cookie envoyé, aucun style partagé avec
   la page), chargée quand elle approche de l'écran, à la hauteur exacte du widget (message
   {type: "musee-widget", height} de js/widget.js, accepté seulement depuis l'origine du musée).
   Les autres attributs data-* deviennent des options du widget (data-cat="matchs",
   data-saison="1987-1988"…) ; data-height : hauteur de départ, data-max-width : largeur maximale. */
(function (w, d) {
  'use strict';
  var me = d.currentScript || [].slice.call(d.querySelectorAll('script[src*="embed.js"]')).pop();
  if (!me || !me.src) return;
  var origin = new URL(me.src, location.href).origin;
  var reg = w.__museeWidgets = w.__museeWidgets || {};
  var frames = reg[origin] = reg[origin] || [];
  var H = { bandeau: 64, frise: 300 };
  var SKIP = { museeWidget: 1, museeReady: 1, height: 1, maxWidth: 1, title: 1 };

  function kebab(s) { return s.replace(/[A-Z]/g, function (c) { return '-' + c.toLowerCase(); }); }

  function build(el) {
    var key = el.getAttribute('data-musee-widget') || '';
    if (!/^[a-z0-9-]{1,40}$/.test(key)) return;
    var q = [];
    for (var k in el.dataset) {
      if (!SKIP[k] && el.dataset[k] !== '') q.push(encodeURIComponent(kebab(k)) + '=' + encodeURIComponent(el.dataset[k]));
    }
    var f = d.createElement('iframe');
    f.src = origin + '/w/' + key + (q.length ? '?' + q.join('&') : '');
    f.title = el.getAttribute('data-title') || 'Widget du musée : ' + key;
    // Scripts du widget (hauteur, quiz) et liens vers le musée dans un nouvel onglet ; ni
    // formulaire, ni navigation de la page du partenaire, ni fenêtre modale.
    f.setAttribute('sandbox', 'allow-scripts allow-same-origin allow-popups allow-popups-to-escape-sandbox');
    f.setAttribute('referrerpolicy', 'strict-origin-when-cross-origin');
    f.setAttribute('credentialless', '');
    f.setAttribute('scrolling', 'no');
    var s = f.style;
    s.border = '0'; s.display = 'block'; s.width = '100%'; s.colorScheme = 'normal'; s.background = 'transparent';
    s.maxWidth = el.getAttribute('data-max-width') || (key === 'bandeau' ? '100%' : '440px');
    s.height = (parseInt(el.getAttribute('data-height'), 10) || H[key] || 420) + 'px';
    el.textContent = '';
    el.style.minHeight = '';
    el.appendChild(f);
    frames.push(f);
  }

  function scan() {
    var list = d.querySelectorAll('[data-musee-widget]:not([data-musee-ready])');
    var io = 'IntersectionObserver' in w ? new IntersectionObserver(function (es) {
      es.forEach(function (e) { if (e.isIntersecting) { io.unobserve(e.target); build(e.target); } });
    }, { rootMargin: '300px 0px' }) : null;
    for (var i = 0; i < list.length; i++) {
      list[i].setAttribute('data-musee-ready', '');
      // Place réservée (pas de saut de mise en page, et le chargement paresseux voit la vraie position).
      list[i].style.minHeight = (parseInt(list[i].getAttribute('data-height'), 10) || H[list[i].getAttribute('data-musee-widget')] || 420) + 'px';
      io ? io.observe(list[i]) : build(list[i]);
    }
  }

  if (!reg[origin + '#m']) {
    reg[origin + '#m'] = 1;
    w.addEventListener('message', function (e) {
      var m = e.data;
      if (e.origin !== origin || !m || m.type !== 'musee-widget' || typeof m.height !== 'number') return;
      for (var i = 0; i < frames.length; i++) {
        if (frames[i].contentWindow === e.source) frames[i].style.height = Math.max(40, Math.min(4000, Math.round(m.height))) + 'px';
      }
    });
  }
  w.MuseeWidgets = { scan: scan };
  d.readyState === 'loading' ? d.addEventListener('DOMContentLoaded', scan) : scan();
})(window, document);
