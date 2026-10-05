<?php
return [
    'slug' => 'boutique',
    'title' => 'La boutique',
    'summary' => 'Les objets de l’association (t-shirt, mug, casquette, écharpe, poster…) : supports de l’imprimeur, modèles dessinés dans l’éditeur, banque de phrases au choix du client, aperçu sur le produit et fichier d’impression. Réservé aux administrateurs.',
    'admin' => true,
    'sections' => [
        ['id' => 'principe', 'title' => 'Le principe : du texte et le logo, jamais de photo', 'admin' => true, 'html' => <<<'HTML'
<p>La boutique vend des objets fabriqués <b>à la demande</b> par un imprimeur local. Pour rester maîtres de tout et éviter tout problème de droits, on n’imprime <b>jamais de photo</b> : seulement le <b>logo de l’association</b> (redessiné en vectoriel), des <b>textes</b> (slogans, données du musée, prénom ou dédicace du client) et des formes simples.</p>
<ul>
<li>Tout est <b>vectoriel</b> : le fichier envoyé à l’imprimeur reste net à toutes les tailles, d’un sticker à une écharpe.</li>
<li>Les lettres sont converties en tracés : l’imprimeur n’a besoin d’aucune police.</li>
<li>Le pavé <b>Boutique</b> du menu n’est visible que des administrateurs.</li>
</ul>
[[auto|<p>Prochaines étapes : produits et tarifs, boutique en ligne, paiement Stripe, espace de l’imprimeur et suivi des commandes, puis tableau de bord des ventes et « Ton match ».</p>]]
HTML],
        ['id' => 'supports', 'title' => 'Les supports', 'admin' => true, 'html' => <<<'HTML'
<p><b>Boutique › Supports</b> : les produits vierges de l’imprimeur. Pour chacun :</p>
<ul>
<li>les <b>faces imprimables</b> en millimètres (format fini), par exemple Avant et Dos pour un t-shirt ;</li>
<li>les <b>fonds perdus</b> : la marge coupée après impression (2 à 3 mm pour le papier, aucune pour le textile) ;</li>
<li>les <b>couleurs</b> du produit vierge (« Bleu nuit : #0E1F4D », une par ligne). Sans couleur, le produit est imprimé en entier et le fond fait partie du dessin (poster, écharpe, sticker) ;</li>
<li>les <b>tailles</b>, la <b>référence chez l’imprimeur</b> et ses <b>consignes</b> (affichées dans l’éditeur).</li>
</ul>
<p>Supports de départ : t-shirt, sweat à capuche, mug céramique et émaillé, tote bag, <b>casquette</b> (broderie : texte de 5 mm de haut au moins), <b>écharpe</b> (1 400 × 180 mm, imprimée en entier), posters A3 et A2, carte postale, sticker. Vous pouvez les modifier, en désactiver, en ajouter.</p>
HTML],
        ['id' => 'modeles', 'title' => 'Dessiner un modèle', 'admin' => true, 'html' => <<<'HTML'
<p><b>Boutique › Modèles</b> : donnez un nom, choisissez le support, puis <b>Créer et dessiner</b>. L’éditeur montre côte à côte le <b>produit</b> (dans la couleur choisie) et le <b>fichier d’impression</b>.</p>
[[img:boutique-editeur.webp|L’éditeur : (1) nom, face, couleur du produit, enregistrer et PDF imprimeur ; (2) l’aperçu sur le produit ; (3) le fichier d’impression, où l’on déplace les éléments ; (4) ajouter un logo, un texte, un champ du client, une forme ; (5) les calques ; (6) les réglages de l’élément choisi]]
<ol>
<li><b>Ajouter</b> : le logo (en couleurs, ou d’une seule couleur sur textile foncé), un texte, un <b>champ du client</b>, un rectangle ou un rond.</li>
<li><b>Placer</b> : cliquez un élément dans le fichier d’impression et faites-le glisser ; les flèches du clavier le déplacent de 1 mm (10 mm avec Maj). « Centrer » le met au milieu de la largeur.</li>
<li><b>Régler</b> : police (celles du site), corps, couleur de la charte, alignement, capitales, espacement ; « Réduire pour tenir sur la largeur » pour un prénom de longueur inconnue.</li>
<li><b>Champ du client</b> : le texte d’exemple est remplacé par celui du client (« Votre prénom », 12 caractères au plus…). Essayez des prénoms longs dans « Essai des champs du client ».</li>
<li><b>Enregistrer</b>, puis cochez <b>Prêt à la vente</b> quand le modèle est validé.</li>
</ol>
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
<p>Dans l’éditeur, la carte <b>Vente</b> : prix TTC, supplément par taille (XXL…), description, et les choix laissés au client, toujours dans la charte :</p>
<ul>
<li><b>Couleurs du produit</b> proposées (parmi celles du support) ;</li>
<li><b>Couleurs des textes</b> du client (palette du club) ;</li>
<li><b>Taille du texte</b> : petit, moyen, grand ;</li>
<li><b>Position du texte</b> : haut, centre, bas ; seules les positions où le texte ne recouvre pas le logo sont proposées.</li>
</ul>
<p><b>Le modèle est en vente quand « Prêt à la vente » est coché ET qu’il a un prix</b> (sans prix, il n’apparaît pas dans la boutique ; la liste des modèles indique « pas en vente : sans prix »). Le client ne choisit jamais la police, ne déplace rien et n’envoie aucune image.</p>
[[img:boutique-produit.webp|La page d’un article : aperçu en direct, phrase au choix, couleurs, taille et position du texte, taille du vêtement]]
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
        ['id' => 'reglages', 'title' => 'Les réglages de la boutique', 'admin' => true, 'html' => <<<'HTML'
<p><b>Boutique › Réglages</b> : ouverture de la boutique, frais de port et seuil de livraison offerte, délai annoncé, nom et e-mail de l’imprimeur, e-mail d’alerte de l’association à chaque commande payée, conditions de vente. La boutique est sur le site du musée (bouton « Boutique » dans l’en-tête et le pied de page des deux sites ; l’adresse /boutique/ du site de l’association y renvoie). Avant l’ouverture, seule l’équipe connectée la voit.</p>
HTML],
        ['id' => 'pdf', 'title' => 'Le fichier pour l’imprimeur', 'admin' => true, 'html' => <<<'HTML'
<p><b>PDF imprimeur</b> (dans l’éditeur ou la liste des modèles) : un PDF <b>vectoriel</b>, une page par face dessinée, au <b>format exact</b>, avec les <b>fonds perdus</b>, les <b>traits de coupe</b> et un repère dans la marge (modèle, face, dimensions, couleur du support). Couleurs en <b>CMJN</b> (les couleurs de la charte ont leur équivalent d’imprimerie).</p>
<p>Les champs du client essayés dans l’éditeur sont repris dans le PDF : c’est exactement ce que recevra l’imprimeur pour une commande.</p>
HTML],
    ],
];
