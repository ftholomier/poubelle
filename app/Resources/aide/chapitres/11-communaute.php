<?php
return [
    'slug' => 'communaute',
    'title' => 'Contributions, messages, newsletter, notifications, dons',
    'summary' => 'Traiter ce que proposent les visiteurs, leur répondre, envoyer la lettre « Ce jour-là » et les notifications de l’appli, suivre les dons.',
    'sections' => [
        ['id' => 'contributions', 'title' => 'Les contributions des visiteurs', 'html' => <<<'HTML'
<p>Sur le site, « Contribuer au musée » permet de proposer une correction, des photos ou des documents (avec cession de droits). Elles arrivent dans Communauté › <b>Contributions</b>, onglet « À traiter ».</p>
[[img:contribution.webp|Une contribution : fichiers, publication, décision, échanges]]
<ol>
<li>Lisez la proposition et ouvrez les <b>fichiers</b> envoyés.</li>
<li><b>Publier les fichiers</b> : dans la médiathèque, rattachés à une fiche, ou comme objet des réserves (crédit et droits de l’auteur repris).</li>
<li><b>Décision</b> : ✓ Valider, ? Demander une précision (l’auteur reçoit un e-mail), ou Refuser.</li>
<li>Pour une correction, faites-la dans la fiche concernée, puis validez la contribution.</li>
</ol>
<p><b>Un témoignage</b> (souvenir de match) peut être publié sur la fiche du match, dans le bloc <b>« Ils y étaient »</b> : choisissez la fiche du match dans « Fiche corrigée ou enrichie », relisez le <b>texte publié</b> (corrigez les fautes, raccourcissez au besoin) et la <b>signature</b> (prénom et initiale par défaut), laissez « Publier ce souvenir » coché, puis ✓ Valider. Refuser ou supprimer la contribution le retire de la fiche.</p>
[[auto|<p>Sur chaque fiche de match, le bloc « Ils y étaient » compte aussi les visiteurs qui cliquent « J’y étais ! » et invite à raconter son souvenir (formulaire prérempli).</p>]]
HTML],
        ['id' => 'messages', 'title' => 'Les messages du formulaire de contact', 'html' => <<<'HTML'
<p>Communauté › <b>Messages</b> : chaque message indique l’objet choisi par le visiteur (devenir partenaire, confier une archive, signaler une erreur, autre demande). <b>Répondre par e-mail</b> envoie la réponse et la garde dans l’historique ; <b>Suivi</b> : statut (nouveau, lu, traité) et personne chargée de répondre.</p>
[[img:messages.webp|Les messages reçus]]
HTML],
        ['id' => 'newsletter', 'title' => 'La newsletter « Ce jour-là »', 'html' => <<<'HTML'
<p>Une lettre hebdomadaire présente les matchs joués cette semaine-là dans l’histoire. Elle se fabrique toute seule et part automatiquement (jour et heure dans Réglages › Newsletter) aux abonnés confirmés, chacun dans sa langue (FR ou EN).</p>
<h3>Ce que contient la lettre</h3>
<ul>
<li><b>Le bandeau animé</b> : le match de la page de connexion (« BUT ! pour le FCSM »), en GIF. Outlook sur ordinateur n’en montre que la première image, fixe.</li>
<li><b>Les matchs de la semaine</b>, 7 au plus, en deux colonnes (une seule sur téléphone) : photo de la fiche (recadrée et nette), « Il y a X ans », date, compétition, score et bouton « Lire la fiche ». Un match sans photo reçoit une image bleue avec son année, au même format.</li>
<li><b>Le pavé « Boutique »</b>, quand la boutique est ouverte : un pavé bleu nuit glissé à une place tirée au hasard dans la grille, qui invite à découvrir les objets à personnaliser.</li>
<li><b>Le compte à rebours du centenaire</b> et le bouton « Faire un don ».</li>
<li><b>En bas</b>, centré : la mention d’inscription et le lien « Se désinscrire », propre à chaque abonné.</li>
</ul>
<h3>Le tableau de bord</h3>
<p>Communauté › <b>Newsletter</b> commence par un tableau de bord :</p>
<ul>
<li><b>Indicateurs</b> : taux d’ouverture et taux de clic moyens, désinscriptions (4 dernières lettres) et solde des abonnés sur 12 semaines.</li>
<li><b>Envoi en cours</b> (pendant un envoi seulement) : barre de progression, e-mails envoyés, restants et en échec, fin estimée. La page se met à jour toute seule toutes les 20 secondes.</li>
<li><b>Dernière lettre</b> : envoyés, ouvertures, clics, désinscriptions (par le lien de la lettre, le bouton de la messagerie ou l’équipe) et <b>liens les plus cliqués</b>.</li>
<li><b>Abonnés sur 12 semaines</b> : arrivées (bleu) et départs (rouge), semaine par semaine.</li>
<li><b>Historique des lettres</b> : une ligne par lettre.</li>
</ul>
<p><b>À savoir :</b> les ouvertures sont approximatives : certaines messageries chargent les images d’office (Apple Mail), d’autres les bloquent ; un clic compte aussi comme ouverture. Les abonnés n’y figurent que sous une empreinte anonyme, jamais par leur adresse ; ces mesures sont effacées après 2 ans (voir la page Confidentialité).</p>
<h3>Envoyer</h3>
<ul>
<li><b>M’envoyer un test</b> : la lettre de la semaine arrive dans votre boîte (sans lien de désinscription, vous n’êtes pas abonné).</li>
<li><b>Envoyer maintenant</b> : la lettre part tout de suite à tous les abonnés ; l’envoi automatique de la semaine est alors considéré comme fait.</li>
<li><b>Rythme</b> : par lots de 10 à 30 e-mails, avec des pauses de 3 à 40 secondes tirées au hasard, 300 e-mails par heure au plus, à chaque passage de la tâche planifiée (toutes les 5 minutes). Une adresse ne reçoit jamais la lettre deux fois.</li>
</ul>
<h3>Abonnés et désinscriptions</h3>
<ul>
<li><b>Inscription par le site</b> : double validation, la personne confirme par le lien reçu.</li>
<li><b>Ajouter un abonné</b> : pour quelqu’un qui vous l’a demandé, tapez son e-mail, choisissez la langue et cliquez « Abonner » ; il est abonné tout de suite, sans e-mail de confirmation. N’ajoutez que des personnes qui l’ont vraiment demandé.</li>
<li><b>Désinscription</b> : par le lien de chaque lettre, par le bouton « Se désabonner » de la messagerie (Gmail, Outlook, Apple Mail…, en un clic) ou par la croix ✕ de la liste. L’adresse est alors effacée. Ces liens marchent même quand le site est en page d’attente.</li>
</ul>
<p><b>À savoir :</b> pour que la lettre n’arrive pas en indésirables : réglages SPF, DKIM et DMARC du domaine chez l’hébergeur, et envoi par un compte SMTP (Réglages › E-mail).</p>
[[img:newsletter.webp|La newsletter : tableau de bord, prochaine lettre et abonnés]]
HTML],
        ['id' => 'appli', 'title' => 'L’appli du musée (installation, hors connexion)', 'html' => <<<'HTML'
<p>Le musée s’installe comme une application, sans passer par un store : la page publique <b>« L’appli du musée »</b> (lien en pied de page, adresse /appli/) explique comment faire selon l’appareil. Sur Android et sur ordinateur (Chrome, Edge), un bouton <b>« Installer l’appli »</b> ; sur iPhone et iPad, la marche à suivre dans Safari (Partager › Sur l’écran d’accueil).</p>
<ul>
<li><b>L’icône au blason</b> rejoint l’écran d’accueil ; le musée s’ouvre en plein écran. Un appui long sur l’icône propose des raccourcis : Rétro-Direct, les 100 moments, le quiz, la recherche.</li>
<li><b>Plus rapide</b> : styles, polices et photos déjà vus restent sur le téléphone.</li>
<li><b>Hors connexion</b> : les pages déjà consultées restent lisibles sans réseau ; une page jamais vue affiche « Pas de réseau pour le moment » avec la liste des pages lisibles.</li>
<li>Jamais gardés sur le téléphone : le back-office, la boutique, les dons, les formulaires.</li>
<li><b>Le bandeau « Installer l’appli »</b> : sur téléphone, à partir de la deuxième page vue, un bandeau discret en bas de l’écran invite à installer (bouton « Installer » sur Android ; sur iPhone, les deux gestes à faire). « Plus tard » le fait disparaître pendant 30 jours. Jamais dans l’appli déjà installée, ni sur ordinateur, ni dans la boutique, les dons et les formulaires. Réglages › Application du musée pour le retirer.</li>
</ul>
[[img:appli.webp|La page « L’appli du musée » : la marche à suivre selon l’appareil]]
[[astuce|<p>En cas de souci, Réglages › <b>Application du musée</b> : décocher « Application installable » retire l’appli des téléphones à leur prochaine visite (copies effacées). Le site de l’association n’est pas une application.</p>]]
HTML, 'admin' => true],
        ['id' => 'notifications', 'title' => 'Les notifications de l’appli', 'admin' => true, 'html' => <<<'HTML'
<p>Sur la page « L’appli du musée », les visiteurs choisissent leurs sujets puis touchent <b>« Activer les notifications »</b> : Rétro-Direct, les 100 moments, les nouvelles du musée, le kit souvenirs (cochés d’office) et « Ce jour-là » (au choix). Sur iPhone, il faut d’abord installer l’appli. Un bouton « M’envoyer un essai » vérifie que tout marche.</p>
[[img:appli-notifications.webp|Les notifications côté visiteur : sujets au choix, activation, essai]]
<p><b>Envois automatiques</b>, une seule fois chacun, jamais entre 21 h 30 et 8 h (sauf un Rétro-Direct programmé le soir) :</p>
<ul>
<li><b>Rétro-Direct</b> : 30 minutes avant le coup d’envoi d’un direct programmé (Interactif › Rétro-Direct).</li>
<li><b>100 moments</b> : à la parution de chaque moment (sa date dans le calendrier des moments).</li>
<li><b>Kit souvenirs</b> : la première semaine du mois, à 10 h, quand le match du mois est choisi.</li>
<li><b>Ce jour-là</b> : chaque matin à l’heure réglée (9 h par défaut), s’il y a un match ce jour-là dans l’histoire.</li>
</ul>
<p>Communauté › <b>Notifications</b> (administrateurs) : le nombre d’appareils abonnés et par sujet, les envois automatiques prêts à partir, l’<b>envoi d’une notification de l’équipe</b> (titre, texte, page ouverte au clic, sujet, version anglaise facultative, aperçu en direct), et l’historique : reçues, ouvertures, abonnements disparus.</p>
[[img:notifications.webp|Communauté › Notifications : abonnés par sujet, envoi avec aperçu, historique]]
<ol>
<li>Écrivez un titre court (60 caractères) et un texte d’une ou deux phrases (180).</li>
<li>Choisissez la page qui s’ouvre au clic : une adresse du musée qui commence par « / ».</li>
<li>Choisissez le sujet : « Les nouvelles du musée » pour une annonce ; seuls les abonnés à ce sujet la reçoivent.</li>
<li>« Envoyer » : elle part tout de suite (par lots de 400, la suite au passage suivant de la tâche planifiée pour les très grandes listes).</li>
</ol>
<p><b>L’annonce de l’ouverture</b> : tant que le musée est fermé, sa page d’attente propose <b>« Prévenez-moi de l’ouverture »</b> (abonnement aux nouvelles du musée, sans nom ni e-mail). Communauté › Notifications compte les appareils qui attendent et propose <b>« Préparer l’annonce de l’ouverture »</b> : le message est prêt, en français et en anglais. Le jour J, ouvrez d’abord le musée (décochez la page d’attente), puis relisez et envoyez : l’annonce ne part qu’une fois.</p>

<h3>Carnets du supporter</h3>
<p>Sur le site, chaque supporter peut tenir son <b>carnet</b> : il touche « J’y étais ! » sur la fiche d’un match, ou coche ses matchs saison par saison sur la page <b>Mon carnet du supporter</b> (menu Interactif › Participer). Il obtient son bilan (victoires, buts, joueurs vus), des badges, son « porte-bonheur » et sa <b>carte de supporter en PDF</b> (format carte postale, deux designs au choix : charte du musée ou billet de match, avec son pseudo), et peut ouvrir une page publique sous pseudo, à une adresse courte : <code>musee.fcsochauxretro.com/son-pseudo/</code> (bouton « Voir ma page publique »). Le pseudo est unique et ne peut jamais prendre l’adresse d’une page du musée. Pas de mot de passe : son e-mail (obligatoire) reçoit le lien qui ouvre le carnet sur n’importe quel appareil. S’il le demande, le musée lui rappelle ses matchs <b>jour pour jour</b> (« Il y a 30 ans jour pour jour, vous étiez au stade »), par e-mail et/ou par notification, chaque matin à partir de 9 h (tâche planifiée « carnets »). S’il indique sa <b>date d’anniversaire</b> (jour et mois, jamais l’année), il reçoit ce jour-là un « Joyeux anniversaire » avec son plus beau souvenir au stade.</p>
<p>Communauté › <b>Carnets du supporter</b> : nombre de carnets, matchs cochés, pages publiques et les <b>matchs les plus vécus</b> (idées pour le kit souvenirs ou le Rétro-Direct). Les e-mails ne sont jamais affichés ; chacun supprime son carnet lui-même. Dans « Mon carnet et mes données », le supporter voit aussi sur combien d’autres appareils (ou liens envoyés) son carnet est ouvert, et peut <b>se déconnecter des autres appareils</b> : téléphone perdu, ou carnet ouvert par quelqu’un d’autre avec son adresse.</p>
[[img:carnet.webp|Le carnet d’un supporter : ses matchs vus, son bilan, son porte-bonheur, ses adversaires et ses buteurs]]
[[img:carnet-saisons.webp|La saisie par saison : cocher d’un coup les matchs vus au stade]]
[[img:carnet-badges.webp|Les joueurs les plus vus, les décennies et les badges du carnet]]
[[img:carnets.webp|Communauté › Carnets du supporter : nombre de carnets et matchs les plus vécus]]
[[attention|<p>Une notification envoyée ne peut pas être rattrapée. Avant une annonce importante, abonnez votre propre téléphone et faites un essai. Restez rare : au-delà d’une ou deux par semaine, les abonnés se désabonnent.</p>]]
[[auto|<p>Les messages sont chiffrés pour le seul navigateur abonné (norme Web Push) : Google, Apple, Mozilla ou Microsoft les acheminent sans pouvoir les lire. Le musée ne garde ni nom, ni e-mail, ni adresse IP : seulement l’adresse technique fournie par le navigateur, les sujets et la langue. Un abonnement disparu (appli supprimée) est effacé au premier envoi.</p>]]
HTML],
        ['id' => 'dons', 'title' => 'Le suivi des dons', 'html' => <<<'HTML'
<p>Communauté › <b>Dons</b> : jauge de la collecte, liste des dons (carte bancaire via Stripe, PayPal, hors ligne), dons mensuels, export CSV pour la comptabilité.</p>
<ul>
<li><b>Enregistrer un don hors ligne</b> (chèque, virement, espèces ; administrateurs) : il compte dans la jauge. Son remboursement éventuel se note aussi par un administrateur.</li>
<li><b>Mur des donateurs</b> : les noms que les donateurs ont accepté d’afficher.</li>
<li>Détail d’un don : paiements reçus, état du don mensuel, note interne.</li>
</ul>
[[img:dons.webp|Le suivi de la collecte]]
[[attention|<p>Les reçus fiscaux sont prêts mais désactivés : un administrateur ne les active (Réglages › Dons) que si l’association y a droit. Ils sont émis et consultés par les administrateurs.</p>]]
HTML],
    ],
];
