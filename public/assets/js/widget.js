/* Widgets du musée (mode iframe) : la page annonce sa hauteur à la page qui l'accueille, pour
   une iframe à la bonne taille (script d'intégration de la phase 2, ou quelques lignes chez le
   partenaire). Message : {type: "musee-widget", widget, height}. Aucune donnée personnelle. */
(function () {
  if (window.parent === window) return;
  var box = document.querySelector('.w-box');
  var key = (document.body.className.match(/\bw--([a-z0-9-]+)/) || [])[1] || '';
  var last = 0;
  function send() {
    var h = Math.ceil((box || document.body).getBoundingClientRect().height) + 2;
    if (h === last) return;
    last = h;
    window.parent.postMessage({ type: 'musee-widget', widget: key, height: h, src: location.pathname + location.search }, '*');
  }
  if ('ResizeObserver' in window && box) new ResizeObserver(send).observe(box);
  window.addEventListener('load', send);
  document.addEventListener('change', send);
  send();
})();
