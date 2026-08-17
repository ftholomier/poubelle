/* Cabinet Villard — interactions.
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
