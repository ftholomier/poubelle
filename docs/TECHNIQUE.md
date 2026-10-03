# Sochaux Rétro — documentation technique

Pour un développeur qui reprend le code. Le guide d'installation est dans
[DEPLOIEMENT.md](DEPLOIEMENT.md), l'utilisation du back-office dans
[GUIDE-BACK-OFFICE.md](GUIDE-BACK-OFFICE.md).

## 1. Principes

- PHP 8.3 sans framework ni dépendance ; aucune base de données : les données sont des
  fichiers JSON, lus et écrits par `App\Core\JsonStore` (écriture atomique : fichier
  temporaire puis `rename()` ; mises à jour sous verrou `flock`).
- Seul `public/` est exposé. `public/index.php` charge `app/bootstrap.php` (constantes de
  chemins, chargement automatique des classes `App\…` depuis `app/`, gestion des erreurs)
  puis confie la requête à `App\Kernel`.
- Gabarits PHP natifs (`templates/`), JavaScript natif (`public/assets/`), aucun outil de
  construction. Les URL des fichiers statiques portent leur date de modification
  (`asset()`), ce qui permet un cache navigateur d'un an.
- Vocabulaire du code en français, comme celui du club (fiche, rubrique, composition…).

## 2. Cheminement d'une requête

`App\Kernel::dispatch()`, dans l'ordre :

1. `/media/{largeur}/{fichier}.webp` : vignettes générées à la volée (§ 6).
2. `/wp-content/uploads/…` : redirection 301 vers la médiathèque (anciens liens d'images).
3. `/admin…` : back-office (`App\Admin\Router`, § 8).
4. Préfixe `/en` : passage en anglais, puis traitement normal de l'adresse sans préfixe.
5. `/api/…` : API JSON du site (`App\Front\Api`) : recherche, assistant, carte, votes,
   dons et leurs webhooks ; jamais bloquée par la page d'attente.
6. Page d'attente (si activée, sauf pour les membres connectés du back-office et les pages
   légales), puis mot de passe d'accès éventuel.
7. Adresse sans barre finale → 301 vers l'adresse avec barre finale.
8. Routes fixes (accueil, explorer, interactif, communauté, dons, pages légales…), déclarées
   dans `Kernel::routes()`.
9. Fiche ou rubrique à cette adresse (`Front\Pages::byPath()` : index des fiches, puis
   rubriques).
10. Ancienne adresse connue (`data/redirects.json`) → 301 ; sinon page 404 et adresse
    notée dans `storage/404.json` (Back-office › Redirections › Adresses introuvables).

Toute réponse HTML reçoit un en-tête Content-Security-Policy (`Kernel::csp()`) : scripts du
site uniquement (le court script d'en-tête du gabarit est signé par `csp_nonce()`),
cadres limités aux lecteurs vidéo reconnus, envoi de formulaires limité au site et aux
pages de paiement.

## 3. Données (`data/`, versionné)

| Fichier | Contenu |
|---|---|
| `fiches/{id}.json` | une fiche par fichier |
| `media.json` | médiathèque : clé = chemin du fichier (`2024/10/photo.jpg`) |
| `categories.json` | rubriques : chemin, parent, libellés, ordre des mosaïques |
| `redirects.json` | anciennes adresses → nouvelles |
| `collections/*.json` | quiz, frise, maillots, épopées, lieux, partenaires, bandeau, référentiels clubs et stades, positions de la carte… (contenus par défaut dans `App\Data\Seeds`) |
| `i18n/en.json` | libellés de l'interface : « texte français » → « English text » |
| `legacy/{id}.json.gz` | archive brute de chaque page WordPress (blocs HTML, tableaux) |

### Fiches

Types : `match`, `personne`, `article`, `page`, `objet` (réserves), `moment` (100 moments).
Statuts : `brouillon`, `relire`, `planifie` (avec `publish_at`), `publie`, `corbeille`.
Identifiant : celui de WordPress pour les fiches reprises, à partir de 1 000 000 pour les
fiches créées dans le back-office.

Champs communs : `id, type, status, publish_at, slug, path, title, categories, a_la_une,
featured_image, date, modified, author, seo{title, description}, intro, sections[{title,
html}], key_figure, gallery[{image, caption, credit}], images, videos[{provider, id|url,
title}], embeds, tables[{title, headers, rows}], legacy, i18n{en{…}}`, plus un bloc propre au
type :

- `match` : date, saison, compétition, journée, équipes, score (`home`, `away`, a.p.,
  t.a.b.), stade, spectateurs, arbitre, buteurs par équipe, temps forts, réactions, brèves,
  `lineup` (composition de Sochaux) et `other_lineups`.
  Ligne de composition : `position` (G, D, M, A, R, E), `name`, `number`, `captain`,
  textes saisis `goals_text`, `sub_text`, `cards_text`, et valeurs qui en sont tirées
  `goals`, `own_goals`, `sub_in`, `sub_out`, `yellow`, `red` ; `person_id` (lien manuel
  vers une fiche), `extra` (colonnes inconnues du tableau d'origine).
- `personne` : rubriques (joueur, entraîneur, dirigeant, personnage), noms et
  `aliases` (autres graphies), naissance et décès (lieu géolocalisé), mensurations, poste,
  arrivée et départ, premiers et derniers matchs, palmarès, statistiques, carte de l'album.
- `article` / `page` : genre (article, bilan de saison, portrait, dossier), titre et
  chapeau d'origine ; `listing` pour une page WordPress qui n'était qu'une liste d'articles.
- `objet` : collection, date, provenance, crédit, fiches liées ; `moment` : numéro
  (1 à 100), année, fiches liées.

Les textes des compositions sont lus par une seule classe, `App\Data\Lineup` (reprise et
back-office) : « 31' csc » est un but contre son camp, « J 35' R 80' » un jaune puis un
rouge, « ↑ 46' ↓ 80' » une entrée puis une sortie, « x2 » deux buts sans minute.

### Médiathèque

`data/media.json` décrit chaque fichier (légende, crédit, droits, texte alternatif,
dimensions, poids, empreinte `sha1`, retouche non destructive `edit` : rotation et
recadrage). Les originaux sont dans `storage/media/originals/{année}/{mois}/` (hors dépôt).

## 4. Caches (`storage/cache/`, tous reconstructibles)

| Fichier | Contenu | Mise à jour |
|---|---|---|
| `index.php` | résumé de chaque fiche (titre, adresse, type, rubriques…) | à chaque enregistrement ; reconstruit s'il manque |
| `derived.php` | données calculées (§ 5) | marqué « à recalculer » à chaque enregistrement, recalculé après l'envoi de la page ou par la tâche planifiée |
| `search.php` | index de recherche | à chaque enregistrement |
| `media.php`, `media-versions.php`, `media-usage.json` | médiathèque, versions des fichiers retouchés, « utilisée dans » | à chaque modification |
| `carte-*.json`, `sitemap.xml`, `share/` | données de la carte, plan du site, images de partage | à la demande |
| `pdf/` | PDF exportés (fiches, saisons, face-à-face, bilans, records) | à la demande ; nom lié à la date de modification de la fiche et aux données calculées, donc refait dès qu'un contenu change ; ménage des fichiers de plus de 30 jours |
| `correcteur/` | réponses de Gemini au correcteur, une par texte (empreinte du texte, du modèle et des consignes) : un texte inchangé n'est jamais renvoyé | à la demande ; ménage des réponses inutilisées depuis 180 jours |

Après une modification de fichiers faite à la main (envoi FTP de `data/`, script), vider
`storage/cache/` ou lancer `php bin/console.php index`, `derived` et `search`.

## 5. Données calculées (`App\Data\Derived`)

Recalculées à partir des fiches, sans aucune saisie : liste des matchs, apparitions de
chaque joueur (buts, minutes, cartons), totaux par personne, saisons, référentiels des
clubs et des stades (regroupement des variantes de noms), bilans, records, « Ce jour-là »,
utilisation des médias et alertes de qualité.

Liaison d'un nom de composition à une fiche personne, dans l'ordre : lien manuel
(`person_id`), nom identique (tous les noms et `aliases` de la fiche, ordre des mots
indifférent), nom de famille seul si un seul joueur correspond à la période, même nom
autrement écrit (apostrophe, trait d'union), une lettre de différence (deux pour un nom
long), nom incomplet (« Carlao » → « Carlao Roberto Da Cruz », avec contrôle de période).
Les rapprochements approchés sont signalés dans Qualité.

## 6. Images

`/media/{largeur}/{fichier}.webp` (largeurs 160, 320, 480, 640, 800, 1200, 1600) :
`App\Services\Images` génère la vignette WebP avec GD (en appliquant la retouche
éventuelle) dans `public/media/…`, d'où Apache la sert ensuite directement. `img()` ajoute
`?v=` aux fichiers retouchés ou remplacés. Les vignettes des vidéos sont copiées dans
`storage/media/originals/_video/` (`App\Services\VideoThumbs`) pour ne contacter
l'hébergeur vidéo qu'après l'accord du visiteur.

## 7. Services

| Classe | Rôle |
|---|---|
| `Search` | recherche plein texte sans base de données (normalisation des accents, pondération des champs) |
| `Rag`, `Gemini` | assistant IA : index sémantique (`storage/ai/`), réponses limitées aux données du site, quotas, journal des questions (RGPD) |
| `Translator`, `I18n` | traduction anglaise des fiches avec Gemini (champ `i18n.en`), libellés de l'interface (`t()`) |
| `Proofreader` | correcteur d'orthographe et de syntaxe (§ 7 ter) |
| `AiCosts` | coût de l'IA en temps réel, budget, remboursements (§ 7 quater) |
| `FicheAudio` | fiches audio : résumé de 30 secondes, voix IA, traitement groupé (§ 7 quinquies) |
| `Payments` | Stripe Checkout et abonnements, PayPal Orders v2 et abonnements, vérification des webhooks |
| `Mailer`, `Newsletter` | e-mails (SMTP ou mail()), newsletter hebdomadaire « Ce jour-là » |
| `Geo` | géolocalisation (répertoire intégré, puis Nominatim d'OpenStreetMap, une requête par seconde) |
| `Backup` | sauvegardes ZIP de `data/` et des fichiers importants de `storage/` |
| `Stats` | mesure d'audience sans cookie ni adresse IP |
| `Pdf` | reçus fiscaux en PDF, sans bibliothèque (les exports des fiches utilisent `App\Pdf`, § 7 bis) |
| `Cron` | tâches planifiées (§ 9) |

## 7 bis. Export PDF (`App\Pdf`, `App\Front\PdfExport`)

Un vrai document A4, pas une impression du navigateur, fabriqué en PHP pur (aucune
bibliothèque à installer) :

| Classe | Rôle |
|---|---|
| `Pdf\TrueType` | lit les polices TrueType (`app/Resources/fonts/`, instances fixes de Big Shoulders Display et Newsreader tirées des polices du site) et n'embarque que les glyphes employés (sous-ensemble) ; caractères absents remplacés (espaces fines, lettres accentuées rares) |
| `Pdf\Writer` | écrit le fichier PDF 1.7 : polices Type0/CIDFontType2 avec table ToUnicode (texte sélectionnable et copiable), images JPEG (photos converties depuis les vignettes WebP) et PNG avec transparence (blason), liens, signets, métadonnées, langue |
| `Pdf\Layout` | mise en page aux couleurs du musée : bandeau rayé et blason qui déborde, texte enrichi avec retour à la ligne, intertitres (signets), listes, citations, encadré « le chiffre », grilles, tableaux à en-tête répété, photos, galerie recadrée, bandeau courant et pieds de page « page n / total » |
| `Pdf\HtmlFlow` | convertit le HTML des fiches (paragraphes, gras, italique, liens, listes, citations, tableaux, images) |
| `Front\PdfExport` | contenu de chaque document (mêmes données que les pages : `Fiche::matchData()`, `personData()`, `articleData()`, `Explore::seasonData()`, `opponentData()`, `bilanPage()`), cache, réponse |

Adresses : `/pdf/fiche/{id}.pdf`, `/pdf/saison/{saison}.pdf`, `/pdf/face-a-face/{club}.pdf`,
`/pdf/bilan/{clé}.pdf`, `/pdf/records.pdf` (et `/en/pdf/…` en anglais). Réponse en
téléchargement (`Content-Disposition: attachment`), `X-Robots-Tag: noindex` et `Disallow`
dans `robots.txt` (pas de contenu en double pour Google). Fabrication limitée à 40 PDF
par adresse IP et par 10 minutes (les PDF déjà en cache sont servis sans limite) ; une
fiche non publiée n'est exportable que par un membre connecté du back-office.
Repères : un match ≈ 0,5 s et 5 pages ; le joueur le plus capé (423 matchs) ≈ 1 à 3 s,
24 pages, 65 Mo de mémoire au plus.

Pour changer la mise en page : `Pdf\Layout` (couleurs, polices, blocs) et
`Front\PdfExport` (contenu) ; augmenter `PdfExport::VERSION` pour refaire les PDF en cache.

## 7 ter. Correcteur d'orthographe (`App\Services\Proofreader`)

- **Champs vérifiés** : dans les formulaires, un champ de texte rédigé porte `data-proof`
  (nature `text`, `title`, `quote` ou `caption`, option `proof` de `App\Admin\Form` ; par
  défaut pour les éditeurs de texte riche) et `lang="en"` pour un champ `…_en` ou
  `i18n_en.…`. Ces champs ont aussi `spellcheck` (soulignement du navigateur).
  `public/assets/admin/correcteur.js` envoie leurs valeurs à `POST /admin/api/correcteur`
  (bouton `[data-proofread]` : fiches, accueil, rubriques, collections) et affiche le
  panneau des propositions.
- **Texte** : le HTML est ramené à ses nœuds de texte, un retour à la ligne autour de chaque
  bloc ; le même calcul côté navigateur retrouve le passage (contexte avant/après, rang de
  l'occurrence, espaces tolérées) et ne modifie que les nœuds de texte : balises et liens
  restent intacts. La valeur passe ensuite par `BO.setValue` (éditeur visuel rechargé,
  formulaire marqué « non enregistré »).
- **Règles du musée** (français, sans service extérieur) : mot répété, espace avant une
  virgule ou un point, espace oubliée après la ponctuation, apostrophe d'élision suivie d'une
  espace, ordinaux (« 2e », « 1re », « XXe »), « À » en tête de phrase. Volontairement
  prudentes ; essayées sur les 2 940 fiches reprises (`tests/correcteur.php`).
- **Gemini** (si la clé est réglée) : textes groupés par lots de 6 000 caractères (un texte
  long est découpé aux paragraphes), lots envoyés en parallèle (`Gemini::generateMany`,
  réponse JSON à structure imposée). Chaque proposition est contrôlée avant d'être montrée :
  l'extrait doit exister dans le texte, les chiffres restent identiques, les mots protégés
  (noms des personnes, clubs, stades, joueurs cités, dictionnaire du musée) ne changent pas,
  une réécriture trop éloignée est écartée, une différence purement typographique aussi.
- **Dictionnaire du musée** : collection `dictionnaire` (`data/collections/dictionnaire.json`,
  écran Qualité › Dictionnaire du musée, bouton « + Dictionnaire » du panneau).
- **Corrections ignorées** : `storage/correcteur/ignorees.json`, par fiche ou par écran.
- **Tâche de fond** (`correcteur`, à chaque passage du cron, 40 s au plus) : vérifie d'abord
  les fiches nouvelles ou modifiées, puis les autres ; résultat par fiche dans
  `storage/correcteur/fiches/{id}.json`, résumé dans `storage/correcteur/index.json` (date
  de modification de la fiche, nombre de corrections, fautes de langue, exemple).
  Plafond quotidien d'appels (Réglages › Correcteur, 300 par défaut), pause d'une heure si
  Gemini répond « quota atteint ». Un passage complet du musée demande environ 4 200 appels
  (environ 10 millions de jetons envoyés) : `php bin/console.php correcteur` le fait d'un
  coup, sans plafond.
- **Essais sans clé** : avec le serveur de développement de PHP uniquement (`php -S`), la
  variable d'environnement `GEMINI_MOCK_URL=http://127.0.0.1:port/` dirige les appels vers
  un faux Gemini local ; impossible sur l'hébergement.

## 7 quater. Coûts de l'IA (`App\Services\AiCosts`)

- **Comptage** : chaque réponse de Gemini (`Gemini::generate`, `generateMany`, `embed`) est
  enregistrée par `AiCosts::record()` avec son usage (`assistant`, `traduction`,
  `correcteur`, `index`, `autre` : option `for` des appels), le demandeur (membre connecté,
  « Visiteur du site » pour l'assistant, « Tâche automatique » en ligne de commande) et la
  fiche concernée (option `ref` ou `AiCosts::$ref`, ex. `fiche:123`). Jetons lus dans
  `usageMetadata` : envoyés (`promptTokenCount` + `toolUsePromptTokenCount`), dont lus dans
  le cache (`cachedContentTokenCount`, tarif réduit), produits (`candidatesTokenCount`) et
  de réflexion (`thoughtsTokenCount`, facturés comme des jetons produits). Embeddings sans
  compte : estimation à 4 caractères par jeton (signalée). Une erreur d'écriture n'interrompt
  jamais l'appel à Gemini.
- **Barème** : dollars par million de jetons (entrée, sortie, entrée en cache) par début
  d'identifiant de modèle ; la ligne au début d'identifiant le plus long l'emporte, puis la
  plus récente déjà en vigueur (date « à partir du » : une hausse annoncée se saisit à
  l'avance). Tarifs publics de Google d'octobre 2026 par défaut (`DEFAULT_PRICES`), barème
  modifié dans l'écran : `storage/ia/tarifs.json`. Modèle inconnu : 0,50 $ / 3,00 $
  (signalé « tarif par défaut »). Le coût est figé à l'appel : changer le barème ne
  recalcule rien.
- **Fichiers** (`storage/ia/`) : `AAAA-MM.jsonl` (une ligne par appel : date, usage,
  modèle, jetons, coût en dollars, demandeur, fiche ; `g` = coût évité au niveau gratuit,
  `e` = jetons estimés, `x` = tarif par défaut), `totaux.json` (cumuls par mois, jour, usage
  et modèle, mis à jour sous verrou), `remboursements.json`, `tarifs.json`.
- **Écran** Système › Coûts IA (`App\Admin\Costs`, `templates/admin/system/couts.php`,
  `public/assets/admin/couts.js`) : chiffres rafraîchis toutes les 10 secondes par
  `GET /admin/api/couts` quand l'onglet est visible ; relevé mensuel PDF
  (`/admin/couts-ia/releve/AAAA-MM.pdf`, `Pdf\Layout` : totaux par usage, modèle et jour,
  cases de signature) et détail CSV (`/admin/couts-ia/detail/AAAA-MM.csv`, UTF-8 avec BOM,
  séparateur « ; » pour Excel). Remboursement noté par un administrateur, mois terminés
  seulement ; rappel sur le tableau de bord.
- **Budget** (Réglages › Coûts IA, en euros, au taux réglé) : une fois atteint,
  `AiCosts::paused()` suspend les tâches automatiques (traductions, index de l'assistant,
  Gemini dans la relecture de fond : les règles du musée continuent) et, si la case est
  cochée, l'assistant du site (réponse 429 « L'assistant fait une pause »). Les boutons du
  back-office ne sont jamais bloqués. Niveau gratuit de Google : appels comptés à 0 $.
- **Coût d'une action** : `AiCosts::request()` (appels de la requête en cours) est renvoyé
  par `/admin/api/correcteur` et `/admin/api/traduire` et ajouté aux messages de traduction
  et de réindexation (`Admin\Base::aiCost()`).

## 7 quinquies. Fiches audio (`App\Services\FicheAudio`)

- **Sur le site** : bouton « Écouter (30 s) » des fiches (`templates/partials/audio-button.php`,
  `public/assets/js/audio.js`), caché sans JavaScript. Il joue la voix IA enregistrée si elle
  correspond au texte lu, sinon lit le texte avec la synthèse vocale du navigateur
  (`speechSynthesis`, phrase par phrase, meilleure voix de la langue). Le texte lu s'affiche
  sous le bouton pendant l'écoute.
- **Texte lu** (75 mots au plus), par ordre de priorité : écrit à la main (`src: manual`),
  rédigé par Gemini (`src: ai`, valable tant que l'empreinte `sig` des titres, textes et faits
  de la fiche n'a pas changé : modifier une photo ne l'invalide pas), sinon résumé automatique
  construit à la volée (`template()` : date, stade, score, buteurs, carrière, introduction ou
  brève ; abréviations dites en toutes lettres). Version anglaise pour les fiches traduites.
- **Voix IA** : `Gemini::speech()` (modèle de voix réglé ou le meilleur disponible, voix et
  consigne de ton réglables), PCM 16 bits mono converti en MP3 48 kbit/s par ffmpeg s'il est
  présent, sinon WAV. Fichier `public/media/audio/{id}-{langue}-{empreinte}.mp3`, servi
  directement par Apache (mis en cache un an : le nom change avec le contenu). Valable tant
  que l'empreinte `th` du texte lu ne change pas.
- **Traitement groupé** (API Batch de Google, moitié prix) : `launch()` crée des travaux de
  150 fiches (voix) ou 800 (résumés IA, suivis automatiquement des voix) dans
  `storage/audio/jobs.json` ; la tâche planifiée `audio` (et `php bin/console.php audio`)
  écrit le fichier JSONL des demandes, l'envoie (`Gemini::uploadFile`, envoi en deux temps),
  crée le traitement (`batchCreate`), l'interroge toutes les 2 minutes (`batchGet`),
  télécharge les résultats sans les charger en mémoire (`download`) et les range ligne par
  ligne en reprenant là où il s'était arrêté. Chaque nuit, les voix IA devenues anciennes
  sont refaites (réglage). Coûts comptés (usage « Fiches audio », `batch` : moitié prix).
- **Back-office** : carte « Écouter » de l'éditeur (`public/assets/admin/audio.js`,
  `POST /admin/api/audio` : enregistrer, automatique, ia-texte, voix, supprimer-voix) ;
  écran Système › Fiches audio (`App\Admin\Audio`) : chiffres, estimation, essai sur
  20 fiches ou tout le musée, suivi et annulation des travaux.

## 8. Back-office

- `App\Admin\Router` : connexion obligatoire (sauf connexion, premier accès, invitation,
  mot de passe oublié), jeton CSRF sur toute requête POST (champ `_csrf` ou en-tête
  `X-CSRF`), en-têtes `X-Frame-Options: DENY` et `noindex`.
- Deux niveaux (`App\Core\Auth::can()`) : l'utilisateur peut tout faire sauf la gestion
  des comptes (`users`), les réglages (`settings`), la suppression définitive (`destroy`)
  et la restauration de versions (`restore`, `backup_restore`).
- Formulaires : les écrans envoient du JSON (`public/assets/admin/admin.js`, champs nommés
  par chemin pointé : `match.referee`, répétitions `data-repeater`), contrôle de
  modification simultanée, brouillon conservé dans le navigateur.
- **Verrou de modification** (`App\Services\EditLock`, `public/assets/admin/verrou.js`) :
  un formulaire `data-lock="fiche:123"` (aussi `collection:…`, `ecran:accueil`,
  `ecran:rubrique-…`) signale sa présence toutes les 30 secondes à `POST /admin/api/verrou`
  (`hold`, `watch`, `take`, `release` par `navigator.sendBeacon` à la fermeture). Un
  identifiant par onglet ; un onglet muet depuis 2 minutes ne compte plus ; l'éditeur rend
  la fiche après 30 minutes sans frappe ni clic. L'éditeur d'une fiche prend le verrou dès
  l'ouverture (`Fiches::edit`). Qui ouvre ensuite voit un bandeau à son nom, en lecture seule
  (`inert` sur le contenu, boutons d'enregistrement désactivés) ; « Prendre la main » prévient
  la personne évincée au signal suivant et l'écrit au journal. Côté serveur, tout
  enregistrement (fiche, corbeille, restauration, traduction, collection, accueil,
  rubrique) est refusé par `Base::lockedJson()` / `lockMessage()` (423) si quelqu'un
  d'autre tient le verrou ; les actions groupées laissent de côté les fiches ouvertes par
  d'autres. État dans `storage/verrous.json` (écrit sous verrou de fichier).
- Textes longs : éditeur WYSIWYG natif (`wysiwyg.js`) ; le HTML est nettoyé côté serveur
  par liste blanche (`App\Admin\Html`).
- Chaque enregistrement crée une version (`storage/versions/{id}/`) ; le premier
  enregistrement d'une fiche reprise archive d'abord son état d'origine.
- Réglages (`config/settings.php` pour la liste, `storage/settings.json` pour les
  valeurs) ; les secrets (clés API, mots de passe) sont chiffrés avec sodium
  (`storage/secret.key`) et ne sont jamais renvoyés au navigateur.

## 9. Tâches planifiées

Une ligne de cron toutes les 5 minutes (`php bin/console.php cron`) ; chaque tâche a sa
fréquence, l'état est dans `storage/cron.json`, un verrou empêche deux passages simultanés :
publication des fiches programmées, statistiques, audience, traductions, correcteur
d'orthographe, newsletter,
géolocalisation (toutes les 10 min), médiathèque, vignettes des vidéos (toutes les heures),
assistant IA (toutes les heures), dons (toutes les heures), plan du site (chaque jour),
sauvegarde, reçus annuels, purges RGPD.

## 10. `storage/` (hors dépôt)

| Dossier ou fichier | Contenu | Sauvegardé |
|---|---|---|
| `media/originals/` | photos originales | le dimanche si l'option est cochée |
| `users.json`, `settings.json`, `secret.key` | comptes, réglages, clé de chiffrement | oui |
| `versions/` | historique des fiches et des collections | oui |
| `inbox/`, `newsletter/`, `dons/`, `votes/`, `counters.json`, `activity/` | messages et contributions, abonnés, dons, votes, compteurs, journal d'activité | oui |
| `ai/` | index et journal de l'assistant | non (reconstructible, journal purgé) |
| `correcteur/` | résultats de la vérification de fond, corrections ignorées | non (recalculé) |
| `ia/` | dépense d'IA : détail des appels, cumuls, remboursements, barème (§ 7 quater) | oui |
| `audio/` | fiches audio : texte lu et voix IA de chaque fiche, traitements groupés (§ 7 quinquies) ; `audio/jobs/` : fichiers d'échange temporaires | oui (sauf `jobs/`) ; les voix IA (`public/media/audio/`) avec les photos, le dimanche |
| `verrous.json` | fiches et écrans ouverts en ce moment (verrou de modification) | non (temporaire) |
| `cache/`, `sessions/`, `ratelimit/`, `logs/`, `backups/`, `import/` | fichiers techniques | non |

## 11. Sécurité

- Seul `public/` est exposé ; `public/.htaccess` interdit l'affichage des dossiers et
  ajoute les en-têtes de sécurité aux fichiers statiques.
- Politique CSP sur toutes les pages HTML ; jeton CSRF sur tous les formulaires.
- Mots de passe hachés en Argon2id ; cookie de session `HttpOnly`, `SameSite=Lax`,
  `Secure` en HTTPS ; limitation des tentatives (connexion : 8 par compte et 30 par adresse
  en 15 minutes ; formulaires, recherche, assistant, dons : quotas par adresse).
- Formulaires publics : champ piège et délai minimal contre les robots.
- Envois de fichiers : extensions limitées (jpg, png, gif, webp, pdf), 25 Mo au plus,
  contenu vérifié.
- Vie privée : aucun cookie tiers avant accord (vidéos chargées au clic), YouTube sans
  cookie, polices hébergées sur le site, mesure d'audience sans cookie.

## 12. Reprise WordPress (`scripts/wp/`)

1. `fetch.php` : aspiration de l'ancien site (pages HTML, API REST, ordre des mosaïques,
   médias) dans `storage/import/` ; mot de passe du site dans la variable `WP_PASSWORD`.
2. `parser.php` : lecture des blocs du thème BeTheme (textes, galeries, vidéos, tableaux,
   compositions…) ; `import.php` : écriture de `data/` (adresses « option B »,
   redirections, rubriques, médiathèque).
3. `completeness.php` : contrôle d'exhaustivité, page par page (texte mot à mot, images,
   vidéos, tableaux) ; rapport dans `storage/import/completeness.json`.
4. `media-sync.php`, `media-extra.php` : photos originales.

L'import est rejouable mais réécrit les fiches reprises (seules les fiches créées dans le
back-office sont préservées) : il ne sert plus une fois le site en service.

## 13. Vérifications

- `php tests/lineup.php` : lecture des cellules de composition (buts, remplacements,
  cartons) sur tous les formats rencontrés dans les fiches d'origine.
- `php tests/correcteur.php` : correcteur d'orthographe (texte des champs, règles du musée,
  contrôle des propositions de Gemini simulé, mots protégés, cache, découpage).
- `php tests/couts.php` : coûts de l'IA (barème daté, calcul, cumuls, niveau gratuit,
  budget et pause, remboursements, relevé PDF, détail CSV).
- `php tests/verrou.php` : verrou de modification (prise, observation, prise de main,
  onglets multiples, libération, expiration, inactivité).
- `php tests/audio.php` : fiches audio (résumés automatiques, texte retenu, voix enregistrée,
  rangement des résultats d'un traitement groupé, coût à moitié prix, barème des voix).
- `tests/smoke.js` (Playwright) : parcourt les pages du site et du back-office et signale
  les erreurs JavaScript et les blocages de la politique CSP.
