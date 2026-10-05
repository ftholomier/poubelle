<?php
return [
    'slug' => 'articles',
    'title' => 'Articles, pages, objets et moments',
    'summary' => 'Rédiger avec l’éditeur de texte, gérer les pages (dont les pages légales), les objets des réserves et les 100 moments (calendrier, boîte à idées de l’IA).',
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
<p>Jusqu’au centenaire (20 mai 2028), le site dévoile un à un <b>100 moments</b> de l’histoire du club. Chaque moment est une fiche « Moment » : un titre, une année, la date de l’événement, un récit, une image et des fiches liées. Sur le site, la page « 100 ans, 100 moments » montre 100 cases : celles des moments parus s’ouvrent, les autres restent « À venir », <b>sans date</b> (la surprise est gardée).</p>
<p><b>Vous choisissez la date de parution</b> de chaque moment : dans la fiche, statut <b>Planifié</b> et sa date (panneau Publication), puis <b>Enregistrer</b>. C’est la validation : une seule suffit, et elle est notée dans la fiche (qui, quand). « Publié » le met en ligne tout de suite. Le moment paraît seul à la date choisie.</p>
<p><b>Le numéro suit l’ordre des dates</b> : le premier moment à paraître est le n° 1, et ainsi de suite. Tant qu’un moment n’est pas en ligne, son numéro est provisoire (un moment daté avant lui le décale) ; une fois en ligne, son numéro et sa date ne bougent plus. Brouillons et moments à relire n’ont pas de numéro.</p>
<p><b>La date anniversaire</b> : renseignez la date de l’événement (ou liez le match) ; la fiche propose le bouton « Planifier à la date anniversaire » (par exemple le 11 juin 2027 pour la finale du 11 juin 1988), que vous pouvez changer.</p>
[[img:moments.webp|Le calendrier de parution, les moments à dater et les points à surveiller]]
<p>Éditorial › <b>100 moments</b> : le <b>calendrier de parution</b> (numéro, date modifiable tant que le moment n’est pas en ligne, état), les <b>moments à dater</b> (à relire, brouillons) avec la date anniversaire proposée, le rythme à tenir jusqu’au centenaire et les points <b>à surveiller</b> : deux moments le même jour, un long trou sans moment, un moment à venir sans image, un numéro dont le moment a été retiré.</p>
HTML],
        ['id' => 'moments-idees', 'title' => 'La boîte à idées et les premiers jets de l’IA', 'html' => <<<'HTML'
<p>Éditorial › 100 moments › <b>Boîte à idées</b> : l’IA propose, les historiens décident. Rien n’est publié sans vous, et rien n’indique l’IA sur le site.</p>
<ol>
<li><b>Le sommaire</b> : « Proposer le sommaire » fait lire à l’IA les fiches publiées du musée, époque par époque (matchs marquants, joueurs, entraîneurs, dirigeants, articles, objets). Elle propose des idées de moments, chacune avec un titre, une année, deux ou trois lignes pour dire pourquoi c’est un moment, ses <b>fiches sources</b> et une photo. Une idée qui ne s’appuie sur aucune fiche est écartée d’office : l’IA n’invente rien. Pour une piste précise (« les années 1930 », « les supporters », un joueur), tapez-la et cliquez « Demander ».</li>
<li><b>Le tri</b> : pour chaque idée, <b>Retenir</b>, <b>Modifier</b> (titre, année, date, sources, photo), <b>Autre idée</b> (l’IA en propose une autre de la même époque) ou <b>Écarter</b> (votre raison est rappelée à l’IA, qui ne la proposera plus). « + Ajouter une idée » : les vôtres, retenues d’office.</li>
<li><b>Le premier jet</b> : sur une idée retenue, « Premier jet (IA) » fait écrire le récit à partir des seules fiches sources. La fiche « Moment » est créée « À relire », avec un bandeau qui liste ses sources et les <b>points à vérifier</b> (chiffres, dates, noms, crédit de la photo). « Écrire moi-même » ouvre une fiche préremplie si vous préférez rédiger.</li>
<li><b>La relecture et la validation</b> : corrigez librement (correcteur d’orthographe, recherche sur le web pour vérifier un fait), choisissez la date de parution : c’est la validation. L’IA ne touche plus à un moment rédigé.</li>
</ol>
[[img:moments-idees.webp|Les idées proposées : sources, photo, date anniversaire, actions]]
[[img:moments-premier-jet.webp|Un premier jet « À relire » : sources et points à vérifier]]
[[attention|<p>Le musée n’a de fiches de matchs que depuis 1970 : avant, l’IA ne dispose que des fiches des joueurs et dirigeants et de quelques articles. Le tableau « Couverture par décennie » montre les époques peu couvertes : comblez-les avec vos idées, ou une piste précise.</p>]]
[[auto|<p>Coût : de l’ordre d’un euro pour le sommaire et une centaine de premiers jets ; la dépense réelle s’affiche dans Système › Coûts IA (usage « 100 moments »). Les idées sont gardées dans data/collections/moments-idees.json, avec les sauvegardes.</p>]]
HTML],
    ],
];
