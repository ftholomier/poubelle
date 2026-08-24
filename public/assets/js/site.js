/* Menuiserie Tréhant — interactions.
   En-tête au défilement, menu (panneau glissant et barre horizontale),
   révélations, consentement aux cookies.

   Écrit en ES5 tolérant : chaque bloc se désactive de lui-même si sa cible
   est absente de la page. Aucune dépendance. */
(function () {
  "use strict";

  var corps = document.body;

  /* ---------- En-tête compact au défilement ---------- */
  (function () {
    var entete = document.querySelector(".entete");
    if (!entete) return;

    var majEntete = function () {
      entete.classList.toggle("entete--pleine", window.scrollY > 40);
    };
    window.addEventListener("scroll", majEntete, { passive: true });
    majEntete();
  })();

  /* ---------- Panneau de navigation ----------
     Servi tel quel en disposition latérale, et comme menu du téléphone en
     disposition horizontale : le même code couvre les deux cas.

     Bloc enfermé dans sa propre portée. var appartient à la fonction, pas au
     bloc : sans cette enveloppe, un « var panneau » écrit ailleurs dans le
     fichier écraserait celui-ci, et le menu resterait inerte. */
  (function () {
    var burger = document.querySelector(".burger");
    var panneau = document.querySelector(".panneau");
    var voile = document.querySelector(".voile");
    var fermerBtn = document.querySelector(".panneau__fermer");
    var dernierFocus = null;

    if (!burger || !panneau) return;

    function ouvrirMenu() {
      dernierFocus = document.activeElement;
      corps.classList.add("menu-ouvert");
      burger.setAttribute("aria-expanded", "true");
      panneau.removeAttribute("inert");
      corps.style.overflow = "hidden";
      var premier = panneau.querySelector("a, button");
      if (premier) premier.focus({ preventScroll: true });
    }

    function fermerMenu(rendreFocus) {
      corps.classList.remove("menu-ouvert");
      burger.setAttribute("aria-expanded", "false");
      panneau.setAttribute("inert", "");
      corps.style.overflow = "";
      if (rendreFocus !== false && dernierFocus && document.contains(dernierFocus)) {
        dernierFocus.focus({ preventScroll: true });
      }
    }

    panneau.setAttribute("inert", "");

    burger.addEventListener("click", function () {
      if (corps.classList.contains("menu-ouvert")) { fermerMenu(); } else { ouvrirMenu(); }
    });
    if (fermerBtn) fermerBtn.addEventListener("click", function () { fermerMenu(); });
    if (voile) voile.addEventListener("click", function () { fermerMenu(); });
    document.addEventListener("keydown", function (e) {
      if (e.key === "Escape" && corps.classList.contains("menu-ouvert")) fermerMenu();
    });

    /* En disposition horizontale, le burger disparaît au-delà de 1080 px.
       Sans ce garde-fou, un menu ouvert sur téléphone puis élargi laisserait
       le panneau visible et le défilement de la page bloqué, sans plus aucun
       bouton pour le refermer. */
    if (window.matchMedia) {
      var large = window.matchMedia("(min-width: 1080px)");
      var surLargeur = function (e) {
        if (e.matches && corps.classList.contains("menu-horizontal")
            && corps.classList.contains("menu-ouvert")) {
          fermerMenu(false);
        }
      };
      if (large.addEventListener) {
        large.addEventListener("change", surLargeur);
      } else if (large.addListener) {
        large.addListener(surLargeur);
      }
    }

    /* piège de focus simple */
    panneau.addEventListener("keydown", function (e) {
      if (e.key !== "Tab") return;
      var focusables = panneau.querySelectorAll("a[href], button:not([disabled])");
      if (!focusables.length) return;
      var premier = focusables[0];
      var dernier = focusables[focusables.length - 1];
      if (e.shiftKey && document.activeElement === premier) {
        e.preventDefault(); dernier.focus();
      } else if (!e.shiftKey && document.activeElement === dernier) {
        e.preventDefault(); premier.focus();
      }
    });

    /* accordéons des sous-menus */
    panneau.querySelectorAll(".panneau__accordeon > button").forEach(function (b) {
      b.addEventListener("click", function () {
        var item = b.parentElement;
        var ouvert = item.hasAttribute("data-ouvert");
        panneau.querySelectorAll(".panneau__accordeon[data-ouvert]").forEach(function (autre) {
          autre.removeAttribute("data-ouvert");
          autre.querySelector("button").setAttribute("aria-expanded", "false");
        });
        if (!ouvert) {
          item.setAttribute("data-ouvert", "");
          b.setAttribute("aria-expanded", "true");
        }
      });
    });
  })();

  /* ---------- Barre horizontale : ouverture au clavier ----------
     Le survol suffit à la souris, mais un sous-menu ouvert au clavier doit se
     refermer sur Échap sans quoi la tabulation reste prisonnière du panneau. */
  (function () {
    var parents = document.querySelectorAll(".navbar__item--parent");
    if (!parents.length) return;

    parents.forEach(function (item) {
      item.addEventListener("keydown", function (e) {
        if (e.key !== "Escape") return;
        var lien = item.querySelector(".navbar__lien");
        if (lien) lien.focus();
      });
    });
  })();

  /* ---------- Révélation au défilement ---------- */
  (function () {
    var reveles = document.querySelectorAll(".reveler");
    if (!reveles.length) return;

    if (!("IntersectionObserver" in window)) {
      reveles.forEach(function (el) { el.classList.add("visible"); });
      return;
    }

    var obs = new IntersectionObserver(function (entrees) {
      entrees.forEach(function (en) {
        if (en.isIntersecting) {
          en.target.classList.add("visible");
          obs.unobserve(en.target);
        }
      });
    }, { threshold: 0.1, rootMargin: "0px 0px -40px" });

    reveles.forEach(function (el) { obs.observe(el); });
  })();

  /* ---------- Carrousel des avis ----------
     Les avis défilent de la droite vers la gauche, marquent une pause, puis
     reviennent au début d'un seul mouvement une fois le dernier atteint.

     Le défilement natif de la piste sert de moteur : le script ne fait que
     l'animer. Sans lui, la piste reste parcourable au doigt, à la molette et
     au clavier — les avis suivants ne deviennent jamais inaccessibles. */
  (function () {
    var carrousel = document.querySelector("[data-avis]");
    if (!carrousel) return;

    var piste = carrousel.querySelector(".avis__piste");
    var cartes = [].slice.call(carrousel.querySelectorAll(".avis__carte"));
    var commandes = carrousel.querySelector("[data-avis-commandes]");
    var boutonPrec = carrousel.querySelector("[data-avis-prec]");
    var boutonSuiv = carrousel.querySelector("[data-avis-suiv]");
    var points = [].slice.call(carrousel.querySelectorAll("[data-avis-point]"));
    if (!piste || !cartes.length) return;

    var DUREE_PAS = 600;
    /* Le retour parcourt toute la piste : à durée égale il paraîtrait
       précipité, d'où ce temps plus long. */
    var DUREE_RETOUR = 950;
    var MARGE = 4;   // tolérance d'arrondi sur les positions de défilement

    var pause = (parseInt(carrousel.getAttribute("data-pause"), 10) || 0) * 1000;
    var lent = window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches;

    var minuteur = null;
    var animation = null;
    var suspendu = false;

    function maxDefilement() { return piste.scrollWidth - piste.clientWidth; }
    function deborde() { return maxDefilement() > MARGE; }

    /* Position de gauche de chaque carte, relative à la première. */
    function positions() {
      var base = cartes[0].offsetLeft;
      return cartes.map(function (c) { return c.offsetLeft - base; });
    }

    /* Carte la plus proche du bord gauche : c'est elle que la pastille
       active désigne. */
    function indexCourant() {
      var p = positions(), x = piste.scrollLeft, rang = 0, ecart = Infinity;
      for (var i = 0; i < p.length; i++) {
        var d = Math.abs(p[i] - x);
        if (d < ecart) { ecart = d; rang = i; }
      }
      return rang;
    }

    function allerA(x, duree) {
      if (animation) { cancelAnimationFrame(animation); animation = null; }

      x = Math.max(0, Math.min(x, maxDefilement()));
      var depart = piste.scrollLeft;
      var delta = x - depart;
      if (Math.abs(delta) < 1) return;

      // mouvement réduit demandé : on saute, sans animer
      if (lent || !duree) { piste.scrollLeft = x; majEtat(); return; }

      var t0 = null;
      animation = requestAnimationFrame(function pas(t) {
        if (t0 === null) t0 = t;
        var p = Math.min(1, (t - t0) / duree);
        var e = p < 0.5 ? 4 * p * p * p : 1 - Math.pow(-2 * p + 2, 3) / 2;
        piste.scrollLeft = depart + delta * e;
        animation = p < 1 ? requestAnimationFrame(pas) : null;
      });
    }

    function avancer() {
      var p = positions();
      var i = indexCourant();
      // dernier avis atteint : retour au début, d'un seul mouvement
      if (piste.scrollLeft >= maxDefilement() - MARGE || i + 1 >= p.length) {
        allerA(0, DUREE_RETOUR);
      } else {
        allerA(p[i + 1], DUREE_PAS);
      }
    }

    function majEtat() {
      var p = positions();
      var max = maxDefilement();
      var i = indexCourant();

      /* Une pastille par position réellement atteignable, et non par avis :
         avec quatre avis dont trois tiennent à l'écran, la piste ne s'arrête
         qu'à deux endroits. Quatre pastilles dont deux inertes laisseraient
         croire à un défilement bloqué. */
      points.forEach(function (b, rang) {
        var atteignable = p[rang] <= max + MARGE;
        b.hidden = !atteignable;
        if (atteignable && rang === i) { b.setAttribute("aria-current", "true"); }
        else { b.removeAttribute("aria-current"); }
      });
      if (boutonPrec) boutonPrec.disabled = piste.scrollLeft <= MARGE;
      if (boutonSuiv) boutonSuiv.disabled = piste.scrollLeft >= maxDefilement() - MARGE;

      var utile = deborde();
      if (commandes) commandes.hidden = !utile;
      // sans débordement, la piste n'a rien à faire dans l'ordre de tabulation
      piste.setAttribute("tabindex", utile ? "0" : "-1");
    }

    function arreter() { clearInterval(minuteur); minuteur = null; }

    function relancer() {
      arreter();
      if (suspendu || lent || pause <= 0 || !deborde()) return;
      minuteur = setInterval(avancer, pause);
    }

    // --- commandes manuelles -------------------------------------------
    // Les flèches ne bouclent pas : à la différence du défilement
    // automatique, une commande volontaire ne doit pas envoyer à l'autre
    // bout de la piste sans prévenir.
    if (boutonSuiv) {
      boutonSuiv.addEventListener("click", function () {
        var p = positions(), i = indexCourant();
        if (i + 1 < p.length) allerA(p[i + 1], DUREE_PAS);
        relancer();
      });
    }
    if (boutonPrec) {
      boutonPrec.addEventListener("click", function () {
        var p = positions(), i = indexCourant();
        if (i > 0) allerA(p[i - 1], DUREE_PAS);
        relancer();
      });
    }
    points.forEach(function (b) {
      b.addEventListener("click", function () {
        var rang = parseInt(b.getAttribute("data-avis-point"), 10) || 0;
        allerA(positions()[rang], DUREE_PAS);
        relancer();
      });
    });

    // --- la lecture prime sur le défilement -----------------------------
    ["mouseenter", "pointerdown", "focusin"].forEach(function (ev) {
      carrousel.addEventListener(ev, function () { suspendu = true; arreter(); });
    });
    ["mouseleave", "focusout"].forEach(function (ev) {
      carrousel.addEventListener(ev, function () { suspendu = false; relancer(); });
    });
    piste.addEventListener("touchend", function () { suspendu = false; relancer(); }, { passive: true });

    var enCours = false;
    piste.addEventListener("scroll", function () {
      if (enCours) return;
      enCours = true;
      requestAnimationFrame(function () { majEtat(); enCours = false; });
    }, { passive: true });

    // inutile de faire défiler un onglet que personne ne regarde
    document.addEventListener("visibilitychange", function () {
      if (document.hidden) { arreter(); } else { relancer(); }
    });

    var redimension = null;
    window.addEventListener("resize", function () {
      clearTimeout(redimension);
      redimension = setTimeout(function () { majEtat(); relancer(); }, 200);
    });

    majEtat();
    relancer();
  })();


  /* ---------- Galerie des réalisations : filtres et visionneuse ----------
     Sa propre portée, comme chaque bloc de ce fichier : un « var » écrit ici
     ne peut donc pas écraser celui d'une autre section. */
  (function () {
    var galerie = document.querySelector("[data-galerie]");
    if (!galerie) return;

    var fiches = [].slice.call(galerie.querySelectorAll(".galerie__item"));
    var vide = document.querySelector("[data-galerie-vide]");

    /* --- filtres --- */
    var barre = document.querySelector("[data-filtres]");
    if (barre) {
      // les boutons ne servent à rien sans script : ils restent cachés tant
      // que celui-ci n'a pas pris la main
      barre.hidden = false;

      var boutons = [].slice.call(barre.querySelectorAll("[data-filtre]"));
      boutons.forEach(function (bouton) {
        bouton.addEventListener("click", function () {
          var choix = bouton.getAttribute("data-filtre");
          var restants = 0;

          fiches.forEach(function (fiche) {
            var garde = choix === "" || fiche.getAttribute("data-categorie") === choix;
            fiche.hidden = !garde;
            if (garde) restants++;
          });

          boutons.forEach(function (b) {
            b.setAttribute("aria-pressed", b === bouton ? "true" : "false");
          });
          if (vide) vide.hidden = restants > 0;
        });
      });
    }

    /* --- visionneuse --- */
    var boite = document.querySelector("[data-visionneuse-boite]");
    if (!boite) return;

    var image = boite.querySelector("[data-visionneuse-image]");
    var legende = boite.querySelector("[data-visionneuse-legende]");
    var rang = 0;
    var appelant = null;

    /** Les vignettes visibles seulement : la navigation suit le filtre actif. */
    function visibles() {
      return fiches.filter(function (f) { return !f.hidden; })
                   .map(function (f) { return f.querySelector("[data-visionneuse]"); });
    }

    function montrer(i) {
      var liste = visibles();
      if (!liste.length) return;
      rang = (i + liste.length) % liste.length;
      var v = liste[rang];
      image.src = v.getAttribute("data-visionneuse");
      image.alt = v.getAttribute("data-legende") || "";
      legende.textContent = v.getAttribute("data-legende") || "";
    }

    function ouvrir(v) {
      appelant = v;
      montrer(visibles().indexOf(v));
      boite.hidden = false;
      corps.classList.add("visionneuse-ouverte");
      boite.querySelector("[data-visionneuse-fermer]").focus();
    }

    function fermer() {
      boite.hidden = true;
      corps.classList.remove("visionneuse-ouverte");
      // l'image est relâchée : une photo pleine taille n'a pas à rester en
      // mémoire une fois la visionneuse refermée
      image.removeAttribute("src");
      if (appelant) appelant.focus();
    }

    galerie.addEventListener("click", function (e) {
      var v = e.target.closest ? e.target.closest("[data-visionneuse]") : null;
      if (v) ouvrir(v);
    });

    boite.querySelector("[data-visionneuse-fermer]").addEventListener("click", fermer);
    boite.querySelector("[data-visionneuse-avant]").addEventListener("click", function () { montrer(rang - 1); });
    boite.querySelector("[data-visionneuse-apres]").addEventListener("click", function () { montrer(rang + 1); });
    boite.addEventListener("click", function (e) { if (e.target === boite) fermer(); });

    document.addEventListener("keydown", function (e) {
      if (boite.hidden) return;
      if (e.key === "Escape") fermer();
      else if (e.key === "ArrowLeft") montrer(rang - 1);
      else if (e.key === "ArrowRight") montrer(rang + 1);
      else if (e.key === "Tab") {
        // le clavier reste enfermé dans la visionneuse tant qu'elle est ouverte
        var f = [].slice.call(boite.querySelectorAll("button"));
        var premier = f[0], dernier = f[f.length - 1];
        if (e.shiftKey && document.activeElement === premier) { e.preventDefault(); dernier.focus(); }
        else if (!e.shiftKey && document.activeElement === dernier) { e.preventDefault(); premier.focus(); }
      }
    });
  })();


  /* ---------- Assistant de discussion ---------- */
  (function () {
    var boite = document.querySelector("[data-assistant]");
    if (!boite) return;

    var ouvrir = boite.querySelector("[data-assistant-ouvrir]");
    var panneau = boite.querySelector(".assistant__panneau");
    var fermer = boite.querySelector("[data-assistant-fermer]");
    var fil = boite.querySelector("[data-assistant-fil]");
    var form = boite.querySelector("[data-assistant-form]");
    var champ = form.querySelector("textarea");
    var envoyer = form.querySelector("button[type=submit]");
    var historique = [];
    var enCours = false;

    function basculer(etat) {
      panneau.hidden = !etat;
      ouvrir.setAttribute("aria-expanded", etat ? "true" : "false");
      boite.classList.toggle("assistant--ouvert", etat);
      if (etat) champ.focus();
    }

    ouvrir.addEventListener("click", function () { basculer(panneau.hidden); });
    fermer.addEventListener("click", function () { basculer(false); ouvrir.focus(); });
    document.addEventListener("keydown", function (e) {
      if (e.key === "Escape" && !panneau.hidden) { basculer(false); ouvrir.focus(); }
    });

    /** Le champ grandit avec le texte, jusqu'à cinq lignes. */
    champ.addEventListener("input", function () {
      champ.style.height = "auto";
      champ.style.height = Math.min(champ.scrollHeight, 120) + "px";
    });
    champ.addEventListener("keydown", function (e) {
      // Entrée envoie, Maj+Entrée passe à la ligne : c'est l'usage attendu
      // d'une zone de discussion.
      if (e.key === "Enter" && !e.shiftKey) { e.preventDefault(); form.requestSubmit(); }
    });

    function ajouter(role, texte) {
      var p = document.createElement("p");
      p.className = "assistant__message assistant__message--" + role;
      p.textContent = texte;
      fil.appendChild(p);
      fil.scrollTop = fil.scrollHeight;
      return p;
    }

    form.addEventListener("submit", function (e) {
      e.preventDefault();
      if (enCours) return;

      var question = champ.value.trim();
      if (!question) return;

      ajouter("visiteur", question);
      historique.push({ role: "user", texte: question });
      champ.value = "";
      champ.style.height = "auto";

      enCours = true;
      envoyer.disabled = true;
      var attente = ajouter("robot", "…");
      attente.classList.add("assistant__message--attente");

      // l'adresse vient du gabarit : elle reste juste si le site est
      // installé dans un sous-répertoire
      fetch(form.getAttribute("action"), {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "X-CSRF-Token": (form.querySelector("input[name=_csrf]") || {}).value || ""
        },
        body: JSON.stringify({ question: question, historique: historique.slice(0, -1) })
      }).then(function (r) {
        return r.json().then(function (j) { return { ok: r.ok, corps: j }; });
      }).then(function (res) {
        attente.remove();
        if (res.ok && res.corps.reponse) {
          ajouter("robot", res.corps.reponse);
          historique.push({ role: "model", texte: res.corps.reponse });
        } else {
          ajouter("erreur", res.corps.erreur || "Une erreur est survenue.");
          historique.pop();
        }
      }).catch(function () {
        attente.remove();
        ajouter("erreur", "La connexion a échoué. Réessayez.");
        historique.pop();
      }).then(function () {
        enCours = false;
        envoyer.disabled = false;
        champ.focus();
      });
    });
  })();

  /* ---------- Consentement aux cookies ----------
     Rien n'est déposé ni chargé avant un choix explicite. Les scripts et
     contenus soumis à consentement sont écrits en <script type="text/plain">
     ou <template>, et ne sont activés qu'ensuite, catégorie par catégorie. */
  (function () {
    var boite = document.querySelector("[data-cookies]");
    if (!boite) return;

    var NOM = "cv_consentement";
    var VERSION = 1;
    var JOURS = 180;
    var CATEGORIES = ["mesure", "externes"];

    var bandeau = boite.querySelector("[data-cookies-bandeau]");
    var panneau = boite.querySelector("[data-cookies-panneau]");
    var voile = boite.querySelector("[data-cookies-voile]");
    var rendu = null;

    var lire = function () {
      var m = document.cookie.match(new RegExp("(?:^|; )" + NOM + "=([^;]*)"));
      if (!m) return null;
      try {
        var o = JSON.parse(decodeURIComponent(m[1]));
        return o && o.v === VERSION ? o : null;
      } catch (e) { return null; }
    };

    var ecrire = function (choix) {
      var o = { v: VERSION, d: new Date().toISOString().slice(0, 10) };
      CATEGORIES.forEach(function (c) { o[c] = !!choix[c]; });
      document.cookie = NOM + "=" + encodeURIComponent(JSON.stringify(o))
        + ";path=/;max-age=" + (JOURS * 86400) + ";SameSite=Lax"
        + (location.protocol === "https:" ? ";Secure" : "");
      return o;
    };

    /* Active ce qui attendait l'accord : <script type="text/plain"> devient
       un vrai script, et les contenus externes remplacent leur substitut. */
    var appliquer = function (choix) {
      CATEGORIES.forEach(function (cat) {
        if (!choix[cat]) return;

        document.querySelectorAll('script[type="text/plain"][data-cookies="' + cat + '"]')
          .forEach(function (vieux) {
            var neuf = document.createElement("script");
            [].forEach.call(vieux.attributes, function (a) {
              if (a.name !== "type" && a.name !== "data-cookies") neuf.setAttribute(a.name, a.value);
            });
            neuf.text = vieux.text;
            vieux.parentNode.replaceChild(neuf, vieux);
          });

        document.querySelectorAll('[data-cookies-contenu="' + cat + '"]').forEach(function (bloc) {
          var gabarit = bloc.querySelector("template");
          if (gabarit) bloc.replaceWith(gabarit.content.cloneNode(true));
        });
      });

      // les autres scripts peuvent réagir sans connaître ce module
      document.dispatchEvent(new CustomEvent("cv:consentement", { detail: choix }));
    };

    var cases = function () {
      return [].slice.call(panneau.querySelectorAll("[data-cookies-categorie]"));
    };

    var refleter = function (choix) {
      cases().forEach(function (c) {
        c.checked = !!choix[c.getAttribute("data-cookies-categorie")];
      });
    };

    var fermerPanneau = function () {
      panneau.hidden = true;
      voile.hidden = true;
      corps.style.overflow = "";
      if (rendu && document.contains(rendu)) rendu.focus();
    };

    var terminer = function (choix) {
      appliquer(ecrire(choix));
      fermerPanneau();
      boite.hidden = true;
    };

    var tout = function () {
      var c = {}; CATEGORIES.forEach(function (x) { c[x] = true; }); return c;
    };
    var rien = function () {
      var c = {}; CATEGORIES.forEach(function (x) { c[x] = false; }); return c;
    };

    var ouvrirPanneau = function (depuis) {
      rendu = depuis || document.activeElement;
      refleter(lire() || rien());
      voile.hidden = false;
      panneau.hidden = false;
      boite.hidden = false;
      corps.style.overflow = "hidden";
      // le focus se pose sur le panneau, pas sur sa croix de fermeture :
      // un lecteur d'écran annonce ainsi le titre avant les commandes
      panneau.focus();
    };

    boite.addEventListener("click", function (e) {
      if (e.target.closest("[data-cookies-tout]")) { terminer(tout()); return; }
      if (e.target.closest("[data-cookies-rien]")) { terminer(rien()); return; }
      if (e.target.closest("[data-cookies-ouvrir]")) { ouvrirPanneau(e.target); return; }
      if (e.target.closest("[data-cookies-fermer]")) {
        // fermer sans choisir laisse le bandeau : le silence ne vaut pas accord
        fermerPanneau();
        if (!lire()) { bandeau.hidden = false; boite.hidden = false; }
        return;
      }
      if (e.target.closest("[data-cookies-enregistrer]")) {
        var c = {};
        cases().forEach(function (x) { c[x.getAttribute("data-cookies-categorie")] = x.checked; });
        terminer(c);
      }
    });

    voile.addEventListener("click", function () {
      fermerPanneau();
      if (!lire()) { bandeau.hidden = false; boite.hidden = false; }
    });

    document.addEventListener("keydown", function (e) {
      if (e.key !== "Escape" || panneau.hidden) return;
      fermerPanneau();
      if (!lire()) { bandeau.hidden = false; boite.hidden = false; }
    });

    // le panneau garde le focus tant qu'il est ouvert
    panneau.addEventListener("keydown", function (e) {
      if (e.key !== "Tab") return;
      var f = [].slice.call(panneau.querySelectorAll("button, input")).filter(function (x) {
        return !x.disabled && x.offsetParent !== null;
      });
      if (!f.length) return;
      var premier = f[0], dernier = f[f.length - 1];
      if (e.shiftKey && document.activeElement === premier) { e.preventDefault(); dernier.focus(); }
      else if (!e.shiftKey && document.activeElement === dernier) { e.preventDefault(); premier.focus(); }
    });

    // rouvrir depuis le pied de page, à tout moment
    document.querySelectorAll("[data-cookies-reglages]").forEach(function (lien) {
      lien.addEventListener("click", function (e) { e.preventDefault(); ouvrirPanneau(lien); });
    });

    var dejaChoisi = lire();
    if (dejaChoisi) {
      appliquer(dejaChoisi);
    } else {
      bandeau.hidden = false;
      boite.hidden = false;
    }
  })();
})();
