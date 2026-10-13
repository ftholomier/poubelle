<?php
return [
    'slug' => 'communaute',
    'title' => 'Contributions, messages, newsletter, notifications, widgets, dons',
    'summary' => 'Traiter ce que proposent les visiteurs, leur répondre, envoyer la lettre « Ce jour-là » et les notifications de l’appli, diffuser le musée chez les partenaires (widgets), suivre les dons.',
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
        ['id' => 'widgets', 'title' => 'Widgets & partenaires : le musée sur d’autres sites', 'admin' => true, 'html' => <<<'HTML'
<p>Communauté › <b>Widgets &amp; partenaires</b> (administrateurs) : un widget est un petit encart du musée qu’un autre site affiche en copiant quelques lignes de code (un <b>script</b> ou une <i>iframe</i>), ou dont il lit les données brutes (API JSON). Ils se mettent à jour seuls. Vingt-deux widgets sont prêts, plus le <b>mode écran</b> (voir plus bas) :</p>
<ul>
<li><b>Ce jour-là</b>, <b>Chiffre du jour</b>, <b>Il y a 5, 10, 20, 30 ans</b>, <b>Photo du jour</b>, <b>Le saviez-vous ?</b> ;</li>
<li><b>Carte de légende</b> : au hasard ou joueur choisi (voir son réglage « Joueurs tirés au hasard » ci-dessous) ;</li>
<li><b>Records</b> : le top 10 des buteurs ou des joueurs les plus utilisés (option « Classement »), avec exactement les chiffres de la page Records (totaux de référence compris) ;</li>
<li><b>Quiz du jour</b> : une question du quiz du site, la même pour tous dans la journée, <b>jouable dans le widget</b> (sans compte ni cookie) : le visiteur choisit, le musée affiche la bonne réponse et l’explication. La bonne réponse n’est jamais dans le code de la page ni dans l’API ;</li>
<li><b>Défi du jour</b> : invitation au défi quotidien (10 questions chronométrées), avec le nombre de joueurs classés et le meilleur score du jour (jamais de pseudo) ; on joue sur le musée, où le défi garde la partie et les classements ;</li>
<li><b>Dernier match fiché</b> : score, date, compétition et début du récit ;</li>
<li><b>Saison en un coup d’œil</b> : saison choisie (« 1987-1988 » ; vide : la saison en cours si un match y est fiché, sinon la dernière) : classement, victoires, nuls, défaites, meilleurs buteurs, entraîneur et titres ;</li>
<li><b>Palmarès</b> : les titres du club, en liste ou en bandeau (option « Affichage ») ;</li>
<li><b>Frise express</b> : les dates de la frise qui défilent (pause au survol) ;</li>
<li><b>Bandeau « En direct du musée »</b> : les mêmes messages que le bandeau du site (Éditorial › Accueil &amp; bandeau), qui défilent sur une ligne de hauteur fixe, à mettre en haut ou en bas d’une page ;</li>
<li><b>Histoire du duel</b> : le bilan contre un adversaire (option « Adversaire » : l’adresse de son face-à-face, « nantes » ; vide : l’adversaire du dernier match fiché) : victoires, nuls, défaites, buts, plus large victoire, meilleur buteur du duel, dernier match. Mêmes chiffres que la page face-à-face ;</li>
<li><b>Carte des stades</b> : les stades où le club a joué, sur une carte dessinée par le musée (France ou Europe), et les plus fréquentés ;</li>
<li><b>Maillot de l’époque</b> : un maillot de la page Maillots, avec son histoire (une époque choisie, ou un maillot différent chaque jour) ;</li>
<li><b>Compte à rebours</b> : jusqu’à une date choisie (avec son titre), sinon jusqu’au centenaire quand il approche, sinon jusqu’au prochain anniversaire de la fondation ;</li>
<li><b>Soutenir le musée</b> : faire un don, la boutique, contribuer — seulement ce qui est ouvert (dons activés avec un moyen de paiement, boutique ouverte, boutons du site affichés) ;</li>
<li><b>La boutique du musée</b> : quelques objets en vente avec leur aperçu, leur prix (« à partir de » quand il dépend de la taille) et le lien vers leur fiche ; un objet choisi (option « Objet » : la fin de l’adresse de sa fiche, /boutique/<i>objet</i>/) ou trois objets différents chaque jour, en carrousel ou en grille (option « Affichage »). Rien ne s’affiche tant que la boutique est fermée au public ou sans objet en vente ;</li>
<li><b>Recherche du musée</b> : un petit champ qui ouvre la recherche du musée dans un nouvel onglet ;</li>
<li><b>Rétro-Direct</b> : le match rejoué du jour (ou en direct), sinon le prochain au programme, sinon un classique à revivre ; jamais le score final.</li>
</ul>
<h3>Ce qui ne change jamais</h3>
<ul>
<li>Toujours aux couleurs et avec les polices du musée ; le partenaire ne choisit que le <b>thème</b> (clair ou sombre), la <b>langue</b> et le <b>format</b> (standard ou compact).</li>
<li>Toujours le <b>logo du musée</b>, la mention <b>« Musée indépendant, non officiel »</b> et l’<b>adresse du musée</b> cliquable : impossible à retirer.</li>
<li>Seules sortent les photos diffusables : photos livrées sous licence libre (avec auteur et licence) et photos créditées des murs de photos ; jamais une photo « DR », d’agence ou créditée au club lui-même. Une carte de légende sans photo diffusable affiche les initiales du joueur.</li>
<li>Aucune donnée personnelle, aucun cookie chez le visiteur ; « Le saviez-vous ? » ne montre que des anecdotes déjà validées (banque de textes de la boutique, frise publiée), jamais un texte inventé.</li>
</ul>
<h3>Régler l’accès de chaque widget</h3>
<p>Onglet <b>Catalogue &amp; configurateur</b> : chaque widget a son aperçu en direct et son <b>accès</b>, modifiable à tout moment :</p>
<ul>
<li><b>Libre</b> : tout site peut l’afficher, sans clé ; il figure sur la page publique <a href="/widgets/" target="_blank" rel="noopener">/widgets/</a> (« Intégrez le musée sur votre site », lien en pied de page).</li>
<li><b>Réservé</b> : seulement avec la clé d’un partenaire actif.</li>
<li><b>Payant</b> : seulement avec la clé d’un partenaire dont l’offre payante est en cours.</li>
<li><b>Désactivé</b> : servi à personne.</li>
</ul>
<p><b>Actif même site fermé</b> : quand le musée est en page d’attente (ou protégé par mot de passe), les widgets ne sont plus servis, sauf ceux qui ont cette case cochée.</p>
<p><b>Carte de légende › Joueurs tirés au hasard</b> (réglage propre à ce widget) : « Légendes cochées ; si aucune, les plus capés » (par défaut) tire parmi les fiches cochées « Légende » et, tant qu’aucune ne l’est, parmi les 24 joueurs les plus capés d’après les totaux de référence du club ; « Seulement les fiches cochées « Légende » » n’affiche rien tant qu’aucune fiche n’est cochée. C’est une proposition : choisissez ce qui convient au musée.</p>
<h3>Le configurateur</h3>
<p>Choisissez le widget, le thème, la langue, le format, ses options (date, joueur, écart d’années, classement, saison, affichage…) et, pour un widget réservé ou payant, le partenaire : l’aperçu se met à jour, et trois codes sont prêts à copier et à envoyer au partenaire :</p>
<ul>
<li><b>Code script</b> (recommandé) : une ligne <code>&lt;div data-musee-widget="…"&gt;</code> par widget, à l’endroit voulu, et une seule fois par page la ligne <code>&lt;script src="…/embed.js" async&gt;</code>. Exemple avec deux widgets :
<pre>&lt;div data-musee-widget="ce-jour-la" data-theme="sombre"&gt;&lt;/div&gt;
&lt;div data-musee-widget="records" data-cat="matchs" data-key="mk_…"&gt;&lt;/div&gt;
&lt;script src="https://adresse-du-musee/embed.js" async&gt;&lt;/script&gt;</pre>
Le script remplace chaque <code>div</code> par une iframe isolée (le widget ne voit pas la page du partenaire, la page du partenaire ne déforme pas le widget, aucun cookie), chargée quand elle arrive à l’écran et toujours à la bonne hauteur. Les options se mettent en attributs <code>data-…</code> (<code>data-theme</code>, <code>data-lang</code>, <code>data-format</code>, <code>data-key</code>, <code>data-cat</code>, <code>data-saison</code>, <code>data-affichage</code>…).</li>
<li><b>Code iframe</b> : fonctionne partout, même sur les sites qui interdisent les scripts. Le petit code « Hauteur automatique » est alors facultatif : il ajuste la hauteur de l’iframe au contenu.</li>
<li><b>Adresse de l’API</b> : les données brutes (JSON), pour un partenaire qui affiche lui-même les données (avec le nom du musée, la mention « non officiel » et le lien).</li>
</ul>
<h3>Les partenaires</h3>
<p>Onglet <b>Partenaires</b> › « Ajouter un partenaire » : nom, contact, <b>domaines autorisés</b> (la clé ne marche que sur ces sites ; vide : partout, y compris depuis un serveur), statut (<b>actif</b> ou <b>suspendu</b>), <b>offre</b> (gratuite ou payante, avec sa date d’échéance ; pas de paiement en ligne pour l’instant, l’offre se note ici). La <b>clé</b> est créée au hasard et affichée une fois en haut de l’écran : transmettez-la au partenaire. <b>Nouvelle clé</b> en crée une autre (l’ancienne cesse aussitôt de fonctionner, par exemple si elle a circulé).</p>
<h3>Statistiques</h3>
<p>Onglet <b>Statistiques</b> : affichages des widgets, appels de l’API et <b>clics vers le musée</b>, par widget et par partenaire, sur 7, 30, 90 ou 365 jours. Comptés par jour, sans adresse IP ni cookie ; les aperçus du back-office et de la page de démonstration ne comptent pas.</p>
[[attention|<p>Les widgets sont gardés en cache (une heure au plus, et jamais au-delà de minuit). Après une correction urgente (une photo à retirer), cliquez <b>Vider le cache des widgets</b> en bas du catalogue.</p>]]
<h3>Les écrans : le musée sur l’écran du stade, à la buvette, sur une borne</h3>
<p>Onglet <b>Écrans</b> : le <b>mode écran</b> affiche le musée en plein écran, en très gros caractères lisibles de loin, sans souris ni défilement et sans son. Les slides s’enchaînent seules avec un fondu et une barre de progression ; le logo du musée, la mention « non officiel » et l’adresse du musée restent toujours en bas ; l’horloge est facultative. Chaque slide reprend les données d’un widget (histoire du duel, ce jour-là, légende, chiffre, le saviez-vous, quiz, photos d’archives, frise, records, palmarès, il y a…, dernier match, saison, compte à rebours, maillot, Rétro-Direct, carte des stades, soutenir et boutique avec QR codes), mises en page pour l’écran.</p>
<ul>
<li><b>Trois playlists prêtes à l’emploi</b> : <b>Avant-match</b> (le quart d’heure avant le coup d’envoi, en boucle : histoire du duel contre l’adversaire du jour, ce jour-là, une légende, un chiffre, une anecdote, des quiz…), <b>Mi-temps</b> (un peu plus de 2 minutes) et <b>Buvette</b> (en continu : photos, frise, records, palmarès, maillots, carte des stades…). Elles ne se modifient pas : « Dupliquer » en fait une copie à personnaliser.</li>
<li><b>Vos playlists</b> : « + Nouvelle playlist » : nom, format (paysage 16:9 ou vertical 9:16 pour un écran posé debout), langue, adversaire du jour, rechargement (en minutes), horloge, puis les slides : type, durée en secondes et, selon le type, l’adversaire, le classement, la variante (une autre photo, une autre partie de la frise) ou la saison. Réordonnez-les par glisser-déposer (poignée quatre flèches) ou avec ↑ / ↓. Une slide sans rien à montrer ce jour-là est simplement sautée.</li>
<li><b>Le quiz à l’écran</b> : la question s’affiche avec un compte à rebours, puis la bonne réponse en vert et l’explication ; un QR code mène au quiz du musée. Ce n’est jamais la question du « quiz du jour » des widgets, dont la réponse reste secrète.</li>
<li><b>Accès</b> : le mode écran est un widget comme les autres (« Playlist écran » dans le catalogue), <b>réservé</b> par défaut : l’adresse donnée à la régie contient la clé d’un partenaire. Une clé limitée à des domaines marche aussi pour l’écran ; préférez une clé propre à la régie et régénérez-la si l’adresse circule. Vous, connecté, voyez l’aperçu quel que soit l’accès.</li>
<li><b>Lien à donner à la régie</b> : choisissez la playlist, le partenaire, le format, la langue, l’adversaire du jour et l’horloge, puis « Copier l’adresse ». La régie l’ouvre dans un navigateur en plein écran (F11 ou mode kiosque). La page se recharge seule (30 minutes par défaut) et, pendant une coupure de réseau, continue de tourner avec la dernière version ; après un redémarrage sans réseau, elle repart de la copie gardée par le navigateur.</li>
<li><b>Régies qui ne lisent pas de page web</b> : « Images PNG (ZIP) » donne une image par slide (1920 × 1080 ou 1080 × 1920, le quiz en deux images : question puis réponse), l’ordre et les durées, et la commande pour en faire une vidéo sur un ordinateur. « Fabriquer la vidéo MP4 » (H.264, sans son) la prépare en tâche de fond : rechargez la page une à deux minutes plus tard pour la télécharger.</li>
</ul>
[[attention|<p>La vidéo MP4 demande le programme <b>ffmpeg</b> sur le serveur. Les hébergements mutualisés (o2switch) ne l’ont généralement pas : l’onglet Écrans l’indique alors en clair et propose le ZIP des images, que la plupart des logiciels d’affichage dynamique savent enchaîner.</p>]]
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
