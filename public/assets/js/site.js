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

    if (vues.length > 1) {
      var courante = 0;
      var minuteur = null;
      var DUREE = 7000;

      // repères cliquables, annoncés proprement aux lecteurs d'écran
      var points = document.createElement("div");
      points.className = "heros__points";
      points.setAttribute("role", "tablist");
      points.setAttribute("aria-label", "Photos du domaine");
      vues.forEach(function (_, i) {
        var b = document.createElement("button");
        b.type = "button";
        b.setAttribute("role", "tab");
        b.setAttribute("aria-label", "Photo " + (i + 1) + " sur " + vues.length);
        b.setAttribute("aria-current", i === 0 ? "true" : "false");
        b.addEventListener("click", function () { afficher(i); relancer(); });
        points.appendChild(b);
      });
      diaporama.parentNode.appendChild(points);

      var afficher = function (i) {
        i = (i + vues.length) % vues.length;
        if (i === courante) return;

        vues[courante].classList.remove("heros__photo--vue");
        vues[courante].setAttribute("aria-hidden", "true");
        vues[courante].removeAttribute("aria-label");

        var suivante = vues[i];
        // retirer puis remettre la classe relance le mouvement d'approche
        suivante.classList.remove("heros__photo--vue");
        void suivante.offsetWidth;
        suivante.classList.add("heros__photo--vue");
        suivante.removeAttribute("aria-hidden");

        points.children[courante].setAttribute("aria-current", "false");
        points.children[i].setAttribute("aria-current", "true");
        courante = i;
      };

      var relancer = function () {
        clearInterval(minuteur);
        minuteur = setInterval(function () { afficher(courante + 1); }, DUREE);
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
})();
