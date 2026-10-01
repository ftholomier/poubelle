# Le prompt à coller

Décompressez le zip, ouvrez Claude Code (ou l'agent de votre choix) **dans le
dossier obtenu**, puis collez le texte ci-dessous tel quel.

Le relevé du site existant est déjà fait : voir `docs/le-signal-releve.md` et
`docs/le-signal-catalogue.json`. L'IA n'a donc pas à tout redécouvrir.

---

```
Tu as dans ce dossier le code source complet d'un site que j'ai fait développer
pour Le iOiO, un coworking à Besançon : site vitrine + catalogue + back-office,
en PHP natif, sans base de données, sans framework et sans aucune dépendance.

Ta mission : refaire le site https://le-signal.com sur exactement ce socle
— même technologie, même ergonomie, même niveau de finition — avec le contenu,
le métier et l'identité du Signal.

Bonne nouvelle : Le Signal fait le MÊME MÉTIER que le iOiO. Coworking, bureaux
privés et bureaux ouverts, même propriétaire, même numéro de téléphone. Le
modèle de données est réutilisable presque tel quel : un seul lieu au lieu de
deux, 13 bureaux au lieu de 21, les mêmes types et les mêmes statuts.

LIS D'ABORD, DANS CET ORDRE :
  1. docs/le-signal-releve.md — le relevé complet du site existant : identité,
     pages, catalogue, prestations, et les deux décisions à prendre
  2. docs/le-signal-catalogue.json — les 13 bureaux déjà au format
     content/offices.json (prix, surfaces, statuts, photos)
  3. docs/le-signal-medias.json — les 133 médias du site avec leur URL, type,
     dimensions et texte alternatif
  4. docs/le-signal-redirections.md — la table 301/410 complète, déjà écrite
  5. README.md — ce que fait le socle, section par section
  6. docs/REUTILISATION.md — ce qui est générique, ce qui est métier, dans
     quel ordre transposer. Les sections 4 (les six règles), 6 (ce qu'il faut
     remplacer) et 9 (les onze pièges déjà rencontrés) sont les plus utiles.
  7. docs/HANDOFF-CLAUDE-CODE.md — la direction visuelle d'origine

PUIS VÉRIFIE LE RELEVÉ sur le site en ligne : il date du 1er octobre 2026, les
tarifs et les disponibilités ont pu bouger. Attention, c'est l'apex qui
répond : https://le-signal.com, pas www. Si quelque chose diffère, c'est le
site qui fait foi — dis-le-moi, ne corrige pas en silence.

LES RÈGLES À NE PAS ENFREINDRE (elles font tenir l'ensemble) :
  - PHP natif, JS natif, JSON, HTML, CSS. Aucune base de données, aucun
    Composer, aucun npm, aucun CDN, aucune police distante.
  - Front dans /public, tout le reste au-dessus de la racine web.
  - Écriture JSON atomique et versionnée (Store), lecture tolérante aux clés
    absentes (Content) : une clé manquante ne doit jamais produire d'erreur.
  - Chaque service externe a un repli écrit à l'avance : le site doit rester
    complet et vendeur sans aucune clé API.
  - Rien ne part vers un tiers avant consentement.
  - Le site fonctionne sans JavaScript ; le JS n'ajoute que du confort.
  - Tout contenu éditable depuis le back-office, aucun texte en dur dans les
    vues.

CE QUE TU GARDES TEL QUEL (environ 80 % du code) :
  Store, Content, Config, Session, Csrf, Auth, RateLimit, Spam, Api, Mailer,
  Http, Log, Text, Media, Seo, I18n, Consent, Diagnostics, Translator, View,
  la mécanique du Router, l'assistant (Indexer, Gemini, Docs), tout le
  back-office et tout l'anti-spam.

CE QUE TU ADAPTES :
  - app/Offices.php → un seul lieu (« montbeliard ») au lieu de deux ; garde
    les types private/openspace et les statuts tels quels
  - app/Ai/Facts.php → les chiffres du Signal
  - Router::ROUTES → des URL propres (/nos-bureaux, /contact…) ET une table de
    redirections 301 depuis les URL actuelles, qui sont bourrées de mots-clés :
    sans cela le référencement acquis est perdu
  - Admin::PAGES → le schéma des champs éditables
  - content/* → le contenu du Signal
  - les variables CSS en tête de public/assets/css/site.css → la charte du
    Signal (actuellement blanc/gris clair, noir, logo épuré). Propose-moi une
    direction avant de la généraliser.
  - app/views/pages/* et public/media/* → pages et visuels du Signal

DÉCISION DÉJÀ PRISE, NE LA REMETS PAS EN QUESTION : pas de vente en ligne.
On bascule sur le modèle du iOiO. Le visiteur manifeste son intérêt, l'équipe
rappelle. Aucune boutique, aucun paiement, aucune commande, aucun compte
client. Conséquences à traiter, détaillées au § 7 du relevé :
  - /panier/, /commander/ et /mon-compte/ renvoient un 410 Gone — surtout pas
    un 301 vers l'accueil, que Google traite comme un soft 404
  - les URL produit et /categorie-produit/… se redirigent en 301 vers la fiche
    correspondante ou le catalogue filtré
  - « Ajouter au panier » devient « Ce bureau m'intéresse » ; « Réservé » reste
    et correspond au statut rented du socle
  - le formulaire du socle remplace la commande : enregistrement dans
    requests.json, email à l'équipe, accusé au visiteur, passage par l'anti-spam

DEUXIÈME DÉCISION DÉJÀ PRISE : MONO-LIEU. Le Signal n'a qu'une adresse,
95 Faubourg de Besançon à Montbéliard. Retire la mécanique à deux lieux du
iOiO : plus de filtre par lieu dans l'interface, plus de bascule entre deux
cartes sur la page contact, un seul bloc de présentation. Garde une seule
entrée dans Config::SITES plutôt que de supprimer le champ : si une seconde
adresse ouvre un jour, il suffira de rallumer les filtres.

TROISIÈME CONSIGNE, LA PLUS IMPORTANTE : TU REPRENDS TOUT LE CONTENU EXISTANT.
Rien n'est à réécrire, rien n'est à inventer. Le site actuel fait foi.

  - TÉLÉCHARGE les 133 médias listés dans docs/le-signal-medias.json depuis
    le-signal.com, convertis les images en WebP par la photothèque du socle
    (Media::importExisting), et reporte le texte alternatif de chaque image —
    il est dans le manifeste.
  - LES 27 PHOTOS DU CATALOGUE sont déjà rattachées à leur bureau dans
    le-signal-catalogue.json. Respecte ce rattachement : sur le projet iOiO,
    des photos réaffectées au hasard entre les lieux et les types ont coûté
    plusieurs itérations.
  - LE FICHIER AUDIO est à reprendre :
    /wp-content/uploads/2026/02/Votre_bureau_cle_en_main_a_Montbeliard.mp3
    Le socle n'a pas de lecteur. Ajoute-en un en natif : <audio controls
    preload="none">, jamais de lecture automatique, repli en lien de
    téléchargement, fichier dans public/media/, chemin éditable au back-office.
  - LE LOGO : prends lesignal.svg, la version vectorielle. Les favicons sont
    favicon.png (416) et cropped-favicon.png (512).
  - LES TEXTES : reprends ceux des pages relevées au § 3 et § 5 du relevé,
    mot pour mot quand ils sont bons, en corrigeant uniquement les fautes
    manifestes (le titre de page « Location de buraux à Montbéliard » en
    contient une). Garde le ton direct et familier du site.
  - N'IMPORTE PAS les 19 SVG nommés tire-* : ce sont les décorations du thème
    WordPress acheté, pas du contenu du Signal.
  - SIGNALE-MOI tout contenu que tu n'arrives pas à récupérer plutôt que de
    le remplacer par du texte générique.

POSE-MOI CES QUESTIONS AVANT DE CODER :
  - faut-il le bilingue FR/EN comme sur le iOiO
  - garde-t-on l'assistant IA, les avis Google, la pop-up de sortie
  - as-tu les photos en haute définition ? Celles du site font 1024 px, c'est
    trop peu pour le socle. Ne commence pas l'intégration sans les originaux.
  - l'email de contact, absent du site actuel
  - hébergement et version de PHP

Attends mes réponses avant la première ligne de code.

ENSUITE, travaille dans cet ordre : charte → modèle métier → routes et
redirections → schéma d'édition → vues → récupération des médias → contenu →
lecteur audio → assistant → clés et tests.

LE NIVEAU DE FINITION ATTENDU — c'est celui du projet que tu as sous les yeux :
  - vérifie chaque écran dans un vrai navigateur, à 1440, 1100, 820 et 390 px
  - zéro erreur console, zéro débordement horizontal
  - teste les formulaires pour de vrai, y compris un envoi depuis curl qui
    doit être bloqué par l'anti-spam
  - teste le site sans aucune clé API, puis avec
  - lis docs/REUTILISATION.md section 9 avant de toucher aux images, aux
    grilles CSS, aux filtres et aux emails : les pièges y sont déjà listés
  - commits en français, un par lot cohérent, expliquant la cause et pas
    seulement le symptôme

Commence par lire les sept documents, vérifier le relevé en ligne, puis
pose-moi tes questions.
```

---

## Ce qu'il faudra fournir en plus

- **les photos originales** — celles du site sont redimensionnées à 1024 px ;
  les noms de fichiers (`20250408_103444.jpg`) montrent que ce sont des photos
  de téléphone d'avril 2025, donc les originaux font probablement 3000 px ;
- le **logo vectoriel** du Signal ;
- une **adresse email** de contact, absente du site actuel ;
- l'accès à l'hébergement, pour vérifier les redirections en ligne une fois
  le site basculé.
