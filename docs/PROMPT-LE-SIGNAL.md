# Le prompt à coller

Décompressez le zip, ouvrez Claude Code (ou l'agent de votre choix) **dans le
dossier obtenu**, puis collez le texte ci-dessous tel quel.

Si vous utilisez une interface sans accès au dossier, joignez d'abord le zip à
la conversation, puis collez le même texte.

---

```
Tu as dans ce dossier le code source complet d'un site que j'ai fait développer
pour Le iOiO, un coworking à Besançon : site vitrine + catalogue + back-office,
en PHP natif, sans base de données, sans framework et sans aucune dépendance.

Ta mission : refaire le site https://www.le-signal.com sur exactement ce socle
— même technologie, même ergonomie, même niveau de finition — avec le contenu,
le métier et l'identité du Signal.

AVANT DE COMMENCER, lis dans cet ordre :
  1. README.md — ce que fait le site, section par section
  2. docs/REUTILISATION.md — ce qui est générique, ce qui est métier, et dans
     quel ordre transposer. Les sections 4 (les six règles), 6 (ce qui est à
     remplacer) et 9 (les pièges déjà rencontrés) sont les plus importantes.
  3. docs/HANDOFF-CLAUDE-CODE.md — la direction visuelle d'origine

PUIS analyse le-signal.com toi-même : parcours toutes les pages, relève
l'activité réelle, les prestations, les références, l'équipe, les coordonnées,
le ton éditorial, les visuels et les appels à l'action. Ne devine rien, ne
réinvente pas le contenu : c'est le site existant qui fait foi. Si des pages
sont inaccessibles, dis-le-moi au lieu de combler.

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
  Router (la mécanique, pas la table de routes), l'assistant (Indexer, Gemini,
  Docs), tout le back-office et tout l'anti-spam.

CE QUE TU REMPLACES :
  - app/Offices.php → l'objet métier du Signal (garde la mécanique : filtres,
    statuts, décoration, compteurs — change le vocabulaire et les champs)
  - app/Ai/Facts.php → les chiffres réels du Signal pour l'assistant
  - Router::ROUTES → les URL du Signal, en français et en anglais
  - Admin::PAGES → le schéma des champs éditables
  - content/* → tout le contenu
  - les variables CSS en tête de public/assets/css/site.css → la charte du
    Signal, reprise de leur identité existante
  - app/views/pages/* → les pages du Signal
  - public/media/* → leurs visuels

AVANT DE DÉVELOPPER, POSE-MOI DES QUESTIONS. Au minimum :
  - l'objet métier central du Signal et ses champs (ce qui remplace « bureau »)
  - les pages à conserver, à fusionner, à supprimer
  - faut-il garder le bilingue FR/EN
  - faut-il garder l'assistant IA, les avis, la pop-up de sortie
  - ce qui doit absolument être repris de l'identité actuelle, et ce que je
    t'autorise à moderniser
  - où le site sera hébergé et avec quelle version de PHP

Attends mes réponses avant la première ligne de code.

ENSUITE, travaille dans cet ordre : charte → modèle métier → routes → schéma
d'édition → vues → contenu → assistant → clés et tests.

LE NIVEAU DE FINITION ATTENDU — c'est celui du projet que tu as sous les yeux :
  - vérifie chaque écran dans un vrai navigateur, à 1440, 1100, 820 et 390 px
  - zéro erreur console, zéro débordement horizontal
  - teste les formulaires pour de vrai, y compris un envoi depuis curl qui doit
    être bloqué
  - teste le site sans aucune clé API, puis avec
  - lis docs/REUTILISATION.md section 9 avant de toucher aux images, aux
    grilles CSS, aux filtres et aux emails : les pièges y sont déjà listés
  - commits en français, un par lot cohérent, expliquant la cause et pas
    seulement le symptôme

Commence par lire les trois documents, analyser le-signal.com, puis pose-moi
tes questions.
```

---

## Si vous voulez aller plus vite

Donnez-lui en plus, dès le départ :

- un export du contenu actuel du Signal (textes, photos en bonne définition) ;
- la charte graphique si elle existe (couleurs exactes, polices, logo vectoriel) ;
- la liste des pages à conserver ;
- les accès d'hébergement, pour qu'il puisse déployer et vérifier en ligne.

Les photos sont le point le plus souvent sous-estimé : sur ce projet, des
visuels récupérés en basse définition ont donné une impression de site bâclé
pendant plusieurs itérations, alors que le code était correct. Fournissez les
originaux.
