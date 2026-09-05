/* Angeot — interactions.
   En-tête au défilement, menu (panneau glissant et barre horizontale),
   diaporama, galerie, avis, assistant, consentement aux cookies.

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

  /* ---------- Diaporama du bandeau d'accueil ----------

     Le fondu et le zoom sont laissés au CSS ; ce bloc ne fait qu'ordonner les
     tours. Deux précautions contre le à-coup : l'animation de zoom de la vue
     entrante est relancée avant qu'elle ne devienne visible, et celle de la
     vue sortante n'est coupée qu'une fois le fondu terminé. Le compte à
     rebours s'arrête quand l'onglet passe en arrière-plan, sinon le retour
     rattrape d'un coup toutes les vues manquées. */
  (function () {
    var scene = document.querySelector("[data-diaporama]");
    if (!scene) return;

    var vues = [].slice.call(scene.querySelectorAll("[data-vue]"));
    if (vues.length < 2) {
      if (vues.length === 1) vues[0].classList.add("se-rapproche");
      return;
    }

    var trait = document.querySelector("[data-diaporama-trait]");
    var doux = window.matchMedia("(prefers-reduced-motion: reduce)");
    var FONDU = 1800;
    var pause = (parseFloat(getComputedStyle(scene).getPropertyValue("--pause")) || 6) * 1000;
    var courant = 0;
    var minuteur = null;

    var relancerZoom = function (vue) {
      vue.classList.remove("se-rapproche");
      void vue.offsetWidth; // forcer la reprise de l'animation depuis son début
      vue.classList.add("se-rapproche");
    };

    var relancerTrait = function () {
      if (!trait) return;
      trait.classList.remove("court");
      void trait.offsetWidth;
      trait.classList.add("court");
    };

    var afficher = function (rang) {
      var sortante = vues[courant];
      var entrante = vues[rang];

      relancerZoom(entrante);
      entrante.classList.add("est-visible");
      sortante.classList.remove("est-visible");

      // couper le zoom de la sortante trop tôt la ferait sauter à vue
      window.setTimeout(function () {
        if (!sortante.classList.contains("est-visible")) {
          sortante.classList.remove("se-rapproche");
        }
      }, FONDU + 60);

      courant = rang;
      relancerTrait();
    };

    var suivante = function () {
      afficher((courant + 1) % vues.length);
    };

    var lancer = function () {
      arreter();
      if (doux.matches) return;
      minuteur = window.setInterval(suivante, pause + FONDU);
    };

    var arreter = function () {
      if (minuteur) { window.clearInterval(minuteur); minuteur = null; }
    };

    document.addEventListener("visibilitychange", function () {
      if (document.hidden) { arreter(); return; }
      relancerTrait();
      lancer();
    });

    /* Une image de fond n'est décodée qu'au moment où elle devient visible :
       la toute première transition attendait donc son fichier, et cette
       attente se voyait. Les vues suivantes sont chargées d'avance. */
    var precharger = function () {
      vues.forEach(function (vue) {
        var url = (vue.style.backgroundImage.match(/url\(["']?([^"')]+)/) || [])[1];
        if (url) new Image().src = url;
      });
    };

    // les images de fond ne déclenchent pas d'événement de chargement : on
    // attend le chargement complet pour que la première vue n'apparaisse pas
    // pendant que son fichier arrive encore
    var demarrer = function () {
      precharger();
      relancerZoom(vues[0]);
      relancerTrait();
      lancer();
    };
    if (document.readyState === "complete") demarrer();
    else window.addEventListener("load", demarrer);
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

    // 600 ms se lisaient comme un saut : le glissement doit être vu
    var DUREE_PAS = 1500;
    /* Le retour parcourt toute la piste : à durée égale il paraîtrait
       précipité, d'où ce temps plus long. */
    var DUREE_RETOUR = 2200;
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
      piste.style.scrollSnapType = "";

      x = Math.max(0, Math.min(x, maxDefilement()));
      var depart = piste.scrollLeft;
      var delta = x - depart;
      if (Math.abs(delta) < 1) return;

      // mouvement réduit demandé : on saute, sans animer
      if (lent || !duree) { piste.scrollLeft = x; majEtat(); return; }

      /* L'ancrage « mandatory » ramène la piste sur le point le plus proche
         à chaque image : les positions intermédiaires étaient annulées et le
         déplacement se voyait comme un saut à l'arrivée. On le suspend le
         temps du mouvement, il reprend la main pour le doigt et la molette. */
      piste.style.scrollSnapType = "none";

      var t0 = null;
      animation = requestAnimationFrame(function pas(t) {
        if (t0 === null) t0 = t;
        var p = Math.min(1, (t - t0) / duree);
        var e = p < 0.5 ? 4 * p * p * p : 1 - Math.pow(-2 * p + 2, 3) / 2;
        piste.scrollLeft = depart + delta * e;
        if (p < 1) { animation = requestAnimationFrame(pas); return; }
        animation = null;
        piste.style.scrollSnapType = "";
        majEtat();
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

    /* Les témoignages trop longs sont bornés en hauteur : sans repère, rien
       ne dit qu'il en reste. On leur adjoint une barre dessinée, la barre
       native étant flottante donc invisible à l'arrêt. */
    function equiperLesLongs() {
      cartes.forEach(function (carte) {
        var texte = carte.querySelector(".avis__texte");
        if (!texte) return;

        var deborde = texte.scrollHeight > texte.clientHeight + 1;
        carte.classList.toggle("avis__carte--long", deborde);

        var barre = texte.parentNode.querySelector(".avis__piste-texte");
        if (!deborde) { if (barre) barre.remove(); return; }
        if (!barre) {
          barre = document.createElement("span");
          barre.className = "avis__piste-texte";
          barre.setAttribute("aria-hidden", "true");
          barre.appendChild(document.createElement("span"));
          texte.parentNode.appendChild(barre);
        }

        // la barre longe le seul témoignage, pas toute la carte
        barre.style.top = texte.offsetTop + "px";
        barre.style.height = texte.clientHeight + "px";

        var pouce = barre.firstChild;
        var majPouce = function () {
          var part = texte.clientHeight / texte.scrollHeight;
          var haut = texte.scrollTop / texte.scrollHeight;
          pouce.style.height = (part * 100) + "%";
          pouce.style.transform = "translateY(" + (haut / part * 100) + "%)";
        };
        majPouce();
        texte.addEventListener("scroll", majPouce, { passive: true });
      });
    }

    equiperLesLongs();
    window.addEventListener("resize", equiperLesLongs);

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


  /* ---------- Assistant de discussion ----------
     La conversation survit à la navigation : elle est gardée dans le
     sessionStorage, qui suit l'onglet de page en page et s'efface à sa
     fermeture. Un cookie n'aurait rien apporté ici — rien n'est envoyé au
     serveur à chaque requête — et aurait demandé un consentement.

     Le serveur en tient son propre journal, consultable dans le back-office :
     celui-ci sert à l'affichage, celui-là au suivi commercial. */
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
    var formContact = boite.querySelector("[data-assistant-contact]");
    var boutonRappel = boite.querySelector("[data-assistant-rappel]");
    var annuler = boite.querySelector("[data-assistant-annuler]");

    var historique = [];
    var enCours = false;
    var CLE = "lsc.assistant";

    /* --- mémoire de l'onglet --- */
    function memoire() {
      try { return JSON.parse(sessionStorage.getItem(CLE)) || {}; } catch (e) { return {}; }
    }
    function retenir(donnees) {
      // Le stockage peut refuser d'écrire (navigation privée, quota) : la
      // discussion doit continuer sans lui.
      try { sessionStorage.setItem(CLE, JSON.stringify(donnees)); } catch (e) {}
    }

    var etat = memoire();
    var conversation = etat.id || (Date.now().toString(36) + "-" + Math.random().toString(36).slice(2, 8));

    /* --- L'animation d'appel -------------------------------------------
       Elle n'a de sens que pour quelqu'un qui n'a pas encore vu le bouton.
       Elle est retirée dès qu'il l'ouvre, et ne se rejoue pas dans un onglet
       où une conversation est déjà commencée : continuer à agiter le bouton
       devant quelqu'un qui s'en sert serait bête. La classe est reconnue à son
       préfixe, comme dans la feuille de style. */
    var animationInitiale = "";
    for (var ci = 0; ci < boite.classList.length; ci++) {
      if (boite.classList[ci].indexOf("assistant--anim-") === 0) {
        animationInitiale = boite.classList[ci];
      }
    }
    var appelTermine = false;

    function cesserDappeler() {
      appelTermine = true;
      if (animationInitiale) boite.classList.remove(animationInitiale);
    }

    /* Le rappel au défilement : le visiteur parcourt la page, s'arrête, et le
       bouton se signale de nouveau. C'est le moment utile — quelqu'un qui
       vient de s'arrêter de lire est justement celui qui cherche quelque
       chose.

       Trois garde-fous, et ils ne sont pas décoratifs. Chaque rappel dure ce
       que dure l'animation, soit cinq secondes au plus : c'est la borne posée
       dans site.css, et elle vaut pour chaque déclenchement. S'y ajoutent une
       distance minimale — un tremblement de molette n'est pas un parcours —,
       un délai entre deux rappels, et un plafond : passé trois rappels, le
       bouton se tait pour de bon. Un bouton qui se remet à bouger à chaque
       arrêt de défilement serait insupportable au bout d'une page, et c'est
       exactement le genre de chose qu'on ne voit pas en la programmant. */
    var DEFILEMENT_MINI = 350;    // px parcourus depuis le dernier rappel
    var ARRET_MS = 450;           // le défilement est considéré arrêté
    var ENTRE_RAPPELS_MS = 8000;  // au plus tôt, après l'animation d'entrée
    var RAPPELS_MAX = 3;

    var rappelsFaits = 0;
    var dernierRappel = Date.now();
    var yRepere = window.pageYOffset || 0;
    var minuteurArret = null;

    function rappeler() {
      if (!animationInitiale || appelTermine) return;
      // Retirer, forcer un recalcul, remettre : sans la lecture intermédiaire,
      // le navigateur regroupe les deux changements et ne voit rien à rejouer.
      boite.classList.remove(animationInitiale);
      void boite.offsetWidth;
      // Le délai d'entrée n'a plus lieu d'être : le visiteur est là depuis
      // longtemps, et attendre une seconde et demie après son arrêt ferait
      // partir l'animation alors qu'il a déjà repris sa lecture.
      boite.classList.add("assistant--rappelle");
      boite.classList.add(animationInitiale);
      rappelsFaits++;
      dernierRappel = Date.now();
      if (rappelsFaits >= RAPPELS_MAX) appelTermine = true;
    }

    if (animationInitiale) {
      window.addEventListener("scroll", function () {
        if (appelTermine) return;
        if (minuteurArret) clearTimeout(minuteurArret);
        minuteurArret = setTimeout(function () {
          var y = window.pageYOffset || 0;
          if (Math.abs(y - yRepere) < DEFILEMENT_MINI) return;
          if (Date.now() - dernierRappel < ENTRE_RAPPELS_MS) return;
          yRepere = y;
          rappeler();
        }, ARRET_MS);
      }, { passive: true });
    }

    function sauver() {
      retenir({ id: conversation, historique: historique, ouvert: !panneau.hidden });
    }

    function basculer(etatOuvert) {
      if (etatOuvert) cesserDappeler();
      panneau.hidden = !etatOuvert;
      ouvrir.setAttribute("aria-expanded", etatOuvert ? "true" : "false");
      boite.classList.toggle("assistant--ouvert", etatOuvert);
      if (etatOuvert) { champ.focus(); fil.scrollTop = fil.scrollHeight; }
      sauver();
    }

    ouvrir.addEventListener("click", function () { basculer(panneau.hidden); });
    fermer.addEventListener("click", function () { basculer(false); ouvrir.focus(); });
    document.addEventListener("keydown", function (e) {
      if (e.key === "Escape" && !panneau.hidden) { basculer(false); ouvrir.focus(); }
    });

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

    /* --- reprise de la conversation en cours --- */
    if (etat.historique && etat.historique.length) {
      historique = etat.historique;
      historique.forEach(function (tour) {
        ajouter(tour.role === "model" ? "robot" : "visiteur", tour.texte);
      });
      // pastille discrète : une discussion est en cours, sans rouvrir de
      // force un panneau que le visiteur avait fermé
      boite.classList.add("assistant--repris");
      cesserDappeler();
      if (etat.ouvert) basculer(true);
    }

    function jeton(f) {
      var champJeton = f.querySelector("input[name=_csrf]");
      return champJeton ? champJeton.value : "";
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
      sauver();

      enCours = true;
      envoyer.disabled = true;
      var attente = ajouter("robot", "…");
      attente.classList.add("assistant__message--attente");

      fetch(form.getAttribute("action"), {
        method: "POST",
        headers: { "Content-Type": "application/json", "X-CSRF-Token": jeton(form) },
        body: JSON.stringify({
          question: question,
          historique: historique.slice(0, -1),
          conversation: conversation,
          page: window.location.pathname
        })
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
        sauver();
      }).catch(function () {
        attente.remove();
        ajouter("erreur", "La connexion a échoué. Réessayez.");
        historique.pop();
        sauver();
      }).then(function () {
        enCours = false;
        envoyer.disabled = false;
        champ.focus();
      });
    });

    /* --- demande de rappel --- */
    if (!formContact || !boutonRappel) return;

    function afficherContact(visible) {
      formContact.hidden = !visible;
      boutonRappel.setAttribute("aria-expanded", visible ? "true" : "false");
      if (visible) formContact.querySelector("input").focus();
    }
    boutonRappel.addEventListener("click", function () { afficherContact(formContact.hidden); });
    if (annuler) annuler.addEventListener("click", function () { afficherContact(false); boutonRappel.focus(); });

    formContact.addEventListener("submit", function (e) {
      e.preventDefault();
      var bouton = formContact.querySelector("button[type=submit]");
      bouton.disabled = true;

      fetch(formContact.getAttribute("action"), {
        method: "POST",
        headers: { "Content-Type": "application/json", "X-CSRF-Token": jeton(formContact) },
        body: JSON.stringify({
          nom: formContact.nom.value,
          telephone: formContact.telephone.value,
          email: formContact.email.value,
          conversation: conversation,
          historique: historique
        })
      }).then(function (r) {
        return r.json().then(function (j) { return { ok: r.ok, corps: j }; });
      }).then(function (res) {
        if (res.ok) {
          afficherContact(false);
          ajouter("robot", res.corps.message || "Merci, nous vous rappelons rapidement.");
          formContact.reset();
        } else {
          ajouter("erreur", res.corps.erreur || "L’envoi a échoué.");
        }
      }).catch(function () {
        ajouter("erreur", "La connexion a échoué. Réessayez.");
      }).then(function () { bouton.disabled = false; });
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
            /* Le nonce ne se recopie PAS par les attributs. Le navigateur
               vide l'attribut `nonce` dès l'analyse de la page — c'est une
               protection contre son vol par une feuille de style — et n'en
               garde la valeur que dans la propriété. Le script réveillé
               partait donc avec nonce="" et la politique de sécurité le
               refusait : la mesure d'audience acceptée ne se chargeait
               jamais, sans autre trace qu'une ligne de console. */
            if (vieux.nonce) neuf.nonce = vieux.nonce;
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

    /* Accepter une seule catégorie, depuis le substitut d'un contenu bloqué.
       Le plan d'accès est le cas type : le visiteur veut voir cette carte-là,
       pas ouvrir un panneau de réglages pour comprendre laquelle cocher. Les
       autres catégories gardent leur état, et un refus antérieur n'est donc
       pas balayé par ce clic. */
    document.addEventListener("click", function (e) {
      var bouton = e.target.closest("[data-cookies-accepter]");
      if (!bouton) return;
      e.preventDefault();
      var choix = lire() || rien();
      choix[bouton.getAttribute("data-cookies-accepter")] = true;
      appliquer(ecrire(choix));
      // le bandeau n'a plus lieu d'être : un choix vient d'être enregistré
      boite.hidden = true;
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

  /* ---------- Ce qui est nouveau depuis la dernière visite ----------
     La pastille de l'en-tête ne compte pas « ce qu'il y a », elle compte « ce
     que vous n'avez pas encore vu ». La différence est tout ce qui distingue
     un compteur utile d'un nombre décoratif : un chiffre qui ne bouge jamais
     cesse d'être lu au bout de deux visites.

     La date de dernière visite reste dans le navigateur, dans localStorage.
     Rien n'est déposé pour le compte d'un tiers, rien ne part vers le serveur,
     rien n'identifie personne : c'est un repère que le visiteur garde chez
     lui, pour lui. Le bandeau de consentement n'a donc pas à le couvrir — il
     traite de la mesure d'audience et des contenus externes, qui sont autre
     chose.

     Sans localStorage — navigation privée verrouillée, stockage refusé — tout
     se passe comme avant : le nombre rendu par le serveur reste affiché, et
     personne ne voit d'erreur. */
  (function () {
    var lien = document.querySelector('[data-actu-dates]');
    if (!lien) return;

    var CLE = 'site.actus-vues';
    var pastille = lien.querySelector('[data-actu-pastille]');
    var nom = lien.querySelector('[data-actu-nom]');

    function lire() {
      try { return window.localStorage.getItem(CLE) || ''; } catch (e) { return ''; }
    }
    function ecrire(valeur) {
      try { window.localStorage.setItem(CLE, valeur); } catch (e) { /* stockage refusé */ }
    }
    function aujourdhui() {
      var d = new Date();
      var mm = ('0' + (d.getMonth() + 1)).slice(-2);
      var jj = ('0' + d.getDate()).slice(-2);
      return d.getFullYear() + '-' + mm + '-' + jj;
    }

    var vues = lire();
    if (!vues) {
      /* Premier passage : on ne sait rien, on garde donc le repli du serveur —
         les parutions du dernier mois — plutôt que d'annoncer comme neuf tout
         ce que la commune a publié depuis 2020. */
      ecrire(aujourdhui());
      return;
    }

    var dates = (lien.getAttribute('data-actu-dates') || '').split(',');
    var neuves = 0;
    for (var i = 0; i < dates.length; i++) {
      if (dates[i] && dates[i] > vues) neuves++;
    }

    if (pastille) {
      pastille.textContent = String(neuves);
      pastille.hidden = neuves === 0;
    }
    lien.classList.toggle('entete__actu--neuf', neuves > 0);
    if (nom) {
      var base = 'Actualités, agenda et Flash Info';
      nom.textContent = neuves > 0
        ? base + ' — ' + neuves + (neuves > 1 ? ' nouveautés' : ' nouveauté')
        : base;
    }

    /* Le repère avance quand on est allé voir : en cliquant sur la pastille,
       ou simplement en arrivant sur la page des actualités par un autre
       chemin — le menu, un lien dans le texte. C'est le fait d'avoir vu qui
       compte, pas le chemin emprunté. */
    function marquerVu() { ecrire(aujourdhui()); }
    lien.addEventListener('click', marquerVu);
    if (document.querySelector('[data-page-actualites]')) marquerVu();
  })();

  /* ---------- Filtrage des démarches par famille ----------
     Le serveur sait déjà filtrer : chaque bouton est un vrai lien vers
     /demarches?famille=… et la page fonctionne sans ce bloc. Il n'évite que
     l'aller-retour, et garde la position de lecture — c'est précisément ce
     qu'un sommaire à ancres ne faisait pas.

     L'adresse est mise à jour malgré tout, par pushState : la sélection reste
     partageable, s'ajoute aux favoris, et le bouton Précédent revient au
     filtre précédent au lieu de quitter la page. */
  (function () {
    var barre = document.querySelector('[data-filtres]');
    var liste = document.querySelector('[data-filtre-liste]');
    if (!barre || !liste || !window.history || !history.pushState) return;

    var liens = barre.querySelectorAll('[data-filtre]');
    var titre = document.querySelector('[data-filtre-titre]');
    var intro = document.querySelector('[data-filtre-texte]');
    var annonce = document.querySelector('[data-filtre-annonce]');
    var cartes = liste.querySelectorAll('[data-famille]');

    function appliquer(famille, deplacerFocus) {
      var visibles = 0;
      for (var i = 0; i < cartes.length; i++) {
        var garder = !famille || cartes[i].getAttribute('data-famille') === famille;
        cartes[i].hidden = !garder;
        if (garder) visibles++;
      }

      var actif = null;
      for (var j = 0; j < liens.length; j++) {
        var est = liens[j].getAttribute('data-filtre') === famille;
        if (est) { liens[j].setAttribute('aria-current', 'page'); actif = liens[j]; }
        else { liens[j].removeAttribute('aria-current'); }
      }

      if (titre) {
        titre.textContent = actif && famille
          ? (actif.getAttribute('data-titre') || '')
          : (titre.getAttribute('data-titre-tout') || '');
      }
      if (intro) {
        var texte = actif && famille ? (actif.getAttribute('data-intro') || '') : '';
        intro.textContent = texte;
        intro.hidden = texte === '';
      }
      if (annonce) {
        annonce.textContent = visibles + (visibles > 1 ? ' démarches affichées' : ' démarche affichée');
      }

      /* Le focus va au titre de la grille : sans cela il reste sur le lien
         qu'on vient de cliquer, et rien ne dit à qui navigue au clavier que
         le contenu a changé sous ses yeux. */
      if (deplacerFocus && titre) {
        titre.setAttribute('tabindex', '-1');
        titre.focus({ preventScroll: true });
      }
    }

    function familleDeLAdresse() {
      var m = /[?&]famille=([^&#]*)/.exec(window.location.search);
      return m ? decodeURIComponent(m[1].replace(/\+/g, ' ')) : '';
    }

    barre.addEventListener('click', function (e) {
      var lien = e.target.closest('[data-filtre]');
      if (!lien || e.metaKey || e.ctrlKey || e.shiftKey || e.button !== 0) return;
      e.preventDefault();
      var famille = lien.getAttribute('data-filtre');
      if (famille === familleDeLAdresse()) return;
      history.pushState({ famille: famille }, '', lien.getAttribute('href'));
      appliquer(famille, true);
    });

    window.addEventListener('popstate', function () {
      appliquer(familleDeLAdresse(), false);
    });

    /* Les anciennes adresses en ancre — /demarches#urbanisme — étaient
       imprimées dans le Flash Info et collées dans des courriels. Elles ne
       pointent plus vers rien depuis que les sections ont fusionné en une
       grille : on les traduit en filtre plutôt que de les laisser tomber en
       haut de page. Le remplacement de l'entrée d'historique évite d'ajouter
       un cran au bouton Précédent pour un lien qu'on n'a pas cliqué. */
    (function () {
      var ancre = (window.location.hash || '').slice(1);
      if (!ancre || familleDeLAdresse()) return;
      for (var i = 0; i < liens.length; i++) {
        if (liens[i].getAttribute('data-filtre') === ancre) {
          history.replaceState({ famille: ancre }, '', liens[i].getAttribute('href'));
          appliquer(ancre, false);
          return;
        }
      }
    })();
  })();
})();
