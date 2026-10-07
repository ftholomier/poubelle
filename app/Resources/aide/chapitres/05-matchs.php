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
<li><b>Sochaux joue à</b> (domicile ou extérieur) et <b>Adversaire</b> : choisissez l’adversaire dans la liste proposée pour que son logo et le face-à-face suivent.</li>
<li><b>Stade</b>, <b>Spectateurs</b> (en chiffres), <b>Arbitre</b>.</li>
</ul>
[[img:match-infos.webp|L’onglet Infos d’une fiche match]]
[[auto|<p>Le titre de la fiche (« J23 – Rouen / Sochaux – National – 04/03/2024 – 1-0 ») et son adresse sont proposés automatiquement à partir de ces informations. La page du match affiche la journée saisie (« J15 » devient « 15e journée »).</p>]]
<p><b>Une fiche = un match.</b> Pour un nouveau match, créez toujours une nouvelle fiche, ne réutilisez pas celle du match précédent : si vous changez l’adversaire ou la date (de plus de deux jours) d’une fiche qui a déjà des textes, une composition ou des photos, le back-office vous arrête avant d’enregistrer (« Est-ce bien le même match ? »). « Annuler », puis <b>+ Nouveau › Fiche match</b> s’il s’agit d’un autre match ; « Même match : enregistrer » s’il s’agit de corriger une erreur de saisie (la correction est notée dans l’Historique).</p>
[[img:garde-fou.webp|Adversaire changé sur une fiche déjà remplie : le back-office demande s’il s’agit bien du même match]]
<p>Sur une fiche reprise de l’ancien site, un encadré jaune en tête de l’onglet Infos signale un en-tête d’origine qui contredit la fiche (date « Vendredi 21 août » pour un match du 2 octobre, « 3e journée de Ligue 2 » pour un amical) : la page du match affiche déjà la date et la journée saisies ; vérifiez-les, l’ancien texte est remplacé à l’enregistrement. Un encadré rouge en haut de la fiche signale des textes qui racontent un autre match (voir Qualité).</p>
HTML],
        ['id' => 'score', 'title' => '2. Le score et les buteurs', 'html' => <<<'HTML'
<ul>
<li><b>Buts à domicile / à l’extérieur</b> : l’indication grise rappelle laquelle est Sochaux.</li>
<li><b>Prolongation</b> : « Après prolongation » ou « Tirs au but » (deux champs apparaissent alors pour la séance).</li>
<li><b>Buts par équipe</b> : une ligne par équipe, avec les buteurs et les minutes (« Prat 33’, Thomas 78’ »). Indiquez « (csc) » pour un but contre son camp.</li>
</ul>
[[auto|<p>Le résultat (victoire, nul, défaite) est déduit du score. Le contrôle qualité vérifie que le score correspond aux buteurs de la composition.</p>]]
HTML],
        ['id' => 'composition', 'title' => '3. La composition', 'html' => <<<'HTML'
<p>Onglet <b>Compo &amp; événements</b> : une ligne par joueur, dans l’ordre (titulaires, remplaçants, entraîneur).</p>
[[img:match-compo.webp|La composition : (1) poste, (2) numéro, (3) joueur, (4) capitaine, (5) buts, (6) remplacement, (7) cartons, (8) fiche reliée, (9) ↑ / ↓ pour monter ou descendre d’un cran, icône quatre flèches pour glisser la ligne plus loin, ✕ pour la supprimer]]
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
[[auto|<p>Les joueurs reliés alimentent aussi le <b>Fil jaune</b> du site (Interactif › Jouer) : deux joueurs qui ont joué le même match deviennent coéquipiers, et le site relie n’importe quels deux Lionceaux de coéquipier en coéquipier. Chaque composition complétée, surtout d’avant 1980, tisse de nouveaux liens.</p>]]
HTML],
        ['id' => 'importer', 'title' => '5. Importer une composition depuis un tableau', 'html' => <<<'HTML'
<p>Bouton <b>Importer depuis un tableau</b> : collez un tableau copié depuis Excel, Word ou une page web. Avec une ligne d’en-tête (Poste, Nom, Numéro, Buts…), les colonnes sont reconnues dans n’importe quel ordre.</p>
[[img:match-import.webp|L’import d’une composition copiée]]
<p>S’il y a déjà des joueurs, vous choisissez de remplacer la composition ou d’ajouter les lignes à la suite. Vérifiez ensuite les postes, puis enregistrez.</p>
HTML],
        ['id' => 'temps-forts', 'title' => '6. Temps forts, réactions et brèves', 'html' => <<<'HTML'
<ul>
<li><b>Temps forts</b> : une ligne par action (minute, texte) ; cochez « But » et indiquez le score du moment pour un but. Ils forment la frise minute par minute de la fiche et le déroulé du <b>Rétro-Direct</b> (voir [[aide:interactif#retro-direct|Le Rétro-Direct]]).</li>
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
        ['id' => 'trouvailles', 'title' => 'Trouvailles : ce que les archives disent du match', 'html' => <<<'HTML'
<p>Contenus › <b>Trouvailles (archives)</b> rassemble ce que le musée a trouvé, pour les fiches de match, dans les archives en ligne :</p>
<ul>
<li>la <b>presse de l’époque</b> numérisée par la BnF (Gallica) : L’Est républicain, Le Petit Comtois, L’Éclair comtois, L’Écho des sports, Match l’Intran, Paris-Soir, la presse de la ville adverse… pour les matchs jusqu’en 1955. Le musée lit les journaux parus du jour du match à trois jours après qui citent Sochaux <i>et</i> l’adversaire ;</li>
<li>le <b>web</b> (recherche Google faite par l’IA), toutes époques.</li>
</ul>
<p>L’IA lit ces sources et en tire des <b>propositions</b> : score, buteurs, composition, affluence, arbitre, stade, un <b>récit</b> rédigé dans le style du musée (jamais recopié du journal), des informations et des pistes. Ce que la fiche dit déjà à l’identique n’est pas proposé ; une proposition <b>Divergence</b> signale que la source dit autre chose que la fiche.</p>
<ol>
<li>Ouvrez la source (lien vers la <b>page exacte du journal</b> sur Gallica, ou la page web) et vérifiez : les vieux journaux sont lus par une machine, les noms peuvent être déformés.</li>
<li>Corrigez la valeur dans la case si besoin (orthographe d’un nom, récit retouché).</li>
<li><b>Envoyer dans la fiche</b> : la valeur est écrite dans la fiche (une version est enregistrée, on peut revenir en arrière), le récit va dans une partie « Dans la presse de l’époque », les informations dans « Compléments », et la source s’ajoute à la partie « Sources ». Sinon <b>Écarter</b> : elle ne sera plus jamais proposée (onglet « Écartées » pour la remettre en attente).</li>
</ol>
[[attention|<p>Rien n’entre dans une fiche sans validation. Une composition envoyée remplace celle de la fiche (les joueurs déjà reliés à leur fiche le restent quand le nom correspond) : vérifiez-la bien.</p>]]
[[auto|<p>Les administrateurs lancent les recherches : période (par exemple 1928-1955), sources, seulement les fiches incomplètes ; <b>Essai sur 3 matchs</b> d’abord, puis <b>Lancer pour toute la période</b> : la tâche planifiée fouille quelques matchs à chaque passage (environ une minute par match pour la presse). Un match précis se fouille tout de suite (« Fouiller un match précis »). Coût dans Coûts IA (« Trouvailles »).</p>]]
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
