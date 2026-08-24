/* Back-office — galerie (dépôt, filtres, classement) et référencement
   (compteurs de caractères).
   Tout fonctionne sans JavaScript : ce fichier n'ajoute que du confort. */
(function () {
  'use strict';

  /* ---------- Classement du diaporama par glisser-déposer ----------
     Les flèches Monter / Descendre restent le chemin sans JavaScript. */
  var diapos = document.querySelector('[data-diapos-classables]');
  if (diapos) {
    var etatD = document.querySelector('[data-diapos-etat]');
    var porteD = null;
    var attente = null;

    var direD = function (texte, erreur) {
      if (!etatD) return;
      etatD.textContent = texte;
      etatD.className = 'bo-ordre-etat' + (erreur ? ' bo-ordre-etat--erreur' : '');
    };

    var enregistrerD = function () {
      var corps = new FormData();
      corps.append('_csrf', diapos.getAttribute('data-csrf'));
      [].forEach.call(diapos.querySelectorAll('.bo-diapo'), function (f) {
        corps.append('ordre[]', f.getAttribute('data-src'));
      });

      fetch(diapos.getAttribute('data-url-ordre'), {
        method: 'POST', body: corps, credentials: 'same-origin'
      }).then(function (r) { return r.json(); }).then(function (r) {
        direD(r.message, !r.ok);
        // les numéros affichés doivent suivre le nouvel ordre
        [].forEach.call(diapos.querySelectorAll('.bo-diapo__rang'), function (el, i) {
          el.textContent = (i + 1) + (i === 0 ? ' — première' : '');
        });
      }).catch(function () {
        direD('Enregistrement impossible. Rechargez la page.', true);
      });
    };

    diapos.addEventListener('dragstart', function (e) {
      porteD = e.target.closest('.bo-diapo');
      if (!porteD) return;
      porteD.classList.add('bo-diapo--portee');
      e.dataTransfer.effectAllowed = 'move';
      // Firefox n'amorce pas le glissement sans donnée transportée
      e.dataTransfer.setData('text/plain', porteD.getAttribute('data-src'));
    });

    diapos.addEventListener('dragend', function () {
      if (porteD) porteD.classList.remove('bo-diapo--portee');
      porteD = null;
    });

    diapos.addEventListener('dragover', function (e) {
      if (!porteD) return;
      e.preventDefault();
      var survolee = e.target.closest('.bo-diapo');
      if (!survolee || survolee === porteD) return;

      var boite = survolee.getBoundingClientRect();
      var apres = (e.clientX - boite.left) > boite.width / 2;
      diapos.insertBefore(porteD, apres ? survolee.nextSibling : survolee);

      clearTimeout(attente);
      attente = setTimeout(enregistrerD, 500);
    });

    diapos.addEventListener('drop', function (e) {
      e.preventDefault();
      clearTimeout(attente);
      enregistrerD();
    });
  }

  /* ---------- Filtre de la mosaïque de photos ----------
     Confort quand la médiathèque s'allonge : le choix lui-même repose sur
     des boutons radio et fonctionne sans JavaScript. */
  [].forEach.call(document.querySelectorAll('[data-choix]'), function (bloc) {
    var champ = bloc.querySelector('[data-choix-filtre]');
    var compte = bloc.querySelector('[data-choix-compte]');
    var tuiles = [].slice.call(bloc.querySelectorAll('.bo-tuile'));
    if (!champ) return;

    var filtrer = function () {
      var q = champ.value.trim().toLowerCase();
      var vus = 0;
      tuiles.forEach(function (t) {
        var nom = (t.getAttribute('data-nom') || '').toLowerCase();
        // le choix « aucune » et la photo retenue restent toujours visibles
        var garde = nom === '' || t.querySelector('input:checked');
        var ok = garde || q === '' || nom.indexOf(q) !== -1;
        t.hidden = !ok;
        if (ok && nom !== '') vus++;
      });
      if (compte) {
        compte.textContent = vus + ' photo' + (vus > 1 ? 's' : '');
      }
    };

    champ.addEventListener('input', filtrer);
    filtrer();
  });

  /* ---------- Aperçu de l'adresse (écran Référencement) ----------
     Reproduit App\Core\Seo::normaliser() pour montrer, pendant la frappe,
     l'adresse qui sera réellement enregistrée. Le serveur normalise de
     toute façon : ceci n'est qu'un aperçu. */
  var LIGATURES = { 'æ': 'ae', 'Æ': 'ae', 'œ': 'oe', 'Œ': 'oe', 'ß': 'ss', 'ø': 'o', 'Ø': 'o',
                    'ł': 'l', 'Ł': 'l', 'đ': 'd', 'Đ': 'd', 'ð': 'd', 'Ð': 'd', 'þ': 'th', 'Þ': 'th' };

  var enSlug = function (valeur) {
    valeur = String(valeur).trim();

    // adresse complète collée depuis le navigateur : on garde le chemin
    var complete = valeur.match(/^[a-z][a-z0-9+.-]*:\/\/[^/]*(\/.*)?$/i);
    if (complete) { valeur = complete[1] || ''; }
    valeur = valeur.split(/[?#]/)[0];

    valeur = valeur.replace(/[æÆœŒßøØłŁđĐðÐþÞ]/g, function (c) { return LIGATURES[c]; });
    valeur = valeur.replace(/[’'«»°]/g, '');

    // décomposition Unicode : « é » devient « e » + accent, que l'on retire
    if (valeur.normalize) {
      valeur = valeur.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
    }

    valeur = valeur.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/-+/g, '-')
                   .replace(/^-|-$/g, '');

    if (valeur.length > 90) {
      valeur = valeur.slice(0, 90);
      var coupe = valeur.lastIndexOf('-');
      if (coupe > 45) { valeur = valeur.slice(0, coupe); }
      valeur = valeur.replace(/^-|-$/g, '');
    }
    return valeur;
  };

  [].forEach.call(document.querySelectorAll('[data-slug]'), function (champ) {
    var apercu = document.createElement('span');
    apercu.className = 'bo-slug-apercu';
    champ.parentNode.parentNode.insertBefore(apercu, champ.parentNode.nextSibling);

    var montrer = function () {
      var propre = enSlug(champ.value);
      if (propre === champ.value) { apercu.textContent = ''; return; }
      apercu.textContent = propre === ''
        ? 'Cette saisie ne laisse aucune adresse valable.'
        : 'Sera enregistré comme : /' + propre;
      apercu.className = 'bo-slug-apercu' + (propre === '' ? ' bo-slug-apercu--vide' : '');
    };

    champ.addEventListener('input', montrer);
    // la saisie est normalisée en quittant le champ : plus de surprise
    champ.addEventListener('blur', function () {
      var propre = enSlug(champ.value);
      if (propre !== '') { champ.value = propre; }
      montrer();
    });
    montrer();
  });

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

/* ---------- Confirmations et report de valeur ----------
   Ajouts du Menuiserie Tréhant, dans leur propre portée. */
(function () {
  "use strict";

  /* Confirme avant un envoi destructeur. Le garde-fou est côté navigateur
     seulement : le serveur, lui, conserve la version précédente de chaque
     contenu, ce qui rend la suppression rattrapable même sans cette boîte. */
  document.addEventListener("submit", function (e) {
    var form = e.target.closest("form[data-confirmer]");
    if (!form) return;
    if (!window.confirm(form.getAttribute("data-confirmer"))) {
      e.preventDefault();
    }
  });

  /* Reporte une valeur dans un champ : « utiliser cette fiche Google »
     évite au client de recopier un identifiant de trente caractères. */
  document.addEventListener("click", function (e) {
    var btn = e.target.closest("[data-remplir]");
    if (!btn) return;

    var cible = document.querySelector(btn.getAttribute("data-remplir"));
    if (!cible) return;

    cible.value = btn.getAttribute("data-valeur") || "";
    cible.focus();
    cible.scrollIntoView({ block: "center", behavior: "smooth" });
  });

  /* ---------- Éditeur de texte enrichi (notes de l'assistant) ----------
     Un éditeur minimal bâti sur contenteditable : gras, italique, listes,
     sous-titres. Aucune bibliothèque — le besoin tient en six boutons, et une
     dépendance de plus serait à maintenir dix ans.

     Le champ caché reçoit le contenu avant l'envoi : c'est lui qui part au
     serveur, où il est de nouveau filtré. Ce nettoyage-ci n'est qu'un confort
     de saisie, jamais une mesure de sécurité. */
  (function () {
    var editeurs = document.querySelectorAll("[data-editeur]");
    if (!editeurs.length) return;

    Array.prototype.forEach.call(editeurs, function (editeur) {
      var zone = editeur.querySelector("[data-editeur-zone]");
      var champ = editeur.querySelector("[data-editeur-champ]");
      var form = editeur.closest("form");
      if (!zone || !champ || !form) return;

      editeur.querySelectorAll("[data-commande]").forEach(function (bouton) {
        bouton.addEventListener("click", function () {
          zone.focus();
          document.execCommand(
            bouton.getAttribute("data-commande"),
            false,
            bouton.getAttribute("data-valeur") || null
          );
        });
      });

      // Un collage depuis Word ou une page web charrie des styles, des
      // classes et parfois des scripts : on ne garde que le texte.
      zone.addEventListener("paste", function (e) {
        e.preventDefault();
        var texte = (e.clipboardData || window.clipboardData).getData("text/plain");
        document.execCommand("insertText", false, texte);
      });

      form.addEventListener("submit", function () {
        champ.value = zone.innerHTML;
      });
    });
  })();


  /* ---------- Planche à cocher des réalisations ----------
     Deux services seulement : tenir la classe de surbrillance à jour pour les
     navigateurs sans :has(), et les boutons tout cocher / tout décocher. */
  (function () {
    var planche = document.querySelector(".bo-planche");
    if (!planche) return;

    var cases = [].slice.call(planche.querySelectorAll("input[type=checkbox]"));

    function refleter(c) {
      c.closest(".bo-planche__photo").classList.toggle("bo-planche__photo--choisie", c.checked);
    }
    cases.forEach(function (c) {
      c.addEventListener("change", function () { refleter(c); });
    });

    function toutes(valeur) {
      cases.forEach(function (c) { c.checked = valeur; refleter(c); });
    }
    var tout = document.querySelector("[data-cocher-tout]");
    var rien = document.querySelector("[data-cocher-rien]");
    if (tout) tout.addEventListener("click", function () { toutes(true); });
    if (rien) rien.addEventListener("click", function () { toutes(false); });
  })();

})();
