<?php
return [
    'slug' => 'interactif',
    'title' => 'Les outils interactifs',
    'summary' => 'Quiz, frise, maillots, carte, partenaires, page « Faire un don », Onze de légende et album, Rétro-Direct, kit souvenirs, murs de photos.',
    'sections' => [
        ['id' => 'principe', 'title' => 'Le principe', 'html' => <<<'HTML'
<p>Interactif › <b>Quiz, frise, carte…</b> : chaque outil est une liste d’éléments (une question, une date, une époque, un lieu…) qu’on ajoute, modifie, réordonne par glisser-déposer et traduit. La carte de chaque outil indique les éléments <b>à valider</b> et ceux <b>sans anglais</b>.</p>
[[img:interactif.webp|Les outils interactifs et ce qu’il reste à valider]]
[[attention|<p>Les contenus de départ (quiz, frise, maillots, épopées, lieux) ont été préparés à partir des fiches du musée : relisez-les et cochez « Validé » élément par élément.</p>]]
HTML],
        ['id' => 'quiz', 'title' => 'Le quiz', 'html' => <<<'HTML'
<p>Une question, ses quatre réponses, la bonne réponse et un « Le saviez-vous ? » affiché après la réponse. « Affichée dans le quiz » la met en jeu ; « Validée par un historien » confirme qu’elle a été vérifiée. Le bouton « Traduire en anglais » propose la version anglaise.</p>
[[img:quiz.webp|L’édition d’une question du quiz]]
HTML],
        ['id' => 'autres', 'title' => 'Frise, maillots, carte, partenaires, page de don', 'html' => <<<'HTML'
<ul>
<li><b>Frise chronologique</b> : une année, un titre, un texte, une image, un lien vers une fiche ou une rubrique, une mise en valeur.</li>
<li><b>Comparateur de maillots</b> : une année de référence, son intitulé, la photo du maillot, une description.</li>
<li><b>Carte : grandes épopées</b> : les étapes d’un parcours (année, saison, compétition, lieu, coordonnées, victoire ou non, lien vers la fiche) ; <b>Carte : lieux du club</b> : nom, type, description, coordonnées, lien. Les stades des matchs et les lieux de naissance viennent des fiches.</li>
<li><b>Partenaires</b> : nom, logo, site web (page Contact).</li>
<li><b>Page « Faire un don »</b> : titre, accroche et textes de la page, paliers et leur impact.</li>
</ul>
HTML],
        ['id' => 'onze-album', 'title' => 'Onze de légende et album', 'html' => <<<'HTML'
<p>Interactif › <b>Onze &amp; album</b> :</p>
<ul>
<li><b>Le Onze du public</b> : le résultat des votes, poste par poste ; <b>Date de dévoilement</b> : quand il est révélé sur le site. La remise à zéro des votes est réservée aux administrateurs.</li>
<li><b>Album du centenaire</b> : les cartes à collectionner ; on ajoute un joueur depuis sa fiche (onglet Identité › Carte de l’album).</li>
</ul>
[[img:onze.webp|Le Onze de légende du public]]
HTML],
        ['id' => 'retro-direct', 'title' => 'Le Rétro-Direct : un grand match rejoué en direct', 'html' => <<<'HTML'
<p>Le jour anniversaire d’un grand match, à l’heure du coup d’envoi, le site le <b>rejoue minute par minute</b> : les temps forts s’affichent à leur minute, le score change à la minute des buts, les remplaçants entrent, l’arbitre siffle la mi-temps (15 minutes de pause), la prolongation et les tirs au but s’il y en a eu. Les visiteurs voient combien de supporters suivent le direct avec eux, réagissent d’un clic (⚽ 👏 😱) et peuvent dire « J’y étais ! ».</p>
[[img:site-retro.webp|Un direct en seconde mi-temps : score, horloge, fil des temps forts, réactions]]
<p>Interactif › <b>Rétro-Direct</b> :</p>
<ol>
<li><b>Anniversaires à venir</b> : les matchs dont c’est l’anniversaire rond (10, 20, 25, 30, 40, 50 ans…) dans les 30, 90, 180 ou 365 prochains jours, les plus marquants retenus (temps forts, coupe, finale, victoire, gros score, affluence, fiche à la une). <b>Programmer à 20 h</b> l’ajoute en un clic.</li>
<li><b>Programmer un match</b> : n’importe quel match, à n’importe quelle date. Tapez quelques mots du match, choisissez-le : la date de son prochain anniversaire est proposée. Réglez l’heure du coup d’envoi et ajoutez, si vous le souhaitez, une courte <b>présentation</b> (le contexte, l’enjeu ; sans dévoiler le score !).</li>
<li><b>Au programme</b> : « Voir » ouvre la page publique, « Modifier » change la date, l’heure ou la présentation, « Retirer » enlève le direct. Après un direct : le pic de spectateurs connectés et les réactions.</li>
</ol>
[[img:retro.webp|Le programme et les anniversaires proposés]]
[[auto|<p>Tout vient de la fiche du match : temps forts et leur minute, buteurs, composition (entrées en jeu, cartons), brèves d’avant-match, réactions d’après-match, photos de la galerie à la mi-temps. Aucune IA, aucun coût. Le direct s’annonce dans le bandeau du site 7 jours avant, figure dans l’agenda à télécharger (.ics) et dans le plan du site.</p>]]
[[astuce|<p>Avant un direct, relisez la fiche : minutes des temps forts et des buts, entrées en jeu, une belle photo à la une. Il faut au moins 4 temps forts datés. Tous les matchs qui en ont assez se revivent aussi <b>en accéléré</b> (×10, ×60), toute l’année : bouton « Revivre en direct » sur la fiche du match.</p>]]
HTML],
        ['id' => 'souvenirs', 'title' => 'Le kit souvenirs : les Après-midi Bonal', 'html' => <<<'HTML'
<p>Chaque mois, le site fabrique un <b>kit de 4 pages en gros caractères</b> à imprimer pour les anciens supporters (en famille, au club des aînés, à la médiathèque, en maison de retraite) : le grand match d’il y a 30, 40 ou 50 ans (photo, score, buteurs, récit, anecdote), « Vous les reconnaissez ? » (six joueurs de l’époque à nommer, réponses à l’envers), le quiz des anciens (deux questions sur le match, quatre du quiz du site) et « Racontez-nous » (questions pour faire naître les souvenirs, QR code vers le formulaire de témoignage, adresse du musée). Il se télécharge sur la page Interactif › Participer › Kit souvenirs.</p>
[[img:kit-souvenirs.webp|Les quatre pages du kit d’octobre 2026]]
<p>Interactif › <b>Kit souvenirs</b> : pour le mois en cours et les deux suivants, le match choisi automatiquement (le plus marquant : temps forts, coupe, anniversaire rond, 30 à 60 ans d’âge, belle photo), les autres propositions et « Ou un autre match » ; un <b>mot d’introduction</b> facultatif ; le PDF à télécharger pour vérifier.</p>
[[auto|<p>Le kit se refait tout seul quand une fiche change. Le QR code mène à l’adresse courte <code>/souvenir/{n° du match}/</code>, qui ouvre le formulaire « Contribuer » avec « Un témoignage » coché et le match rempli. Les témoignages reçus se publient sur la fiche du match (voir [[aide:communaute#contributions|Les contributions]]).</p>]]
[[astuce|<p>Relisez la fiche du match du mois : temps forts, buteurs, photo à la une et une brève. Les visages viennent de la composition (joueurs reliés avec une vraie photo) : une composition complète donne un meilleur jeu.</p>]]
HTML],
        ['id' => 'murs-photos', 'title' => 'Les murs de photos', 'html' => <<<'HTML'
<p>Quatre pages de la rubrique Interactif montrent les photos de la médiathèque, <b>tirées au hasard à chaque visite</b> (bouton « Nouveau tirage » sans recharger la page), avec un filtre par décennie et par photographe ou source :</p>
<ul>
<li><b>Planche-contact</b> : des bandes de film, le numéro et le crédit imprimés dans la marge, une loupe au survol, quelques vues entourées d’un coup de crayon gras rouge, avec un mot griffonné à côté (« la bonne ! », « à tirer ! »), comme le photographe qui choisit au labo ;</li>
<li><b>Le Lion illustré</b> : un journal (Une, articles, « En images », brèves) dont les titres sont ceux des fiches et les textes les légendes de la médiathèque : rien n’est inventé ;</li>
<li><b>Le mur du vestiaire</b> : des tirages punaisés ou scotchés au carrelage, crédit écrit à la main, à déplacer à la souris ;</li>
<li><b>La grande mosaïque</b> : des centaines de photos qui dessinent « 100 », « FCSM », « 1928 » ou « 2028 » ; un bouton montre les photos en couleurs.</li>
</ul>
[[img:site-murs.webp|Les quatre murs : planche-contact, Le Lion illustré, mur du vestiaire, grande mosaïque]]
<p>Un clic agrandit la photo, avec sa légende, son crédit et un lien vers sa fiche. <b>Le crédit est toujours affiché.</b></p>
<p><b>Quelles photos ?</b> Seulement les photos sûres de la médiathèque :</p>
<ul>
<li>un <b>crédit renseigné</b>, qui n’est ni « DR » (droits réservés, auteur inconnu) ni un <b>crédit exclu</b> (agences photo, presse nationale, télévision, sites web), ni une simple date ou légende saisie à la place du crédit (« Saison 1980-1981 », « Sochaux-Metz ») ;</li>
<li>une photo qui illustre au moins <b>une fiche publiée</b> (elle a donc été relue) ;</li>
<li>au moins 300 pixels sur le petit côté (sinon floue en grand), et la case « Jamais sur les murs de photos » de la médiathèque non cochée.</li>
</ul>
<p>Interactif › <b>Murs de photos</b> : le nombre de photos montrées, les <b>photos écartées par raison</b> (un clic ouvre la médiathèque sur ces photos), la <b>liste des crédits exclus</b> (une ligne par crédit, modifiable ; « Ce que retire chaque ligne » en montre l’effet), tous les <b>crédits montrés</b> du plus fréquent au plus rare, et les vignettes préparées d’avance.</p>
[[img:murs-photos.webp|L’écran Interactif › Murs de photos]]
[[astuce|<p>Les « crédits à corriger » sont souvent une date ou une légende tapée dans le champ Crédit : corrigez le crédit dans la médiathèque (et mettez la date dans « Date ou époque ») et la photo rejoint les murs. Une photo qu’il ne faut pas montrer au hasard, même bien créditée : ouvrez-la dans la médiathèque et cochez <b>Jamais sur les murs de photos</b>.</p>]]
[[auto|<p>Une même fiche ne donne pas plus de deux photos par tirage. La décennie vient de la fiche (date du match, année de l’objet ou du moment) ou de « Date ou époque » dans la médiathèque. Les graphies d’un même photographe sont réunies (« L’est républicain », « Est Républicain »). La tâche planifiée « Murs de photos » prépare les petites images d’avance ; tant qu’elles ne sont pas toutes prêtes, les photos déjà prêtes passent en premier.</p>]]
HTML],
    ],
];
