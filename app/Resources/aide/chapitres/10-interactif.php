<?php
return [
    'slug' => 'interactif',
    'title' => 'Les outils interactifs',
    'summary' => 'Quiz, frise, maillots, carte, partenaires, page « Faire un don », Onze de légende et album, Rétro-Direct, quiz du club-house, défi du jour, kit souvenirs, murs de photos.',
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
<h3 id="radio">Le commentaire radio (option)</h3>
<p>Pour un grand direct, le match peut être <b>commenté comme à la radio à l’époque</b> : l’IA écrit le commentaire d’un reporter au micro, réplique par réplique, calé sur le déroulé (coup d’envoi et enjeu, chaque but, les temps forts, la mi-temps, le coup de sifflet final) et, entre deux actions, des moments d’ambiance pour qu’il ne se taise jamais plus de 3 minutes ; puis la voix IA le lit. Chaque réplique passe dans un « poste radio » (son étroit, souffle, craquements) avec la rumeur de la foule, qui gronde aux buts. Sur la page du direct, le visiteur touche <b>« 📻 Écouter le commentaire radio »</b> : les répliques partent à la minute de leur événement, une vraie ambiance de stade tourne en fond (prise de son dans le public, crédit affiché sous le bouton). Sous le bouton, une ligne dit si le reporter a la parole ou dans combien de minutes il la reprend ; allumé entre deux répliques, la dernière est rejouée tout de suite. En rediffusion, le commentaire se joue à vitesse normale (×1).</p>
<p><b>Tester un direct</b> : connecté au back-office, la page du direct affiche une barre <b>Mode test</b> (visible de l’équipe seulement, et qui n’agit que dans votre navigateur : le public suit toujours l’heure réelle) : <b>Rejouer depuis le coup d’envoi</b>, vitesses ×1, ×10, ×60 (en accéléré, la foule continue et le reporter se met en pause ; il reprend tout seul à ×1), <b>+5 min</b>, <b>Temps fort suivant</b> et <b>Revenir au direct</b>. Le chrono du match (minutes et secondes) défile en haut de la page.</p>
[[img:retro-radio.webp|Le bouton « Écouter le commentaire radio » sur la page du direct]]
<p>Interactif › Rétro-Direct, colonne <b>Commentaire radio</b> : <b>📻 Préparer</b> (le coût estimé est rappelé avant de lancer), puis la page suit la fabrication (le texte, puis les répliques une à une, quelques minutes). Page fermée, la tâche planifiée « Rétro-Direct commenté » continue. Ensuite : <b>Prêt</b> (durée, nombre de répliques, coût réel), <b>Refaire</b>, <b>Supprimer</b>.</p>
[[img:retro-radio-admin.webp|La colonne « Commentaire radio » du programme]]
[[attention|<p>C’est la seule option du Rétro-Direct qui coûte en IA : environ 15 centimes par match, une seule fois (Coûts IA, usage « Rétro-Direct commenté »), quel que soit le nombre d’auditeurs. Préparez-le une fois la fiche relue : si les buts ou les minutes de la fiche changent ensuite, le commentaire passe « À refaire » et n’est plus joué (une simple correction de texte ne compte pas). L’IA n’a pas le droit d’inventer de faits (noms, scores, minutes, météo) : écoutez quand même la première réplique avant le jour J.</p>]]
[[astuce|<p>Avant un direct, relisez la fiche : minutes des temps forts et des buts, entrées en jeu, une belle photo à la une. Il faut au moins 4 temps forts datés. Tous les matchs qui en ont assez se revivent aussi <b>en accéléré</b> (×10, ×60), toute l’année : bouton « Revivre en direct » sur la fiche du match.</p>]]
HTML],
        ['id' => 'quiz-club-house', 'title' => 'Le quiz du club-house : une soirée quiz en direct', 'html' => <<<'HTML'
<p>Pour une soirée au club-house, au local de l’association ou dans un bar de supporters : la télé (ou le vidéoprojecteur) affiche les questions, chacun répond depuis <b>son téléphone</b>, sans inscription ni application. Plus on répond vite, plus on marque : de 1 000 points (réponse immédiate) à 500 (dernière seconde), 0 pour une mauvaise réponse. Classement après chaque question, podium à la fin.</p>
[[img:quiz-club-house-ecran.webp|Le grand écran : la salle d’attente avec le QR code, puis une question et sa réponse]]
<p>Interactif › <b>Quiz du club-house</b> :</p>
<ol>
<li><b>Nouvelle partie</b> : le nombre de questions (12 questions ≈ 15 minutes), le temps pour répondre (15, 20 ou 30 secondes), l’origine des questions et la langue. « Créer la partie » donne un <b>code à 5 chiffres</b>.</li>
<li>Sur l’ordinateur relié à la télé, <b>Ouvrir le grand écran</b>, puis ⤢ pour le plein écran. La salle d’attente affiche le QR code, l’adresse et le code ; les pseudos apparaissent au fur et à mesure.</li>
<li>Les joueurs scannent le QR code (ou vont sur <code>/interactif/quiz-live/</code>), tapent le code et un pseudo.</li>
<li><b>Barre d’espace</b> (ou le bouton jaune) : lancer le quiz, afficher la réponse sans attendre la fin du compte à rebours, le classement, la question suivante… jusqu’au podium. La réponse s’affiche d’elle-même quand tout le monde a répondu ou que le temps est écoulé.</li>
</ol>
[[img:quiz-club-house.webp|Interactif › Quiz du club-house : créer une partie, ouvrir son grand écran]]
[[img:quiz-club-house-telephone.webp|Sur le téléphone : la question, les quatre réponses, puis le verdict et le rang]]
[[auto|<p>Les questions sont tirées au hasard à chaque partie : la moitié dans le quiz du site (voir [[aide:interactif#quiz|Le quiz]]), l’autre fabriquée à partir des <b>fiches des grands matchs</b> (au moins 6 temps forts, hors amicaux) : le score, l’année, l’adversaire ou le buteur d’un match, avec trois réponses plausibles et le rappel du match (score, compétition, date, affluence) à l’affichage de la réponse. Aucune IA, aucun coût. « Fiches de match seulement » donne une partie toujours nouvelle.</p>]]
[[astuce|<p>Le lien du grand écran est <b>secret</b> : il permet de piloter la partie (ne le partagez pas, ne l’affichez pas). Un pseudo déplacé ? Cliquez dessus dans la salle d’attente pour le retirer. Un joueur qui recharge sa page retrouve sa partie et ses points. Les parties (pseudos et scores) s’effacent 24 h après leur dernière activité ; « Effacer » le fait tout de suite.</p>]]
<h3 id="championnat">Le championnat du club-house</h3>
<p>D’une partie à l’autre, un <b>classement de la saison</b> (du 1er août au 31 juillet) réunit les joueurs qui ont laissé leur <b>e-mail</b>. Pas de mot de passe : comme pour le carnet du supporter (c’est d’ailleurs le même compte), un <b>lien sécurisé</b> part à l’adresse ; le téléphone est reconnu tout de suite, et le lien retrouve la place du joueur sur un autre appareil. Ensuite, il suffit de taper le code de la partie : le joueur joue sous son pseudo (★ sur l’écran). Sans e-mail, on joue <b>en invité</b>, sans points au championnat.</p>
<ul>
<li><b>Points</b> selon le rang dans la partie : 11 au 1er, 9 au 2e, 7 au 3e, puis 6, 5, 4, 3, et 1 point de participation ensuite. Une partie de 6 questions pèse autant qu’une partie de 30. Une partie compte à partir de 3 joueurs (invités compris).</li>
<li><b>Sur le grand écran</b> : le haut du classement dans la salle d’attente ; après le podium, la barre d’espace montre le championnat (les joueurs de la soirée en jaune, ▲ les places gagnées, « entrée » pour les nouveaux).</li>
<li><b>Page publique</b> <code>/interactif/quiz-live/championnat/</code> : le classement (saisons passées comprises), « Mon championnat » (rang, changer de pseudo) et l’inscription par e-mail.</li>
<li>Un joueur dont l’adresse est déjà connue reçoit un lien ; tant qu’il ne l’a pas ouvert sur son téléphone, il joue en invité, et la partie lui est rattachée dès qu’il l’ouvre pendant la partie.</li>
<li><b>Lien ouvert au moins une fois</b> : un nouveau joueur marque ses points tout de suite, mais n’apparaît aux classements (championnat et défi du jour) qu’après avoir ouvert le lien de l’e-mail, sur n’importe quel appareil. Ses points sont gardés d’ici là ; le back-office le marque « en attente du lien ». Ainsi, personne ne peut remplir le classement avec des adresses inventées.</li>
</ul>
[[img:quiz-championnat-ecran.webp|Le championnat dans la salle d’attente, puis après le podium]]
[[img:quiz-championnat.webp|La page publique du championnat]]
[[astuce|<p>« Partie amicale » (case de la nouvelle partie) : un essai ou une démonstration qui ne compte pas. Dans Interactif › Quiz du club-house, le tableau du championnat permet de remplacer un <b>pseudo déplacé</b> (il devient « Joueur 1234 », le joueur en choisit un autre) ou de <b>retirer un joueur du classement</b> (réintégrable). Aucun e-mail n’y est affiché. Les joueurs du défi du jour qui n’ont pas encore joué en salle ont leur propre tableau, avec les mêmes boutons. Un supporter qui supprime son carnet disparaît aussi du championnat.</p>]]
<h3 id="defi">Le défi du jour : jouer seul</h3>
<p>Sur <code>/interactif/defi/</code> (menu Interactif › Jouer), chacun joue <b>seul sur son téléphone</b>, sans animateur : <b>10 questions</b>, 20 secondes chacune, la réponse et le rappel du match après chaque question. Les questions sont <b>les mêmes pour tout le monde</b> dans la journée (en français comme en anglais) et changent à minuit. <b>Un seul essai par jour</b> et par compte : pas de retour en arrière, le temps tourne même si l’on quitte la page.</p>
<ul>
<li>Même compte que le championnat et le carnet (e-mail, lien sécurisé, même pseudo). Sans compte, on peut jouer <b>en invité</b>, sans classement. Créer son compte après avoir joué en invité le même jour, sur le même appareil, ne permet pas de rejouer : la partie est reprise, hors classement (les réponses étaient déjà connues).</li>
<li>Une partie commencée juste avant minuit se termine normalement : elle compte pour le jour où elle a commencé.</li>
<li><b>Classements</b> du jour (points, puis temps de réponse), du mois et de la saison (points cumulés), séparés du championnat du club-house : en solo, rien n’empêche de chercher les réponses, alors qu’au club-house tout le monde joue au même moment.</li>
<li>« Partager mon résultat » : une grille de cases jaunes et noires, sans dévoiler les réponses. La série de jours d’affilée encourage à revenir.</li>
<li>Rappel chaque matin par une notification de l’appli, pour les abonnés qui choisissent « Le défi du jour » (Réglages › Application du musée).</li>
</ul>
[[img:defi.webp|Sur le téléphone : le compte reconnu, une question, la bonne réponse, une erreur, le résultat]]
[[img:defi-page.webp|La page du défi : inscription par e-mail, classements du jour, du mois et de la saison]]
[[auto|<p>Rien à faire : les questions du jour sont tirées au premier joueur (quiz du site et fiches des grands matchs, sans IA) et gardées pour la journée. Un joueur retiré du classement du championnat l’est aussi du défi. Les parties en cours sont effacées après deux jours ; les résultats restent pour les classements.</p>]]
HTML],
        ['id' => 'souvenirs', 'title' => 'Le kit souvenirs : Raconte-moi Bonal', 'html' => <<<'HTML'
<p>Chaque mois, le site fabrique un <b>kit en gros caractères</b> à imprimer pour les anciens supporters (en famille, au club des aînés, à la médiathèque, en maison de retraite) : le grand match d’il y a 30, 40 ou 50 ans (photo, score, buteurs, récit, anecdote), « On vous raconte le match » (le récit complet de la version audio de la fiche, à lire à voix haute, sur une ou deux pages), puis, en annexe, la fiche complète du match (composition, résumé, réactions, face-à-face…), « Vous les reconnaissez ? » (six joueurs de l’époque à nommer, réponses à l’envers), le quiz des anciens (deux questions sur le match, quatre du quiz du site) et « Racontez-nous » (questions pour faire naître les souvenirs, QR code vers le formulaire de témoignage, adresse du musée). Il se télécharge sur la page Interactif › Participer › Kit souvenirs.</p>
[[img:kit-souvenirs.webp|Les pages du kit d’octobre 2026]]
<p>Interactif › <b>Kit souvenirs</b> : pour le mois en cours et les deux suivants, le match choisi automatiquement (le plus marquant : temps forts, coupe, anniversaire rond, 30 à 60 ans d’âge, belle photo), les autres propositions et « Ou un autre match » ; un <b>mot d’introduction</b> facultatif ; le PDF à télécharger pour vérifier.</p>
[[auto|<p>Le kit se refait tout seul quand une fiche change. Le QR code mène à l’adresse courte <code>/souvenir/{n° du match}/</code>, qui ouvre le formulaire « Contribuer » avec « Un témoignage » coché et le match rempli. Les témoignages reçus se publient sur la fiche du match (voir [[aide:communaute#contributions|Les contributions]]).</p>]]
[[astuce|<p>Relisez la fiche du match du mois : temps forts, buteurs, photo à la une et une brève. Les visages viennent de la composition (joueurs reliés avec une vraie photo) : une composition complète donne un meilleur jeu.</p>]]
HTML],
        ['id' => 'murs-photos', 'title' => 'Les murs de photos', 'html' => <<<'HTML'
<p>Quatre pages de la rubrique Interactif montrent les photos de la médiathèque, <b>tirées au hasard à chaque visite</b> (bouton « Nouveau tirage » sans recharger la page), avec un filtre par décennie et par photographe ou source :</p>
<ul>
<li><b>Planche-contact</b> : des bandes de film, le numéro et le crédit imprimés dans la marge, une loupe au survol, comme sur la table du labo ;</li>
<li><b>Le Lion illustré</b> : un journal (Une, articles, « En images », brèves) dont les titres sont ceux des fiches et les textes les légendes de la médiathèque : rien n’est inventé ;</li>
<li><b>Le mur du vestiaire</b> : 15 tirages (3 lignes de 5) punaisés ou scotchés au carrelage, crédit écrit à la main, à déplacer à la souris ; au fond, flouté et plongé dans la pénombre, le vestiaire des pros du FCSM avec ses maillots jaunes suspendus (photo du club, créditée en bas du mur) ;</li>
<li><b>La grande mosaïque</b> : des centaines de photos qui dessinent « 100 », « FCSM », « 1928 » ou « 2028 » ; un bouton montre les photos en couleurs.</li>
</ul>
[[img:site-murs.webp|Les quatre murs : planche-contact, Le Lion illustré, mur du vestiaire, grande mosaïque]]
<p>Un clic agrandit la photo, avec sa légende, son crédit et un lien vers sa fiche. <b>Le crédit est toujours affiché.</b></p>
<p><b>Télécharger en PDF</b> (planche-contact, Le Lion illustré, grande mosaïque) : le bouton de la barre jaune fabrique un vrai PDF du tirage affiché, avec les mêmes photos, le même numéro de planche ou d’édition et le même motif. La planche et le journal tiennent sur une page A4 ; la mosaïque fait deux pages A4 paysage (le motif, puis les photos en couleurs avec tous les crédits). Dans le PDF, chaque photo est un lien vers sa fiche. Après « Nouveau tirage », le bouton exporte le nouveau tirage.</p>
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
