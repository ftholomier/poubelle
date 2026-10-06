<?php
return [
    'slug' => 'boutique',
    'title' => 'La boutique',
    'summary' => 'Les objets de l’association (t-shirt, mug, casquette, écharpe, poster…) : supports de l’imprimeur, modèles dessinés dans l’éditeur, phrases au choix, anecdotes tirées par le client, poster souvenir d’un match, aperçu en direct, zoom et 3D, commandes, imprimeur et fichier d’impression. Réservé aux administrateurs.',
    'admin' => true,
    'sections' => [
        ['id' => 'principe', 'title' => 'Le principe : du texte et le logo, jamais de photo', 'admin' => true, 'html' => <<<'HTML'
<p>La boutique vend des objets fabriqués <b>à la demande</b> par un imprimeur local. Pour rester maîtres de tout et éviter tout problème de droits, on n’imprime <b>jamais de photo</b> : seulement le <b>logo de l’association</b> (redessiné en vectoriel), des <b>textes</b> (slogans, données du musée, prénom ou dédicace du client) et des formes simples.</p>
<ul>
<li>Tout est <b>vectoriel</b> : le fichier envoyé à l’imprimeur reste net à toutes les tailles, d’un sticker à une écharpe.</li>
<li>Les lettres sont converties en tracés : l’imprimeur n’a besoin d’aucune police.</li>
<li>Le pavé <b>Boutique</b> du menu n’est visible que des administrateurs.</li>
</ul>
HTML],
        ['id' => 'supports', 'title' => 'Les supports', 'admin' => true, 'html' => <<<'HTML'
<p><b>Boutique › Supports</b> : les produits vierges de l’imprimeur. Pour chacun :</p>
<ul>
<li>les <b>faces imprimables</b> en millimètres (format fini), par exemple Avant et Dos pour un t-shirt ;</li>
<li>les <b>fonds perdus</b> : la marge coupée après impression (2 à 3 mm pour le papier, aucune pour le textile) ;</li>
<li>les <b>couleurs</b> du produit vierge (« Bleu nuit : #0E1F4D », une par ligne). Sans couleur, le produit est imprimé en entier et le fond fait partie du dessin (poster, écharpe, sticker) ;</li>
<li>les <b>tailles</b>, la <b>référence chez l’imprimeur</b> et ses <b>consignes</b> (affichées dans l’éditeur) ;</li>
<li>la <b>part de l’imprimeur</b> : un coût fixe par article, ou une commission en % du prix de vente (l’association encaisse les paiements et reverse sa part à l’imprimeur).</li>
</ul>
<p>Supports de départ : t-shirt, sweat à capuche, mug céramique (blanc, noir, bleu nuit, jaune) et émaillé, tote bag, <b>casquette</b> (broderie : texte de 5 mm de haut au moins), <b>écharpe</b> (1 400 × 180 mm, imprimée en entier), <b>posters</b> (A3, A2, et « Poster (A4, A3, A2) »), carte postale, sticker. Vous pouvez les modifier, en désactiver, en ajouter.</p>
[[astuce|<p>Tout poster au format A (A3, A2…) se vend automatiquement en <b>A4, A3 et A2</b> : le dessin, vectoriel, est réduit ou agrandi à l’identique dans le fichier de l’imprimeur, au format exact choisi par le client.</p>]]
HTML],
        ['id' => 'modeles', 'title' => 'Dessiner un modèle', 'admin' => true, 'html' => <<<'HTML'
<p><b>Boutique › Modèles</b> : donnez un nom, choisissez le support, puis <b>Créer et dessiner</b>. L’éditeur montre côte à côte le <b>produit</b> (dans la couleur choisie) et le <b>fichier d’impression</b>.</p>
[[img:boutique-editeur.webp|L’éditeur : (1) nom, face, couleur du produit, enregistrer et PDF imprimeur ; (2) l’aperçu sur le produit ; (3) le fichier d’impression, où l’on déplace les éléments ; (4) ajouter un logo, un texte, un champ du client, une forme ; (5) les calques ; (6) les réglages de l’élément choisi]]
<ol>
<li><b>Ajouter</b> : le logo (en couleurs, ou d’une seule couleur sur textile foncé), un texte, un <b>champ du client</b>, un rectangle ou un rond.</li>
<li><b>Placer</b> : cliquez un élément dans le fichier d’impression et faites-le glisser ; les flèches du clavier le déplacent de 1 mm (10 mm avec Maj). « Centrer » le met au milieu de la largeur.</li>
<li><b>Régler</b> : police (celles du site), corps, couleur de la charte, alignement, capitales, espacement ; « Réduire pour tenir sur la largeur » pour un prénom de longueur inconnue.</li>
<li><b>Champ du client</b> : le texte d’exemple est remplacé par celui du client (« Votre texte », 20 caractères au plus…). Essayez des prénoms longs dans « Essai des champs du client ».</li>
<li><b>Cadre en hauteur</b> : un texte avec une hauteur de cadre peut se placer en haut, au milieu ou en bas du cadre.</li>
<li><b>Enregistrer</b>, puis cochez <b>Prêt à la vente</b> quand le modèle est validé.</li>
</ol>
<p><b>Ordre de la boutique</b> : dans Boutique › Modèles, attrapez un modèle par sa poignée ⠿ et faites-le glisser ; la boutique présente les articles dans cet ordre.</p>
[[astuce|<p>Les pointillés bleus marquent le bord du produit fini ; la zone grisée, les fonds perdus. Un message signale ce qui dépasse de la zone imprimable, ou un texte trop petit pour la broderie d’une casquette.</p>]]
HTML],
        ['id' => 'textes', 'title' => 'La banque de textes : des phrases au choix du client', 'admin' => true, 'html' => <<<'HTML'
<p><b>Boutique › Banque de textes</b> rassemble des listes de phrases : les slogans et les anecdotes « Le saviez-vous ? » du rapport boutique y sont déjà. Le client ne tape pas sa phrase : il la <b>choisit</b> dans une liste, ce qui garde chaque objet dans l’esprit de l’association.</p>
<ul>
<li><b>Validée</b> : seules les phrases cochées sont proposées au client. Les anecdotes arrivent non cochées : un historien vérifie chaque fait (la source est dans la colonne « Note ») avant de les cocher.</li>
<li><b>Ajouter</b> : une phrase par ligne dans « Ajouter des phrases », puis « Enregistrer la liste ».</li>
<li><b>Proposer des phrases avec l’IA</b> : donnez une consigne (« humour sur la météo de Bonal ») et un nombre ; les propositions arrivent non validées, jamais proposées avant votre accord. Le coût apparaît dans Coûts IA.</li>
<li><b>Placer les éléments</b> : au-dessus du fichier d’impression, « Grille » (5, 10 ou 20 mm) et « Aimant ». En glissant, l’élément colle aux bords et au centre de la face, aux autres éléments et à la grille ; un trait rose et une silhouette montrent où il va se poser (touche Alt enfoncée : sans aimant). Les boutons ⇤ ↔ ⇥ ⤒ ↕ ⤓ alignent l’élément choisi sur la face.</li>
<li><b>Dans l’éditeur</b> : sous « Phrase au choix du client », cliquez le bouton de la liste (« + Slogans »…). Un cadre jaune apparaît dans le fichier d’impression : c’est la place réservée à la phrase.</li>
<li><b>Phrases trop longues</b> : chaque phrase est d’abord réduite, puis passée sur plusieurs lignes pour tenir dans le cadre, sans descendre sous le « Corps minimum » (20 pt sur la casquette brodée). Une phrase qui ne tient pas, même au minimum, n’est <b>jamais proposée</b> au client pour ce modèle : l’essai des champs indique combien de phrases sont écartées, et lesquelles. Agrandissez le cadre pour en récupérer.</li>
<li><b>Texte libre du client</b> (prénom…) : même règle ; s’il est trop long, le client est invité à le raccourcir avant de commander.</li>
</ul>
<p>Avant de vendre un slogan, vérifiez qu’il n’est pas déposé comme marque (base de l’INPI).</p>
[[img:boutique-textes.webp|La banque de textes : chaque phrase, sa note ou sa source, et la case « Validée »]]
HTML],
        ['id' => 'vente', 'title' => 'Mettre un modèle en vente', 'admin' => true, 'html' => <<<'HTML'
<p>Dans l’éditeur, la carte <b>Vente</b> : prix TTC, supplément par taille (XXL…), description, part de l’imprimeur pour ce modèle (en %, ou en € par article ; vide : celle du support), et les choix laissés au client, toujours dans la charte :</p>
<ul>
<li><b>Couleurs du produit</b> proposées (parmi celles du support) ; pour un produit imprimé en entier (écharpe, poster…), les <b>couleurs du fond</b> ;</li>
<li><b>Couleurs des textes</b> du client (palette du club) : seules celles <b>lisibles</b> sur la couleur choisie par le client lui sont proposées, et un texte du modèle illisible sur un produit prend automatiquement la couleur de la charte la plus contrastée ;</li>
<li><b>Taille du texte</b> : petit, moyen, grand ;</li>
<li><b>Position du texte</b> : haut, centre, bas ; seules les positions où le texte ne recouvre pas le logo sont proposées.</li>
</ul>
<p><b>Posters (A4, A3, A2)</b> : la carte Vente demande directement le <b>prix de chaque format</b> (« Prix en A4 », « en A3 », « en A2 »). Le moins cher sert de prix « à partir de » dans la boutique ; sur la page de l’article, chaque format affiche son prix et le prix se met à jour dès que le client en change.</p>
<p><b>Le modèle est en vente quand « Prêt à la vente » est coché ET qu’il a un prix</b> (sans prix, il n’apparaît pas dans la boutique ; la liste des modèles indique « pas en vente : sans prix »). Le client ne choisit jamais la police, ne déplace rien et n’envoie aucune image.</p>
<p>Sur la page de l’article, le client voit l’aperçu en direct ; <b>un clic sur l’aperçu l’ouvre en grand</b> dans une fenêtre (net à toutes les tailles), et <b>« Voir en 3D »</b> montre l’objet à faire tourner (t-shirt, sweat, mug, tote bag, casquette). Les aperçus restent indicatifs, la page le précise.</p>
[[img:boutique-produit.webp|La page d’un article : aperçu en direct, phrase au choix, couleurs, taille et position du texte, taille du vêtement]]
HTML],
        ['id' => 'anecdotes', 'title' => 'Les pièces uniques : l’anecdote tirée par le client', 'admin' => true, 'html' => <<<'HTML'
<p>Un modèle avec un champ <b>« Anecdote tirée par le client »</b> (bouton « + Anecdote tirée par le client » de l’éditeur) devient une <b>pièce unique</b> : pastille « ★ Pièce unique » dans la boutique, sur l’aperçu et dans le panier.</p>
<ol>
<li><b>Sujet, facultatif</b> : le client peut taper un match, un joueur, un entraîneur (« Paille », « Metz 1988 », « Bazdarevic »…) et le choisir dans les propositions du musée. Sans sujet, l’anecdote est tirée dans toute l’histoire du club.</li>
<li><b>« Une anecdote »</b> : le musée en tire une ; <b>« Une autre »</b> en tire une nouvelle, jusqu’à celle qui plaît.</li>
<li>Le client commande l’anecdote <b>telle quelle</b> : elle est signée par le site et ne peut pas être retouchée. <b>Une anecdote vendue n’est plus jamais proposée</b> à personne.</li>
</ol>
<p><b>D’où viennent les anecdotes ?</b> L’IA ne fait que rédiger une phrase à partir d’un <b>fait du musée</b>, en cherchant l’angle le plus parlant (record, première fois, coulisses, insolite), jamais un simple compte rendu. Les faits sont pris du plus parlant au plus banal :</p>
<ul>
<li><b>un joueur ou un entraîneur</b> : ses records et rangs dans les 100 chiffres du FCSM (« 4e meilleur buteur de l’histoire »), le chiffre clé et les paragraphes de sa fiche (formation, débuts, coulisses, carrière), son bilan, et seulement en dernier un match où il a marqué ;</li>
<li><b>un match</b> : le chiffre clé de la fiche, les coulisses (avant-match, primes, déclarations, réactions), puis le match lui-même ;</li>
<li><b>sans sujet</b> : un des records du FCSM, le meilleur d’une fiche de match ou d’un joueur marquant (légende, ou 100 matchs et plus).</li>
</ul>
<p>Si la fiche du sujet choisi n’a rien de parlant (ni record, ni chiffre clé, ni histoire, ni coulisses), le client en est prévenu et reçoit une anecdote tirée dans toute l’histoire du club. <b>Mieux les fiches sont remplies, meilleures sont les anecdotes.</b></p>
[[attention|<p>Garde-fous : une phrase qui contient un nom ou un nombre <b>absent du fait</b> est refusée, comme une question ou un « Le saviez-vous ? ». Les anecdotes rédigées sont gardées en réserve (par sujet) et resservies gratuitement ; l’IA n’est appelée que quand le client a vu toute la réserve, dans la limite du <b>budget IA du jour</b> (Boutique › Réglages). Au-delà, le client reçoit une anecdote de la réserve.</p>]]
[[img:boutique-anecdote.webp|Pièce unique : le client choisit un sujet (ici la finale de 1988) et tire son anecdote ; « Une autre sur ce sujet » en tire une nouvelle]]
HTML],
        ['id' => 'poster', 'title' => 'Le poster souvenir d’un match', 'admin' => true, 'html' => <<<'HTML'
<p>Le <b>poster souvenir</b> est une composition entière générée par le musée d’après la fiche d’un match : affiche et score, onze de départ sur le terrain, film du match, tribunes, citations, anecdote, chiffre, récit du match et bilan de la saison. En haut, la dédicace <b>« pour Prénom Nom »</b> ; dans le blason, un <b>numéro de pièce</b> attribué à la commande (N° 0001, 0002…). C’est une pièce unique, commandée à l’unité.</p>
<ol>
<li><b>Créer le modèle</b> : Boutique › Modèles, support <b>« Poster (A4, A3, A2) »</b> (ou Poster A3), puis dans l’éditeur <b>« + Poster souvenir du match »</b> : le calque occupe toute la face, fond marine compris.</li>
<li><b>Prix</b> : un prix par format dans la carte Vente. Couleurs, taille et position du texte ne s’appliquent pas : la charte du poster est fixe.</li>
<li><b>Côté client</b> : il tape son match (« Metz 88 », « finale », « Fiorentina »…) et le choisit dans les propositions, puis donne son prénom, son nom et le format. Seuls les matchs à la fiche complète (composition, au moins trois temps forts, un texte) sont proposés : environ 1 500.</li>
</ol>
<p><b>Le contenu</b> vient des fiches : score, compo et capitaine, temps forts, spectateurs, chiffre clé, citations mot pour mot, récit (résumé audio de la fiche), bilan du championnat de la saison. Quand le client choisit un match, l’IA prépare une fois pour toutes l’anecdote, les citations avec la fonction de chacun, l’enjeu et un récit condensé ; tout est vérifié contre la fiche (citations retrouvées mot pour mot, aucun nom ni nombre absent), puis gardé pour les clients suivants. Sans IA, le poster se fait avec la fiche seule, les blocs manquants laissant leur place aux autres (terrain et film agrandis, fiche technique du match).</p>
[[astuce|<p>Le coût IA d’un poster (moins d’un centime par match, une seule fois) apparaît dans <b>Coûts IA</b>, ligne « Boutique (posters souvenirs) ». Le dessin, l’aperçu et le PDF de l’imprimeur ne coûtent rien.</p>]]
[[img:boutique-poster.webp|Le poster souvenir : le match choisi dans les propositions, la dédicace, le format et son prix, l’aperçu du poster]]
HTML],
        ['id' => 'poster-joueur', 'title' => 'Le poster souvenir d’un joueur', 'admin' => true, 'html' => <<<'HTML'
<p>Le même principe que le poster d’un match, cette fois pour un <b>Lionceau</b> : le client choisit un joueur et le musée compose son affiche d’après sa fiche. On y trouve son nom en très grand avec son poste et ses années au club, ses <b>grands chiffres</b> (matchs, buts, saisons, minutes jouées) et sa <b>carrière saison par saison</b> en bâtons, buts en jaune. S’y ajoutent sa fiche d’identité, son palmarès au FCSM, ses grands matchs, <b>ses places dans les records du club</b>, une anecdote et une citation. En bas viennent son histoire et ses jalons : premier match, premier but, dernier match. Il porte la même dédicace « pour Prénom Nom », le même numéro de pièce et les mêmes formats A4, A3 et A2.</p>
<ol>
<li><b>Créer le modèle</b> : support « Poster (A4, A3, A2) », puis dans l’éditeur <b>« + Poster souvenir du joueur »</b>.</li>
<li><b>Côté client</b> : il tape un nom (« Paille », « Bazdarevic »…) et le choisit dans les propositions. Seuls les joueurs qui ont joué <b>au moins 30 matchs</b> et dont la fiche est publiée sont proposés.</li>
</ol>
<p><b>Les chiffres</b> viennent du tableau de statistiques de la fiche, recalculés saison par saison (une ligne « Total » d’origine fausse est ignorée). Sans tableau, ils viennent des compositions, matchs amicaux exclus. L’IA ajoute une fois par joueur l’anecdote, la citation mot pour mot, un portrait en une phrase et une histoire condensée, avec les mêmes vérifications que pour les matchs.</p>
HTML],
        ['id' => 'poster-carnet', 'title' => 'Le poster « Ma vie en jaune et bleu »', 'admin' => true, 'html' => <<<'HTML'
<p>Le poster du <b>carnet du supporter</b> : les matchs que le client a cochés comme vus au stade deviennent son affiche. On y trouve le nombre de matchs en très grand, son bilan (victoires, nuls, défaites, buts vus), ses <b>saisons au stade</b> en bâtons, son porte-bonheur, ses badges, ses grands matchs (premier, plus belle victoire, plus grosse affluence, dernier), les buteurs et les Lionceaux qu’il a le plus vus et ses adversaires. Il porte la même dédicace « pour Prénom Nom », le même numéro de pièce et les mêmes formats A4, A3 et A2. Aucun coût d’IA.</p>
<ol>
<li><b>Le modèle est prêt</b> : Boutique › Modèles, « Poster « Ma vie en jaune et bleu » » (support « Poster (A4, A3, A2) »). Donnez-lui un prix et activez-le. Pour en créer un autre : bouton <b>« + Poster du carnet du supporter »</b> de l’éditeur.</li>
<li><b>Côté client</b> : le poster se compose d’après son carnet, ouvert sur l’appareil (au moins 5 matchs). Un bouton <b>« Mon poster »</b> apparaît dans son carnet dès que le modèle est en vente. Sans carnet, la fiche produit montre un exemple et l’invite à créer le sien.</li>
</ol>
<p>À la mise au panier, <b>la liste des matchs est figée</b> dans la commande : le fichier de l’imprimeur ne change plus, même si le client modifie ou supprime son carnet ensuite.</p>
[[img:boutique-poster-carnet.webp|La fiche produit du poster « Ma vie en jaune et bleu » : le carnet du client repris, l’aperçu de son poster]]
HTML],
        ['id' => 'commandes', 'title' => 'Les commandes', 'admin' => true, 'html' => <<<'HTML'
<p>Le client paie par carte (Stripe, les mêmes clés que les dons et les adhésions) : l’argent arrive sur le compte de l’association. La commande payée part aussitôt chez l’imprimeur, avec un <b>PDF d’impression par article</b>, et le client reçoit un e-mail avec son lien de suivi.</p>
<ul>
<li><b>Boutique › Commandes</b> : toutes les commandes par étape (payée, en fabrication, expédiée, livrée, annulée, remboursée).</li>
<li>Dans une commande : les articles et leurs PDF, l’adresse de livraison, les messages avec le client, l’historique, et les actions : changer l’étape, <b>rembourser</b> (tout ou partie, directement par Stripe), « paiement reçu hors ligne » (chèque, espèces).</li>
<li>Chaque étape envoie un e-mail au client (case « Prévenir le client »). Les commandes jamais payées sont annulées au bout de deux jours.</li>
</ul>
[[img:boutique-commande.webp|Une commande : articles et PDF, messages, livraison, étape, remboursement, historique]]
HTML],
        ['id' => 'imprimeur', 'title' => 'L’espace imprimeur et le service client', 'admin' => true, 'html' => <<<'HTML'
<p>L’imprimeur a son propre espace, séparé du back-office : <b>/imprimeur/</b> sur l’adresse du musée. Il saisit son e-mail et reçoit un lien de connexion (valable 30 minutes, une seule fois). Seule l’adresse réglée dans <b>Boutique › Réglages</b> peut entrer : la changer coupe l’accès de l’ancienne.</p>
<ul>
<li>Il voit les commandes payées (à fabriquer, en fabrication, expédiées), télécharge les PDF, passe chaque commande « en fabrication » puis « expédiée » avec le transporteur et le numéro de suivi.</li>
<li><b>Service client</b> : le client écrit depuis sa page de suivi ; l’imprimeur est prévenu par e-mail et répond depuis son espace ; le client reçoit la réponse par e-mail. L’association lit tous les échanges dans la commande.</li>
</ul>
[[img:boutique-imprimeur.webp|L’espace imprimeur : une commande expédiée, avec le numéro de suivi et les messages du client]]
HTML],
        ['id' => 'tableau', 'title' => 'Le tableau de bord et l’argent', 'admin' => true, 'html' => <<<'HTML'
<p><b>Boutique › Tableau de bord</b> : ventes du jour, du mois et de l’année, panier moyen, articles vendus, <b>marge</b> (ventes − remboursements − frais Stripe − fabrication et expédition), courbe des 12 derniers mois, meilleures ventes, commandes à fabriquer.</p>
<ul>
<li><b>À suivre</b> : les alertes (litige ouvert par un client, commande payée depuis plus de 3 jours sans fabrication, en fabrication depuis plus de 7 jours, question d’un client sans réponse depuis 2 jours, modèle prêt à la vente sans prix). Les nouvelles alertes partent aussi une fois par jour par e-mail à l’adresse d’alerte.</li>
<li><b>Partage des ventes</b> : ce qui revient à l’imprimeur (coût fixe ou commission) et ce qui reste à l’association, sur le mois et l’année. L’association encaisse tous les paiements et reverse sa part à l’imprimeur.</li>
<li><b>Frais Stripe</b> : relevés à chaque paiement et affichés dans la commande (frais et net encaissé).</li>
<li><b>Rapprocher avec Stripe</b> : compare les paiements des 30 derniers jours aux commandes. Une commande payée chez Stripe mais restée « en attente » (message perdu) est rattrapée : elle part chez l’imprimeur. Les écarts sont signalés.</li>
<li><b>Litiges</b> : quand un client conteste un paiement, la commande l’indique et l’association est prévenue ; on répond dans le tableau de bord de Stripe, avec le numéro de suivi du colis.</li>
</ul>
[[img:boutique-tableau.webp|Le tableau de bord de la boutique : ventes, marge, courbe, meilleures ventes, rapprochement Stripe]]
HTML],
        ['id' => 'releves', 'title' => 'Les relevés de l’imprimeur', 'admin' => true, 'html' => <<<'HTML'
<p><b>Boutique › Relevés imprimeur</b> : pour chaque mois, la liste des articles fabriqués (commandes payées dans le mois, sauf celles remboursées) avec leur coût de fabrication, plus une expédition par commande : c’est ce que l’imprimeur facture à l’association. Téléchargeable en <b>PDF</b> et en <b>tableur</b> (CSV). Le relevé donne aussi les ventes, les frais Stripe et la marge.</p>
<ul>
<li>Le coût de fabrication de chaque produit se règle dans <b>Boutique › Supports</b> (valeurs de départ indicatives, à remplacer par les tarifs de l’imprimeur) ; le coût d’une expédition dans <b>Boutique › Réglages</b>. Une commande garde les coûts du jour où elle a été payée.</li>
<li>L’imprimeur voit le même relevé dans son espace (bouton « Relevé mensuel »), <b>sans</b> les ventes ni la marge de l’association.</li>
</ul>
HTML],
        ['id' => 'tonmatch', 'title' => '« Ton match » : le match d’une date', 'admin' => true, 'html' => <<<'HTML'
<p>Le client donne une date (naissance, mariage, premier match à Bonal…) : jour, mois et année, ou seulement le mois et l’année, ou l’année. Le musée retrouve le match de Sochaux de ce jour-là (sinon le plus proche ; pour un mois ou une année, le plus marquant) et l’imprime : année, date, affiche et score, compétition, stade, buteurs, spectateurs, et une phrase « Ce jour-là, Sochaux battait… ».</p>
<ul>
<li>Deux modèles sont prêts : <b>Poster « Ton match »</b> (A3) et <b>T-shirt « Ton match »</b> (logo devant, le match au dos).</li>
<li>Pour en créer d’autres : dans l’éditeur, « Ton match » propose un bouton par information (+ Année, + Affiche et score…). Dès qu’un modèle en contient une, sa page demande la date au client, et lui affiche le match trouvé.</li>
<li>Les informations viennent des fiches du musée : une fiche corrigée corrige aussi les prochains posters.</li>
</ul>
[[img:boutique-tonmatch.webp|« Ton match » : le client choisit sa date, le poster se met à jour avec le match trouvé]]
HTML],
        ['id' => 'promos', 'title' => 'Les codes promo', 'admin' => true, 'html' => <<<'HTML'
<p><b>Boutique › Codes promo</b> : créez un code (ex. CENTENAIRE) et choisissez la remise : <b>pourcentage</b>, <b>montant fixe</b> ou <b>livraison offerte</b>. Options : dates de validité, montant d’achats minimum, nombre d’utilisations maximum, une seule fois par client (même e-mail), limité à certains modèles. Décochez « Actif » pour le suspendre.</p>
<p>Le client saisit le code dans son panier (« J’ai un code promo ») : la remise s’affiche aussitôt et elle est appliquée au paiement Stripe. Une utilisation n’est comptée que quand la commande est payée ; la liste des commandes qui ont utilisé un code est dans sa fiche.</p>
HTML],
        ['id' => 'reglages', 'title' => 'Les réglages de la boutique', 'admin' => true, 'html' => <<<'HTML'
<p><b>Boutique › Réglages</b> : ouverture de la boutique, frais de port et seuil de livraison offerte, délai annoncé, nom et e-mail de l’imprimeur, e-mail d’alerte de l’association à chaque commande payée, <b>budget IA du jour</b> (anecdotes et posters), conditions de vente : laissées vides, la boutique affiche le texte proposé, qu’un bouton « Remettre le texte proposé » rétablit. La boutique est sur le site du musée (bouton « Boutique » dans l’en-tête et le pied de page des deux sites ; l’adresse /boutique/ du site de l’association y renvoie). Avant l’ouverture, seule l’équipe connectée la voit.</p>
HTML],
        ['id' => 'pdf', 'title' => 'Le fichier pour l’imprimeur', 'admin' => true, 'html' => <<<'HTML'
<p><b>PDF imprimeur</b> (dans l’éditeur ou la liste des modèles) : un PDF <b>vectoriel</b>, une page par face dessinée, au <b>format exact</b>, avec les <b>fonds perdus</b>, les <b>traits de coupe</b> et un repère dans la marge (modèle, face, dimensions, couleur du support). Couleurs en <b>CMJN</b> (les couleurs de la charte ont leur équivalent d’imprimerie).</p>
<p>Les champs du client essayés dans l’éditeur sont repris dans le PDF : c’est exactement ce que recevra l’imprimeur pour une commande. Pour un poster commandé en A4 ou en A2, le PDF de la commande est au format choisi ; pour un poster souvenir, il porte la dédicace et le numéro de la pièce (repris aussi dans la marge).</p>
HTML],
    ],
];
