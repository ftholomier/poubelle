<?php
/** Aide : mémo de deux pages (impression, PDF). */
?><!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Mémo du back-office · Sochaux Rétro</title>
<link rel="stylesheet" href="<?= asset('css/fonts.css') ?>">
<link rel="stylesheet" href="<?= asset('admin/aide.css') ?>">
</head>
<body class="aide-print aide-memo">
<section class="mpage">
  <header class="mhead">
    <img src="/assets/img/logo-sochaux-retro.png" alt="">
    <div><span>Sochaux Rétro · back-office</span><h1>Mémo des historiens</h1></div>
  </header>
  <div class="mgrid">
    <div class="mbox">
      <h2>Accès</h2>
      <p>Adresse : <b>votre-site/admin</b>. Mot de passe oublié : lien sous le formulaire de connexion. Votre profil : menu en haut à droite.</p>
      <h2>Raccourcis</h2>
      <table>
        <tr><td><kbd>Ctrl</kbd> + <kbd>K</kbd></td><td>rechercher partout (fiches, photos, écrans)</td></tr>
        <tr><td><kbd>Ctrl</kbd> + <kbd>S</kbd></td><td>enregistrer la fiche ouverte</td></tr>
        <tr><td><b>+ Nouveau</b></td><td>créer un match, une personne, un article…</td></tr>
        <tr><td><b>? Aide</b></td><td>l’aide de l’écran affiché ; les « ? » donnent une aide rapide</td></tr>
        <tr><td><b>★ Favoris</b></td><td>vos écrans à un clic : « + Ajouter un favori », dans la bande du haut</td></tr>
      </table>
    </div>
    <div class="mbox">
      <h2>Statuts d’une fiche</h2>
      <table>
        <tr><td><b>Brouillon</b></td><td>invisible sur le site</td></tr>
        <tr><td><b>À relire</b></td><td>invisible, signalée pour relecture</td></tr>
        <tr><td><b>Planifié</b></td><td>publiée seule à la date choisie</td></tr>
        <tr><td><b>Publié</b></td><td>visible par tous</td></tr>
        <tr><td><b>Corbeille</b></td><td>retirée, récupérable</td></tr>
      </table>
      <p>Chaque enregistrement crée une version (onglet Historique). Un brouillon non enregistré est retrouvé sur l’ordinateur.</p>
    </div>
    <div class="mbox mbox--wide">
      <h2>Composition d’un match</h2>
      <table class="mcompo">
        <tr><th>Colonne</th><th>À saisir</th><th>Exemple</th></tr>
        <tr><td>Poste</td><td>G gardien, D défenseur, M milieu, A attaquant, R remplaçant, E entraîneur</td><td>D</td></tr>
        <tr><td>N°</td><td>numéro de maillot (facultatif)</td><td>4</td></tr>
        <tr><td>Joueur</td><td>choisir la fiche proposée (✓ reliée, + à créer)</td><td>VITELLI Arthur</td></tr>
        <tr><td>Buts</td><td>minutes ; « s.p. » penalty, « csc » contre son camp</td><td>33', 90'+2 s.p.</td></tr>
        <tr><td>Remplacement</td><td>Entrée / Sortie (ou ↑ / ↓)</td><td>Entrée 75'</td></tr>
        <tr><td>Cartons</td><td>J jaune, R rouge</td><td>J 35' R 80'</td></tr>
      </table>
      <p>« Importer depuis un tableau » : coller un tableau copié (Excel, Word, page web). Statistiques, saisons, face-à-face et records se recalculent seuls.</p>
    </div>
    <div class="mbox mbox--wide">
      <h2>L’éditeur de texte</h2>
      <p class="mtools"><b>G · I</b> gras, italique (Ctrl+B, Ctrl+I) · <b>Titre · Sous-titre</b> intertitres · <b>• Liste · 1. Liste</b> · <b>❝ Citation</b> · <b>🔗 Lien</b> vers une adresse · <b>🔗 Fiche</b> lien vers une fiche du musée · <b>🖼 Image</b> de la médiathèque · <b>⏱ Minute</b> minute de jeu en gras (33') · <b>⌫</b> retire la mise en forme d’un texte collé depuis Word · <b>↶</b> annuler · <b>&lt;/&gt;</b> code HTML (avancé).</p>
    </div>
  </div>
</section>
<section class="mpage">
  <div class="mgrid">
    <div class="mbox mbox--wide">
      <h2>Où trouver… ?</h2>
      <table class="mwhere">
        <tr><td>Saisir ou corriger un match</td><td>Contenus › Matchs</td></tr>
        <tr><td>Créer une fiche joueur, entraîneur, dirigeant</td><td>Contenus › Personnes (ou + Nouveau)</td></tr>
        <tr><td>Relier un nom mal orthographié à une fiche</td><td>fiche du joueur › Identité › Autres graphies</td></tr>
        <tr><td>Ajouter, créditer, retoucher des photos</td><td>Contenus › Médiathèque</td></tr>
        <tr><td>Corriger un adversaire, un stade, un lieu</td><td>Contenus › Saisons, adversaires, lieux</td></tr>
        <tr><td>Slider, bandeau, accueil</td><td>Éditorial › Accueil &amp; bandeau</td></tr>
        <tr><td>Ordre des fiches d’une décennie, d’une rubrique</td><td>Éditorial › Rubriques &amp; menus (↑ ↓ un cran, ✥ glisser plus loin)</td></tr>
        <tr><td>Corriger l’orthographe et la syntaxe</td><td>panneau Orthographe › Vérifier l’orthographe ; Qualité › Orthographe</td></tr>
        <tr><td>Vérifier ou compléter une fiche, sources à l’appui</td><td>panneau Recherche sur le web › Chercher sur le web</td></tr>
        <tr><td>Rediriger une adresse, fermer le site un moment</td><td>Éditorial › Redirections, Page d’attente</td></tr>
        <tr><td>Quiz, frise, carte, Rétro-Direct, kit souvenirs</td><td>Interactif ; témoignages « Ils y étaient » : Contributions</td></tr>
        <tr><td>Contributions et messages des visiteurs</td><td>Communauté</td></tr>
        <tr><td>Ce qui reste à vérifier, les nouvelles anomalies</td><td>Pilotage › Qualité › Contrôler maintenant</td></tr>
        <tr><td>Version anglaise</td><td>Système › Traductions EN, ou onglet Version EN</td></tr>
        <tr><td>Voix « Écouter » des fiches et des pages de synthèse</td><td>carte Écouter de l’éditeur ; Système › Fiches audio</td></tr>
        <tr><td>Installer la dernière version du site (admin.)</td><td>Système › Mises à jour</td></tr>
        <tr><td>Une ancienne version, une fiche supprimée</td><td>onglet Historique ; « Voir la corbeille »</td></tr>
      </table>
    </div>
    <div class="mbox">
      <h2>Photos</h2>
      <ul>
        <li><b>Crédit obligatoire</b> à l’envoi, légende et droits ensuite.</li>
        <li>Retouche sans abîmer l’original.</li>
        <li>« Remplacer le fichier » : la photo change partout.</li>
      </ul>
    </div>
    <div class="mbox">
      <h2>Ce que le site fait seul</h2>
      <ul>
        <li>Fiches des joueurs : tous leurs matchs, buts, cartons.</li>
        <li>Saisons, face-à-face, bilans, records, carte.</li>
        <li>PDF et voix « Écouter » de chaque fiche.</li>
        <li>Recherche, partage, sauvegarde chaque jour.</li>
      </ul>
    </div>
    <div class="mbox">
      <h2>Avant de publier</h2>
      <ul>
        <li>Titre et date justes, rubriques cochées.</li>
        <li>Image à la une (mosaïques, partage).</li>
        <li>Photos créditées, joueurs reliés (✓).</li>
        <li>« Vérifier l’orthographe », puis Enregistrer.</li>
        <li>Aperçu, puis Publier.</li>
      </ul>
    </div>
    <div class="mbox">
      <h2>En cas de souci</h2>
      <ul>
        <li><b>Brouillon retrouvé</b> : « Le récupérer » ou « L’ignorer ».</li>
        <li><b>Fiche ouverte par un autre</b> : lecture seule ; « Prendre la main » si besoin.</li>
        <li><b>« Est-ce bien le même match ? »</b> : autre match → Annuler, puis + Nouveau.</li>
        <li><b>Erreur de saisie</b> : Historique › restaurer (administrateur).</li>
        <li><b>Fiche supprimée</b> : corbeille › restaurer.</li>
        <li>Guide complet : menu <b>Aide</b>.</li>
      </ul>
    </div>
  </div>
</section>
</body>
</html>
