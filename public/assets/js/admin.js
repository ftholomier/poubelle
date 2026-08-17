/* Back-office — galerie (dépôt, filtres, classement) et référencement
   (compteurs de caractères).
   Tout fonctionne sans JavaScript : ce fichier n'ajoute que du confort. */
(function () {
  'use strict';

  /* ---------- Compteurs de caractères (écran Référencement) ---------- */
  [].forEach.call(document.querySelectorAll('[data-compteur]'), function (champ) {
    var limite = parseInt(champ.getAttribute('data-compteur'), 10);
    var jauge = document.createElement('span');
    jauge.className = 'bo-compteur';

    var aide = champ.parentNode.querySelector('.aide');
    if (aide) { aide.parentNode.insertBefore(jauge, aide); }
    else { champ.parentNode.appendChild(jauge); }

    var mesurer = function () {
      // à vide, c'est la valeur héritée — affichée en gris dans le champ —
      // qui sera publiée : c'est donc elle que l'on mesure
      var texte = champ.value || champ.getAttribute('placeholder') || '';
      var n = texte.length;
      jauge.textContent = n + ' / ' + limite + ' caractères'
        + (champ.value ? '' : ' (valeur héritée)');
      jauge.className = 'bo-compteur' + (n > limite ? ' bo-compteur--trop' : '');
    };

    champ.addEventListener('input', mesurer);
    mesurer();
  });

  /* ---------- Dépôt de fichiers par glisser-déposer ---------- */
  var depot = document.querySelector('[data-depot]');
  var champ = depot && depot.querySelector('input[type="file"]');
  var liste = depot && depot.querySelector('[data-depot-liste]');

  if (depot && champ) {
    var decrire = function (fichiers) {
      if (!liste) return;
      if (!fichiers || !fichiers.length) { liste.textContent = ''; return; }
      var noms = [].slice.call(fichiers, 0, 4).map(function (f) { return f.name; });
      liste.textContent = fichiers.length + ' fichier' + (fichiers.length > 1 ? 's' : '')
        + ' : ' + noms.join(', ') + (fichiers.length > 4 ? '…' : '');
    };

    champ.addEventListener('change', function () { decrire(champ.files); });

    ['dragenter', 'dragover'].forEach(function (evt) {
      depot.addEventListener(evt, function (e) {
        e.preventDefault();
        depot.classList.add('bo-depot--survol');
      });
    });
    ['dragleave', 'drop'].forEach(function (evt) {
      depot.addEventListener(evt, function (e) {
        e.preventDefault();
        if (evt === 'dragleave' && depot.contains(e.relatedTarget)) return;
        depot.classList.remove('bo-depot--survol');
      });
    });

    depot.addEventListener('drop', function (e) {
      var fichiers = e.dataTransfer && e.dataTransfer.files;
      if (!fichiers || !fichiers.length) return;
      // DataTransfer permet d'alimenter le champ : l'envoi reste un POST normal
      if (typeof DataTransfer === 'function') {
        var dt = new DataTransfer();
        [].forEach.call(fichiers, function (f) {
          if (/^image\/(jpeg|png|webp)$/.test(f.type)) dt.items.add(f);
        });
        champ.files = dt.files;
      }
      decrire(champ.files);
    });
  }

  /* ---------- Filtres par catégorie ---------- */
  var filtres = document.querySelector('.bo-filtres');
  var galerie = document.querySelector('.bo-galerie');

  if (filtres && galerie) {
    filtres.addEventListener('click', function (e) {
      var bouton = e.target.closest('button[data-filtre]');
      if (!bouton) return;

      var cible = bouton.getAttribute('data-filtre');
      filtres.querySelectorAll('button').forEach(function (b) {
        b.setAttribute('aria-pressed', String(b === bouton));
      });
      galerie.querySelectorAll('.bo-media').forEach(function (fig) {
        var visible = cible === 'tout' || fig.getAttribute('data-cat') === cible;
        fig.hidden = !visible;
      });
      // le classement n'a de sens que sur la galerie entière
      galerie.classList.toggle('bo-galerie--filtree', cible !== 'tout');
    });
  }

  /* ---------- Classement par glisser-déposer ---------- */
  var classable = document.querySelector('[data-classable]');
  if (!classable) return;

  var etat = document.querySelector('[data-ordre-etat]');
  var porte = null;
  var minuteur = null;

  var dire = function (texte, erreur) {
    if (!etat) return;
    etat.textContent = texte;
    etat.classList.toggle('bo-ordre-etat--erreur', Boolean(erreur));
  };

  var enregistrer = function () {
    var ordre = [].map.call(
      classable.querySelectorAll('.bo-media'),
      function (f) { return f.getAttribute('data-src'); }
    );

    var corps = new FormData();
    corps.append('_csrf', classable.getAttribute('data-csrf'));
    ordre.forEach(function (src) { corps.append('ordre[]', src); });

    dire('Enregistrement du nouvel ordre…');
    fetch(classable.getAttribute('data-url-ordre'), {
      method: 'POST',
      body: corps,
      credentials: 'same-origin'
    })
      .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
      .then(function (res) {
        if (!res.ok) throw new Error(res.j && res.j.erreur ? res.j.erreur : 'échec');
        dire('Ordre enregistré.');
        setTimeout(function () { dire(''); }, 2500);
      })
      .catch(function (err) {
        dire('L’ordre n’a pas pu être enregistré (' + err.message + '). Rechargez la page.', true);
      });
  };

  classable.addEventListener('dragstart', function (e) {
    var fig = e.target.closest('.bo-media');
    // classer une liste filtrée produirait un ordre trompeur
    if (!fig || classable.classList.contains('bo-galerie--filtree')) {
      e.preventDefault();
      return;
    }
    porte = fig;
    fig.classList.add('bo-media--porte');
    e.dataTransfer.effectAllowed = 'move';
    // Firefox exige des données pour amorcer le glissement
    e.dataTransfer.setData('text/plain', fig.getAttribute('data-src'));
  });

  classable.addEventListener('dragend', function () {
    if (porte) porte.classList.remove('bo-media--porte');
    porte = null;
  });

  classable.addEventListener('dragover', function (e) {
    if (!porte) return;
    e.preventDefault();
    var survole = e.target.closest('.bo-media');
    if (!survole || survole === porte) return;

    var boite = survole.getBoundingClientRect();
    var apres = (e.clientX - boite.left) > boite.width / 2;
    classable.insertBefore(porte, apres ? survole.nextSibling : survole);

    // on n'enregistre qu'une fois le déplacement stabilisé
    clearTimeout(minuteur);
    minuteur = setTimeout(enregistrer, 500);
  });

  classable.addEventListener('drop', function (e) {
    e.preventDefault();
    clearTimeout(minuteur);
    enregistrer();
  });
})();
