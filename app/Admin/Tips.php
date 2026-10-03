<?php
declare(strict_types=1);

namespace App\Admin;

/**
 * Bulles d'aide « ? » du back-office : un texte court par écran, par bloc et par champ.
 * Le script du back-office les place à côté du titre de l'écran (.top__h), des titres de
 * blocs (.card__t) et des libellés de champs (.f__k) dont le texte correspond à une clé
 * (en minuscules). Les textes sont rédigés ici, en un seul endroit.
 */
final class Tips
{
    /** Blocs et champs présents sur plusieurs écrans. */
    private const COMMON = [
        // Panneaux de l'éditeur de fiche
        'publication' => 'Choisissez le statut : <b>Brouillon</b> (invisible), <b>À relire</b>, <b>Planifié</b> (publication automatique à la date choisie) ou <b>Publié</b>. Enregistrez avec le bouton ou <b>Ctrl + S</b> ; une note de version aide à retrouver une modification.',
        'mis à jour automatiquement' => 'Ce que le site recalcule seul quand vous enregistrez cette fiche : saison, fiches des joueurs, carte, records, image de partage… Rien à faire de votre côté.',
        'contrôle qualité' => 'Les vérifications propres à cette fiche : score cohérent, photos sans crédit, image à la une manquante… Une alerte disparaît dès que la fiche est corrigée.',
        'version en' => 'État de la traduction anglaise de cette fiche. « À revoir » : le français a changé depuis la traduction.',
        'version anglaise' => 'Texte anglais de la fiche. Laissez vide pour afficher le français ; le bouton « Traduire » propose une traduction par l’IA, à relire.',
        'tableaux' => 'Tableaux de la fiche (statistiques, compositions d’autres équipes…). Cliquez dans une case pour la modifier ; « Coller un tableau » importe un tableau copié depuis Excel, Word ou une page web.',
        'rubriques' => 'Les rubriques où la fiche apparaît (mosaïques et menus). Une fiche peut être dans plusieurs rubriques ; la saison d’un match est ajoutée automatiquement.',
        'référencement' => 'Ce que Google et les réseaux sociaux affichent : titre, description et adresse de la page. Laissés vides, ils sont remplis automatiquement.',
        'galerie' => 'Les photos affichées en galerie sur la fiche. Ajoutez-les depuis la médiathèque, glissez pour changer l’ordre ; le crédit de la médiathèque s’applique si celui de la galerie est vide.',
        'vidéos' => 'Collez le lien d’une vidéo YouTube, Dailymotion, Vimeo ou Rutube. Elle ne se charge qu’après l’accord du visiteur (cookies), avec son image en attendant.',
        'publications intégrées' => 'Messages de réseaux sociaux (X, Facebook…) cités dans la fiche : le lien et le texte, affichés sans charger le réseau social.',
        'images dans le texte' => 'Images insérées au fil du texte (reprises de l’ancien site). Pour les nouvelles fiches, préférez la galerie.',
        'encart « chiffre clé »' => 'Le grand chiffre mis en avant sur la fiche (ex. « 16 063 spectateurs »), avec sa légende.',
        // Champs
        'titre de la fiche' => 'Le titre affiché en haut de la fiche et dans les mosaïques. Pour un match, il est proposé automatiquement à partir des équipes et de la date.',
        'titre (google & partage)' => 'Titre montré par Google et les réseaux sociaux. Vide : le titre de la fiche est utilisé.',
        'description' => 'Une ou deux phrases qui donnent envie de cliquer dans les résultats de Google (160 caractères environ). Vide : le début du texte est utilisé.',
        'adresse de la page' => 'L’adresse (URL) de la fiche, créée automatiquement avec des mots-clés. Si vous la changez après publication, l’ancienne adresse est redirigée vers la nouvelle.',
        'date de publication' => 'Date de mise en ligne de la fiche (ordre « plus récentes » des mosaïques). Pour publier plus tard, utilisez le statut « Planifié ».',
        'à la une (slider de l’accueil, tirage aléatoire)' => 'Cochée, la fiche peut apparaître dans le grand slider de l’accueil (tirage au hasard parmi les fiches « À la une » qui ont une image).',
        'image à la une (mosaïques, partage, en-tête)' => 'La photo principale : vignette dans les mosaïques, image de partage sur les réseaux sociaux et en-tête de la fiche. Choisissez une photo de bonne taille, créditée.',
        'intertitre' => 'Titre d’une partie du texte (ex. « Avant-match », « Résumé »). Il structure la page et aide Google.',
        'lien de la vidéo' => 'Adresse de la vidéo copiée depuis YouTube, Dailymotion, Vimeo ou Rutube (barre d’adresse ou bouton « Partager »).',
        'crédit' => 'Auteur ou source de la photo (photographe, journal, collection). Obligatoire pour toute photo publiée.',
        'légende' => 'Le texte d’accompagnement : pour une photo, ce qu’elle montre (qui, où, quand), affiché dessous et lu aux personnes malvoyantes ; pour un chiffre clé, ce qu’il représente.',
        'fiches liées' => 'Fiches en rapport (match, joueur…) : tapez quelques lettres du titre et choisissez dans la liste.',
    ];

    /** Par écran (clé du menu) : 'screen' pour le titre de l'écran, puis blocs et champs. */
    private const SCREENS = [
        'dash' => [
            'screen' => 'Votre point de départ : chiffres du musée, travail récent de l’équipe, publications programmées et liste « À faire » des points à reprendre.',
            'à faire' => 'Les tâches qui attendent quelqu’un : contributions à traiter, photos à créditer, joueurs sans fiche… Cliquez pour ouvrir l’écran concerné.',
            'activité récente' => 'Les dernières modifications de toute l’équipe (le détail complet est dans Pilotage › Journal).',
            'audience · 30 jours' => 'Pages vues sur le site public, mesurées sans cookie ni adresse IP.',
            'publications programmées' => 'Les fiches au statut « Planifié » et leur date de mise en ligne automatique.',
        ],
        'qualite' => ['screen' => 'Tout ce qui mérite une vérification, classé par onglet : statistiques incohérentes, liens joueurs, photos sans crédit, lieux de naissance inconnus, traductions. Corrigez la fiche : l’alerte disparaît d’elle-même.'],
        'journal' => ['screen' => 'Qui a fait quoi et quand dans le back-office. Filtrez par personne ; utile pour retrouver une modification récente.'],
        'matchs' => [
            'screen' => 'Les fiches match. La liste se filtre par saison, compétition et statut ; dans une fiche, les onglets Infos, Compo & événements, Récit, Médias… se remplissent dans l’ordre que vous voulez.',
            'score' => 'Buts de chaque équipe (domicile puis extérieur). Prolongation et tirs au but sont facultatifs. Le résultat (victoire, nul, défaite) est déduit automatiquement.',
            'composition sochaux' => 'Une ligne par joueur : poste (G, D, M, A, R remplaçant, E entraîneur), numéro, nom, buts, remplacement, cartons. Choisissez le joueur dans la liste proposée pour relier sa fiche (✓) ; « Importer depuis un tableau » colle une composition copiée.',
            'temps forts (minute par minute)' => 'Les actions marquantes avec leur minute ; cochez « But » et indiquez le score pour les buts. Ils forment la frise du résumé sur la fiche.',
            'réactions' => 'Citations d’après-match : qui parle et ce qu’il a dit.',
            'brèves' => 'Petites informations autour du match (anecdotes, transferts, blessures).',
            'date' => 'Date du match (jj/mm/aaaa). Elle place le match dans sa saison et dans « Ce jour-là ».',
            'compétition' => 'Famille de compétition (championnat, Coupe de France…) : elle sert aux filtres, bilans et records.',
            'libellé' => 'Nom exact de la compétition affiché sur la fiche (ex. « Ligue 2 », « Coupe de la Ligue »).',
            'journée / tour' => 'Ex. « J12 » ou « 32e de finale ».',
            'adversaire' => 'Le club rencontré : choisissez-le dans la liste pour que le face-à-face et le logo suivent.',
            'stade' => 'Nom du stade (et ville entre parenthèses si besoin). Il alimente la carte des stades et les bilans par stade.',
            'spectateurs' => 'Nombre de spectateurs, en chiffres. Il sert aux records d’affluence.',
            'buteurs (texte de l’en-tête)' => 'La ligne des buteurs telle qu’affichée en tête de fiche, si elle diffère des buteurs par équipe.',
            'buts par équipe' => 'Pour chaque équipe, la liste des buteurs avec les minutes (ex. « Prat 33’, Thomas 78’ »).',
            'événement (match particulier)' => 'Pour un match à part (jubilé, finale, inauguration) : un mot qui le met en valeur.',
            'prolongation' => 'Non, après prolongation (a.p.) ou tirs au but : avec « Tirs au but », deux champs apparaissent pour le score de la séance.',
            'domicile / extérieur' => 'Sochaux reçoit (domicile) ou se déplace (extérieur) : cela fixe l’ordre des équipes dans le titre et le score.',
            'niveau adversaire' => 'La division de l’adversaire à l’époque (ex. « D2 », « N1 », « L1 Sui ») : affichée sous son nom.',
            'niveau sochaux' => 'La division de Sochaux à l’époque (ex. « D1 », « L2 »).',
        ],
        'personnes' => [
            'screen' => 'Les fiches des joueurs, entraîneurs, dirigeants et personnages. Leurs matchs, buts et cartons sont reliés automatiquement depuis les compositions.',
            'identité' => 'Nom, prénom, rubriques et poste. La première rubrique cochée donne l’adresse de la fiche (/joueurs/…, /entraineurs/…).',
            'naissance' => 'Date et lieu de naissance : la ville place la personne sur la carte des origines (position trouvée automatiquement, modifiable).',
            'décès' => 'Date et lieu de décès, si connus.',
            'statuts & carto' => 'Formé au club, international, à l’essai, légende (mise en avant) et présence sur la carte.',
            'carte de l’album du centenaire' => 'La carte à collectionner du joueur : présence dans l’album, numéro et rareté.',
            'au club' => 'Arrivée, départ, premiers et derniers matchs. Les dates d’arrivée et de départ aident aussi à relier les compositions au bon joueur.',
            'palmarès' => 'Titres et distinctions, une ligne par titre.',
            'après sochaux' => 'La suite du parcours, une ligne par étape.',
            'fiche d’identité (tableau d’origine)' => 'Le tableau d’identité de l’ancien site, conservé tel quel (libellé et valeur).',
            'matchs marquants' => 'Quelques matchs à mettre en avant sur la fiche : tapez une équipe ou une date et choisissez.',
            'matchs reliés automatiquement' => 'Tous les matchs où la personne figure dans une composition, avec ses buts et son temps de jeu. Rien à saisir : ajoutez un nom mal écrit dans « Autres graphies ».',
            'statistiques (tableau saison par saison)' => 'Le tableau de statistiques saison par saison, modifiable comme une feuille de calcul.',
            'nom affiché' => 'Le nom tel qu’il apparaît sur le site. Vide : « Prénom Nom ».',
            'autres graphies dans les compositions' => 'Les autres façons dont le nom est écrit dans les compositions (« CAMARA Razza »…) : ces matchs seront reliés à cette fiche.',
            'ligne (filtres, terrain)' => 'Gardien, défenseur, milieu ou attaquant : sert aux filtres de la rubrique Nos Lions et au placement sur le terrain.',
            'latitude' => 'Calculée automatiquement à partir de la ville ; à corriger seulement si le point est mal placé sur la carte.',
            'longitude' => 'Calculée automatiquement à partir de la ville ; à corriger seulement si le point est mal placé sur la carte.',
            'légende (mise en avant)' => 'Met la personne en valeur (cartes, sélections, page d’accueil).',
            'visible sur la carto' => 'Décochez pour ne pas afficher la personne sur la carte des origines.',
            'à l’essai' => 'Joueur venu à l’essai sans signer : la fiche le précise.',
        ],
        'articles' => [
            'screen' => 'Articles thématiques (infrastructures, symboles, supporters, bilans de saison…) et pages du site (dont les pages légales).',
            'type d’article' => 'Article, bilan de saison, portrait ou dossier : change la présentation et les liens automatiques (un bilan est relié à sa saison).',
            'saison (bilan)' => 'Pour un bilan de saison : la saison concernée (ex. 1987-1988).',
            'chapeau / surtitre' => 'La courte phrase au-dessus ou sous le titre qui résume l’article.',
        ],
        'objets' => [
            'screen' => 'Les objets des réserves du musée (maillots, affiches, programmes, coupures de presse…), présentés dans la rubrique « Réserves ».',
            'collection' => 'La collection des réserves où l’objet est rangé (maillots, affiches…).',
            'provenance' => 'D’où vient l’objet (don, prêt, collection privée).',
            'crédit / propriétaire' => 'À qui appartient l’objet ou qui l’a photographié.',
        ],
        'moments' => [
            'screen' => '« 100 ans, 100 moments » : un moment de l’histoire du club par semaine jusqu’au centenaire (20 mai 2028).',
            'numéro (1 à 100)' => 'Le rang du moment dans la série ; il fixe sa semaine de publication.',
        ],
        'referentiels' => ['screen' => 'Les listes communes : adversaires (regroupement des variantes de noms, ville, logo), stades et lieux (position sur la carte). Une correction ici s’applique à toutes les fiches.'],
        'medias' => ['screen' => 'Toutes les photos et PDF du musée. Envoyez par glisser-déposer (crédit demandé), cliquez une photo pour la légende, les droits, la retouche ou le remplacement ; les filtres montrent ce qu’il reste à compléter.'],
        'accueil' => [
            'screen' => 'La page d’accueil du site : slider, bandeau défilant, chiffres, grandes époques, réserves, encarts. Chaque bloc s’enregistre avec le bouton en bas de page.',
            'grand slider de l’accueil' => 'Tirage au hasard parmi les fiches cochées « À la une » (avec image), comme sur l’ancien site, ou liste choisie à la main dans l’ordre voulu.',
            'bandeau « en direct du musée »' => 'Le bandeau défilant en haut du site : messages automatiques (Ce jour-là, centenaire, dernier match fiché) et messages de l’équipe, avec lien facultatif.',
            'compteurs et centenaire' => 'Les chiffres de l’accueil (membres, vidéos…) et la date du centenaire pour le compte à rebours.',
            'les grandes époques' => 'Les périodes de l’histoire du club présentées sur l’accueil : nom, dates, texte, image et dates clés.',
            'les réserves du musée' => 'Les collections d’objets mises en avant sur l’accueil.',
            'palmarès (bandeau jaune)' => 'Le bandeau des titres du club : chiffre (années) et titre.',
            '« ils ont porté le lion »' => 'Le bloc de joueurs mis en avant sur l’accueil.',
            'images des encarts' => 'Les images des encarts Quiz, Maillots, Frise et Contribuer.',
            'référencement de l’accueil' => 'Titre et description de l’accueil pour Google et les réseaux sociaux.',
        ],
        'rubriques' => [
            'screen' => 'Les rubriques du site (catégories de l’ancien WordPress) : libellés, menus et ordre des fiches dans les mosaïques.',
            'libellés' => 'Nom de la rubrique en français et en anglais, tel qu’affiché dans les menus et les mosaïques.',
            'menu' => 'Place de la rubrique dans les méga-menus.',
            'ordre des fiches' => 'L’ordre d’affichage dans la mosaïque : glissez-déposez les fiches. Sans ordre manuel, les plus récentes viennent en premier.',
            'sous-rubriques' => 'Les rubriques contenues dans celle-ci (onglets de la mosaïque).',
            'texte d’introduction de la mosaïque' => 'Le court texte affiché en tête de la mosaïque.',
            'adresse' => 'L’adresse de la rubrique. Les anciennes adresses WordPress y sont redirigées.',
        ],
        'redirections' => [
            'screen' => 'Les anciennes adresses redirigées vers les nouvelles (301), et l’onglet « Adresses introuvables » : les adresses demandées par des visiteurs qui n’existent pas, à rediriger en un clic.',
            'nouvelle redirection' => 'Ancienne adresse (commençant par /) et nouvelle adresse : les visiteurs et Google sont envoyés vers la nouvelle.',
        ],
        'attente' => ['screen' => 'Une page d’attente remplace le site pendant une opération (logo, texte, compte à rebours facultatif). Les membres connectés du back-office voient toujours le site ; « Aperçu » montre la page d’attente.'],
        'interactif' => [
            'screen' => 'Les outils interactifs du site : quiz, frise, maillots, carte (épopées, lieux), partenaires, page « Faire un don ». Chaque outil est une liste d’éléments à compléter, réordonner ou traduire.',
            'textes' => 'Les textes d’introduction de l’outil, en français et en anglais.',
            'dernières modifications' => 'L’historique des modifications de cet outil (qui, quand).',
            'onze de légende' => 'Le vote du public pour le Onze du centenaire : candidats et date de révélation du résultat.',
            'album du centenaire' => 'Les cartes à collectionner : choisissez les joueurs dans leur fiche (onglet Identité).',
            'carte des stades' => 'Les stades viennent des fiches match ; leur position se corrige dans Saisons, adversaires, lieux.',
            '100 moments' => 'La série hebdomadaire du centenaire (menu Éditorial › 100 moments).',
        ],
        'onze' => [
            'screen' => 'Le vote « Onze de légende du centenaire » et la sélection des cartes de l’album.',
            'le onze du public' => 'Le résultat des votes à ce jour, poste par poste.',
            'date de dévoilement' => 'Date à laquelle le Onze du public est révélé sur le site.',
        ],
        'contributions' => [
            'screen' => 'Les propositions des visiteurs (corrections, photos, documents). Traitez-les : demander une précision, publier (les fichiers vont dans la médiathèque ou une fiche) ou refuser.',
            'décision' => 'Publier la contribution, demander une information à son auteur ou la refuser (il est prévenu par e-mail).',
            'fichiers' => 'Les fichiers envoyés : cliquez pour les voir avant de les publier.',
            'publier les fichiers' => 'Verse les fichiers dans la médiathèque, avec le crédit et la cession de droits indiqués par l’auteur, et peut les rattacher à une fiche.',
            'échanges' => 'Les messages échangés avec l’auteur de la contribution.',
        ],
        'messages' => [
            'screen' => 'Les messages du formulaire de contact. Répondez par e-mail, attribuez à un membre de l’équipe, marquez comme traité.',
            'répondre par e-mail' => 'Votre réponse part par e-mail à l’expéditeur et reste dans l’historique du message.',
            'suivi' => 'Statut du message (nouveau, lu, traité) et personne chargée de la réponse.',
        ],
        'newsletter' => [
            'screen' => 'La lettre hebdomadaire « Ce jour-là » : les matchs joués cette semaine-là dans l’histoire, envoyée automatiquement aux abonnés.',
            'abonnés' => 'Les inscrits (confirmés par e-mail). Chacun peut se désinscrire d’un clic.',
            'prochaine lettre' => 'L’aperçu de la prochaine lettre ; « Envoi de test » l’envoie à votre adresse.',
            'dernier envoi' => 'Date et nombre de destinataires du dernier envoi.',
        ],
        'dons' => [
            'screen' => 'Le suivi de la collecte : jauge, dons reçus (carte, PayPal, hors ligne), dons mensuels, export.',
            'enregistrer un don hors ligne' => 'Pour un chèque ou des espèces : il compte dans la jauge et peut figurer sur le mur des donateurs.',
            'mur des donateurs' => 'Les donateurs qui ont accepté d’afficher leur nom sur le site.',
            'paiements' => 'Les paiements reçus pour ce don (un par mois pour un don mensuel).',
            'reçus fiscaux' => 'Fonction prête mais désactivée : à activer dans les réglages seulement si l’association y a droit.',
            'note interne' => 'Une note visible seulement dans le back-office.',
            'don mensuel' => 'État de l’abonnement (actif, arrêté) chez le prestataire de paiement.',
        ],
        'traductions' => ['screen' => 'La version anglaise : libellés de l’interface (boutons, menus) et traduction des fiches par l’IA, à relire. Le français fait toujours foi.'],
        'assistant' => ['screen' => 'L’assistant IA du site (bulle en bas à droite) : questions posées par les visiteurs, avis, export et réindexation après de grosses modifications.'],
        'utilisateurs' => [
            'screen' => 'Les membres de l’équipe : invitations, niveau d’accès, désactivation. Réservé aux administrateurs.',
            'inviter une personne' => 'La personne reçoit un lien pour choisir son mot de passe. Le lien peut aussi être copié et envoyé autrement.',
            'deux niveaux d’accès' => 'Administrateur : tout. Utilisateur : tout sauf les utilisateurs, les réglages, la suppression définitive et la restauration de versions.',
            'équipe du back-office' => 'Les comptes existants, leur niveau et leur dernière connexion.',
        ],
        'reglages' => ['screen' => 'Les réglages du site (l’équivalent d’un fichier de configuration) : identité, e-mail, assistant IA, traduction, dons, carte, mentions légales, cookies, sauvegardes. Les clés secrètes sont chiffrées et ne sont jamais réaffichées.'],
        'sauvegardes' => [
            'screen' => 'Une sauvegarde complète est faite chaque jour (données, réglages, comptes, versions…). Téléchargez-en régulièrement une pour la garder hors du serveur.',
            'restaurer' => 'Pour revenir à une sauvegarde : décompressez l’archive et remplacez les dossiers data/ et storage/ sur le serveur (gestionnaire de fichiers de l’hébergeur). Une fiche seule se restaure plus simplement depuis son onglet Historique.',
        ],
        'taches' => ['screen' => 'Les tâches automatiques (toutes les 5 minutes) : publication programmée, statistiques, traductions, newsletter, carte, sauvegarde… et leur dernier passage. « Lancer » en exécute une tout de suite.'],
        'aide' => ['screen' => 'Le guide d’utilisation complet du back-office, la recherche dans l’aide et les PDF à télécharger.'],
    ];

    /** Bulles de l'écran courant : clés en minuscules → texte (HTML simple). */
    public static function forNav(string $nav): array
    {
        $screen = self::SCREENS[$nav] ?? [];
        // L'éditeur d'un objet ou d'un moment profite aussi des bulles des fiches « articles ».
        if (in_array($nav, ['objets', 'moments'], true)) {
            $screen += array_diff_key(self::SCREENS['articles'], ['screen' => 1]);
        }
        return $screen + self::COMMON;
    }
}
