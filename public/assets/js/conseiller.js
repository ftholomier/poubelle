/* Conseiller du back-office — la pastille en bas à droite.
 *
 * ES5 tolérant, comme le reste du socle, et le bloc se désarme seul si sa
 * cible est absente de la page : les écrans servis sans conseiller — parce
 * qu'il est éteint, ou qu'aucune clé n'est enregistrée — n'ont rien à faire
 * de ce fichier, et il ne doit rien y casser.
 *
 * RÈGLE ABSOLUE DE CE FICHIER : rien de ce que le modèle renvoie ne passe par
 * innerHTML. Tout est posé en textContent, et la mise en forme est construite
 * nœud par nœud. Le conseiller est un tiers qui écrit dans une page
 * d'administration : le traiter comme du texte, jamais comme du balisage, est
 * la seule façon d'être sûr qu'une réponse ne devient jamais du script.
 */
(function () {
  "use strict";

  var boite = document.querySelector("[data-conseil]");
  if (!boite) return;

  var jeton = boite.getAttribute("data-conseil-jeton") || "";
  var pastille = boite.querySelector("[data-conseil-ouvrir]");
  var panneau = boite.querySelector("#bo-conseil-panneau");
  var fermer = boite.querySelector("[data-conseil-fermer]");
  var fil = boite.querySelector("[data-conseil-fil]");
  var form = boite.querySelector("[data-conseil-form]");
  var champ = boite.querySelector("[data-conseil-question]");
  var envoyer = boite.querySelector("[data-conseil-envoyer]");
  var zoneBilan = boite.querySelector("[data-conseil-bilan]");
  var lancer = boite.querySelector("[data-conseil-lancer]");

  if (!pastille || !panneau || !fil || !form || !champ) return;

  /* Le fil de la conversation survit à un changement d'écran : le conseiller
     est ouvert PENDANT qu'on travaille, et l'on navigue en le lisant. Il ne
     survit pas à la fermeture du navigateur — sessionStorage, comme la bulle
     du site public. */
  var CLE_FIL = "bo.conseil.fil";
  var CLE_OUVERT = "bo.conseil.ouvert";
  var historique = [];

  var lire = function (cle) {
    try { return window.sessionStorage.getItem(cle); } catch (e) { return null; }
  };
  var ecrire = function (cle, valeur) {
    try { window.sessionStorage.setItem(cle, valeur); } catch (e) { /* navigation privée */ }
  };

  // ---------------------------------------------------------------- affichage

  /* Le texte du modèle, posé en nœuds.
   *
   * On ne rend qu'une mise en forme : les paragraphes, les listes à puces, et
   * les blocs ```proposition``` qui deviennent un texte encadré avec son
   * bouton. Le gras et les titres sont ignorés — ils n'apporteraient rien
   * dans un panneau de trois cents pixels de large. */
  var rendre = function (texte, ou) {
    var blocs = texte.split(/```/);

    blocs.forEach(function (bloc, rang) {
      // les rangs impairs sont l'intérieur d'un bloc encadré
      if (rang % 2 === 1) {
        var lignes = bloc.replace(/^\s*proposition\s*\n?/i, "");
        proposition(lignes.replace(/^\n+|\n+$/g, ""), ou);
        return;
      }

      bloc.split(/\n{2,}/).forEach(function (para) {
        para = para.replace(/^\n+|\n+$/g, "");
        if (!para) return;

        var puces = para.split("\n").filter(function (l) {
          return /^\s*[·\-*]\s+/.test(l);
        });

        if (puces.length && puces.length === para.split("\n").length) {
          var ul = document.createElement("ul");
          ul.className = "bo-conseil__puces";
          puces.forEach(function (l) {
            var li = document.createElement("li");
            li.textContent = l.replace(/^\s*[·\-*]\s+/, "");
            ul.appendChild(li);
          });
          ou.appendChild(ul);
          return;
        }

        var p = document.createElement("p");
        p.textContent = para;
        ou.appendChild(p);
      });
    });
  };

  /* Un texte proposé, avec le bouton qui le pose dans un champ.
   *
   * « Poser », et jamais « enregistrer » : la mairie voit le texte dans son
   * formulaire, le relit, et valide elle-même. Un modèle de langage invente
   * des pièces à fournir avec beaucoup d'aplomb — le dernier geste reste
   * humain, c'est la règle de ce site. */
  var proposition = function (texte, ou) {
    if (!texte) return;

    var cadre = document.createElement("div");
    cadre.className = "bo-conseil__proposition";

    var corps = document.createElement("p");
    corps.className = "bo-conseil__proposition-texte";
    corps.textContent = texte;
    cadre.appendChild(corps);

    var gestes = document.createElement("p");
    gestes.className = "bo-conseil__proposition-gestes";

    /* Le champ visé est le dernier que l'agent a touché sur l'écran. C'est
       plus sûr que de laisser le modèle nommer un champ : il se tromperait,
       et poserait un texte dans la mauvaise case sans qu'on le voie. */
    if (dernierChamp && document.contains(dernierChamp)) {
      var poser = document.createElement("button");
      poser.type = "button";
      poser.className = "bo-btn bo-btn--petit";
      poser.textContent = "Poser dans « " + nomLisible(dernierChamp) + " »";
      poser.addEventListener("click", function () {
        dernierChamp.value = texte;
        dernierChamp.dispatchEvent(new Event("input", { bubbles: true }));
        dernierChamp.focus();
        poser.textContent = "Posé — à relire puis enregistrer";
        poser.disabled = true;
      });
      gestes.appendChild(poser);
    } else {
      var aide = document.createElement("span");
      aide.className = "bo-conseil__aide";
      aide.textContent = "Cliquez d’abord dans le champ à remplir, puis redemandez.";
      gestes.appendChild(aide);
    }

    var copier = document.createElement("button");
    copier.type = "button";
    copier.className = "bo-btn bo-btn--fantome bo-btn--petit";
    copier.textContent = "Copier";
    copier.addEventListener("click", function () {
      if (navigator.clipboard) {
        navigator.clipboard.writeText(texte).then(function () {
          copier.textContent = "Copié";
        }, function () { /* refus du navigateur : rien à dire */ });
      }
    });
    gestes.appendChild(copier);

    cadre.appendChild(gestes);
    ou.appendChild(cadre);
  };

  /* Le nom que l'agent lit sur l'écran, pour le bouton. À défaut, le name=
     du champ, qui reste plus parlant que « ce champ ». */
  var nomLisible = function (element) {
    var id = element.getAttribute("id");
    var etiquette = id ? document.querySelector('label[for="' + id + '"]') : null;
    if (!etiquette) etiquette = element.closest("label");
    var texte = etiquette ? etiquette.textContent : "";
    texte = (texte || element.getAttribute("name") || "ce champ").replace(/\s+/g, " ").trim();

    return texte.length > 32 ? texte.slice(0, 31) + "…" : texte;
  };

  /* Le champ où poser une proposition : le dernier touché hors du panneau. */
  var dernierChamp = null;
  document.addEventListener("focusin", function (e) {
    var cible = e.target;
    if (!cible || boite.contains(cible)) return;
    if (cible.matches("input[type=text], input[type=url], input[type=email], textarea")) {
      dernierChamp = cible;
    }
  });

  var message = function (qui, texte) {
    var bulle = document.createElement("div");
    bulle.className = "bo-conseil__message bo-conseil__message--" + qui;
    rendre(texte, bulle);
    fil.appendChild(bulle);
    fil.scrollTop = fil.scrollHeight;

    return bulle;
  };

  var attente = function (ou, mot) {
    var p = document.createElement("p");
    p.className = "bo-conseil__attente";
    p.textContent = mot;
    ou.appendChild(p);
    ou.scrollTop = ou.scrollHeight;

    return p;
  };

  // ------------------------------------------------------------------- réseau

  var demander = function (adresse, charge) {
    return fetch(adresse, {
      method: "POST",
      headers: { "Content-Type": "application/json", "X-CSRF": jeton },
      body: JSON.stringify(charge || {}),
      credentials: "same-origin"
    }).then(function (r) {
      return r.json().then(function (json) {
        if (!r.ok) throw new Error(json && json.erreur ? json.erreur : "Le conseiller n’a pas répondu.");
        return json;
      }, function () {
        throw new Error("Le conseiller n’a pas répondu.");
      });
    });
  };

  // -------------------------------------------------------------- conversation

  form.addEventListener("submit", function (e) {
    e.preventDefault();
    var question = champ.value.replace(/^\s+|\s+$/g, "");
    if (!question) return;

    champ.value = "";
    champ.disabled = true;
    if (envoyer) envoyer.disabled = true;
    message("agent", question);
    var patiente = attente(fil, "Le conseiller lit le site…");

    demander("/admin/conseiller", { question: question, historique: historique })
      .then(function (json) {
        patiente.remove();
        message("conseil", json.reponse || "");
        historique.push({ role: "user", texte: question });
        historique.push({ role: "model", texte: json.reponse || "" });
        // douze tours suffisent : au-delà, le début n'est plus lu
        historique = historique.slice(-12);
        ecrire(CLE_FIL, JSON.stringify(historique));
      })
      .catch(function (err) {
        patiente.remove();
        message("erreur", err.message);
      })
      .then(function () {
        champ.disabled = false;
        if (envoyer) envoyer.disabled = false;
        champ.focus();
      });
  });

  // -------------------------------------------------------------------- bilan

  var afficherBilan = function (bilan) {
    zoneBilan.textContent = "";

    var liste = bilan && bilan.recommandations ? bilan.recommandations : [];
    if (!liste.length) {
      var vide = document.createElement("p");
      vide.className = "bo-conseil__accueil";
      vide.textContent = "Aucune recommandation. Relancez le bilan après avoir modifié le site.";
      zoneBilan.appendChild(vide);
      return;
    }

    if (bilan.date) {
      var quand = document.createElement("p");
      quand.className = "bo-conseil__date";
      quand.textContent = "Bilan du " + new Date(bilan.date * 1000).toLocaleDateString("fr-FR");
      zoneBilan.appendChild(quand);
    }

    liste.forEach(function (r) {
      var item = document.createElement("article");
      item.className = "bo-conseil__reco bo-conseil__reco--" + (r.urgence || "moyenne");

      var haut = document.createElement("p");
      haut.className = "bo-conseil__reco-haut";
      var etiquette = document.createElement("span");
      etiquette.className = "bo-conseil__etiquette";
      etiquette.textContent = r.domaine || "contenu";
      haut.appendChild(etiquette);
      item.appendChild(haut);

      var titre = document.createElement("h3");
      titre.className = "bo-conseil__reco-titre";
      titre.textContent = r.titre || "";
      item.appendChild(titre);

      if (r.constat) {
        var constat = document.createElement("p");
        constat.textContent = r.constat;
        item.appendChild(constat);
      }
      if (r.geste) {
        var geste = document.createElement("p");
        geste.className = "bo-conseil__reco-geste";
        geste.textContent = r.geste;
        item.appendChild(geste);
      }
      /* L'adresse est validée côté serveur : elle ne peut désigner qu'un
         écran du back-office, jamais un site extérieur. */
      if (r.ecran) {
        var lien = document.createElement("a");
        lien.className = "bo-conseil__reco-lien";
        lien.href = r.ecran;
        lien.textContent = "Ouvrir l’écran";
        item.appendChild(lien);
      }

      zoneBilan.appendChild(item);
    });
  };

  if (lancer && zoneBilan) {
    lancer.addEventListener("click", function () {
      lancer.disabled = true;
      zoneBilan.textContent = "";
      var patiente = attente(zoneBilan, "Le conseiller relit tout le site. Comptez une minute.");

      demander("/admin/conseiller/bilan", {})
        .then(function (json) {
          patiente.remove();
          afficherBilan(json);
        })
        .catch(function (err) {
          patiente.remove();
          var p = document.createElement("p");
          p.className = "bo-conseil__message bo-conseil__message--erreur";
          p.textContent = err.message;
          zoneBilan.appendChild(p);
        })
        .then(function () { lancer.disabled = false; });
    });
  }

  // ------------------------------------------------------------------ onglets

  var onglets = [].slice.call(boite.querySelectorAll("[data-conseil-onglet]"));
  var vues = [].slice.call(boite.querySelectorAll("[data-conseil-vue]"));

  onglets.forEach(function (onglet) {
    onglet.addEventListener("click", function () {
      var vise = onglet.getAttribute("data-conseil-onglet");
      onglets.forEach(function (o) {
        var actif = o === onglet;
        o.classList.toggle("est-actif", actif);
        o.setAttribute("aria-selected", actif ? "true" : "false");
      });
      vues.forEach(function (v) {
        v.hidden = v.getAttribute("data-conseil-vue") !== vise;
      });

      // le dernier bilan connu s'affiche sans rien redemander à Google
      if (vise === "bilan" && zoneBilan && !zoneBilan.querySelector(".bo-conseil__reco")) {
        fetch("/admin/conseiller/bilan", { credentials: "same-origin" })
          .then(function (r) { return r.json(); })
          .then(function (json) { if (json && json.recommandations && json.recommandations.length) afficherBilan(json); })
          .catch(function () { /* pas de bilan enregistré : le texte d'accueil reste */ });
      }
    });
  });

  // -------------------------------------------------------------- ouverture

  var basculer = function (ouvrir) {
    panneau.hidden = !ouvrir;
    pastille.setAttribute("aria-expanded", ouvrir ? "true" : "false");
    boite.classList.toggle("est-ouvert", ouvrir);
    ecrire(CLE_OUVERT, ouvrir ? "1" : "0");
    if (ouvrir) champ.focus();
  };

  pastille.addEventListener("click", function () { basculer(panneau.hidden); });
  if (fermer) fermer.addEventListener("click", function () { basculer(false); pastille.focus(); });

  document.addEventListener("keydown", function (e) {
    if (e.key === "Escape" && !panneau.hidden) { basculer(false); pastille.focus(); }
  });

  // ------------------------------------------------------------- reprise

  var repris = lire(CLE_FIL);
  if (repris) {
    try {
      historique = JSON.parse(repris) || [];
      historique.forEach(function (tour) {
        message(tour.role === "model" ? "conseil" : "agent", tour.texte || "");
      });
    } catch (e) { historique = []; }
  }
  if (lire(CLE_OUVERT) === "1") basculer(true);
})();
