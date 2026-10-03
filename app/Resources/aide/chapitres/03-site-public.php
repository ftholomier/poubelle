<?php
return [
    'slug' => 'site-public',
    'title' => 'Comment le site est organisé',
    'summary' => 'Ce que voient les visiteurs, d’où vient chaque élément et ce qui se met à jour tout seul.',
    'sections' => [
        ['id' => 'accueil', 'title' => 'L’accueil et les menus', 'html' => <<<'HTML'
<p>L’accueil réunit le bandeau « En direct du musée », le grand slider des fiches « À la une », le compte à rebours du centenaire (20 mai 2028), « Ce jour-là », le palmarès, les grandes époques et les réserves.</p>
[[img:site-accueil.webp|L’accueil du site public]]
<p>Les menus reprennent l’arborescence de l’ancien site : <b>Accueil, Matchs, Nos Lions, Supporters, Infrastructures, Symboles</b>, et la nouvelle rubrique <b>Interactif</b>. Chaque menu s’ouvre en méga-menu avec ses sous-rubriques.</p>
[[img:site-megamenu.webp|Le méga-menu « Matchs »]]
[[ecran:/admin/accueil|Régler l’accueil et le bandeau]]
HTML],
        ['id' => 'mosaiques', 'title' => 'Les rubriques en mosaïque', 'html' => <<<'HTML'
<p>Chaque rubrique affiche ses fiches en mosaïque : vignettes avec image à la une, filtres, tri, vue liste et bouton « Afficher plus ». Une fiche apparaît dans toutes les rubriques cochées dans son onglet « Classement &amp; SEO ».</p>
[[img:site-mosaique.webp|Une mosaïque de rubrique]]
[[astuce|<p>L’ordre des fiches d’une mosaïque se règle rubrique par rubrique dans Éditorial › Rubriques &amp; menus : ordre manuel (glisser-déposer, comme dans l’ancien WordPress), chronologique ou alphabétique. Les décennies et les saisons gardent l’ordre manuel repris de l’ancien site.</p>]]
HTML],
        ['id' => 'fiches', 'title' => 'Les fiches', 'html' => <<<'HTML'
<p><b>Fiche match</b> : score, informations, terrain et tableau de composition, temps forts, réactions, vidéos, galerie, et un encadré face-à-face calculé automatiquement.</p>
[[img:site-match.webp|Une fiche match : la composition sur le terrain et en tableau]]
<p><b>Fiche personne</b> : carte à collectionner, identité, récit, statistiques et la liste de tous ses matchs, reliés automatiquement depuis les compositions.</p>
[[img:site-joueur.webp|Une fiche joueur]]
HTML],
        ['id' => 'calcule', 'title' => 'Les pages calculées automatiquement', 'html' => <<<'HTML'
<p>Ces pages n’ont rien à saisir : elles sont construites à partir des fiches match et se mettent à jour à chaque enregistrement.</p>
<table>
<tr><th>Page</th><th>Ce qu’elle montre</th><th>D’où viennent les données</th></tr>
<tr><td>Saisons</td><td>résultats, effectif, buteurs d’une saison</td><td>fiches match et compositions</td></tr>
<tr><td>Face-à-face</td><td>bilan contre chaque adversaire</td><td>fiches match (adversaire, score)</td></tr>
<tr><td>Bilans</td><td>par compétition, par stade</td><td>fiches match (compétition, stade)</td></tr>
<tr><td>Records</td><td>buteurs, joueurs les plus utilisés, affluences, séries</td><td>compositions, spectateurs, scores</td></tr>
<tr><td>Les chiffres du FCSM</td><td>100 statistiques en 11 chapitres, de 1929 à aujourd’hui</td><td>tableaux de carrière des fiches joueurs, compositions, temps forts, fiches des personnes</td></tr>
<tr><td>Carte</td><td>stades, origines des joueurs, épopées, lieux</td><td>fiches match et personnes, outils interactifs</td></tr>
</table>
[[img:site-explorer.webp|Une page face-à-face, entièrement calculée]]
HTML],
        ['id' => 'chiffres', 'title' => 'Les chiffres du FCSM : 100 statistiques', 'html' => <<<'HTML'
<p>Matchs › Explorer › <b>Les chiffres</b> (adresse <a href="/chiffres/" target="_blank" rel="noopener">/chiffres/</a>, aussi dans Interactif › Explorer l’histoire) : cent statistiques du club en onze chapitres. Les monuments (meilleur buteur de l’histoire, recordman des matchs, club des 100 buts…), les buteurs, le chrono (but le plus rapide, remontadas, victoires arrachées), les gardiens et la défense (blanchissages, minutes d’invincibilité), les séries, les scores, les acteurs (passeurs, capitaines, entraîneurs), les adversaires, Bonal et les tribunes, les saisons, les Lions en portrait (âges records, tailles, origines). Chaque chiffre mène à la fiche du joueur ou du match concerné.</p>
[[img:site-chiffres.webp|La page « Les chiffres du FCSM »]]
<table>
<tr><th>Source (badge)</th><th>Ce qui est utilisé</th></tr>
<tr><td>Carrières</td><td>l’onglet Statistiques des fiches joueurs, saison par saison depuis 1929 (records de toute une carrière)</td></tr>
<tr><td>Matchs racontés</td><td>les fiches match officielles (sans les amicaux) : scores, compositions, buteurs et minutes, affluences, stades</td></tr>
<tr><td>Récits des matchs</td><td>les temps forts minute par minute dont les scores « (1-0) » retrouvent le score final : passes décisives, buts de la tête, remontadas, penaltys arrêtés</td></tr>
<tr><td>Fiches des Lions</td><td>dates et lieux de naissance, taille, pied fort, « formé au club »</td></tr>
</table>
[[auto|<p>Tout est recalculé dès que les fiches changent (et d’avance par la tâche planifiée « Recalcul des statistiques »). Les séries ne sont comptées que dans les saisons racontées en entier, pour qu’un match manquant ne fausse rien. Sont mis de côté : une composition signalée « Même tableau de composition » ou « Total des buts ≠ buteurs » dans [[aide:qualite#tableau|le tableau Qualité]], un tableau de statistiques recopié sur plusieurs fiches, une date de naissance invraisemblable.</p>]]
[[astuce|<p>Un chiffre surprenant est souvent le signe d’une donnée à corriger : un joueur « doyen » trop âgé révèle une date de naissance fausse, une série de buts improbable une composition recopiée. Corrigez la fiche : la page se met à jour toute seule.</p>]]
HTML],
        ['id' => 'pdf', 'title' => 'Télécharger en PDF', 'html' => <<<'HTML'
<p>Chaque fiche (match, joueur, entraîneur, dirigeant, article, objet, moment) et chaque page de synthèse (saison, face-à-face, bilans, livre des records) a un bouton <b>Télécharger en PDF</b>. Ce n’est pas une impression de la page : c’est un vrai document A4 mis en page aux couleurs du musée, avec le blason, les polices du site, des pages numérotées, des signets et des liens cliquables.</p>
<ul>
<li><b>Match</b> : tableau d’affichage, fiche technique, chiffre clé, récit, minute par minute (buts surlignés), réactions, brèves, terrain avec la composition, face-à-face, galerie, vidéos.</li>
<li><b>Personne</b> : photo façon carte de collection, grands chiffres, fiche d’identité, palmarès, récit, saison par saison, matchs marquants, la liste complète de ses matchs.</li>
<li><b>Saison, face-à-face, bilans, records</b> : totaux, répartition victoires / nuls / défaites, faits marquants, tableaux complets.</li>
</ul>
[[img:pdf-fiche-match.webp|Les premières pages du PDF d’une fiche match]]
[[auto|<p>Le PDF suit toujours la dernière version enregistrée : il est refait automatiquement dès qu’une fiche change. Il existe aussi en anglais depuis la version anglaise du site.</p>]]
[[astuce|<p>Dans le back-office, le panneau Publication d’une fiche propose aussi <b>Télécharger le PDF</b>, même pour une fiche pas encore publiée.</p>]]
HTML],
        ['id' => 'ecouter', 'title' => 'Écouter une fiche en 30 secondes', 'html' => <<<'HTML'
<p>À côté de « Télécharger en PDF », le bouton <b>Écouter (30 s)</b> raconte la fiche à voix haute : pour un match, la date, le stade, le score, les buteurs et un fait marquant ; pour un joueur, son poste, ses années au club, ses matchs et le début de son histoire. Le texte lu s’affiche sous le bouton pendant l’écoute (accessibilité : malvoyants, personnes âgées, lecture difficile). Un second clic arrête.</p>
[[img:site-ecouter.webp|Le bouton « Écouter » et le texte lu]]
<p>Par défaut, c’est la voix de l’appareil du visiteur qui lit : c’est gratuit. Quand une fiche a reçu sa <b>voix IA</b> (voix naturelle de Gemini, enregistrée), c’est elle que l’on entend. Voir [[aide:administration#audio|Fiches audio]].</p>
HTML],
        ['id' => 'interactif', 'title' => 'Interactif, recherche, assistant, dons', 'html' => <<<'HTML'
<ul>
<li><b>Interactif</b> : Rétro-Direct (les grands matchs rejoués en direct le jour anniversaire, voir [[aide:interactif#retro-direct|Le Rétro-Direct]]), Fil jaune (voir ci-dessous), kit souvenirs à imprimer pour les anciens (voir [[aide:interactif#souvenirs|Le kit souvenirs]]), quiz, album de cartes, maillots, frise, carte, centenaire (100 moments, Onze de légende), réserves du musée.</li>
<li><b>Recherche</b> : loupe en haut du site, sur toutes les fiches.</li>
<li><b>Assistant IA</b> : bulle en bas à droite, qui répond à partir des données du musée.</li>
<li><b>Contact, Contribuer, Newsletter, Faire un don</b> : leurs messages et leurs dons arrivent dans le menu Communauté.</li>
<li><b>Anglais</b> : chaque page existe en anglais (bouton FR / EN) ; voir [[aide:anglais|Version anglaise]].</li>
</ul>
[[img:site-interactif.webp|La rubrique Interactif]]
HTML],
        ['id' => 'fil-jaune', 'title' => 'Le Fil jaune : tous les Lionceaux sont reliés', 'html' => <<<'HTML'
<p>Interactif › Jouer › <b>Fil jaune</b> : le visiteur choisit deux joueurs ; le site trouve la chaîne la plus courte de coéquipiers qui les relie (deux joueurs sont coéquipiers s’ils ont joué le même match), chaque maillon menant à la fiche du premier match joué ensemble. Autour : la <b>constellation</b> de chaque joueur (tous ses coéquipiers, du plus fidèle au plus rare), les <b>records</b> (les plus connectés, les inséparables, les plus éloignés) et un <b>défi du jour</b> à partager : relier deux joueurs en choisissant soi-même un coéquipier à chaque passe. Sur la fiche d’un joueur, un encadré « Le Fil jaune » mène à sa constellation.</p>
[[img:site-fil-jaune.webp|La constellation d’un joueur : ses coéquipiers, les plus fidèles au centre]]
[[auto|<p>Tout est calculé à partir des compositions des fiches de match (titulaires et remplaçants entrés en jeu) dont les joueurs sont reliés à leur fiche : rien à saisir. Aujourd’hui, 442 joueurs forment une seule famille, reliés en 6 passes au plus ; 18 joueurs des années 1960-1970 forment une famille à part, faute de compositions des années 1970.</p>]]
[[astuce|<p>Une chaîne étonnante (deux joueurs d’époques très différentes reliés par un seul match) révèle souvent un jubilé, un match de gala… ou une erreur de saisie (homonyme relié à la mauvaise fiche) : un bon moyen de repérer les compositions à vérifier.</p>]]
HTML],
        ['id' => 'visibilite', 'title' => 'Qui voit quoi', 'html' => <<<'HTML'
<ul>
<li>Seules les fiches <b>publiées</b> sont visibles. Les brouillons, fiches à relire et planifiées ne le sont pas.</li>
<li>Le bouton <b>Aperçu</b> d’une fiche montre son rendu avant publication.</li>
<li>Connecté au back-office, vous voyez le site normal même quand la <b>page d’attente</b> est activée.</li>
</ul>
HTML],
    ],
];
