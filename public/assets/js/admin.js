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
   Ajouts propres à ce site, dans leur propre portée. */
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

  /* ---------- Éditeur de texte enrichi ----------
     Déménagé dans editeur.js : le même éditeur sert désormais les notes de
     l'assistant et le corps des pages, et la version qui vivait ici n'avait
     ni les couleurs de charte, ni le bouton, ni le repli sans JavaScript. */



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

  /* ---------- Glissières : afficher la valeur choisie ---------- */
  (function () {
    document.querySelectorAll("input[type=range][data-valeur-de]").forEach(function (glissiere) {
      var vu = document.getElementById(glissiere.getAttribute("data-valeur-de"));
      if (!vu) return;
      glissiere.addEventListener("input", function () {
        vu.textContent = glissiere.value + " %";
      });
    });
  })();

  /* ---------- Diaporama d'accueil : ordre des vues ----------

     L'ordre envoyé au serveur est celui des champs dans la page : déplacer
     une ligne suffit donc, il n'y a aucun numéro de rang à recalculer.
     Les flèches doublent le glisser-déposer, qui reste inatteignable au
     clavier comme sur un écran tactile. */
  (function () {
    var liste = document.querySelector("[data-diapos]");
    if (!liste) return;

    var porte = null;

    var vide = function () {
      var mot = document.querySelector("[data-diapos-vide]");
      if (liste.querySelector("[data-diapo]")) { if (mot) mot.remove(); return; }
      if (!mot) {
        liste.insertAdjacentHTML("afterend",
          '<p class="bo-vide" data-diapos-vide>Aucune vue : le bandeau affichera la photo de repli.</p>');
      }
    };

    /* Ajouter une photo : la vue apparaît aussitôt en fin de liste, et la
       tuile se décoche — sans quoi la même image partirait deux fois, une
       fois par la liste et une fois par la mosaïque. */
    document.querySelectorAll("[name='diapo_ajout[]']").forEach(function (tuile) {
      tuile.addEventListener("change", function () {
        if (!tuile.checked) return;
        tuile.checked = false;

        var chemin = tuile.value;
        var deja = liste.querySelector("[name='diapo_image[]'][value=\"" + chemin + "\"]");
        if (deja) {
          deja.closest("[data-diapo]").scrollIntoView({ block: "center", behavior: "smooth" });
          return;
        }

        var vignette = tuile.parentNode.querySelector("img");
        var nom = chemin.split("/").pop();
        var ligne = document.createElement("li");
        ligne.className = "bo-diapo";
        ligne.setAttribute("data-diapo", "");
        ligne.setAttribute("draggable", "true");
        ligne.innerHTML =
          '<span class="bo-diapo__poignee" aria-hidden="true">⣿</span>' +
          '<img alt="">' +
          '<span class="bo-diapo__nom"></span>' +
          '<input type="hidden" name="diapo_image[]">' +
          '<input type="hidden" name="diapo_etat[]" value="1" data-diapo-etat>' +
          '<button class="bo-bascule" type="button" role="switch" data-diapo-bascule aria-checked="true">' +
            '<span class="bo-bascule__piste" aria-hidden="true"></span>' +
            '<span class="bo-bascule__nom">Affichée</span>' +
          '</button>' +
          '<span class="bo-diapo__ordre">' +
            '<button type="button" data-diapo-monter aria-label="Monter">▲</button>' +
            '<button type="button" data-diapo-descendre aria-label="Descendre">▼</button>' +
          '</span>' +
          '<button class="bo-diapo__retrait" type="button" data-diapo-retirer>Retirer</button>';

        ligne.querySelector("img").src = vignette ? vignette.src : "";
        ligne.querySelector(".bo-diapo__nom").textContent = nom;
        ligne.querySelector("[name='diapo_image[]']").value = chemin;
        ligne.querySelector("[data-diapo-retirer]")
             .setAttribute("aria-label", "Retirer " + nom + " du diaporama");

        liste.appendChild(ligne);
        vide();
        ligne.scrollIntoView({ block: "center", behavior: "smooth" });
      });
    });

    liste.addEventListener("dragstart", function (e) {
      porte = e.target.closest("[data-diapo]");
      if (!porte) return;
      porte.classList.add("est-portee");
      e.dataTransfer.effectAllowed = "move";
      // Firefox n'amorce pas le déplacement sans données transportées
      e.dataTransfer.setData("text/plain", "");
    });

    liste.addEventListener("dragend", function () {
      if (porte) porte.classList.remove("est-portee");
      porte = null;
    });

    liste.addEventListener("dragover", function (e) {
      if (!porte) return;
      e.preventDefault();

      var survolee = e.target.closest("[data-diapo]");
      if (!survolee || survolee === porte) return;

      // insérer avant ou après selon le côté survolé, sinon la ligne
      // oscille entre deux positions dès que le curseur tremble
      var cadre = survolee.getBoundingClientRect();
      var apres = e.clientY > cadre.top + cadre.height / 2;
      liste.insertBefore(porte, apres ? survolee.nextSibling : survolee);
    });

    liste.addEventListener("click", function (e) {
      var ligne = e.target.closest("[data-diapo]");
      if (!ligne) return;

      if (e.target.closest("[data-diapo-monter]") && ligne.previousElementSibling) {
        liste.insertBefore(ligne, ligne.previousElementSibling);
        e.target.focus();
      }
      if (e.target.closest("[data-diapo-descendre]") && ligne.nextElementSibling) {
        liste.insertBefore(ligne.nextElementSibling, ligne);
        e.target.focus();
      }

      var bascule = e.target.closest("[data-diapo-bascule]");
      if (bascule) {
        var champ = ligne.querySelector("[data-diapo-etat]");
        var affichee = bascule.getAttribute("aria-checked") !== "true";
        bascule.setAttribute("aria-checked", affichee ? "true" : "false");
        bascule.querySelector(".bo-bascule__nom").textContent = affichee ? "Affichée" : "Masquée";
        champ.value = affichee ? "1" : "0";
      }

      if (e.target.closest("[data-diapo-retirer]")) {
        var nom = ligne.querySelector(".bo-diapo__nom").textContent.trim();
        if (!window.confirm("Retirer " + nom + " du diaporama ?")) return;
        // la ligne quitte la page : elle ne sera plus envoyée à l'enregistrement
        ligne.remove();
        vide();
      }
    });
  })();


  /* ------------------------------------------------------------------
     Ajouter un bloc sans perdre la saisie en cours.

     Le bouton d'ajout appartient à un second formulaire, hors du grand
     formulaire d'édition — un formulaire imbriqué n'existe pas en HTML. Sans
     rien faire, cliquer « ajouter » enverrait ce petit formulaire seul, et
     tout ce qui vient d'être tapé serait perdu.

     On recopie donc, au moment du clic, l'ensemble des champs du formulaire
     principal en champs cachés. Le serveur reçoit alors la page complète, y
     ajoute le bloc vide, et enregistre le tout d'un coup.
     ------------------------------------------------------------------ */
  (function () {
    var principal = document.querySelector("form[data-form-page]");
    var ajouts = document.querySelectorAll("form[data-ajout-bloc]");
    if (!principal || !ajouts.length) return;

    Array.prototype.forEach.call(ajouts, function (ajout) {
      ajout.addEventListener("submit", function () {
        // les copies du clic précédent, s'il y en a eu un
        Array.prototype.forEach.call(
          ajout.querySelectorAll("[data-copie]"),
          function (n) { n.remove(); }
        );

        var donnees = new FormData(principal);
        donnees.forEach(function (valeur, cle) {
          // le jeton du petit formulaire fait foi : celui du grand ferait doublon
          if (cle === "csrf" || typeof valeur !== "string") return;
          var champ = document.createElement("input");
          champ.type = "hidden";
          champ.name = cle;
          champ.value = valeur;
          champ.setAttribute("data-copie", "");
          ajout.appendChild(champ);
        });
      });
    });
  })();

  /* ------------------------------------------------------------------
     Rouvrir le bloc désigné par l'ancre.

     Après un ajout, le serveur renvoie vers #bloc-7 ; un <details> replié
     ignore l'ancre, et l'on retombe en haut d'une page de quinze blocs sans
     savoir lequel vient d'être créé.
     ------------------------------------------------------------------ */
  (function () {
    function ouvrirCible() {
      if (!window.location.hash) return;
      var cible = document.querySelector(window.location.hash);
      if (!cible) return;
      if (cible.tagName === "DETAILS") cible.open = true;
      cible.scrollIntoView({ block: "center" });
    }
    ouvrirCible();
    window.addEventListener("hashchange", ouvrirCible);
  })();

  /* ---------- Apparence : la couleur de la commune ----------
     L'aperçu recalcule la palette à chaque mouvement du sélecteur, avec la
     MÊME formule que App\Core\Charte : mêmes bornes de saturation, mêmes
     cibles de contraste, même sens de recherche. C'est une duplication, et
     elle est assumée — l'alternative serait un aller-retour serveur à chaque
     pixel de la roue, sur un hébergement mutualisé. Si les cibles changent
     d'un côté, elles doivent changer de l'autre : le commentaire de Charte.php
     le rappelle, et couleur.py mesure le résultat côté serveur, qui fait foi.

     Rien ici n'est nécessaire pour enregistrer : sans JavaScript, l'écran
     montre la palette enregistrée et le formulaire fonctionne. */
  (function () {
    var champ = document.querySelector('[data-couleur]');
    var boite = document.querySelector('[data-palette]');
    if (!champ || !boite) return;

    var hex = document.querySelector('[data-couleur-hex]');
    var SAT_MIN = 18, SAT_MAX = 55;

    function versTsl(c) {
      var r = parseInt(c.substr(1, 2), 16) / 255,
          v = parseInt(c.substr(3, 2), 16) / 255,
          b = parseInt(c.substr(5, 2), 16) / 255;
      var max = Math.max(r, v, b), min = Math.min(r, v, b), l = (max + min) / 2;
      if (max === min) return [0, 0, l * 100];
      var d = max - min,
          s = l > 0.5 ? d / (2 - max - min) : d / (max + min), h;
      if (max === r)      h = (v - b) / d + (v < b ? 6 : 0);
      else if (max === v) h = (b - r) / d + 2;
      else                h = (r - v) / d + 4;
      return [h * 60, s * 100, l * 100];
    }

    function depuisTsl(h, s, l) {
      h = ((h % 360) + 360) % 360 / 360;
      s = Math.max(0, Math.min(100, s)) / 100;
      l = Math.max(0, Math.min(100, l)) / 100;
      var deux = function (n) { n = Math.round(n * 255).toString(16); return n.length < 2 ? "0" + n : n; };
      if (s === 0) { var g = deux(l); return "#" + g + g + g; }
      var q = l < 0.5 ? l * (1 + s) : l + s - l * s, p = 2 * l - q;
      var canal = function (t) {
        t = ((t % 1) + 1) % 1;
        if (t < 1 / 6) return p + (q - p) * 6 * t;
        if (t < 1 / 2) return q;
        if (t < 2 / 3) return p + (q - p) * (2 / 3 - t) * 6;
        return p;
      };
      return "#" + deux(canal(h + 1 / 3)) + deux(canal(h)) + deux(canal(h - 1 / 3));
    }

    function luminance(c) {
      var somme = 0, poids = [0.2126, 0.7152, 0.0722];
      for (var i = 0; i < 3; i++) {
        var x = parseInt(c.substr(1 + i * 2, 2), 16) / 255;
        somme += poids[i] * (x <= 0.04045 ? x / 12.92 : Math.pow((x + 0.055) / 1.055, 2.4));
      }
      return somme;
    }
    function rapport(a, b) {
      var la = luminance(a), lb = luminance(b);
      return (Math.max(la, lb) + 0.05) / (Math.min(la, lb) + 0.05);
    }
    function composer(dessus, alpha, dessous) {
      var deux = function (n) { n = Math.round(n).toString(16); return n.length < 2 ? "0" + n : n; }, r = "#";
      for (var i = 0; i < 3; i++) {
        var a = parseInt(dessus.substr(1 + i * 2, 2), 16),
            b = parseInt(dessous.substr(1 + i * 2, 2), 16);
        r += deux(a * alpha + b * (1 - alpha));
      }
      return r;
    }

    var NEUTRES = {
      "ardoise": [36, 17], "ardoise-2": [38, 13], "anthracite": [16, 16],
      "encre-2": [23, 24], "fond-teinte": [24, 96]
    };

    function palette(choix) {
      var tsl = versTsl(choix), h = tsl[0];
      var sat = Math.max(SAT_MIN, Math.min(SAT_MAX, tsl[1]));
      var n = {}, cle;
      for (cle in NEUTRES) if (NEUTRES.hasOwnProperty(cle)) {
        n[cle] = depuisTsl(h, NEUTRES[cle][0], NEUTRES[cle][1]);
      }
      function resoudre(depart, cibles, eclaircir) {
        var sens = eclaircir ? 1 : -1;
        for (var i = 0; i <= 200; i++) {
          var l = depart + sens * i * 0.5;
          if (l < 0 || l > 100) break;
          var c = depuisTsl(h, sat, l), tenu = true;
          for (var j = 0; j < cibles.length; j++) {
            if (rapport(c, cibles[j][0]) < cibles[j][1]) { tenu = false; break; }
          }
          if (tenu) return c;
        }
        return depuisTsl(h, sat, eclaircir ? 100 : 0);
      }
      var fonce = resoudre(33, [["#ffffff", 7]], false);
      var tuile = composer("#ffffff", 0.03, n["ardoise"]);
      return {
        "bleu":       resoudre(41, [["#ffffff", 4.5], [n["fond-teinte"], 4.5]], false),
        "bleu-fonce": fonce,
        "bleu-texte": resoudre(36, [[n["fond-teinte"], 6]], false),
        "bleu-clair": resoudre(61, [[n["ardoise"], 4.6], [n["ardoise-2"], 4.6],
                                    [n["anthracite"], 4.6], [n["encre-2"], 4.6],
                                    [tuile, 4.6]], true),
        "bleu-barre": resoudre(83, [["#565a5d", 4.6], [fonce, 4.6]], true),
        "ardoise":    n["ardoise"]
      };
    }

    function peindre() {
      var p = palette(champ.value), cle;
      for (cle in p) if (p.hasOwnProperty(cle)) {
        var pastille = boite.querySelector('[data-ton="' + cle + '"]');
        var libelle  = boite.querySelector('[data-ton-hex="' + cle + '"]');
        if (pastille) pastille.style.background = p[cle];
        if (libelle) libelle.textContent = p[cle];
      }
      if (hex) hex.textContent = champ.value;
    }

    champ.addEventListener("input", peindre);
    peindre();
  })();

  /* ---------- Assistant : le bouton du site ----------
     Forme, libellé, taille, couleurs : l'aperçu suit la saisie sans
     aller-retour serveur. La résolution de contraste est la MÊME que celle de
     App\Core\Bulle — même seuil, même choix de sens, même pas d'un
     demi-point. Si l'une change, l'autre doit changer : c'est du PHP que le
     site tire ce qu'il peint, et bulle.py mesure le site.

     Rien ici n'est nécessaire pour enregistrer : sans JavaScript, les champs
     fonctionnent et l'écran montre l'état enregistré, rendu par le serveur. */
  (function () {
    var apercu = document.querySelector('[data-bulle-apercu]');
    if (!apercu) return;

    var MINI = 4.5;
    var formes = [].slice.call(document.querySelectorAll('[data-bulle-forme]'));
    var animations = [].slice.call(document.querySelectorAll('[data-bulle-animation]'));
    var rejouer = document.querySelector('[data-bulle-rejouer]');
    var libelle = document.querySelector('[data-bulle-libelle]');
    var nombre = document.querySelector('[data-bulle-taille]');
    var curseur = document.querySelector('[data-bulle-curseur]');
    var champFond = document.querySelector('[data-bulle-champ-fond]');
    var fond = document.querySelector('[data-bulle-fond]');
    var texte = document.querySelector('[data-bulle-texte]');
    var suivre = document.querySelector('[data-bulle-suivre]');
    var fondHex = document.querySelector('[data-bulle-fond-hex]');
    var texteHex = document.querySelector('[data-bulle-texte-hex]');
    var apercuTexte = document.querySelector('[data-bulle-apercu-texte]');
    var sortieRapport = document.querySelector('[data-bulle-rapport]');
    var noteCorrige = document.querySelector('[data-bulle-corrige]');
    var commune = champFond ? champFond.getAttribute('data-bulle-commune') : '';

    function versTsl(c) {
      var r = parseInt(c.substr(1, 2), 16) / 255,
          v = parseInt(c.substr(3, 2), 16) / 255,
          b = parseInt(c.substr(5, 2), 16) / 255;
      var max = Math.max(r, v, b), min = Math.min(r, v, b), l = (max + min) / 2;
      if (max === min) return [0, 0, l * 100];
      var d = max - min, s = l > 0.5 ? d / (2 - max - min) : d / (max + min), h;
      if (max === r)      h = (v - b) / d + (v < b ? 6 : 0);
      else if (max === v) h = (b - r) / d + 2;
      else                h = (r - v) / d + 4;
      return [h * 60, s * 100, l * 100];
    }
    function depuisTsl(h, s, l) {
      h = ((h % 360) + 360) % 360 / 360;
      s = Math.max(0, Math.min(100, s)) / 100;
      l = Math.max(0, Math.min(100, l)) / 100;
      var deux = function (n) { n = Math.round(n * 255).toString(16); return n.length < 2 ? "0" + n : n; };
      if (s === 0) { var g = deux(l); return "#" + g + g + g; }
      var q = l < 0.5 ? l * (1 + s) : l + s - l * s, p = 2 * l - q;
      var canal = function (t) {
        t = ((t % 1) + 1) % 1;
        if (t < 1 / 6) return p + (q - p) * 6 * t;
        if (t < 1 / 2) return q;
        if (t < 2 / 3) return p + (q - p) * (2 / 3 - t) * 6;
        return p;
      };
      return "#" + deux(canal(h + 1 / 3)) + deux(canal(h)) + deux(canal(h - 1 / 3));
    }
    function luminance(c) {
      var somme = 0, poids = [0.2126, 0.7152, 0.0722];
      for (var i = 0; i < 3; i++) {
        var x = parseInt(c.substr(1 + i * 2, 2), 16) / 255;
        somme += poids[i] * (x <= 0.04045 ? x / 12.92 : Math.pow((x + 0.055) / 1.055, 2.4));
      }
      return somme;
    }
    function rapport(a, b) {
      var la = luminance(a), lb = luminance(b);
      return (Math.max(la, lb) + 0.05) / (Math.min(la, lb) + 0.05);
    }

    function resoudreTexte(choisi, surFond) {
      if (rapport(choisi, surFond) >= MINI) return choisi;
      var tsl = versTsl(choisi), eclaircir = luminance(choisi) >= luminance(surFond);
      if (rapport(eclaircir ? "#ffffff" : "#000000", surFond) < MINI) eclaircir = !eclaircir;
      var sens = eclaircir ? 1 : -1;
      for (var i = 0; i <= 200; i++) {
        var l = tsl[2] + sens * i * 0.5;
        if (l < 0 || l > 100) break;
        var c = depuisTsl(tsl[0], tsl[1], l);
        if (rapport(c, surFond) >= MINI) return c;
      }
      return eclaircir ? "#ffffff" : "#000000";
    }

    function formeChoisie() {
      for (var i = 0; i < formes.length; i++) if (formes[i].checked) return formes[i].value;
      return "barre";
    }
    function animationChoisie() {
      for (var i = 0; i < animations.length; i++) if (animations[i].checked) return animations[i].value;
      return "aucune";
    }

    /* Relancer une animation CSS demande de retirer la classe, de forcer un
       recalcul, puis de la remettre : sans la lecture intermédiaire, le
       navigateur regroupe les deux changements et ne voit rien à rejouer. */
    function jouer() {
      var anim = animationChoisie();
      apercu.classList.remove("bo-apercu-bulle--joue");
      if (anim === "aucune") return;
      void apercu.offsetWidth;
      apercu.classList.add("bo-apercu-bulle--joue");
    }

    function peindre() {
      var forme = formeChoisie();
      var suit = suivre && suivre.checked;
      var couleurFond = suit && commune ? commune : (fond ? fond.value : commune);
      var choisi = texte ? texte.value : "#ffffff";
      var peint = resoudreTexte(choisi, couleurFond);

      if (suit && commune && fond) fond.value = commune;
      if (champFond) champFond.classList.toggle("bo-couleur-champ--inactif", !!suit);
      if (fond) fond.disabled = !!suit;

      var taille = nombre ? parseInt(nombre.value, 10) : 52;
      if (isNaN(taille)) taille = 52;

      var anim = animationChoisie();
      // La classe est réécrite en bloc : on lui rend celle qui marque une
      // animation en cours, sinon régler la taille ou la couleur pendant
      // qu'elle se joue l'interromprait sans raison.
      var joue = apercu.classList.contains("bo-apercu-bulle--joue");
      apercu.className = "bo-apercu-bulle assistant--" + forme
                       + (anim !== "aucune" ? " assistant--anim-" + anim : "")
                       + (joue ? " bo-apercu-bulle--joue" : "");
      if (rejouer) rejouer.disabled = (anim === "aucune");
      apercu.style.setProperty("--bulle-fond", couleurFond);
      apercu.style.setProperty("--bulle-texte", peint);
      apercu.style.setProperty("--bulle-taille", taille + "px");

      if (apercuTexte && libelle) {
        apercuTexte.textContent = libelle.value.trim() || libelle.getAttribute("placeholder") || "";
      }

      if (fondHex) fondHex.textContent = couleurFond;
      if (texteHex) texteHex.textContent = choisi;
      if (sortieRapport) {
        sortieRapport.textContent = rapport(peint, couleurFond).toFixed(2).replace(".", ",") + ":1";
      }
      if (noteCorrige) noteCorrige.hidden = (peint === choisi);
    }

    /* Le curseur est révélé ici : sans script, seul le nombre s'affiche et
       le formulaire fonctionne. */
    if (curseur && nombre) {
      curseur.hidden = false;
      curseur.removeAttribute("aria-hidden");
      curseur.addEventListener("input", function () { nombre.value = curseur.value; peindre(); });
      nombre.addEventListener("input", function () { curseur.value = nombre.value; peindre(); });
    }

    /* Le bouton de rejeu n'existe que si le script est là : sans lui il ne
       ferait rien, et un bouton qui ne fait rien est pire qu'absent. */
    if (rejouer) {
      rejouer.hidden = false;
      rejouer.addEventListener("click", jouer);
    }

    for (var i = 0; i < formes.length; i++) formes[i].addEventListener("change", peindre);
    for (var k = 0; k < animations.length; k++) {
      animations[k].addEventListener("change", function () { peindre(); jouer(); });
    }
    if (libelle) libelle.addEventListener("input", peindre);
    if (nombre) nombre.addEventListener("change", peindre);
    if (fond) fond.addEventListener("input", peindre);
    if (texte) texte.addEventListener("input", peindre);
    if (suivre) suivre.addEventListener("change", peindre);
    peindre();
    jouer();
  })();

  /* ---------- Apparence : taille du logo ----------
     Le champ nombre est la source de vérité et fonctionne seul : le curseur
     est révélé ici, et l'aperçu suit. Rien de ce bloc n'est nécessaire pour
     enregistrer — c'est du confort, et il se retire de lui-même sur les
     autres écrans. */
  (function () {
    var nombre = document.querySelector('[data-logo-nombre]');
    var apercu = document.querySelector('[data-logo-apercu]');
    if (!nombre || !apercu) return;

    var curseur = document.querySelector('[data-logo-curseur]');
    var deborde = document.querySelector('[data-logo-deborde]');
    var resume  = document.querySelector('[data-logo-resume]');

    var mini = parseInt(nombre.getAttribute('min'), 10) || 36;
    var maxi = parseInt(nombre.getAttribute('max'), 10) || 120;

    /* Le curseur n'existe pour l'utilisateur qu'à partir d'ici : sans script
       il resterait un doublon muet du champ nombre. */
    if (curseur) {
      curseur.hidden = false;
      curseur.removeAttribute('aria-hidden');
      curseur.removeAttribute('tabindex');
    }

    function valeur() {
      var v = parseInt(nombre.value, 10);
      if (isNaN(v)) v = 52;
      return Math.max(mini, Math.min(maxi, v));
    }

    function peindre() {
      var v = valeur();
      var d = deborde ? deborde.checked : false;
      apercu.style.setProperty('--ap-logo', v + 'px');
      apercu.classList.toggle('bo-logo-apercu--deborde', d);
      if (curseur) curseur.value = v;
      /* Ce que le site appliquera vraiment, avec les formules de site.css :
         --entete-h-suivie d'un côté, la hauteur d'origine et le plancher
         --logo-air-mini de l'autre. Un dépassement annoncé de travers serait
         pire que pas d'annonce du tout. */
      if (resume) {
        if (d) {
          var haut = Math.max(12, (96 - v) / 2);
          var sous = Math.max(0, Math.round(haut + v - 96));
          resume.textContent = sous > 0
            ? 'Barre de 96 px de haut ; le logo la dépasse de ' + sous + ' px.'
            : 'Barre de 96 px de haut ; le logo y tient encore.';
        } else {
          resume.textContent = 'Barre de ' + (v + 44) + ' px de haut.';
        }
      }
    }

    nombre.addEventListener('input', peindre);
    nombre.addEventListener('change', peindre);
    if (deborde) deborde.addEventListener('change', peindre);
    if (curseur) {
      curseur.addEventListener('input', function () {
        nombre.value = curseur.value;
        peindre();
      });
    }
    peindre();
  })();

})();
