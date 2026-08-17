/* Étang Fourchu — interactions.
   Menu latéral (glisse depuis la gauche), en-tête au défilement,
   menus réservation, révélations, visionneuse, filtres galerie. */
(function () {
  "use strict";

  var corps = document.body;

  /* ---------- En-tête compact au défilement ---------- */
  var entete = document.querySelector(".entete");
  if (entete) {
    var majEntete = function () {
      entete.classList.toggle("entete--pleine", window.scrollY > 40);
    };
    window.addEventListener("scroll", majEntete, { passive: true });
    majEntete();
  }

  /* ---------- Panneau de navigation ---------- */
  var burger = document.querySelector(".burger");
  var panneau = document.querySelector(".panneau");
  var voile = document.querySelector(".voile");
  var fermerBtn = document.querySelector(".panneau__fermer");
  var dernierFocus = null;

  function ouvrirMenu() {
    dernierFocus = document.activeElement;
    corps.classList.add("menu-ouvert");
    burger.setAttribute("aria-expanded", "true");
    panneau.removeAttribute("inert");
    corps.style.overflow = "hidden";
    var premier = panneau.querySelector("a, button");
    if (premier) premier.focus({ preventScroll: true });
  }

  function fermerMenu() {
    corps.classList.remove("menu-ouvert");
    burger.setAttribute("aria-expanded", "false");
    panneau.setAttribute("inert", "");
    corps.style.overflow = "";
    if (dernierFocus) dernierFocus.focus({ preventScroll: true });
  }

  if (burger && panneau) {
    panneau.setAttribute("inert", "");
    burger.addEventListener("click", function () {
      corps.classList.contains("menu-ouvert") ? fermerMenu() : ouvrirMenu();
    });
    if (fermerBtn) fermerBtn.addEventListener("click", fermerMenu);
    if (voile) voile.addEventListener("click", fermerMenu);
    document.addEventListener("keydown", function (e) {
      if (e.key === "Escape" && corps.classList.contains("menu-ouvert")) fermerMenu();
    });

    /* piège de focus simple */
    panneau.addEventListener("keydown", function (e) {
      if (e.key !== "Tab") return;
      var focusables = panneau.querySelectorAll("a[href], button:not([disabled])");
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
  }

  /* ---------- Menus déroulants « Réserver » ---------- */
  document.querySelectorAll(".resa").forEach(function (resa) {
    var btn = resa.querySelector(".resa__btn");
    btn.addEventListener("click", function (e) {
      e.stopPropagation();
      var ouvert = resa.hasAttribute("data-ouvert");
      document.querySelectorAll(".resa[data-ouvert]").forEach(function (r) {
        r.removeAttribute("data-ouvert");
        r.querySelector(".resa__btn").setAttribute("aria-expanded", "false");
      });
      if (!ouvert) {
        resa.setAttribute("data-ouvert", "");
        btn.setAttribute("aria-expanded", "true");
      }
    });
  });
  document.addEventListener("click", function () {
    document.querySelectorAll(".resa[data-ouvert]").forEach(function (r) {
      r.removeAttribute("data-ouvert");
      r.querySelector(".resa__btn").setAttribute("aria-expanded", "false");
    });
  });

  /* ---------- Révélation au défilement ---------- */
  var reveles = document.querySelectorAll(".reveler");
  if ("IntersectionObserver" in window && reveles.length) {
    var obs = new IntersectionObserver(function (entrees) {
      entrees.forEach(function (en) {
        if (en.isIntersecting) {
          en.target.classList.add("visible");
          obs.unobserve(en.target);
        }
      });
    }, { threshold: 0.12, rootMargin: "0px 0px -40px" });
    reveles.forEach(function (el) { obs.observe(el); });
  } else {
    reveles.forEach(function (el) { el.classList.add("visible"); });
  }

  /* ---------- Visionneuse d'images ---------- */
  var liens = Array.prototype.slice.call(document.querySelectorAll("[data-visionneuse]"));
  if (liens.length) {
    var vis = document.createElement("div");
    vis.className = "visionneuse";
    vis.setAttribute("role", "dialog");
    vis.setAttribute("aria-label", "Visionneuse d'images");
    vis.innerHTML =
      '<button class="visionneuse__fermer" aria-label="Fermer">✕</button>' +
      '<button class="visionneuse__prec" aria-label="Image précédente">‹</button>' +
      '<img src="" alt="">' +
      '<button class="visionneuse__suiv" aria-label="Image suivante">›</button>';
    document.body.appendChild(vis);

    var img = vis.querySelector("img");
    var courant = 0;

    function visibles() {
      return liens.filter(function (l) { return l.offsetParent !== null; });
    }
    function montrer(i) {
      var liste = visibles();
      if (!liste.length) return;
      courant = (i + liste.length) % liste.length;
      var lien = liste[courant];
      img.src = lien.getAttribute("href");
      img.alt = lien.getAttribute("data-alt") || "";
      vis.setAttribute("data-ouverte", "");
      corps.style.overflow = "hidden";
    }
    function fermer() {
      vis.removeAttribute("data-ouverte");
      img.src = "";
      corps.style.overflow = "";
    }

    liens.forEach(function (lien) {
      lien.addEventListener("click", function (e) {
        e.preventDefault();
        montrer(visibles().indexOf(lien));
      });
    });
    vis.querySelector(".visionneuse__fermer").addEventListener("click", fermer);
    vis.querySelector(".visionneuse__prec").addEventListener("click", function () { montrer(courant - 1); });
    vis.querySelector(".visionneuse__suiv").addEventListener("click", function () { montrer(courant + 1); });
    vis.addEventListener("click", function (e) { if (e.target === vis) fermer(); });
    document.addEventListener("keydown", function (e) {
      if (!vis.hasAttribute("data-ouverte")) return;
      if (e.key === "Escape") fermer();
      if (e.key === "ArrowLeft") montrer(courant - 1);
      if (e.key === "ArrowRight") montrer(courant + 1);
    });
  }

  /* ---------- Filtres de galerie ---------- */
  var filtres = document.querySelector(".filtres");
  if (filtres) {
    filtres.querySelectorAll("button").forEach(function (b) {
      b.addEventListener("click", function () {
        filtres.querySelectorAll("button").forEach(function (x) { x.setAttribute("aria-pressed", "false"); });
        b.setAttribute("aria-pressed", "true");
        var cat = b.getAttribute("data-filtre");
        document.querySelectorAll(".galerie-grille [data-cat]").forEach(function (item) {
          item.style.display = (cat === "tout" || item.getAttribute("data-cat") === cat) ? "" : "none";
        });
      });
    });
  }

  /* ---------- Diaporama du bandeau d'accueil ----------
     Fondu enchaîné d'une photo à l'autre, puis lent mouvement d'approche.
     Sans JavaScript, la première photo reste affichée : rien n'est cassé. */
  var diaporama = document.querySelector("[data-diaporama]");
  if (diaporama) {
    var vues = [].slice.call(diaporama.querySelectorAll(".heros__photo"));
    var lent = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

    // vitesse d'approche constante : la durée suit le temps de pause, si bien
    // qu'une pause plus longue ne donne pas un mouvement plus rapide
    var FONDU = 1600;
    var VITESSE = 0.008;   // agrandissement par seconde
    var pause = Math.max(3, Math.min(30, parseInt(diaporama.getAttribute("data-pause"), 10) || 7)) * 1000;
    var course = pause + FONDU;
    diaporama.style.setProperty("--heros-course", (course / 1000) + "s");
    diaporama.style.setProperty("--heros-echelle", (1 + VITESSE * course / 1000).toFixed(4));

    if (vues.length > 1) {
      var courante = 0;
      var minuteur = null;
      var DUREE = pause;

      // filet de progression : la seule indication qu'une photo suit
      var filet = document.querySelector(".heros__minuteur");
      var relancerFilet = function () {
        if (!filet) return;
        // retirer puis remettre la classe fait repartir l'animation de zéro
        filet.classList.remove("heros__minuteur--court");
        void filet.offsetWidth;
        filet.classList.add("heros__minuteur--court");
      };
      if (filet) {
        filet.style.setProperty("--heros-pause", (pause / 1000) + "s");
      }

      var afficher = function (i) {
        i = (i + vues.length) % vues.length;
        if (i === courante) return;

        var sortante = vues[courante];
        // la sortante s'efface mais garde son animation : la lui retirer
        // tout de suite ramènerait son agrandissement à 1 d'un seul coup,
        // et ce saut se voyait pendant le fondu
        sortante.classList.remove("heros__photo--visible");
        sortante.setAttribute("aria-hidden", "true");
        sortante.removeAttribute("aria-label");
        setTimeout(function () {
          if (!sortante.classList.contains("heros__photo--visible")) {
            sortante.classList.remove("heros__photo--anime");
          }
        }, FONDU);

        var suivante = vues[i];
        // retirer puis remettre la classe relance le mouvement d'approche
        suivante.classList.remove("heros__photo--anime");
        void suivante.offsetWidth;
        suivante.classList.add("heros__photo--anime", "heros__photo--visible");
        suivante.removeAttribute("aria-hidden");

        courante = i;
      };

      var relancer = function () {
        clearInterval(minuteur);
        relancerFilet();
        minuteur = setInterval(function () {
          afficher(courante + 1);
          relancerFilet();
        }, DUREE);
      };

      if (!lent) {
        relancer();
        // inutile de faire tourner le diaporama quand l'onglet est en veille
        document.addEventListener("visibilitychange", function () {
          if (document.hidden) { clearInterval(minuteur); } else { relancer(); }
        });
      }
    }
  }

  /* ---------- Consentement aux cookies ----------
     Rien n'est déposé ni chargé avant un choix explicite. Les scripts et
     contenus soumis à consentement sont écrits en <script type="text/plain">
     ou <template>, et ne sont activés qu'ensuite, catégorie par catégorie.

     Ce bloc est enfermé dans sa propre portée. var appartient à la fonction,
     pas au bloc : sans cette enveloppe, le « var panneau » écrit ici écrasait
     celui du menu de navigation déclaré en haut du fichier. Le menu restait
     alors inerte, et le moindre clic dessus le refermait. */
  (function () {
  var boite = document.querySelector("[data-cookies]");
  if (boite) {
    var NOM = "ef_consentement";
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
      document.dispatchEvent(new CustomEvent("ef:consentement", { detail: choix }));
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
      document.body.style.overflow = "";
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
      document.body.style.overflow = "hidden";
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
  }
  })();
})();
