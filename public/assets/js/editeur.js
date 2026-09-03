/* Back-office — éditeur de texte allégé.

   Volontairement pauvre. Un éditeur complet laisse coller une mise en page
   entière depuis un traitement de texte, et la page cesse de ressembler au
   reste du site sans que personne ne sache pourquoi. Ici : gras, italique,
   listes, deux tailles, les couleurs de la charte, un lien, un bouton. Rien
   d'autre n'est atteignable, et ce qui passe quand même est retiré côté
   serveur par App\Core\TexteRiche — le filtre fait foi, ce fichier n'est
   qu'un confort.

   Sans JavaScript, la <textarea> reste seule et visible, avec le HTML dedans :
   l'enregistrement fonctionne à l'identique. C'est elle que le serveur lit
   dans tous les cas ; la zone éditable ne fait que la remplir. */
(function () {
  'use strict';

  var editeurs = document.querySelectorAll('[data-editeur]');
  if (!editeurs.length || !document.execCommand) return;

  /* Les deux familles de classes, et ce qu'elles autorisent. Elles doivent
     rester le miroir de TexteRiche::TAILLES et ::COULEURS : une classe connue
     d'ici mais pas de là serait retirée à l'enregistrement, et l'utilisateur
     verrait sa mise en forme disparaître sans explication. Les valeurs sont
     lues dans les <select>, qui sont eux rendus depuis PHP — donc rien à
     tenir à jour ici. */
  function famille(select) {
    var classes = [];
    for (var i = 0; i < select.options.length; i++) {
      if (select.options[i].value) classes.push(select.options[i].value);
    }
    return classes;
  }

  /* Deux écrans, deux façons de poser le contenu de départ, et il faut les
     servir tous les deux avec le même code — c'est tout l'intérêt d'avoir
     réuni les deux éditeurs qui existaient.

       · corps des pages   la <textarea> porte le HTML et reste visible tant
                           que ce script n'a pas tourné : sans JavaScript,
                           l'écran fonctionne toujours ;
       · notes de l'assistant  la zone est déjà remplie côté serveur et la
                           <textarea> est cachée dès le départ.

     Dans les deux cas la <textarea> est ce que le serveur lit ; la zone ne
     fait que la remplir. */
  function monter(bloc) {
    var champ = bloc.parentNode;
    var source = bloc.querySelector('textarea[data-editeur-champ]')
      || (champ && champ.querySelector('textarea[data-editeur-source]'));
    var zone = bloc.querySelector('[data-editeur-zone]');
    if (!source || !zone) return;

    var preRempli = source.hasAttribute('data-editeur-champ');
    if (!preRempli) zone.innerHTML = source.value;
    zone.setAttribute('contenteditable', 'true');
    zone.setAttribute('role', 'textbox');
    zone.setAttribute('aria-multiline', 'true');
    /* L'étiquette pointe vers la textarea, qu'on masque : sans reprise du
       libellé, la zone d'édition serait annoncée sans nom. */
    var etiquette = source.id && champ
      ? champ.querySelector('label[for="' + source.id + '"]') : null;
    if (etiquette && !zone.getAttribute('aria-labelledby')) {
      zone.setAttribute('aria-label', etiquette.textContent.trim());
    }
    bloc.hidden = false;
    source.hidden = true;
    source.value = zone.innerHTML;

    /* Entrée doit produire un paragraphe, pas un <div> : le <div> n'est pas
       dans la liste blanche, il serait déballé, et deux paragraphes tapés à la
       suite se retrouveraient collés en un seul. */
    try { document.execCommand('defaultParagraphSeparator', false, 'p'); } catch (e) {}

    function synchroniser() { source.value = zone.innerHTML; }
    zone.addEventListener('input', synchroniser);
    zone.addEventListener('blur', synchroniser);

    /* Le collage passe en texte brut. C'est la protection la plus utile de
       tout le fichier : un copier-coller depuis un traitement de texte apporte
       des polices, des tailles en points et des couleurs de fond qui font
       exploser la page. Le serveur les retirerait de toute façon, mais
       l'utilisateur les verrait à l'écran jusqu'à l'enregistrement, et croirait
       les avoir perdues. */
    zone.addEventListener('paste', function (e) {
      var presse = e.clipboardData || window.clipboardData;
      if (!presse) return;
      e.preventDefault();
      var texte = presse.getData('text/plain') || '';
      document.execCommand('insertText', false, texte);
    });

    function commande(nom, valeur) {
      zone.focus();
      reposerSelection();
      document.execCommand(nom, false, valeur || null);
      synchroniser();
    }

    /* --- Les classes de charte -------------------------------------------
       Posées à la main, pas par execCommand('insertHTML'). Chromium fait
       passer le fragment inséré par son propre nettoyage : il retire la classe
       et la remplace par le style calculé, en couleurs et en pixels. On
       obtenait donc `style="color: rgb(127,197,118)"` au lieu de
       `class="texte-vert"` — c'est-à-dire exactement ce que le filtre serveur
       refuse, et la mise en forme disparaissait à l'enregistrement. Les API
       de Range n'ont pas ce nettoyage.

       On retire d'abord les classes de la même famille présentes dans la
       sélection : sans cela, choisir deux tailles de suite empilerait deux
       classes contradictoires, et la dernière ne gagnerait que par hasard. */
    function appliquerClasse(classe, classesFamille) {
      zone.focus();
      reposerSelection();
      var sel = window.getSelection();
      if (!sel || sel.isCollapsed || !sel.rangeCount) return;

      var intervalle = sel.getRangeAt(0);
      var contenu = intervalle.extractContents();

      var spans = contenu.querySelectorAll ? contenu.querySelectorAll('span') : [];
      for (var i = 0; i < spans.length; i++) {
        var span = spans[i];
        for (var j = 0; j < classesFamille.length; j++) {
          span.classList.remove(classesFamille[j]);
        }
        if (!span.className) {
          while (span.firstChild) span.parentNode.insertBefore(span.firstChild, span);
          span.parentNode.removeChild(span);
        }
      }

      var pose;
      if (classe) {
        pose = document.createElement('span');
        pose.className = classe;
        pose.appendChild(contenu);
      } else {
        pose = contenu;
      }
      var repere = pose.nodeType === 11 ? pose.lastChild : pose;
      intervalle.insertNode(pose);

      /* Laisser la sélection sur ce qu'on vient de mettre en forme : sinon le
         curseur retombe ailleurs et un second réglage — la couleur après la
         taille — s'appliquerait à autre chose. */
      if (repere) {
        var neuf = document.createRange();
        neuf.selectNodeContents(repere.nodeType === 1 ? repere : repere.parentNode);
        sel.removeAllRanges();
        sel.addRange(neuf);
        selectionGardee = neuf.cloneRange();
      }
      synchroniser();
    }

    /* --- Lien et bouton ---------------------------------------------------
       Même contrôle d'adresse que côté serveur, pour que le refus arrive
       pendant la saisie et non après l'enregistrement. Les deux listes doivent
       dire la même chose que TexteRiche::adresse(). */
    function adresseValide(url) {
      // mêmes caractères de contrôle que côté serveur : ils servent à
      // couper « java\nscript: » en deux pour passer sous le filtre
      url = (url || '').replace(/[\u0000-\u0020]/g, '');
      if (!url) return null;
      if (url.charAt(0) === '/' || url.charAt(0) === '#') return url;
      if (/^(https?|mailto|tel):/i.test(url)) return url;
      return null;
    }

    var boutonBoite = bloc.querySelector('[data-editeur-bouton]');
    var champLibelle = bloc.querySelector('[data-bouton-libelle]');
    var champUrl = bloc.querySelector('[data-bouton-url]');
    var erreur = bloc.querySelector('[data-bouton-erreur]');

    /* --- Garder la sélection ----------------------------------------------
       Le point le plus délicat de tout l'éditeur. Dès qu'on touche à la barre
       d'outils, la zone perd le focus et, avec lui, ce que l'utilisateur avait
       sélectionné : on applique alors le gras à rien du tout. Deux parades,
       nécessaires toutes les deux.

       La première : on retient en continu le dernier intervalle qui se trouvait
       dans la zone. selectionchange est le seul événement qui le voie passer —
       un mousedown sur un <select> arrive trop tard, et certains navigateurs
       n'en émettent aucun quand la liste s'ouvre au clavier.

       La seconde est plus bas : la barre annule le mousedown, pour que les
       boutons ne prennent jamais le focus. */
    var selectionGardee = null;

    document.addEventListener('selectionchange', function () {
      var sel = window.getSelection();
      if (!sel || !sel.rangeCount) return;
      var intervalle = sel.getRangeAt(0);
      if (zone.contains(intervalle.commonAncestorContainer)) {
        selectionGardee = intervalle.cloneRange();
      }
    });

    function garderSelection() {
      var sel = window.getSelection();
      if (sel && sel.rangeCount && zone.contains(sel.getRangeAt(0).commonAncestorContainer)) {
        selectionGardee = sel.getRangeAt(0).cloneRange();
      }
      return selectionGardee;
    }

    function reposerSelection() {
      if (!selectionGardee) return;
      var sel = window.getSelection();
      sel.removeAllRanges();
      sel.addRange(selectionGardee);
    }

    function direErreur(message) {
      if (!erreur) return;
      erreur.textContent = message || '';
      erreur.hidden = !message;
    }

    function ouvrirBouton() {
      var intervalle = garderSelection();
      if (champLibelle) {
        champLibelle.value = intervalle ? String(intervalle.toString()).trim() : '';
      }
      if (champUrl) champUrl.value = '';
      direErreur('');
      if (boutonBoite) boutonBoite.hidden = false;
      if (champLibelle) champLibelle.focus();
    }

    function insererBouton() {
      var libelle = champLibelle ? champLibelle.value.trim() : '';
      var url = adresseValide(champUrl ? champUrl.value : '');
      if (!libelle) { direErreur('Indiquez l’intitulé du bouton.'); return; }
      if (!url) {
        direErreur('Adresse invalide : commencez par / pour une page du site, ou par https://');
        return;
      }
      zone.focus();
      reposerSelection();

      // même raison qu'appliquerClasse : insertHTML mangerait la classe
      var ancre = document.createElement('a');
      ancre.className = 'bouton';
      ancre.setAttribute('href', url);
      ancre.textContent = libelle;

      var sel = window.getSelection();
      if (sel && sel.rangeCount) {
        var intervalle = sel.getRangeAt(0);
        intervalle.deleteContents();
        intervalle.insertNode(ancre);
        intervalle.setStartAfter(ancre);
        intervalle.collapse(true);
        sel.removeAllRanges();
        sel.addRange(intervalle);
      } else {
        zone.appendChild(ancre);
      }
      if (boutonBoite) boutonBoite.hidden = true;
      synchroniser();
    }

    function poserLien() {
      var intervalle = garderSelection();
      if (!intervalle || intervalle.collapsed) {
        direErreur('Sélectionnez d’abord le texte à transformer en lien.');
        if (boutonBoite) boutonBoite.hidden = false;
        return;
      }
      var saisie = window.prompt('Adresse du lien (/contact ou https://…)', '');
      if (saisie === null) return;
      var url = adresseValide(saisie);
      zone.focus();
      reposerSelection();
      if (!url) {
        window.alert('Adresse invalide : commencez par / pour une page du site, ou par https://');
        return;
      }
      document.execCommand('createLink', false, url);
      synchroniser();
    }

    /* Retirer la mise en forme : removeFormat ne touche pas aux classes que
       nous avons posées nous-mêmes, il faut donc déballer les spans à la main. */
    function nettoyer() {
      zone.focus();
      reposerSelection();
      var sel = window.getSelection();
      if (!sel || sel.isCollapsed || !sel.rangeCount) return;
      var intervalle = sel.getRangeAt(0);
      var texte = document.createTextNode(intervalle.toString());
      intervalle.deleteContents();
      intervalle.insertNode(texte);
      intervalle.selectNode(texte);
      sel.removeAllRanges();
      sel.addRange(intervalle);
      selectionGardee = intervalle.cloneRange();
      synchroniser();
    }

    /* La barre annule le mousedown : sans cela le bouton prend le focus, la
       zone le perd, et le navigateur replie la sélection avant que la commande
       ne s'exécute. Les <select> et les champs du bouton en sont exclus — ils
       ont besoin du focus pour s'ouvrir et se saisir. */
    var barre = bloc.querySelector('.bo-editeur__barre');
    if (barre) {
      barre.addEventListener('mousedown', function (e) {
        if (e.target.closest('select')) return;
        e.preventDefault();
      });
    }

    bloc.addEventListener('click', function (e) {
      var cible = e.target.closest('button');
      if (!cible) return;
      if (cible.hasAttribute('data-commande')) {
        commande(cible.getAttribute('data-commande'), cible.getAttribute('data-valeur'));
        return;
      }
      if (cible.hasAttribute('data-lien')) { poserLien(); return; }
      if (cible.hasAttribute('data-bouton')) { ouvrirBouton(); return; }
      if (cible.hasAttribute('data-bouton-valider')) { insererBouton(); return; }
      if (cible.hasAttribute('data-bouton-annuler')) {
        if (boutonBoite) boutonBoite.hidden = true;
        return;
      }
      if (cible.hasAttribute('data-nettoyer')) { nettoyer(); }
    });

    var choix = bloc.querySelectorAll('[data-classes]');
    for (var k = 0; k < choix.length; k++) {
      (function (select) {
        var classes = famille(select);
        /* La sélection disparaît quand le <select> prend le focus : on la
           garde au moment où l'on appuie, avant que le navigateur ne la perde. */
        select.addEventListener('mousedown', garderSelection);
        select.addEventListener('keydown', garderSelection);
        select.addEventListener('change', function () {
          appliquerClasse(select.value, classes);
          select.selectedIndex = 0;
        });
      })(choix[k]);
    }

    /* Le formulaire peut partir sans que la zone ait perdu le focus — un
       raccourci clavier, un bouton cliqué directement. */
    var formulaire = bloc.closest('form');
    if (formulaire) formulaire.addEventListener('submit', synchroniser);
  }

  for (var i = 0; i < editeurs.length; i++) monter(editeurs[i]);
})();
