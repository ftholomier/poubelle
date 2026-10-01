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
  3. README.md — ce que fait le socle, section par section
  4. docs/REUTILISATION.md — ce qui est générique, ce qui est métier, dans
     quel ordre transposer. Les sections 4 (les six règles), 6 (ce qu'il faut
     remplacer) et 9 (les onze pièges déjà rencontrés) sont les plus utiles.
  5. docs/HANDOFF-CLAUDE-CODE.md — la direction visuelle d'origine

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

DEUX DÉCISIONS QUE JE DOIS PRENDRE AVANT QUE TU CODES — pose-les-moi :
  1. Le Signal vend en ligne (panier, commande, compte client WooCommerce).
     Le socle iOiO n'a pas de boutique : il récolte des demandes et l'équipe
     rappelle. On garde la vente en ligne, ou on bascule sur le modèle iOiO ?
  2. Un seul lieu aujourd'hui. D'autres adresses sont-elles prévues ? Si oui
     je garde la mécanique multi-lieux, sinon je la simplifie.

ET POSE-MOI AUSSI :
  - faut-il le bilingue FR/EN comme sur le iOiO
  - garde-t-on l'assistant IA, les avis Google, la pop-up de sortie
  - la version audio de la présentation : on la reprend ?
  - as-tu les photos en haute définition ? Celles du site font 1024 px, c'est
    trop peu pour le socle. Ne commence pas l'intégration sans les originaux.
  - l'email de contact, absent du site actuel
  - hébergement et version de PHP

Attends mes réponses avant la première ligne de code.

ENSUITE, travaille dans cet ordre : charte → modèle métier → routes et
redirections → schéma d'édition → vues → contenu → assistant → clés et tests.

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

Commence par lire les cinq documents, vérifier le relevé en ligne, puis
pose-moi tes questions.
```

---

## Ce qu'il faudra fournir en plus

- **les photos originales** — celles du site sont redimensionnées à 1024 px ;
  les noms de fichiers (`20250408_103444.jpg`) montrent que ce sont des photos
  de téléphone d'avril 2025, donc les originaux font probablement 3000 px ;
- le **logo vectoriel** du Signal ;
- une **adresse email** de contact, absente du site actuel ;
- la décision sur la vente en ligne, qui conditionne toute l'architecture.
