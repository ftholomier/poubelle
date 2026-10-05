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
<li><b>Dans l’éditeur</b> : sous « Phrase au choix du client », cliquez le bouton de la liste (« + Slogans »…). Un cadre jaune apparaît dans le fichier d’impression : c’est la place réservée à la phrase.</li>
<li><b>Phrases trop longues</b> : chaque phrase est d’abord réduite, puis passée sur plusieurs lignes pour tenir dans le cadre, sans descendre sous le « Corps minimum » (20 pt sur la casquette brodée). Une phrase qui ne tient pas, même au minimum, n’est <b>jamais proposée</b> au client pour ce modèle : l’essai des champs indique combien de phrases sont écartées, et lesquelles. Agrandissez le cadre pour en récupérer.</li>
<li><b>Texte libre du client</b> (prénom…) : même règle ; s’il est trop long, le client est invité à le raccourcir avant de commander.</li>
</ul>
<p>Avant de vendre un slogan, vérifiez qu’il n’est pas déposé comme marque (base de l’INPI).</p>
[[img:boutique-textes.webp|La banque de textes : chaque phrase, sa note ou sa source, et la case « Validée »]]
HTML],
        ['id' => 'pdf', 'title' => 'Le fichier pour l’imprimeur', 'admin' => true, 'html' => <<<'HTML'
<p><b>PDF imprimeur</b> (dans l’éditeur ou la liste des modèles) : un PDF <b>vectoriel</b>, une page par face dessinée, au <b>format exact</b>, avec les <b>fonds perdus</b>, les <b>traits de coupe</b> et un repère dans la marge (modèle, face, dimensions, couleur du support). Couleurs en <b>CMJN</b> (les couleurs de la charte ont leur équivalent d’imprimerie).</p>
<p>Les champs du client essayés dans l’éditeur sont repris dans le PDF : c’est exactement ce que recevra l’imprimeur pour une commande.</p>
HTML],
    ],
];
