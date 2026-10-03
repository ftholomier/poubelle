<?php
return [
    'slug' => 'matchs',
    'title' => 'Saisir un match',
    'summary' => 'Pas à pas : informations, score, composition, temps forts, récit, photos et vidéos, classement.',
    'sections' => [
        ['id' => 'creer', 'title' => '1. Créer la fiche et remplir les informations', 'html' => <<<'HTML'
<p><b>+ Nouveau › Fiche match</b>, puis l’onglet <b>Infos</b> :</p>
<ul>
<li><b>Date</b> (jj/mm/aaaa) : elle range le match dans sa saison et dans « Ce jour-là ».</li>
<li><b>Compétition</b> (famille : championnat, Coupe de France…) et son <b>libellé</b> exact (« Ligue 2 », « 32e de finale »…), <b>Journée / tour</b>.</li>
<li><b>Domicile / extérieur</b> et <b>Adversaire</b> : choisissez l’adversaire dans la liste proposée pour que son logo et le face-à-face suivent.</li>
<li><b>Stade</b>, <b>Spectateurs</b> (en chiffres), <b>Arbitre</b>.</li>
</ul>
[[img:match-infos.webp|L’onglet Infos d’une fiche match]]
[[auto|<p>Le titre de la fiche (« J23 – Rouen / Sochaux – National – 04/03/2024 – 1-0 ») et son adresse sont proposés automatiquement à partir de ces informations.</p>]]
HTML],
        ['id' => 'score', 'title' => '2. Le score et les buteurs', 'html' => <<<'HTML'
<ul>
<li><b>Buts équipe à domicile / à l’extérieur</b> : l’indication grise rappelle laquelle est Sochaux.</li>
<li><b>Prolongation</b> : « Après prolongation » ou « Tirs au but » (deux champs apparaissent alors pour la séance).</li>
<li><b>Buts par équipe</b> : une ligne par équipe, avec les buteurs et les minutes (« Prat 33’, Thomas 78’ »). Indiquez « (csc) » pour un but contre son camp.</li>
</ul>
[[auto|<p>Le résultat (victoire, nul, défaite) est déduit du score. Le contrôle qualité vérifie que le score correspond aux buteurs de la composition.</p>]]
HTML],
        ['id' => 'composition', 'title' => '3. La composition', 'html' => <<<'HTML'
<p>Onglet <b>Compo &amp; événements</b> : une ligne par joueur, dans l’ordre (titulaires, remplaçants, entraîneur).</p>
[[img:match-compo.webp|La composition : (1) poste, (2) numéro, (3) joueur, (4) capitaine, (5) buts, (6) remplacement, (7) cartons, (8) fiche reliée, (9) déplacer ou supprimer la ligne]]
<table>
<tr><th>Colonne</th><th>À saisir</th><th>Exemple</th></tr>
<tr><td>Poste</td><td>G gardien, D défenseur, M milieu, A attaquant, R remplaçant, E entraîneur</td><td>D</td></tr>
<tr><td>N°</td><td>numéro de maillot (facultatif)</td><td>4</td></tr>
<tr><td>Joueur</td><td>NOM Prénom, choisi dans la liste proposée</td><td>VITELLI Arthur</td></tr>
<tr><td>Cap.</td><td>case cochée pour le capitaine</td><td>☑</td></tr>
<tr><td>Buts</td><td>minutes séparées par des virgules ; « s.p. » pour un penalty, « csc » pour un but contre son camp</td><td>33’, 90’+2 s.p.</td></tr>
<tr><td>Remplacement</td><td>« Entrée 75’ » ou « Sortie 81’ » (les flèches ↑ ↓ marchent aussi)</td><td>Sortie 81’</td></tr>
<tr><td>Cartons</td><td>J pour jaune, R pour rouge, avec la minute</td><td>J 35’ R 80’</td></tr>
</table>
[[auto|<p>À l’enregistrement, chaque ligne alimente la fiche du joueur (matchs, buts, minutes jouées, cartons), la page de la saison, les records et les face-à-face. Un but « csc » compte pour l’adversaire, pas pour le joueur.</p>]]
HTML],
        ['id' => 'relier', 'title' => '4. Relier chaque joueur à sa fiche', 'html' => <<<'HTML'
<p>En tapant un nom dans la colonne Joueur, la liste propose les fiches existantes : choisissez la bonne. La pastille verte <b>✓</b> indique une fiche reliée (cliquez pour l’ouvrir) ; la pastille rose <b>+</b> signale un joueur sans fiche (cliquez pour la créer, déjà remplie avec son nom).</p>
[[img:match-autocomplete.webp|Les fiches proposées en tapant un nom]]
[[astuce|<p>Même sans le choisir dans la liste, un nom bien écrit est relié automatiquement à la bonne fiche. Si un joueur est écrit autrement dans de vieilles compositions, ajoutez cette graphie dans sa fiche (« Autres graphies dans les compositions »).</p>]]
HTML],
        ['id' => 'importer', 'title' => '5. Importer une composition depuis un tableau', 'html' => <<<'HTML'
<p>Bouton <b>Importer depuis un tableau</b> : collez un tableau copié depuis Excel, Word ou une page web. Avec une ligne d’en-tête (Poste, Nom, Numéro, Buts…), les colonnes sont reconnues dans n’importe quel ordre.</p>
[[img:match-import.webp|L’import d’une composition copiée]]
<p>S’il y a déjà des joueurs, vous choisissez de remplacer la composition ou d’ajouter les lignes à la suite. Vérifiez ensuite les postes, puis enregistrez.</p>
HTML],
        ['id' => 'temps-forts', 'title' => '6. Temps forts, réactions et brèves', 'html' => <<<'HTML'
<ul>
<li><b>Temps forts</b> : une ligne par action (minute, texte) ; cochez « But » et indiquez le score du moment pour un but. Ils forment la frise minute par minute de la fiche.</li>
<li><b>Réactions</b> : qui parle et sa citation.</li>
<li><b>Brèves</b> : anecdotes autour du match, une par bloc.</li>
</ul>
[[img:match-temps-forts.webp|Les temps forts minute par minute]]
HTML],
        ['id' => 'recit', 'title' => '7. Le récit', 'html' => <<<'HTML'
<p>Onglet <b>Récit</b> : les textes du match, par parties (Avant-match et enjeux, Résumé de la rencontre, Réactions…). Chaque partie a un intertitre et un éditeur de texte : gras, italique, liens, liens vers une fiche du musée, listes.</p>
[[img:match-recit.webp|L’éditeur de texte d’une partie du récit]]
[[aide:articles#editeur|Tout sur l’éditeur de texte]]
HTML],
        ['id' => 'medias', 'title' => '8. Photos et vidéos', 'html' => <<<'HTML'
<p>Onglet <b>Médias</b> :</p>
<ul>
<li><b>Image à la une</b> : la photo de la vignette, du partage et de l’en-tête.</li>
<li><b>Galerie</b> : ajoutez des photos de la médiathèque (ou envoyez-en de nouvelles), avec légende et crédit ; glissez pour l’ordre.</li>
<li><b>Vidéos</b> : collez le lien YouTube, Dailymotion, Vimeo ou Rutube.</li>
</ul>
[[img:match-medias.webp|L’onglet Médias : image à la une, galerie, vidéos]]
HTML],
        ['id' => 'classement', 'title' => '9. Classement, référencement et publication', 'html' => <<<'HTML'
<p>Onglet <b>Classement &amp; SEO</b> : rubriques, « À la une », titre et description pour Google, adresse de la page.</p>
[[img:match-classement.webp|Rubriques et référencement]]
[[auto|<p>Un match est rangé tout seul dans la rubrique de sa saison (et ses rubriques parentes).</p>]]
<p>Vérifiez les panneaux de droite, puis passez le statut à <b>Publié</b> (ou <b>Planifié</b>) et enregistrez.</p>
[[img:match-auto.webp|Ce que l’enregistrement met à jour, et le contrôle qualité de la fiche]]
HTML],
        ['id' => 'checklist', 'title' => 'Avant de publier un match', 'html' => <<<'HTML'
<ul>
<li>Date, compétition, adversaire et score justes ; le titre proposé est correct.</li>
<li>Composition complète, joueurs reliés (✓), buteurs cohérents avec le score.</li>
<li>Image à la une choisie ; photos créditées.</li>
<li>Aperçu vérifié, puis Publier.</li>
</ul>
HTML],
    ],
];
