<?php
return [
    'slug' => 'articles',
    'title' => 'Articles, pages, objets et moments',
    'summary' => 'Rédiger avec l’éditeur de texte, gérer les pages (dont les pages légales), les objets des réserves et les 100 moments.',
    'sections' => [
        ['id' => 'articles', 'title' => 'Articles et pages', 'html' => <<<'HTML'
<p>Contenus › <b>Articles &amp; pages</b>. Un article est classé dans les rubriques Supporters, Infrastructures, Symboles…; son <b>type</b> (article, bilan de saison, portrait, dossier) adapte la présentation. Les pages sont les textes hors rubrique, dont les mentions légales, la confidentialité et les cookies.</p>
HTML],
        ['id' => 'editeur', 'title' => 'L’éditeur de texte', 'html' => <<<'HTML'
<p>Tous les textes longs du back-office ont le même éditeur de texte, sans code à connaître :</p>
[[img:article-editeur.webp|La barre d’outils de l’éditeur de texte]]
<table>
<tr><th>Bouton</th><th>Effet</th></tr>
<tr><td><b>G</b> / <i>I</i></td><td>gras / italique (<kbd>Ctrl</kbd> + <kbd>B</kbd>, <kbd>Ctrl</kbd> + <kbd>I</kbd>)</td></tr>
<tr><td>Titre, Sous-titre, ¶</td><td>intertitre, sous-intertitre, paragraphe normal</td></tr>
<tr><td>• Liste, 1. Liste, ❝ Citation</td><td>liste à puces, liste numérotée, citation</td></tr>
<tr><td>🔗 Lien</td><td>lien vers une adresse : sélectionnez le texte, cliquez, collez l’adresse</td></tr>
<tr><td>🔗 Fiche</td><td>lien vers une fiche du musée, choisie par son titre</td></tr>
<tr><td>🖼 Image</td><td>image de la médiathèque insérée dans le texte</td></tr>
<tr><td>⏱ Minute</td><td>minute de jeu mise en gras (« 33’ ») dans un résumé de match</td></tr>
<tr><td>⌫, ↶</td><td>retirer la mise en forme (utile après un copier-coller depuis Word), annuler</td></tr>
</table>
<p>Les champs courts (réactions, brèves, légendes…) ont une version simplifiée : gras, italique, lien, lien vers une fiche.</p>
[[attention|<p>Les styles copiés depuis Word ou un site (polices, couleurs) sont retirés à l’enregistrement : le site garde une présentation homogène.</p>]]
HTML],
        ['id' => 'tableaux', 'title' => 'Tableaux', 'html' => <<<'HTML'
<p>Onglet <b>Tableaux</b> : statistiques, classements, compositions d’autres équipes. Cliquez dans une case pour la modifier, ajoutez lignes et colonnes, ou <b>Coller un tableau…</b> copié depuis Excel ou une page web.</p>
HTML],
        ['id' => 'objets', 'title' => 'Les objets des réserves', 'html' => <<<'HTML'
<p>Contenus › <b>Objets (réserves)</b> : maillots, affiches, programmes, coupures de presse, écharpes… Chaque objet a une photo (image à la une), une collection, une date, une provenance, un crédit et des fiches liées (le match ou le joueur concernés). Ils sont présentés dans la rubrique « Réserves du musée ».</p>
[[img:objet.webp|Une fiche objet des réserves]]
HTML],
        ['id' => 'moments', 'title' => '100 ans, 100 moments', 'html' => <<<'HTML'
<p>Éditorial › <b>100 moments</b> : la série hebdomadaire du centenaire. Chaque moment a un numéro (1 à 100), une année, un récit, une image et des fiches liées. Le statut <b>Planifié</b> permet de préparer les moments à l’avance.</p>
[[img:moments.webp|La liste des 100 moments]]
<p><b>Changer la semaine d’un moment</b> : dans le calendrier, les moments pas encore révélés se déplacent avec ↑ / ↓ (une semaine) ou l’icône quatre flèches (glisser plus loin, le trait jaune indique le futur numéro). Les numéros et les dates se recalculent à l’écran ; cliquez <b>Enregistrer le calendrier</b> pour valider. Les moments déjà révélés ne bougent plus.</p>
HTML],
    ],
];
