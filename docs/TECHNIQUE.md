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

Avant tout, `app/bootstrap.php` vérifie l'hébergement : PHP 8.3 et les extensions sans
lesquelles rien ne marche (`mbstring`, `intl`, `dom`, `sodium`, `ctype`, chiffrement
Argon2) ; s'il manque quelque chose, une page autonome « Réglage du serveur en cours » (503)
dit quoi régler dans cPanel, au lieu d'une erreur 500 muette. Le point complet (toutes les
extensions, dossiers inscriptibles, dernières erreurs du journal PHP) est fait par
`App\Services\ServerCheck`, montré aux administrateurs : carte Serveur de Tâches
planifiées, tâche « Régler le serveur » en tête du tableau de bord, et détail de l'erreur
sur la page « Arrêt de jeu » quand un administrateur est connecté.

`App\Kernel::dispatch()`, dans l'ordre :

1. `/media/{largeur}/{fichier}.webp` : vignettes générées à la volée (§ 6).
2. `/wp-content/uploads/…` : redirection 301 vers la médiathèque (anciens liens d'images).
3. Adresse du **site de l'association** (www et ses alias) → `App\Vitrine\Kernel::handle()` ;
   `/apercu-association/…` sur le musée → `Vitrine\Kernel::preview()` (§ 7 undecies).
4. `/admin…` : back-office (`App\Admin\Router`, § 8).
5. Préfixe `/en` : passage en anglais, puis traitement normal de l'adresse sans préfixe.
6. `/api/…` : API JSON du site (`App\Front\Api`) : recherche, assistant, carte, votes,
   dons et leurs webhooks. Les webhooks des paiements et le consentement aux cookies ne sont
   jamais bloqués ; le reste de l'API est fermé avec le site (page d'attente, mot de passe).
7. `/video/teaser.mp4` et `.jpg` (`Front\Pages::teaser()`, fichiers dans
   `app/Resources/video/`, hors de `public/`) : servis au public seulement quand le teaser est
   montré (case de la page d'attente, ou accueil une fois le site ouvert), à l'équipe
   connectée toujours ; lecture par morceaux (`Range`, `Response::media()`) pour l'avance
   rapide et Safari ; sinon l'adresse suit le chemin ordinaire (page d'attente ou 404).
8. Page d'attente, puis mot de passe d'accès éventuel. La page d'attente est **active par
   défaut** (`waiting.enabled` vaut `true` tant que rien n'est enregistré : une installation
   neuve est fermée au public). Les membres connectés du back-office voient le site, avec un
   bandeau `.team-bar` « Site fermé au public » (gabarit `layout.php`) ; les pages légales et
   `robots.txt` restent servis ; la page d'attente (503, `no-store`) n'a aucun lien vers le
   back-office.
9. Adresse sans barre finale → 301 vers l'adresse avec barre finale.
10. Routes fixes (accueil, explorer, interactif, communauté, dons, pages légales…), déclarées
   dans `Kernel::routes()`.
11. Fiche ou rubrique à cette adresse (`Front\Pages::byPath()` : index des fiches, puis
    rubriques).
12. Ancienne adresse connue (`data/redirects.json`) → 301 ; sinon page 404 et adresse
    notée dans `storage/404.json` (Back-office › Redirections › Adresses introuvables).

Site fermé ou masqué (`Front\Seo::closed()` : page d'attente ou mot de passe ;
`Seo::hidden()` : en plus, Réglages › Général › « Masquer le site aux moteurs de recherche ») :
`Kernel::unindexed()` ajoute `X-Robots-Tag: noindex, nofollow` à toute réponse hors
back-office, y compris ce que voit l'équipe connectée, qui reçoit en plus
`Cache-Control: private, no-store` ; `robots.txt` répond `Disallow: /` ; la mesure d'audience
ne compte rien tant que le site est fermé (`public/index.php`).

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
| `index-2.php` | résumé de chaque fiche (titre, adresse, type, rubriques, extrait sans « xx », empreinte des textes relus par le correcteur…) ; le numéro change quand le contenu du résumé change, l'ancien fichier est alors ignoré | à chaque enregistrement ; reconstruit s'il manque (une seule fois, même si plusieurs pages le demandent ensemble) |
| `derived.php` + `derived/` | données calculées (§ 5) : `derived.php` n'est qu'un résumé (version, date, durée, nom du fichier de chaque partie) ; chaque partie (`matches`, `seasons`, `person_totals`, `apps`…) a son fichier dans `derived/`, nommé d'après son contenu, et les compositions sont aussi rangées par joueur (`apps_p.0` à `apps_p.15`) et par match (`apps_m.*`) : une page ne lit que ce qu'elle affiche (`Derived::part()`, `match()`, `personMatches()`, `lineupLinks()`) | marqué « à recalculer » à chaque enregistrement, recalculé après l'envoi de la page ou par la tâche planifiée ; une partie inchangée garde son fichier (déjà en mémoire d'OPcache) ; les fichiers du calcul précédent restent jusqu'au suivant (une page en cours peut encore les lire) |
| `search.php` | index de recherche | à chaque enregistrement |
| `media.php`, `media/`, `media-versions.php`, `media-usage.json` | médiathèque (entière, et en 16 groupes selon le chemin du fichier : `Media::get()` n'en lit qu'un), versions des fichiers retouchés, « utilisée dans » | à chaque modification |
| `carte-*.json`, `sitemap.xml`, `share/` | données de la carte, plan du site, images de partage | à la demande |
| `pdf/` | PDF exportés (fiches, saisons, face-à-face, bilans, records, kits souvenirs) | à la demande ; nom lié à la date de modification de la fiche et aux données calculées, donc refait dès qu'un contenu change (kit souvenirs : un fichier par langue, refait seulement si le contenu du kit change) ; ménage des fichiers de plus de 30 jours |
| `correcteur/` | réponses de Gemini au correcteur, une par texte (empreinte du texte, du modèle et des consignes) : un texte inchangé n'est jamais renvoyé | à la demande ; ménage des réponses inutilisées depuis 180 jours |
| `chiffres-{fr,en}.json` | les 100 chiffres du FCSM (§ 7 nonies), déjà mis en forme dans chaque langue | refaits quand `derived.php`, `index-2.php` ou le dictionnaire anglais changent ; calculés d'avance par la tâche « statistiques » |
| `audio-resumes.ser` | résumés automatiques des fiches audio (texte lu par défaut), avec l'empreinte de chaque fiche (et, pour une personne, de ses totaux de matchs et de buts) : l'écran Système › Fiches audio et les traitements groupés ne les recalculent pas (4 s pour tout le musée sinon) | à chaque calcul sur tout le musée, pour les seules fiches modifiées ; tout est refait si `FicheAudio.php`, `Unknown.php` ou `MatchText.php` change |
| `controle-site.php` | vérifications du site hors fiches (redirections, référentiels, rubriques, textes de l'interface), pour le compteur d'alertes graves du menu | refaites dès qu'un des fichiers lus change (empreinte des dates et tailles) et à chaque contrôle complet |
| `ascii-fold.php` | table caractère → ASCII minuscule tirée d'ICU pour `Names::ascii()` (latin, ponctuation, symboles, lettres mathématiques, émojis : 6 900 caractères d'écriture latine ou commune) | refaite si la version d'ICU ou les plages changent |
| `memo/*.php` | calculs coûteux gardés par `App\Core\Memo` : réseau du Fil jaune, ordre de chaque rubrique et personnes par ordre alphabétique, compteurs des menus, dernier match fiché, numéros de l'album, plans de l'écran Fiches audio, fiches que le slider de l'accueil peut tirer au hasard, photos des murs de photos | refaits dès qu'un fichier source change (date et taille : index des fiches, rubriques, réglages, état des voix, code du calcul) ou que les données calculées sont refaites, et au plus tard après 5 minutes (publications programmées) |
| `pages/` | pages publiques déjà calculées pour les visiteurs anonymes (`App\Core\PageCache`, appelé par `public/index.php` avant et après `Kernel::handle`) : première ligne JSON (heure, empreinte, ETag, en-têtes), puis le HTML. Jamais pour une session (cookie `sr_session`), le site fermé, le site de l'association, `/admin`, `/api`, la boutique, les dons, les formulaires, la recherche, le Rétro-Direct, les paires du Fil jaune, les paramètres inconnus ; jamais gardée si la page a ouvert une session, posé un cookie ou fixé `Cache-Control`. Accueil et murs : 4 tirages gardés. Un seul processus refait une page périmée (verrou non bloquant), les autres reçoivent la précédente | refaites dès que l'empreinte change (index des fiches, `derived.php`, réglages, rubriques, médiathèque, redirections, traduction anglaise, dossiers des fiches et collections, `public/assets`), au plus tard après 60 s, jamais servies après 10 min ni d'un jour sur l'autre ; `php bin/console.php pages-vider` ; désactivable (Réglages › Général) |

**Travail après l'envoi de la page** (recalcul de `derived.php` et des 100 chiffres, mesure
d'audience, e-mail de mot de passe oublié) : `Response::detach()` libère la session puis termine
la page (`fastcgi_finish_request()` sous PHP-FPM, `litespeed_finish_request()` sous LiteSpeed).
Sans cela, le fichier de session reste verrouillé jusqu'à la fin du calcul et le clic suivant
dans le back-office attend (plusieurs secondes après chaque enregistrement). Les calculs longs
faits pendant une page (écran Fiches audio, lancement d'un traitement groupé, vérification
GitHub) appellent `Session::release()` d'abord : la session se rouvre d'elle-même au prochain
accès (message, jeton des formulaires créé avant). `Settings::schema()` et `defaults()` sont lus
une fois par page (chaque réglage jamais enregistré relisait `config/settings.php`).

`index-2.php` et `search.php` sont modifiés par `App\Core\PhpCache::update()` : le fichier est
relu sous verrou, seules les fiches enregistrées sont remplacées, puis il est réécrit. Un
processus qui a chargé l'index plus tôt (tâche planifiée, longue requête) n'écrase donc
jamais ce que d'autres ont enregistré entre-temps (en mode « lot », les modifications sont
appliquées ensemble à la fin). Recalcul de `derived.php` : la marque `derived.dirty` est
renommée `derived.building` au début du calcul ; un enregistrement fait pendant le calcul
recrée `derived.dirty` et un nouveau calcul suivra (un calcul interrompu depuis plus de
15 minutes est refait). Le recalcul d'après-page ne fait jamais attendre : si un autre
processus calcule déjà, il abandonne. La limite de mémoire est portée à 512 Mo pendant le
calcul (environ 200 Mo pour 3 000 fiches).

**Pourquoi tant de petits fichiers** : un cache PHP est quasi gratuit tant qu'OPcache le garde en
mémoire, mais coûte sa relecture complète quand il change (recalcul après un enregistrement) ou
qu'OPcache repart de zéro (mise à jour, redémarrage de PHP). Avec un seul `derived.php` de 9 Mo,
lu par le bandeau de l'en-tête sur toutes les pages, chaque page « à froid » perdait près d'une
seconde. Mesures à froid (OPcache vide), avant → après : mentions légales 356 → 87 ms, accueil
895 → 159 ms, fiche de match 238 → 98 ms, liste des matchs 730 → 118 ms, fiche d'un joueur de
500 matchs 429 → 187 ms ; site de l'association 147-171 → 70-80 ms.

**Après une mise à jour** (`Updater::afterChange()`) : les caches sont vidés, sauf les gros
caches de données (`derived*`, `index-*.php`, `search.php`, `media*` dont `media-usage.json`, sans
lequel les murs de photos seraient vides ; s'il manque, `PhotoWall` le recalcule) qui servent encore ; la
marque `apres-mise-a-jour` demande de les refaire avec le nouveau code (`Updater::refresh()`) :
par la première page qui suit, au moins 5 s après et une fois envoyée (seulement si PHP sait
terminer la page avant : PHP-FPM ou LiteSpeed), sinon par la tâche planifiée « statistiques ».
Avant, le premier visiteur attendait le recalcul de tout (plusieurs secondes). Une nouvelle
version de `Derived::VERSION` ou un nouveau numéro d'index restent recalculés tout de suite.
Système › Tâches planifiées, carte Serveur : mémoire d'OPcache utilisée et alerte si elle est
trop petite, ou si PHP ne sait pas terminer une page avant son travail d'après-page.

Après une modification de fichiers faite à la main (envoi FTP de `data/`, script), vider
`storage/cache/` ou lancer `php bin/console.php index`, `derived` et `search`.

**Temps d'accès** (mesurés avec OPcache, comme sur o2switch ; octobre 2026) : toutes les pages
publiques entre 8 et 25 ms (fiche joueur 120 → 21 ms, recherche 207 → 23 ms, saison 96 → 17 ms,
Fil jaune 88 → 18 ms) ; back-office sous 20 ms, sauf Tableau de bord, Médiathèque et Qualité
(35-40 ms ; 8 000 alertes) ; écran Fiches audio 1 s → 8 ms. Ce qui coûtait :
- `Names::ascii()` (repliement des accents, partout : recherche, tri des noms, rapprochements)
  démarrait ICU (10 ms) puis translittérait lentement ; la table `ascii-fold.php` donne le même
  résultat (vérifié sur tout le musée et sur 700 000 chaînes au hasard), 15 à 25 fois plus vite ;
  un texte avec un caractère hors table (autre écriture, accent « combinant ») passe par ICU ;
- `Derived::lineupLinks()` et `personMatches()` parcouraient les 26 000 apparitions à chaque
  appel (une page de saison en fait une soixantaine) : index `app_index` (rangs par match et par
  personne) calculé avec les données (refait par requête pour un cache plus ancien) ;
- le réseau du Fil jaune (70 ms) était refait à chaque fiche joueur, les rubriques retriées à
  chaque page, les compteurs des menus recomptés : `Memo` (ci-dessus) ;
- la page de recherche refaisait toute la recherche pour ses raccourcis (saison, face-à-face) :
  `Search::shortcuts()` reprend le résultat ; `Unknown::has()` ne lance l'expression complète
  que si le texte contient « xx » ;
- l'écran Fiches audio relisait deux fois les 3 000 fiches : un seul passage pour les deux plans
  (`computePlans()`), gardé par `Memo` (`FicheAudio::overview()`) ; le lancement d'un traitement
  recalcule toujours ;
- Médiathèque : clés de tri calculées une fois (12 700 fichiers) ; Qualité : alertes rangées par
  gravité sans tri.
Pas de cache de pages entières : l'accueil tire ses photos au hasard à chaque visite, les
formulaires portent un jeton, et le gain resterait faible devant le temps réseau.

**Slider de l'accueil** (`Pages::slides()`) : en tirage au hasard, seulement les fiches
publiées « À la une » dont la vraie photo (silhouette « ? » écartée) fait au moins
`Pages::$slideMin` (1 200 × 600 pixels, dimensions lues dans la médiathèque) : le slider
occupe toute la largeur de l'écran (jusqu'à 840 pixels de haut, avec un léger zoom), une photo
plus petite y paraît floue. La liste (`Pages::slidePool()`, 613 fiches sur 2 300 en
octobre 2026 : 562 matchs, 36 personnes, 15 articles) est gardée par `Memo` et refaite quand
l'index des fiches ou `data/media.json` change ; s'il n'en reste aucune, le tirage reprend
toutes les fiches « À la une » qui ont une vraie photo. La sélection manuelle n'est pas
filtrée : le back-office y signale les photos trop petites.

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

`scorers` garde, pour chaque match, les buteurs sochaliens de la composition avec les minutes
de leurs buts et leurs penaltys (« 32' s.p. », « sp », « pen. »). Alertes de qualité propres
aux statistiques : total des buts ≠ buteurs (`buts`), composition recopiée d'un autre match
(`tableau`), total d'un tableau de statistiques ≠ somme des saisons (`stats`) et tableau de
statistiques identique sur plusieurs fiches de joueurs (`stats-copie`, modèle recopié de
l'ancien site : 594 fiches à la reprise), joueur inscrit deux fois dans une composition
officielle (`doublon` : une seule apparition, buts sans doublon de minute, temps de jeu
plafonné) et dates d'une personne incohérentes (`dates` : naissance improbable, décès avant
la naissance, départ avant l'arrivée, âge d'arrivée impossible).

Autres alertes :
- en-tête de l'ancien site qui contredit la fiche (`App\Services\MatchText::dateIssue()` et
  `roundIssue()`) : date en toutes lettres différente de la date (`date`, `ecart`), jour de la
  semaine incohérent (`jour`), date illisible (`illisible`) ; tour en toutes lettres
  (`tour`) : journée de championnat pour un amical (`amical`), journée à plus de deux
  journées de celle saisie (`journee` ; une ou deux d'écart = match en retard, non signalé),
  autre division (`division`, « de D2 » pour la Division 1), autre tour de coupe (`coupe`) ;
- match de coupe rangé dans la compétition « Championnat » (`competition`, compté avec le
  championnat dans les chiffres) ;
- tirs au but sans score de séance (`tab`) ;
- lien vidéo non reconnu (`video`) ;
- « xx » de l'ancien site (`inconnu`, information inconnue, avec l'endroit : fiche
  d'identité, naissance, arbitre, texte…).

Contrôles des matchs (`matchChecks`, surtout utiles pour les fichiers modifiés hors du
masque de saisie, qui calcule lui-même saison et résultat) : date absente ou impossible
(`match-date`), date hors de la saison indiquée ou saison vide (`saison`, amicaux de juin
et de juillet tolérés), résultat incohérent avec le score ou tirs au but sur un score non nul
(`resultat`), score d'un match à venir ou match officiel joué sans score (`score`),
composition d'un match officiel (`compo` : minute d'entrée sur une ligne de titulaire, plus
de 11 titulaires, deux gardiens, moins de 9), même match saisi deux fois (`doublon-match`,
même jour et même adversaire), textes d'un autre match (`autre-match`, haute,
`App\Services\MatchText::otherMatch()` : texte d'au moins 60 mots qui ne nomme jamais
l'adversaire — ni son nom, ni une variante du référentiel, ni un mot distinctif, à l'espace
près —, plus une preuve nette : réaction d'après-match de l'entraîneur, du gardien, du
président ou d'un joueur d'un autre club, ou « deuxième journée de Ligue 2 » pour un amical ;
les preuves d'abord, le texte entier ensuite : 0,4 s pour tout le musée ; une seule fiche
signalée sur 1 664, sans fausse alerte, les coulisses de la semaine qui citent d'autres clubs
restant tranquilles). `FicheAudio::blocked()` en tire la règle de l'audio : ni récit de
l'IA (plan, envoi groupé, boutons de l'éditeur refusés avec la raison), ni ses textes (seul
l'en-tête du match est lu, sauf texte audio écrit à la main), ni extrait dans les récits des
pages de synthèse ; la consigne de l'IA dit aussi que l'en-tête d'un match fait foi.

En-tête affiché : `MatchText::header()` (appelé par `Fiche::localize()`, donc page, PDF,
Rétro-Direct, kit souvenirs, et par l'assistant) ne garde la date et le tour en toutes
lettres de l'ancien site que s'ils concordent avec les champs saisis ; sinon la page
affiche la date saisie (`date_text` vidé → `date_fr()`) et la journée saisie
(`roundLabel()` : « J15 » → « 15e journée », « 1/8e aller » → « 8e de finale aller »,
« Matchday 15 » en anglais, où la date vient toujours de la date saisie). Les fiches créées
au back-office (sans tour en toutes lettres) affichent ainsi leur journée. L'audio ne lit
pas un tour contredit et l'IA reçoit la journée saisie ; l'empreinte du récit de l'IA ne
change que pour ces fiches-là. À l'enregistrement (`FicheForm::match()`), le tour en toutes
lettres est effacé quand la journée saisie change (« J04 » = « J4 »), et un en-tête
contredit est remplacé (l'ancien reste dans l'historique).

Garde-fou de l'éditeur : `MatchText::switched($avant, $après)` repère l'adversaire changé
(autre club : ni même clé, ni même nom à l'espace près, ni même club du référentiel) ou la
date déplacée de plus de deux jours sur une fiche déjà remplie (textes d'au moins 30 mots,
composition, temps forts, réactions, photos ou vidéos). `Fiches::save()` répond alors 409
avec `confirm` (titre, texte, bouton, `extra: {_same_match: 1}`) ; `admin.js` affiche
`BO.confirm` et renvoie la fiche avec `extra` si la personne confirme ; la version est notée
« En-tête corrigé (même match) : adversaire A → B, date … » quand aucune note n'est saisie.
L'éditeur affiche aussi, en haut, l'alerte « Texte d'un autre match ? » et, dans l'onglet
Infos, l'encadré « En-tête de l'ancien site à vérifier ».

Personnes : date impossible au calendrier (dans `dates`),
aucune rubrique (`role`), même nom qu'une autre fiche sans dates de naissance différentes
(`homonyme`). Toutes les fiches (`ficheChecks`) : titre vide (`titre`), adresse vide, mal
formée ou partagée par deux fiches (`adresse`), rubrique supprimée (`rubrique`), image absente
de la médiathèque (`image`), fichier d'image absent du serveur (`image` par fiche ; une seule
alerte `photos` s'il manque plus de 20 % des fichiers, cas d'un serveur neuf avant la copie
des photos), fichier de fiche illisible ou mal numéroté (`fichier`), version anglaise
dépassée (`traduction`, moyenne si elle avait été corrigée à la main).

L'écran Qualité (`App\Admin\Quality::all()`) les range en onglets (`Quality::TABS`) :
« Statistiques et dates », « À compléter » (`inconnu`, `avenir`, `arbitre`, `video`,
`role`), « Liens joueurs » (sans fiche, `rapproche`, `homonyme`), « Adresses et médias »
(`titre`, `adresse`, `rubrique`, `image`, `fichier`), « Traductions à revoir »
(`traduction`)… S'y ajoutent les vérifications hors fiches de
`App\Services\Controle::siteChecks()` : redirections, suivies comme par un visiteur avec
`Kernel::probe()` (même ordre que `dispatch()`, sans exécuter les pages : fichier de `public/`,
image de la médiathèque, `/en`, « / » final ajouté, anciens liens `?p=`, pages calculées à
paramètre vérifiées comme par la page elle-même — saison, face-à-face, bilan, Rétro-Direct,
Fil jaune, réserves —, fiche ou rubrique, redirection) : jamais utilisée, vers une page
absente ou non publiée, en chaîne, en boucle (A → B → A), vers elle-même ;
référentiels (adversaire ou stade en double, graphie qui désigne deux entrées), rubriques
orphelines, textes de l'interface dont les variables `{…}` ou les balises diffèrent en
anglais. Les plus graves viennent d'abord, 300 par page ; « Corriger » ouvre la fiche à
l'onglet concerné (`#infos`, `#compo`, `#seo`…).

### Contrôle complet (bouton « Contrôler maintenant »)

`App\Services\Controle::run()` (route `POST /admin/qualite/controler`, ouverte à tous les
comptes ; `php bin/console.php controle` en ligne de commande) :
1. remet l'index des fiches et la recherche à jour si des fichiers ont changé hors du
   back-office : résumé recalculé depuis chaque fichier et comparé à l'index (même si la
   date de modification interne n'a pas changé, cas d'une restauration), fichier plus
   récent que la recherche ;
2. recalcule toutes les données calculées (`Derived::rebuild()`) ;
3. donne à chaque alerte une clé stable (`Controle::key()` : onglet, fiche ou nom, nature et
   `ref`, ce que vise l'alerte — nom rapproché, adresse partagée, sorte d'écart de date,
   d'erreur de composition, identifiant en double… ; jamais le libellé, qui peut changer :
   nombre de compositions, titre d'une autre fiche, liste de fichiers) ;
4. compare avec le contrôle précédent : nouvelles et corrigées ;
5. enregistre `storage/controle.json` (clés par onglet, nouvelles, historique des 8 derniers
   contrôles) et une ligne du journal.

Un seul contrôle à la fois (verrou `storage/controle.lock`) ; environ 2 à 3 secondes pour
~3 000 fiches ; quatre contrôles au plus en deux minutes par personne, session libérée pendant
le contrôle (les autres onglets restent utilisables). Une alerte est marquée « Nouveau » si
elle est nouvelle au dernier contrôle ou absente de celui-ci (apparue depuis). Exception,
l'onglet Orthographe, rempli peu à peu par le correcteur qui relit tout le musée en tâche de
fond (`Controle::BACKGROUND_TABS`) : une correction proposée n'y est nouvelle que si les
**textes** de sa fiche ont changé depuis le contrôle (empreinte `Proofreader::textSig()` des
champs relus, gardée par fiche dans `controle.json` ; une traduction anglaise, un numéro
d'album ou une publication programmée ne comptent pas). Le correcteur lui-même garde sa
vérification tant que cette empreinte ne change pas (pas de nouvel appel à Gemini). Avant le
premier contrôle, la comparaison se fait avec `app/Resources/controle-reference.json`, les
clés des anomalies des données du dépôt (onglet Orthographe exclu : il dépend du correcteur
de chaque serveur). Elle se réécrit avec `php bin/console.php controle-reference "libellé"`
après une modification des vérifications ou des clés, données du dépôt à jour.

### « xx » de l'ancien site (`App\Front\Unknown`)

Information inconnue au moment de la saisie : « xx » isolé (ou « XX »), année « 19xx »,
taille « 1mxx », rang « xxè » (en minuscules : « XXe siècle » est un vrai siècle), score
« x-x » (`Unknown::RE` ; « XXL », « Maxxsport » ou un identifiant de vidéo ne sont pas
concernés). Jamais affichée ni lue à voix haute : `Unknown::doc()` dans `Fiche::localize()`
(page, PDF), pour l'image de partage et dans le résumé audio réécrit la phrase (« né le xx/xx/1925 à
Aulnoye » → « né en 1925 à Aulnoye », « né le xx à xx (Hongrie) » → « né en Hongrie »,
« Sochaux - Strasbourg du xx/09/1990 » → « … en septembre 1990 »), ou retire la ligne quand
il ne reste rien d'utile (« Taille 1mxx », « Poids xx kg », modèle de match jamais rempli).
Texte riche : seuls les nœuds de texte sont touchés, attributs intacts ; un bloc réduit à
son étiquette (« Arbitre : ») disparaît. L'extrait de l'index (cartes, accueil, recherche) et
les extraits de la recherche sont nettoyés de même. La fiche n'est pas modifiée : l'écran
Qualité liste les « xx » à compléter (`inconnu`). Le résumé audio est calculé sur la fiche
enregistrée, comme au back-office : le texte de l'IA et la voix enregistrée sont reconnus.
Cas couverts : `tests/inconnu.php`.

### Fichiers retouchés à la main

Une fiche est lue seulement si son fichier est un objet JSON qui porte son numéro, un type
connu et, pour un match ou une personne, ses données (`Fiches::usable()` ; sinon alerte
`fichier`, « illisible, incomplet ou mal numéroté »). Les champs d'un type inattendu (« 2 » ou
2.0 au lieu de 2, liste écrite comme un texte, date en nombre…) sont lus comme s'ils étaient
bien formés (`Fiches::normalize()`, sans effet sur une fiche bien formée) et la fiche est
signalée (`fichier`, ref `format`) jusqu'à son prochain enregistrement, qui la répare. En
dernier recours, une fiche qui ferait encore échouer un calcul en est écartée et signalée
(`fichier`, ref `donnees`) ; l'index et la recherche font de même : une fiche ne peut plus
mettre le site entier en erreur. Fichiers de référence abîmés (`data/redirects.json`,
`data/i18n/en.json`, `storage/controle.json`) : signalés dans Qualité, le site continue (sans
redirections, en français) ; ils ne sont jamais réécrits tant qu'ils ne sont pas remplacés.

Les clubs et stades créés automatiquement (`data/collections/clubs.json`, `stades.json`)
sont fusionnés sous verrou à la fin du calcul : seuls les ajouts et les noms des entrées
encore « automatiques » sont écrits, une correction faite pendant le calcul est gardée.

## 6. Images

`/media/{largeur}/{fichier}.webp` (largeurs 160, 320, 480, 640, 800, 1200, 1600) :
`App\Services\Images` génère la vignette WebP avec GD (en appliquant la retouche
éventuelle) dans `public/media/…`, d'où Apache la sert ensuite directement.
`php bin/console.php images [largeur]` les prépare à l'avance (800 par défaut, seulement une
des largeurs ci-dessus). Sur un serveur neuf, tant que les originaux ne sont pas copiés
(`scripts/wp/media-sync.php`), la tâche `statistiques` refait les alertes toutes les
30 minutes pour que l'alerte `photos` disparaisse à la fin de la copie. `img()` ajoute
`?v=` aux fichiers retouchés ou remplacés. Original absent ou illisible : cadre beige servi
en `Cache-Control: no-store`, pour que la photo apparaisse dès que l'original arrive (jamais
gardé par le navigateur). AVIF : vignette si GD sait le lire (`imagecreatefromavif`), sinon
l'original est servi tel quel (les navigateurs l'affichent). Les vignettes des vidéos sont copiées dans
`storage/media/originals/_video/` (`App\Services\VideoThumbs`) pour ne contacter
l'hébergeur vidéo qu'après l'accord du visiteur.

Images de partage 1200 × 630 (`App\Front\Share`, GD, `/partage/{id}.png`) : fiche lue sans
ses « xx » (`Unknown::doc()`) ; emoji retirés et lettres stylisées ramenées aux lettres
simples (`printable()`), sinon GD dessine un carré vide. Cache `storage/cache/share/`,
refait quand la fiche ou sa photo change (`Share::VERSION` pour tout refaire).

## 7. Services

| Classe | Rôle |
|---|---|
| `Search` | recherche plein texte sans base de données (normalisation des accents, pondération des champs) |
| `Rag`, `Gemini` | assistant IA : index sémantique (`storage/ai/`), réponses limitées aux données du site, quotas, journal des questions (RGPD) |
| `Translator`, `I18n` | traduction anglaise des fiches avec Gemini (champ `i18n.en`), libellés de l'interface (`t()`) |
| `Proofreader` | correcteur d'orthographe et de syntaxe (§ 7 ter) |
| `AiCosts` | coût de l'IA en temps réel, budget, remboursements (§ 7 quater) |
| `FicheAudio` | fiches audio : explication de la fiche (durée maximale réglable, 3 min), voix IA, traitement groupé (§ 7 quinquies) |
| `PageAudio` | pages de synthèse racontées (face-à-face, saisons, bilans, records, chiffres) : récit rédigé par l'IA puis voix IA enregistrée (traitements groupés de `FicheAudio`, refaits la nuit quand les chiffres changent), sinon récit automatique calculé à chaque affichage, lu par la voix du navigateur ; français et anglais (§ 7 quinquies) |
| `Updater` | mises à jour en un clic depuis GitHub (Système › Mises à jour) : dernière version de la branche (API, flux Atom en secours), changements, application du seul code qui a changé (`app/`, `bin/`, `config/`, `scripts/`, `templates/`, `public/` sauf `public/media/`), libellés `data/i18n/en.json` fusionnés, `public/.htaccess` modifié gardé, fichiers retirés du dépôt supprimés (manifeste CRC32), sauvegarde et retour arrière, pause du site pendant la copie (`storage/update/maintenance`, lu par le Kernel), caches vidés sauf `storage/cache/correcteur/` ; synchronisation : à chaque vérification, empreintes Git (`sha1("blob <taille>\0<contenu>")`) des fichiers du serveur comparées à l'arborescence de la version (API `git/trees`, un appel par dossier du code, listes gardées sous leur empreinte dans `storage/update/arbres/`), fins de ligne ignorées pour les fichiers texte ; site mis en ligne par FTP et identique : version reconnue (manifeste écrit, sans le `.htaccess` réglé à la main) ; fichiers différents : « Synchroniser avec GitHub » |
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
| `Pdf\TrueType` | lit les polices TrueType (`app/Resources/fonts/`, instances fixes de Big Shoulders Display et Newsreader tirées des polices du site) et n'embarque que les glyphes employés (sous-ensemble). Caractère absent de la police : espace fine ou séparateur → espace ; lettre accentuée ou stylisée (« 𝗣 » des tweets, « ᵉ ») → lettre de base ; emoji, pictogramme, flèche, variante d'affichage → omis ; autre écriture → « ? ». La table ToUnicode donne le caractère réellement imprimé (texte copié ou cherché exact) |
| `Pdf\Writer` | écrit le fichier PDF 1.7 : polices Type0/CIDFontType2 avec table ToUnicode (texte sélectionnable et copiable), images JPEG (photos converties depuis les vignettes WebP) et PNG avec transparence (blason), liens, signets, métadonnées, langue |
| `Pdf\Layout` | mise en page aux couleurs du musée : bandeau rayé et blason qui déborde, texte enrichi avec retour à la ligne, intertitres (signets), listes, citations, encadré « le chiffre », grilles, tableaux à en-tête répété, photos, galerie recadrée, bandeau courant et pieds de page « page n / total » |
| `Pdf\HtmlFlow` | convertit le HTML des fiches (paragraphes, gras, italique, liens, listes, citations, tableaux, images) ; une citation posée directement dans une liste (tweets de l'ancien site) est imprimée à sa place, la numérotation de la liste continue après elle |
| `Front\PdfExport` | contenu de chaque document (mêmes données que les pages : `Fiche::matchData()`, `personData()`, `articleData()`, `Explore::seasonData()`, `opponentData()`, `bilanPage()`), cache, réponse |

Adresses : `/pdf/fiche/{id}.pdf`, `/pdf/saison/{saison}.pdf`, `/pdf/face-a-face/{club}.pdf`,
`/pdf/bilan/{clé}.pdf`, `/pdf/records.pdf` (et `/en/pdf/…` en anglais). Réponse en
téléchargement (`Content-Disposition: attachment`), `X-Robots-Tag: noindex` et `Disallow`
dans `robots.txt` (pas de contenu en double pour Google). Fabrication limitée à 40 PDF
par adresse IP et par 10 minutes (les PDF déjà en cache sont servis sans limite) ; une
fiche non publiée n'est exportable que par un membre connecté du back-office.
Repères : un match ≈ 0,5 s et 5 pages ; le joueur le plus capé (423 matchs) ≈ 1 à 3 s,
24 pages, 65 Mo de mémoire au plus.

Compositions : les symboles saisis au back-office (↑ ↓, 🟨 🟥, ⚽), absents des polices,
s'impriment en lettres comme on les écrit aussi (`PdfExport::marks()` : « Entrée 59' Sortie
66' », « J 45'+2 R 55' »). Ménage du cache (un PDF sur 40 fabriqués) : fichiers de plus de
30 jours ; un fichier renommé ou supprimé entre-temps par une autre demande est ignoré.
`tests/pdf.php` vérifie polices, symboles et citations.

Pour changer la mise en page : `Pdf\Layout` (couleurs, polices, blocs) et
`Front\PdfExport` (contenu) ; augmenter `PdfExport::VERSION` (et `Kit::VERSION` pour le kit
souvenirs) pour refaire les PDF en cache.

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
  `e` = jetons estimés, `x` = tarif par défaut, `q` = recherches Google), `totaux.json`
  (cumuls par mois, jour, usage et modèle, recherches Google du mois, mis à jour sous verrou),
  `remboursements.json`, `tarifs.json`. Recherches Google (recherche sur le web des fiches,
  § 7 decies) : facturées à part, part gratuite déduite (`AiCosts::searchCost()`).
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

- **Sur le site** : bouton « Écouter (durée) » des fiches (`templates/partials/audio-button.php`,
  `public/assets/js/audio.js`), caché sans JavaScript. Il joue la voix IA enregistrée si elle
  correspond au texte lu, sinon lit le texte avec la synthèse vocale du navigateur
  (`speechSynthesis`, phrase par phrase, meilleure voix de la langue). Le texte lu reste caché :
  le lien discret « Voir le texte de l'audio » (`[data-audio-show]`) l'affiche ou le masque.
- **Pages de synthèse** (`App\Services\PageAudio`) : même bouton sur les face-à-face, saisons,
  bilans, records et chiffres (`Explore::withAudio()`). Le récit est construit à chaque affichage
  à partir des variables de la page (`opponent()`, `competition()`, `stadium()`, `season()`,
  `records()`, `chiffres()`) : accroche selon le nombre de rencontres, bilan et sa tendance,
  domicile et extérieur, compétitions, premier et dernier match, plus large victoire, plus lourde
  défaite, affluence, buteurs (compositions, `Derived::apps`), série sans défaite, finales et
  tour le plus avancé des coupes, conclusion. `speakable()` prépare le texte pour la voix
  (milliers sans espace, scores « 7 à 0 », dates en toutes lettres, saisons « 1987‑1988 » à
  trait d'union insécable). Gratuit, sans état ni cache, coupé à `FicheAudio::maxWords()`.
- **Récits des pages par l'IA** : chaque page a ses « faits » (`factsFor()`, toujours calculés en
  français : même empreinte pour les deux langues) : chiffres, domicile et extérieur,
  compétitions, premier et dernier match, plus large victoire, plus lourde défaite, affluence et
  début de leur fiche, buteurs, série sans défaite, finales, saison par saison, tous les matchs
  (80 au plus), bilan de la saison (fiche), classement des records, 100 chiffres. `aiPrompt()` :
  consigne de conteur (comme les fiches) et faits en JSON. Texte rangé dans
  `storage/audio/pages/{page}.json` avec l'empreinte des faits (`sig()`, avec `TEXT_VERSION` et
  la durée maximale) ; lu seulement si l'empreinte correspond encore (`choose()`), sinon récit
  automatique. Pages : `slugs()` (adversaires, saisons, compétitions, stades, records avec leurs
  filtres, chiffres), environ 830 pages qui se racontent, 1 660 récits. Rédaction : clés
  `page:{page}:{langue}` dans les traitements groupés de texte (`FicheAudio::queueTexts()`,
  `submit()`, `process()`, sans voix ensuite) ; chaque nuit, `PageAudio::launch()` (réglage
  `audio.pages_ai`) confie les récits manquants ou dépassés (`plan()`, une dizaine de secondes,
  dernier calcul dans `storage/audio/pages-plan.json`). Coûts : usage « Fiches audio », référence
  `page:{page}`.
- **Voix IA des pages** (réglage `audio.pages_voice`) : un traitement de récits de pages a
  `then_voice` ; à la fin, les voix de ses pages sont commandées (travail `voix` marqué `pages`).
  Essai : `tryPage()` (adresse → page par `slugFromUrl()`, récit par `Gemini::generate` et voix
  par `Gemini::speech`, tout de suite au tarif normal). La rédaction de nuit ne commence qu'après
  « Lancer pour toutes les pages » (`activate()`, `storage/audio/pages-etat.json`).
  `plan()` liste aussi les voix manquantes des récits à jour (`voices`), confiées par `launch()`
  (`FicheAudio::queueVoices()`). Demande : `PageAudio::speechRequest()` (le récit rangé, passé par
  `speakable()`, voix réglée, sans consigne de ton) ; rangement : `PageAudio::storeVoice()` via
  `FicheAudio::encodeVoice()` (MP3, voir « Voix IA ») dans
  `public/media/audio/pages/{page}-{langue}-{empreinte}.mp3`, ancienne voix supprimée. Jouée
  (`url` du bouton) seulement si son empreinte `th` est celle du récit affiché.
- **Texte lu** (`maxWords()` : durée maximale `audio.max_minutes`, 3 par défaut, × 150 mots),
  par ordre de priorité : écrit à la main (`src: manual`),
  rédigé par Gemini (`src: ai`, valable tant que l'empreinte `sig` des titres, textes et faits
  de la fiche n'a pas changé : modifier une photo ne l'invalide pas), sinon résumé automatique
  construit à la volée (`template()` : date, stade, score, buteurs, carrière, puis
  introduction, texte et brèves de la fiche ; abréviations dites en toutes lettres). La
  consigne de l'IA demande d'expliquer toute la fiche, longueur selon son contenu ; l'empreinte
  `sig` inclut la version de cette consigne et la durée maximale (les changer fait refaire les
  textes IA). Consigne : un historien qui raconte (accroche, décor, récit en paragraphes,
  conclusion), paragraphes gardés (`paragraphs()`, `fitText()`). Modèle de rédaction :
  `textModel()` (`audio.text_model`, sinon `ai.model`). `plan(..., textOnly: true)` /
  `launch(..., textOnly: true)` : textes seulement, sans voix IA (carte « Réécrire les textes
  avec l'IA »). Lots de voix : `voiceBatch()`, environ 300 Mo de résultats quelle que soit la durée. Version anglaise pour les fiches traduites.
- **Voix IA** : `Gemini::speech()` (modèle de voix réglé ou le meilleur disponible, voix
  réglable ; le texte seul est envoyé), PCM 16 bits mono à 24 kHz (`Gemini::speechAudio()`
  recolle les morceaux d'une réponse en plusieurs parties et ignore ce qui n'est pas de
  l'audio). `FicheAudio::encodeVoice()` :
  1. **fin nettoyée** (`trimTail()`) : la synthèse ajoute parfois du bruit (grésillement) ou un
     long silence après la dernière phrase. Dernier son voisé cherché en remontant depuis la fin
     (trames de 20 ms assez fortes et périodiques : autocorrélation ≥ 0,5 pour une hauteur de
     70 à 400 Hz, signal pré-accentué ramené vers 8 kHz ; 3 trames de suite), coupe 0,26 s
     après (consonne finale) ou dès que le niveau tombe 35 dB sous la voix, fondu de 50 ms ;
  2. **MP3** : ffmpeg (LAME, 48 kbit/s) s'il est sur le serveur, sinon l'encodeur du site
     `App\Services\Mp3Encoder` (cas d'o2switch), sinon WAV en dernier recours.

  `Mp3Encoder` : PHP pur, adapté de shine (licence LGPL 2 pour ce seul fichier,
  `docs/licences/LGPL-2.0.txt`) ; MPEG-2 Layer III mono, 16 / 22,05 / 24 kHz, débit constant
  (`FicheAudio::MP3_KBPS` = 64 kbit/s, un peu plus que LAME faute de modèle psychoacoustique).
  Banc de filtres polyphase (fenêtre de la norme, matrice repliée en 32 × 32), MDCT des blocs
  longs, réduction du repliement ; pour chaque granule, le pas de quantification le plus fin qui
  tient dans le débit (dichotomie puis pas à pas), tables de Huffman choisies par région, sans
  facteurs d'échelle ni réservoir de bits : chaque trame se suffit à elle-même, sa fin est
  remplie de données annexes. Quadruplets dans l'ordre de la norme (8v + 4w + 2x + y ; shine
  les inverse). Mesures : voix rendue au même niveau, rapport signal/bruit 45 dB à 64 kbit/s
  (39 à 48), environ 6 % du temps réel en local (3 min de voix : une dizaine de secondes) ;
  décodage vérifié par ffmpeg et Chromium (tests/audio.php).

  Fichier `public/media/audio/{id}-{langue}-{empreinte}.mp3`, servi directement par Apache (mis
  en cache un an : le nom change avec le contenu). Valable tant que l'empreinte `th` du texte
  lu ne change pas. Les voix enregistrées en WAV avant l'encodeur sont converties par la tâche
  planifiée (`convertWavs()`, avec le temps qui reste au passage « audio », fin nettoyée,
  ancien fichier supprimé ; nombre restant affiché dans Système › Fiches audio).
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
  20 fiches ou tout le musée, suivi et annulation des travaux ; téléchargement des voix
  (`GET /admin/audio/telecharger?quoi=fiches|pages&langue=fr|en`, administrateurs) : ZIP fait par
  `Audio::zip()` dans `storage/tmp/` (MP3 rangés sans recompression, noms « titre-langue.mp3 »,
  `sommaire.csv`), supprimé après l'envoi ; une voix seule par lien direct `/media/audio/…`
  avec l'attribut `download`.

## 7 sexies. Rétro-Direct (`App\Services\RetroDirect`, `App\Front\Retro`)

- **Principe** : un match est rejoué en direct le jour et à l'heure programmés
  (`data/collections/retrodirect.json` : `id`, `date`, `time`, `intro`, `intro_en`, `by`,
  `at`). Aucune IA : la chronologie est calculée depuis la fiche et déroulée par le navigateur
  (`public/assets/js/retro.js`) à l'horloge du serveur (heure de la page + `performance.now()`).
- **Chronologie** (`timeline()`, en secondes depuis le coup d'envoi ; une minute de match =
  une minute réelle) : temps forts (`highlights`), buts, entrées en jeu appariées aux sorties
  de la même minute (à deux minutes près ; une sortie seule n'est pas annoncée), cartons,
  mi-temps (15 min), reprise, fin du temps réglementaire, prolongation (5 min de pause, si
  `aet`, « a.p » ou tirs au but avec des minutes au-delà de la 90e), séance de tirs au but
  (8 min), coup de sifflet final. Temps additionnel : « 45+2 », ou « 93 » sans prolongation.
  Les buts viennent des temps forts si leurs scores mènent au score final (retournés quand
  ils sont notés du point de vue de Sochaux), sinon de la ligne des buteurs ; le camp du
  buteur colore le but (jaune pour Sochaux). Noms des joueurs : ceux de la fiche du joueur
  quand elle est mieux accentuée. Il faut 4 événements datés (`playable()`).
- **Programme** (`program()`) : états `avenir`, `direct` (jusqu'à 15 min après le coup de
  sifflet final), `termine`. Durée exacte (chronologie) pour les directs à moins de 2 jours,
  estimée sinon (bandeau, API : `program(…, false)` ne relit pas la fiche).
  `suggestions()` : anniversaires ronds (10, 20, 25, 30, 40, 50… ans) à venir, matchs d'au
  moins 5 temps forts, classés par `interest()` (temps forts plafonnés à 20, coupe, finale ou
  demi-finale, victoire large, 5 buts ou plus, affluence, fiche à la une ; amicaux écartés).
  `classics()` : grands matchs à revivre (un par adversaire). Derived : `hl` (nombre de
  temps forts) dans le résumé de chaque match.
- **Site** : `/interactif/retro-direct/` (direct en cours, prochains directs, grands matchs,
  directs passés), `/interactif/retro-direct/{dernier segment de l'adresse du match}/`
  (modes `live`, `upcoming` : compte à rebours sans score puis rechargement au coup d'envoi,
  `replay` : rediffusion à ×1, « Lancer / Pause », « Temps fort suivant » qui relance le match,
  « Revoir depuis le début », pauses ramenées à 4 s ; commandes dans un pavé collant à droite
  du fil avec rappel du chrono et du score). Chrono minutes:secondes (`clockAt()`, 46:12 dans le
  temps additionnel, 00:00 avant le coup d'envoi d'une rediffusion). Équipe connectée, en direct
  ou avant : pavé « Mode test » (même place) qui décale l'horloge de ce seul navigateur
  (`off`) : Temps fort suivant, Rejouer depuis le coup d'envoi, Revenir au direct. Photos de la
  mi-temps du fil : visionneuse du site (`SR.lightbox`),
  `/interactif/retro-direct/agenda.ics` (tous les directs à venir, ou un seul avec
  `?match=&date=` ; heures UTC, lignes pliées à 75 octets). Bouton « Revivre en direct » sur
  les fiches de match rejouables, message dans le bandeau (en cours, ou dans les 7 jours ;
  case « Rétro-Direct » d'Éditorial › Accueil & bandeau), plan du site.
- **API** `POST /api/retro-direct` (JSON) : `presence` (jeton aléatoire du navigateur toutes
  les 30 s, oublié après 75 s ; spectateurs et pic) et `react` (⚽ 👏 😱) seulement pendant un
  direct, `etais` (« J'y étais ! », par match, 2 par jour et par adresse) ; 40 requêtes par
  minute et par adresse. Compteurs dans `storage/retro/{id}-{date}.json` et
  `storage/retro/etais.json`.
- **Back-office** : Interactif › Rétro-Direct (`App\Admin\Retro`) : programme (modifier,
  retirer), programmation d'un match (date du prochain anniversaire proposée,
  `public/assets/admin/retro.js`), anniversaires des 30 à 365 prochains jours, « Programmer
  à 20 h » en un clic.

## 7 septies. Le Fil jaune (`App\Services\FilJaune`, `App\Front\Fil`)

- **Réseau** : deux joueurs sont coéquipiers s'ils figurent dans la même composition
  (rôle « joueur » des apparitions de `Derived` : titulaires et remplaçants entrés en jeu ;
  fiches publiées seulement). `graph()` le construit à chaque page (quelques centièmes de
  seconde) : `adj` (matchs joués ensemble), premier et dernier match de chaque paire,
  matchs par joueur, adresses (dernier segment de l'adresse de la fiche).
- **Chaîne** (`path()`) : distances depuis l'arrivée (parcours en largeur), puis descente par
  le lien le plus solide (le plus de matchs ensemble) à longueur égale. `find()` retrouve un
  joueur par adresse, numéro ou nom (le plus capé en cas d'homonymes).
- **Records** (`records()`) : familles (composantes), plus grande distance et distance
  moyenne (parcours depuis chaque joueur, 0,2 s), plus connectés, inséparables ; gardés dans
  `storage/cache/fil-jaune.json`, refaits quand `storage/cache/derived.php` change.
- **Défi du jour** (`daily()`) : tirage déterministe par date (`Random\Randomizer` sur
  `Mt19937`, sans toucher au générateur global) de deux joueurs d'au moins 40 matchs à 3 ou 4
  passes ; le visiteur choisit un coéquipier à chaque passe (`public/assets/js/filjaune.js`,
  `GET /api/fil-jaune?id=` : coéquipiers d'un joueur, 300 requêtes par minute et par adresse),
  progression gardée dans le navigateur, score à partager.
- **Pages** : `/interactif/fil-jaune/` (recherche avec liste native `datalist`, défi,
  records ; `?a=&b=` redirige vers la chaîne, `?de=` préremplit), `/interactif/fil-jaune/{a}/`
  (constellation en SVG : coéquipiers en spirale du plus fidèle au plus rare, taille selon les
  matchs ensemble), `/interactif/fil-jaune/{a}/{b}/` (la chaîne, avec le premier match de
  chaque lien). Encadré « Le Fil jaune » sur les fiches des joueurs reliés.

## 7 octies. Kit souvenirs et « Ils y étaient » (`App\Services\Souvenirs`, `App\Front\Kit`, `App\Services\Qr`)

- **Match du mois** (`candidates()`, `match()`) : matchs joués ce mois-là les années passées
  (3 temps forts au moins), classés par l'intérêt du Rétro-Direct, plus un bonus pour un
  anniversaire rond, pour 30 à 60 ans d'âge et pour une vraie photo. Choix des historiens et
  mot d'introduction dans `data/collections/souvenirs.json` (`{"2026-10": {"match", "intro"}}`).
- **Kit** (`kit()`) : récit (buts d'abord, 8 temps forts au plus), brève, six visages (joueurs
  de la composition avec une vraie photo, complétés par les joueurs de l'époque, ordre tiré
  par mois), quiz (premier buteur de Sochaux et affluence, avec des réponses plausibles, puis
  le quiz du site ; 3 réponses au plus, tirage déterministe par mois), questions pour
  raconter, adresse du musée (`legal.address`, sinon `dons.org_address`) et e-mail.
- **PDF** (`Kit::build()`, moteur `App\Pdf\Layout`) : pages A4 en gros caractères, dont « On vous raconte le match » (`Souvenirs::kit()['recit']` = `FicheAudio::current()`, le texte de la version audio), puis la fiche complète en annexe (`PdfExport::buildMatch($doc, $l, false)`) ; le PDF des fiches de match a aussi « On vous raconte le match » ;
  solutions à l'envers (`Layout::textUpsideDown()`) ; QR code vers `/souvenir/{id}/` (redirige
  vers `/contribuer/?type=temoignage&fiche=…`). Cache `storage/cache/pdf/souvenirs-{mois}-{empreinte}.pdf`
  (empreinte : données calculées, choix du mois, adresse du site, langue). Pages :
  `/interactif/souvenirs/` (mois en cours, mois suivant à préparer, six mois passés, derniers
  témoignages), `/interactif/souvenirs/{aaaa-mm}.pdf` (jusqu'à deux mois à l'avance).
- **QR code** (`App\Services\Qr`, PHP pur) : mode octet, correction M, versions 1 à 10
  (213 octets), Reed-Solomon sur GF(256), placement en zigzag, 8 masques et pénalités de la
  norme ; `matrix()` et `svg()`. Vérifié par décodage (versions 1 à 10, accents).
- **« Ils y étaient »** : sur chaque fiche de match, compteur « J'y étais ! »
  (`RetroDirect::etais`, `public/assets/js/etais.js`), témoignages publiés, lien vers le
  formulaire. Un témoignage validé (Communauté › Contributions) avec « Publier ce souvenir »,
  rattaché à une fiche de match, est publié avec un texte et une signature relus
  (`public`, `public_text`, `public_name` de la contribution) ; l'index
  `storage/inbox/temoignages.json` est refait à chaque validation, refus ou suppression.
- **Back-office** : Interactif › Kit souvenirs (`App\Admin\Kit`) : trois mois, choix du
  match, mot d'introduction, PDF ; souvenirs publiés.

## 7 octies ter bis. Carnet du supporter (`App\Services\Carnet`, `App\Front\CarnetPages`)

- **Principe** : chaque supporter coche les matchs vus au stade (domicile et déplacements) et
  obtient son bilan : V/N/D, buts vus, premier et dernier match, plus belle victoire,
  adversaires, buteurs et joueurs vus (d'après les compositions, entraîneurs exclus), décennies,
  « porte-bonheur » (son taux de victoires contre celui du club sur les mêmes saisons, à partir
  de 5 matchs), 14 badges, carte 1200 × 630 (`Share::carnet()`), page publique sous pseudo.
- **Accès sans mot de passe** : `storage/carnets/{id}.json` (e-mail, empreintes des jetons, matchs,
  pseudo) et `index.json` (empreinte HMAC de l'e-mail → id, pseudo → id). Cookie `sr_carnet`
  = `id.jeton` (httponly, 400 jours) ; le lien `/carnet/acces/{id.jeton}/` envoyé à l'e-mail
  l'ouvre ailleurs (5 liens valables au plus).
  Page publique : adresse courte `/{slug}/` (slug = pseudo, unique ; `Carnet::slugFree()` refuse un
  mot réservé ou toute adresse que `Kernel::probe()` sert déjà : route, fiche, rubrique,
  redirection). Servie par `Kernel::dispatch()` après les pages et avant les redirections, donc
  jamais à la place d'une page. `/carnet/p/{slug}/` et les anciens slugs « pseudo-xxxx »
  redirigent (301) ; `upgradeSlug()` passe les anciens au slug court s'il est libre. « Se déconnecter des autres appareils » (API
  `deconnecter`, `Carnet::logoutOthers()`) : un seul jeton neuf pour cet appareil, tous les autres
  appareils et liens envoyés coupés. E-mail obligatoire, un carnet par e-mail : le
  redemander envoie un lien à cette adresse seulement, même réponse que l'adresse ait un carnet
  ou non. Carnet vide jamais confirmé : effacé après 90 jours (tâche « ménage »).
- **Fiches de match** : « J'y étais ! » (`etais.js`) ajoute le match au carnet (création avec
  l'e-mail au premier clic, ou « juste compter » comme avant) et compte une fois dans « Ils y
  étaient ». Les fiches restent dans le cache des pages : l'état du carnet est lu dans le
  navigateur (`localStorage` « sr-carnet », tenu à jour par chaque réponse de `/api/carnet`).
- **Pages** (`/carnet/` hors cache des pages, `private, no-store`) : `/carnet/` (bilan ou
  présentation), `/carnet/saisons/?saison=` (saisie rapide, « domicile seulement »),
  `/carnet/carte.png` (`?telecharger=1`), `/carnet/p/{pseudo-xxxx}/` et sa carte (noindex).
  API `POST /api/carnet` : etat, ajouter, retirer, lot, creer, renvoyer, public, supprimer.
- **Anniversaires** (`Carnet::anniversaries()`, tâche « carnets » toutes les 10 min, à partir de
  9 h) : le jour anniversaire d'un match du carnet (le plus ancien si plusieurs), « Il y a N ans
  jour pour jour, vous étiez au stade » par e-mail (case cochée, carnet confirmé ; lien signé
  `/carnet/rappels/arret/{id}/{signature}/` pour arrêter) et/ou notification vers les appareils
  choisis (`remind_push`, identifiants d'abonnement de l'appli, 5 au plus ; un envoi caché par
  match fêté, `only` = ces appareils). Un rappel par carnet et par jour (`reminded`).
- **Poster « Ma vie en jaune et bleu »** (`App\Shop\CarnetPoster`, `CarnetPosterLayout`) : calque
  « poster » de genre « carnet » (champs `poster_carnet`, `poster_prenom`, `poster_nom`), même grille
  A3 que les posters du match et du joueur. Commandable pour son propre carnet (cookie) ou une page
  publique, à partir de 5 matchs ; `Orders::line()` fige la liste des matchs dans l'article
  (`_carnet_ids`), utilisée ensuite pour l'aperçu et le PDF. Exemple (`exemple`) : matchs à domicile
  de 1985-1986 à 1991-1992, un sur deux. Le catalogue n'affiche jamais un carnet réel (ses aperçus
  sont des fichiers publics) ; la fiche produit montre le carnet de l'appareil. Modèle livré
  inactif (`c4a7e1b3d9`), sans prix.
- **Back-office** : Communauté › Carnets du supporter (nombre, matchs cochés, matchs les plus
  vécus ; aucun e-mail affiché). Tests : `tests/carnet.php`.

## 7 octies ter sexies. Rétro-Direct commenté façon radio (`App\Services\RetroRadio`)

Option par match (Interactif › Rétro-Direct, colonne « Commentaire radio »). Fabrication par
étapes, une à la fois (`step()`, verrou `storage/radio/{id}-{lang}.json.work`) : 1. le texte,
une demande Gemini (`FicheAudio::textModel()`, JSON) avec les événements numérotés du déroulé
(`RetroDirect::timelineFor()`, textes nettoyés des restes de tweets) ; réponse
`{segments: [{e: n° d'événement | m: minute d'ambiance, text}]}` lue par `parse()` (au plus 75
répliques, doublons et didascalies écartés, calées sur l'instant de l'événement ; la consigne
demande des moments d'ambiance pour ne jamais rester plus de 3 minutes de jeu sans parler ;
12 000 jetons de sortie au plus) ; 2. chaque réplique : `Gemini::speech()` sur le seul texte de
la réplique, toujours ponctué (`speakable()`, aucune consigne de lecture : la synthèse la lisait
à voix haute), voix gardée pour tout le commentaire (Fenrir par défaut) ;
`FicheAudio::trimTail(…, 0.6, 48.0)` (fin de phrase gardée 0,6 s après la dernière voyelle) ;
voix trop courte pour son texte (plus de 4,2 mots par seconde) redemandée une fois, la plus
longue gardée ; poste radio `radioize()` (biquads 350 Hz – 3,4 kHz, saturation douce, souffle
et craquements discrets, rumeur sous la voix qui gronde aux buts, 0,4 s avant et 0,7 s après),
MP3 par `Mp3Encoder` dans `public/media/radio/{id}-{lang}-{n}-{hash}.mp3`, version de
fabrication `v` (`VOICE_V` = 3) par réplique : `old` dans `status()` (« Ancienne voix ») si
inférieure ; les répliques `v` = 2 (consigne entendue) ne sont jamais jouées et la tâche les
refait d'office (`spoiled()`, même texte, même voix). Ambiance : prise de son dans le public,
`public/assets/audio/stade-ambiance.mp3` (Work With Sounds / Torsten Nilsson, CC BY 4.0, boucle
de 3 min 29, crédit affiché sous le bouton radio), adresse versionnée par `asset()` ; secours
`media/radio/ambiance.mp3` fabriquée. Étapes lancées par la page (POST
`/admin/retro-direct`, `radio`/`radio-etape`, `ajax=1`) ou par la tâche planifiée « radio »
(40 s par passage). Trois échecs de suite : arrêt (l'équipe relance). Coûts : usage « radio »,
référence `radio:{id}` (`AiCosts`), estimation `estimate()`. `sig` = empreinte des instants,
types et scores du déroulé : si elle change, `playlist()` renvoie null (« À refaire »). Page du
direct : `data.radio` = [{t (instant + 1 s), url, dur, kind}] et `data.ambiance` ; `retro.js`
joue les répliques à leur instant (direct, mode test, rediffusion), reprend une réplique
commencée, rejoue à l'allumage la dernière réplique de moins de 90 s, saute celles en retard de
plus de 90 s, coupe avec la pause ; une ligne d'état dit si le reporter parle, quand il reprend
ou qu'une réplique n'a pu être lue ; la foule (60 %) baisse sous la voix (32 %) et à la mi-temps.
Les sons de `/assets/audio/` ne passent pas par le cache de l'appli (`sw.js`, lecture par morceaux). Réglages :
`audio.radio`, `audio.radio_voice`. Tests : `tests/radio.php` (fausse IA, fausse voix).

## 7 octies ter quater. Quiz du club-house (`App\Services\QuizLive`, `App\Front\QuizLivePages`)

Partie en direct façon jeu télévisé : un grand écran affiche les questions, les joueurs répondent
sur leur téléphone. Pas de websocket : écran et téléphones interrogent `POST /api/quiz-live`
chaque seconde (o2switch, PHP-FPM ; une lecture de fichier JSON par appel).

- **Partie** : `storage/quizlive/{code}.json` (code à 5 chiffres ; `key` = clé de l'animateur,
  24 caractères hexa ; `questions`, `duration` 15/20/30 s, `lang`, `phase` lobby → question →
  reveal → board → … → end, `idx`, `t0` en ms, `players{pid: name, score, tok (sha256)}`,
  `answers{idx: {pid: [choix, ms, points]}}`, `v` incrémenté à chaque écriture). Écritures sous
  verrou (`JsonStore::update`). Ménage : parties sans activité depuis 24 h (tâche « ménage »).
- **Déroulé** : « Suivant » de l'animateur (`next()`) ouvre une question 3 s plus tard (temps de
  lecture), ferme une question en cours, passe de la réponse au classement puis à la question
  suivante ou au podium. Une question se ferme d'elle-même à la lecture (`phase()`) : temps écoulé
  ou tout le monde a répondu. Réponse acceptée une fois, entre `t0` et la fin + 600 ms.
  « Suivant » pendant les 3 s de lecture est ignoré (double appui de l'animateur).
  Points : 500 + 500 × (1 − temps de réponse / durée), 0 si faux ; ex aequo au même rang.
- **Questions** (`questions($n, mix|site|fiches)`) : quiz du site (`Collections` « quiz »,
  traduit) et `fromMatches()` sur les grands matchs (`hl` ≥ 6, hors amicaux) : score (scores
  voisins), année (années voisines sans autre match identique), adversaire (adversaires de la
  même décennie), buteur (`Derived::part('scorers')` contre des titulaires de la composition qui
  n'ont pas marqué, personnes publiées seulement). Réponses mélangées ; rappel du match en
  anecdote. Aucune IA.
- **Pages** (hors cache des pages, `private, no-store`) : `/interactif/quiz-live/?code=` (joueur,
  sans bandeau cookies : `no_cookie`), `/interactif/quiz-live/ecran/{code}/{clé}/` (page autonome,
  QR code `Qr::svg`, 404 sans la bonne clé). Jeton du joueur gardé dans `localStorage`
  (`ql:{code}`), code remis dans l'adresse après l'inscription : un rechargement reprend la
  partie. Retour de veille : état demandé aussitôt ; réponse renvoyée une fois si le réseau
  coupe. Pas de bandeau « Installer l'appli » sur les pages de jeu (quiz, défi). API : `rejoindre` (limite 2 000/h par
  adresse IP, une salle entière partageant la même ; 300 codes faux/h), `etat`, `repondre` (jeton
  du joueur), `ecran`, `suivant`, `retirer` (clé). Téléphones : toutes les secondes pendant une
  question, toutes les 2 à 2,5 s sinon. Test de charge : `tests/charge-quiz.js` (200 joueurs :
  4 ms par appel, 0,3 cœur ; voir docs/PLAN-VITESSE-LANCEMENT.md § 8).
- **Championnat** (`App\Services\QuizChampionship`, `storage/quiz-championnat.json` : `players`
  {id du carnet: pseudo unique, since, last, banned, ok} et `seasons` {« 2026-2027 » : games, scores
  {pts, games, wins, podiums, best}}). Identité = le compte du carnet du supporter (cookie
  `sr_carnet`, lien sécurisé envoyé à l'e-mail, `Carnet::sendLink(…, $quiz)` : texte du quiz et
  `/carnet/acces/{id.jeton}/?quiz={code|championnat}` qui ramène à la partie). Rejoindre :
  compte ouvert → joue sous son pseudo (`players[pid].cid`, même compte = même place) ; nouvel
  e-mail → compte créé et ouvert sur le téléphone ; e-mail connu → lien envoyé, joueur invité
  avec `claim`, rattaché (`QuizLive::claim()`) dès que `etat` arrive avec le cookie de ce compte.
  Fin de partie (`next()` → end) : `QuizChampionship::record()` une seule fois par partie
  (clé code-created), si ≥ 3 joueurs et pas `friendly` ; points = `SCALE[rang-1]` + 1, rang de la
  partie invités compris ; `moves` (rang avant/après) gardé dans la partie pour l'écran.
  Saison du 1er août au 31 juillet. Page `/interactif/quiz-live/championnat/` (hors cache),
  API `championnat` (inscription ou pseudo). Carnet supprimé → `forget()` ; ménage des carnets :
  un joueur qui a joué dans l'année est gardé.
- **Back-office** : Interactif › Quiz du club-house (`App\Admin\QuizClub`), avec le tableau du
  championnat (pseudo déplacé, retrait / réintégration). Tests : `tests/quizlive.php`.

## 7 octies ter sexies. Carte du carnet (`App\Shop\CarnetCard`)

Carte postale 148 × 105 mm dessinée en calques du moteur vectoriel de la boutique, designs `a`
(charte du musée) et `b` (billet de match). `data($ids, $pseudo)` rédige le contenu dans la langue
de la page (`Carnet::stats`), `build()` les calques, `pdf()` le PDF RVB sans traits de coupe
(`/carnet/carte-{a|b}.pdf`, carnet du cookie), `svg()` l'aperçu (`/carnet/carte-{a|b}.svg`).
Pseudo : celui de la page publique, sinon celui du quiz et du défi. Boutique : calque `type: carte`
(`style` a|b) → `CarnetCard::layers()` (champ client `poster_carnet` avec `cmin` 1 ; `_carnet_ids`
et `_carnet_pseudo` figés par `Orders`). L'image de partage 1200 × 630 (`Share::carnet`, GD)
reprend le design `a` avec `logo-sochaux-retro-400.png`. Anniversaire du supporter :
`Carnet::setBirthday()` (« MM-JJ »), `isBirthday()` (29 février fêté le 28), `birthdayMessage()`,
envoyé par la tâche « carnets » avec les rappels jour pour jour (un message par carnet et par jour).
Posters prêts à l'emploi (Boutique › Modèles, action `ready` de `App\Admin\Shop`, style a|b) : support
`poster-paysage` (A4, A3, A2 ; `Catalog::scaleFace()` intervertit largeur et hauteur des formats
papier pour une face plus large que haute), calque `carte` pleine page, modèle inactif sans prix.
Pièce unique (`Catalog::unique()`) : d'office (`uniqueAuto()` : anecdote, poster, carte du carnet)
ou cochée dans le masque du produit (`sale.unique`, pour un produit personnalisé par le client).

## 7 octies ter quinquies. Défi du jour (`App\Services\DailyQuiz`, `App\Front\DailyQuizPages`)

Quiz en solo sur `/interactif/defi/` (hors cache des pages), API `POST /api/defi` (etat,
commencer, repondre, suivante, inscrire). 10 questions de `QuizLive::questions(10, 'mix')`,
tirées au premier appel du jour avec `mt_srand(crc32('defi-' . date . site_key))` (même tirage en
français et en anglais : les questions des fiches utilisent `mt_rand`/`shuffle`), gardées dans
`storage/defi/{date}.json` (`q.fr`, `q.en`, `results`). Partie d'un joueur :
`storage/defi/{date}/{clé}.json` (clé = id du carnet avec pseudo du championnat, sinon invité
`g` + empreinte d'un jeton gardé dans le navigateur) ; question en cours, `t0` serveur, réponses
`[choix, ms, points]`. La bonne réponse n'est envoyée qu'après la réponse ; points comme au
club-house (20 s, 500 à 1 000). Une partie par compte et par jour ; à la fin, résultat du jour,
totaux du mois et de la saison et séries dans `storage/defi/totals.json` (invités : jamais).
Classement du jour : points puis temps total ; mois et saison : points puis jours joués ; joueurs
exclus du championnat exclus aussi. Classements publics (championnat et défi) : comptes `ok`
seulement, c'est-à-dire lien de l'e-mail ouvert au moins une fois (`Carnet::confirm()` →
`QuizChampionship::confirm()`) ; les points d'un compte pas encore confirmé sont enregistrés et
apparaissent ensuite (`ranking(…, all: true)` pour le back-office). Invité devenu membre sur le
même appareil le même jour (jeton d'invité envoyé aussi par un compte) : `DailyQuiz::adopt()`
recopie sa partie, `unranked`, jamais classée. Partie commencée avant minuit : l'API reçoit la
date de la page et termine la partie de la veille si elle est en cours (`unfinished()`). Carnet supprimé → `DailyQuiz::forget()` ; ménage (tâche
« ménage ») : parties en cours de plus de 2 jours. Notification du matin : sujet `defi`
(`app.push_defi`, à l'heure de « Ce jour-là »). Tests : `tests/defi.php`.

## 7 octies bis. Murs de photos (`App\Services\PhotoWall`, `App\Front\Walls`)

Quatre pages de la rubrique Interactif tirent des photos de la médiathèque au hasard à chaque
visite : `/interactif/planche-contact/`, `/interactif/le-lion-illustre/` (journal),
`/interactif/mur-du-vestiaire/` et `/interactif/mosaique/`.

- **Photos montrables** (`PhotoWall::photos()`, `reason()`) : image JPG, PNG, GIF ou WebP ;
  crédit renseigné, ni « DR » (`isDr()` : « DR », « D.R. », « droits réservés », auteur
  inconnu…), ni crédit exclu (`risky()` : un mot de la liste `PhotoWall::RISKY` contenu dans le
  crédit, comparé sans majuscules ni accents, ou une adresse de site web ; liste remplaçable
  dans `data/collections/murs-photos.json`, clé `exclus`), ni « sans auteur » (`noAuthor()` :
  une année, « saison », « années », ou un match « Sochaux-X » dans le crédit, une fois
  l'auteur extrait par `credit()`) ; au moins 300 px sur le petit côté ; au moins une fiche
  publiée qui l'utilise ; fichier présent ; pas de case `nowall` (médiathèque, « Jamais sur les
  murs de photos »). Les raisons (`PhotoWall::REASONS`) sont comptées pour le back-office.
- **Crédit** (`credit()`) : préfixes retirés (« Crédit photo : »), auteur entre parenthèses
  après une légende (« Melisey (Lionel Vadam) »), légende recopiée avant le crédit
  (« Finale 1988. L'est républicain », « Sochaux-Metz 1999-2000 -L'est républicain »),
  photographe d'un journal (« L'est républicain Lionel Vadam » → « Lionel Vadam · L'Est
  Républicain »), « X pour Y », « X / Y » ; graphies d'un même photographe ou d'une même
  source réunies (`ALIASES`). `who` / `key` : le photographe ou la source, pour le filtre.
- **Année** : `Date ou époque` de la médiathèque, sinon la fiche (date du match, année de
  l'objet ou du moment, saison de l'article). Fiche liée (« Voir la fiche ») : match, puis
  personne, article, objet, moment, page.
- **Cache** : `Memo` « murs-photos » (≈ 2 Mo), refait quand l'index des fiches, la médiathèque,
  « utilisée dans », la liste des crédits exclus ou `PhotoWall.php` changent, au plus tard
  après 5 minutes ; 1 s à refaire pour 10 000 médias.
- **Tirage** (`draw()`) : au hasard, deux photos au plus par fiche, celles dont la vignette
  est prête d'abord (bit `t` : 160 et 480 px). Filtres : décennie (`decades()`, 12 photos
  datées au moins) et photographe ou source (`photographers()`, 8 photos au moins) ;
  `counts()` grise les choix vides une fois l'autre filtre choisi.
- **Pages** (`App\Front\Walls`, gabarits `templates/interactif/murs/`, `css/murs.css`,
  `js/murs.js`) : en-tête, filtres et bouton « Nouveau tirage » communs ; `?partiel=1` ne rend
  que le mur, plus les nombres des filtres en JSON (`data-wall-counts`), pour refaire le tirage
  sans recharger (`no-store`, `noindex`). Page filtrée : `noindex`. Agrandissement par
  `SR.lightbox(items, i)` (`site.js`) avec crédit et lien vers la fiche.
  Planche-contact : 36 vues paysage en bandes de 6, loupe ×2,6 (pointeur fin), sans trait de
  crayon (retiré à la demande du client). Journal : Une (photo
  d'au moins 1 000 px de large si possible), deux articles, « En images », brèves ; titres =
  fiches, textes = légendes. Vestiaire : ambiance sombre, fond flouté et très assombri fixé à l'écran
  (`Walls::LOCKER_ROOM` : les maillots jaunes suspendus dans le vestiaire des pros, photo de la
  médiathèque assombrie mais lisible, crédit en bas du mur ; sans elle, carrelage sombre seul) ;
  15 tirages, 3 lignes de 5 (rotation, punaise ou scotch), déplacés au
  pointeur (capture après 6 px : le clic reste un clic). Mosaïque : motif « 100 », « FCSM »,
  « 1928 » ou « 2028 » en lettres de 5 × 7 cases (24 × 11 cases) ou 3 × 5 sur deux lignes pour
  les téléphones (`Walls::grid()`), photos en niveaux de gris teintées (jaune pour le motif),
  crédits regroupés (dix, puis une liste dépliable).
- **Export PDF** (`App\Front\WallPdf`, routes `Walls::PDF`) : le mur contient un formulaire caché
  `#wall-pdf` (refait à chaque tirage) que le bouton « Télécharger en PDF » de la barre envoie en
  POST (attribut `form`) : codes des photos (`PhotoWall::code()`, 10 caractères de l'empreinte du
  chemin, relus par `PhotoWall::byCodes()` : seules les photos des murs sont acceptées), numéro de
  planche ou d'édition, motif, filtres. GET ou aucune photo valable : retour au mur. Fabrication
  par `PdfExport::respond()` (sans cache, limite par adresse IP). Planche : A4, bandes de film
  légèrement tournées (`Layout::rotated()`). Journal : A4, la Une prend la place laissée par
  « En images » et « Brèves » (une seule page). Mosaïque : A4 paysage (`Layout::$pw/$ph`), fond
  sombre (`Layout::$dark` : pied de page clair), cases teintées avec GD comme à l'écran (gris,
  luminosité, contraste, puis jaune ou bleu par la palette), puis une page en couleurs avec tous
  les crédits. Chaque photo est un lien vers sa fiche. Mur du vestiaire : pas d'export.
- **Police manuscrite** : Caveat (OFL 1.1, `docs/licences/OFL-1.1-Caveat.txt`), sous-ensembles
  latin et latin étendu en woff2 dans `public/assets/fonts/`, chargés par `murs.css` seulement.
- **Back-office** : Interactif › Murs de photos (`App\Admin\PhotoWalls`) : photos montrées,
  écartées par raison (lien vers la médiathèque filtrée `?murs={raison}`), liste des crédits
  exclus et ce que retire chaque ligne (`excludedHits()`), crédits montrés, vignettes prêtes
  et « Préparer maintenant » (20 s). Médiathèque : statut de chaque photo et case
  « Jamais sur les murs de photos » (`nowall`, retirée du média quand elle est décochée).
- **Vignettes d'avance** : tâche planifiée `murs-photos` (`PhotoWall::prepare(40)`), à chaque
  passage tant qu'il en manque.

## 7 octies ter. 100 moments du centenaire (`App\Services\Moments`, `App\Services\MomentIdeas`)

- **Dates et numéros** (`Moments`) : chaque moment (fiche `moment`) a la date de parution
  choisie par les historiens : statut `planifie` et `publish_at`, ou `publie` (en ligne tout
  de suite). C'est la validation, une seule suffit : `moment.validated` (qui, quand),
  retirée si le moment repasse en brouillon ou à relire (`Moments::stamp()`). Contrôles à
  l'enregistrement (`check()`) : date à venir (une date déjà enregistrée reste acceptée),
  pas après le centenaire (`home.centenary_date`), 100 moments datés au plus. Numéros
  (`numbering()`, `renumber()`) : un moment en ligne garde le sien ; les moments datés pas
  encore en ligne prennent les suivants dans l'ordre de leur date (leur adresse, qui
  contient le numéro, suit tant qu'ils ne sont pas publics) ; brouillons, moments à relire
  et corbeille n'en ont pas, ni les moments datés au-delà de 100. Renumérotation après
  chaque enregistrement d'un moment, mise à la corbeille, sortie, action groupée (où
  « Publier » laisse les moments de côté : ils se datent un par un) et publication
  programmée. Date de l'événement (`moment.event_date`, sinon date du premier match lié) :
  date anniversaire proposée (`anniversary()`, prochain anniversaire d'ici le centenaire,
  de préférence un jour libre ; 29 février → 28). L'ancien calendrier hebdomadaire
  (`centenary.moments_start`, `scheduleMoment`, glisser-déposer) est retiré.
- **Site** : `Interactive::moments100()` : 100 cases, ouvertes pour les moments en ligne
  (par numéro), « À venir » sans date sinon. Page d'un moment : « Moment n° X · 100 ans,
  100 moments », fil d'Ariane Centenaire › 100 moments. Rien n'indique l'IA.
- **Calendrier** (Éditorial › 100 moments, `Editorial::moments()`) : moments datés dans
  l'ordre (date modifiable tant qu'ils ne sont pas en ligne : `POST /admin/moments/date`),
  moments à dater avec la date anniversaire, alertes (`alerts()` : même jour, trou de plus
  de 4 semaines jusqu'au centenaire, numéro en ligne retiré, au-delà de 100, sans image) et
  rythme (`pace()`). Dans la fiche : numéro (provisoire ou gelé), bouton « Planifier à la
  date anniversaire » (`data-plan-at` : statut et date préparés, Enregistrer valide).
- **Boîte à idées** (`MomentIdeas`, `App\Admin\MomentIdeas`, `/admin/moments/idees`,
  `public/assets/admin/moments.js`) : idées dans `data/collections/moments-idees.json`
  (`ideas` : titre, année, date, pourquoi, thème, sources, photo, état `proposee` /
  `retenue` / `redigee` / `ecartee` et raison, origine `ia` ou `equipe`, fiche ; `runs` :
  50 dernières demandes). Catalogue par époque (`catalog()`, une ligne par fiche publiée,
  numéro en tête) : 40 matchs les plus marquants (`weight()` : finale, demie, Europe,
  barrages, à la une, temps forts, affluence ; pas les premiers tours de coupe contre des
  amateurs), 45 personnes actives à l'époque, articles et objets qui en parlent ; époque
  [0, 0] : sujets transversaux. Sommaire (`propose()`) : une demande par époque (`ERAS`,
  ≈ 150 idées), en parallèle (`Gemini::generateMany`), réponse JSON imposée ; piste
  (`ask()`) : catalogue entier ; « Autre idée » (`replace()`). Chaque demande rappelle les
  idées déjà là et les idées écartées avec leur raison (`memory()`). Réponses lues par
  `parseIdeas()` : sources limitées au catalogue envoyé (sans source : écartée), date
  valide (l'année suit), doublons écartés (même titre, ou mêmes sources la même année).
  Premier jet (`draft()`) : dossier des fiches sources (`WebCheck::describe()`, 6 au plus),
  réponse JSON (titre, accroche, paragraphes, légende, points à vérifier, sources
  utilisées : `parseDraft()`), fiche « À relire » (`draftDoc()`) avec `moment.ai` (modèle,
  sources, points à vérifier, légende proposée ; plus un point si la photo a un crédit DR,
  à risque ou absent) montré dans le back-office seulement. L'IA ne date ni ne publie
  rien, et ne réécrit pas une idée déjà rédigée. « Écrire moi-même » : fiche préremplie
  (`/admin/fiche/nouvelle/moment?idee=…`), idée marquée rédigée à l'enregistrement.
  Coûts : usage `moments`. Session libérée pendant les demandes (une minute environ).
  Essais : `MomentIdeas::$ai` (faux Gemini), `MomentIdeas::$file`.

## 7 nonies. Les chiffres du FCSM (`App\Services\Chiffres`, `/chiffres/`)

- **Sources** (badge de chaque chiffre) : *Carrières* (onglet Statistiques des fiches
  joueurs, lu par `career()` : colonnes « compétition / Buts » rangées par `compKey()`, total
  de la ligne s'il existe, « ? » = saison au club aux chiffres perdus), *Matchs racontés*
  (matchs officiels de `Derived`, sans amicaux, Coupe d'été ni Coupes diverses ; apparitions
  et `scorers`), *Récits des matchs* (temps forts : `goalSequence()` ne garde un match que si
  les scores « (1-0) » avancent d'un but à la fois, dans l'ordre des minutes, jusqu'au score
  final), *Fiches des Lions* (naissance au jour près, taille, pied, formé au club).
- **Garde-fous** : séries comptées seulement dans les blocs de saisons racontées en entier
  (28 matchs de championnat au moins ; `longestRun()` ne passe jamais d'un bloc à l'autre) ;
  compositions signalées `tableau` ou `buts` écartées de tout ce qui vient des compositions ;
  dates signalées `date` écartées des âges ; tableau de carrière recopié sur plusieurs fiches
  gardé pour la seule fiche dont l'arrivée et le départ collent (`dropCopiedCareers()`),
  sinon écarté ; date de naissance donnant moins de 15 ou plus de 45 ans le jour du match, ou
  plus de 36 ans à l'arrivée au club, ignorée.
- **Chapitres** (`CHAPTERS` : titre, chapeau, quota) et ordre de priorité (`ORDER`) : chaque
  chapitre calcule plus de chiffres que son quota ; `quotas()` en retient 100 et comble un
  chiffre manquant par les réserves des autres chapitres. Doublons évités (festival offensif
  identique à la plus large victoire, plus longue fidélité du recordman des matchs,
  invincibilité à Bonal faite de victoires seulement).
- **Passes décisives** (`passer()`) : « passe / centre / corner / coup franc / remise… de X »
  dans le récit d'un but sochalien, X reconnu parmi les joueurs de la composition du match ;
  le buteur est retrouvé par la minute du but (`scorers`).
- **Cache** : `all()` lit `storage/cache/chiffres-{langue}.json` (textes, nombres, dates et
  adresses déjà dans la langue) ; signature : dates de `derived.php`, `index-2.php` et du
  dictionnaire anglais. Calcul complet : 5 s environ ; `warm()` le fait d'avance pour chaque
  langue dans la tâche « statistiques ».
- **Chiffre du jour** (accueil, sous « Ce jour-là ») : `daily()` lit `cached()` (le cache,
  même périmé : un cache absent ou périmé est refait après l'envoi de la page, l'accueil
  n'attend jamais) ; un chiffre par jour, ordre mélangé par cycle (`Random\Randomizer` sur
  `Mt19937`, graine 1928 + numéro du cycle), chacun une fois par cycle. Lien vers
  `/chiffres/#clé` ; réglage `home.daily_figure` (Éditorial › Accueil & bandeau).
- **Page** : `Explore::chiffres()`, gabarit `templates/chiffres.php`, styles
  `public/assets/css/chiffres.css` (compteurs animés par `site.js`, `data-count`, nombres
  entiers seulement). Liens : méga-menu Matchs › Explorer, Interactif › Explorer l'histoire,
  page des records, plan du site.

## 7 decies. Recherche sur le web (`App\Services\WebCheck`)

Aide à l'historien : le bouton **Chercher sur le web** de l'éditeur (carte `[data-webcheck]`,
`public/assets/admin/recherche.js`) appelle `POST /admin/api/recherche-web` (`Api::webCheck`) :
fiche existante, recherche activée et clé Gemini réglée, plafond mensuel (`recherche.monthly_limit`,
300 par défaut, compté dans les cumuls des coûts IA, usage `recherche`), 20 recherches par heure
et par personne ; la session est libérée pendant l'appel (10 à 60 s).

- **Demande** : `WebCheck::prompt()` = consigne (sources seulement, pas d'homonyme ni d'autre
  match, divergences / compléments / pistes, 12 au plus, réponse JSON) + `describe()` : la fiche
  telle qu'elle s'affiche (`MatchText::header()`, « xx » retirés) en lignes courtes, champs vides
  marqués « non renseigné » (match : date, compétition, équipes, score, buteurs, stade, affluence,
  arbitre, composition ; personne : naissance, décès, années au club, totaux du musée, fiche
  d'identité, sélections), plus le début du texte (2 500 caractères). `Gemini::generate()` avec
  l'outil `google_search` (`$opt['tools']`) et la réponse complète (`$opt['raw']`).
- **Réponse** (`WebCheck::parse()`) : JSON lu même entouré de « ```json » ; type et confiance
  normalisés, textes nettoyés et bornés, divergences d'abord. Sources : `groundingChunks`
  (adresses http(s) seulement, liens de redirection de Google gardés tels quels) ; chaque
  proposition est reliée aux pages des `groundingSupports` qui recouvrent l'endroit où elle est
  écrite dans la réponse (positions en octets, comptées dans le texte brut de chaque partie de
  la réponse, `partIndex`). Recherches lancées (`webSearchQueries`) et
  suggestions de Google (`searchEntryPoint.renderedContent`) gardées : le panneau affiche ces
  dernières telles quelles, comme Google le demande, dans un cadre isolé (`iframe` `srcdoc`,
  `sandbox` sans scripts, liens dans un nouvel onglet).
- **Conservation** : dernier résultat de chaque fiche dans `storage/recherche-web/{id}.json`,
  relu 30 jours (« Voir les propositions », sans nouvel appel), effacé ensuite par la tâche de
  ménage. Rien n'est jamais écrit dans la fiche ; le journal d'activité note la recherche.
- **Coût** (`AiCosts`) : jetons du modèle + recherches Google comptées à part (`usage()['search']`,
  ligne `q`, cumul mensuel `search`, demandes du jour `sp`) : Gemini 3 et suivants, 5 000
  recherches gratuites par mois puis 0,014 $ l'une ; modèles plus anciens, 1 500 demandes
  gratuites par jour puis 0,035 $ la demande (`AiCosts::SEARCH`, `searchCost()`).
- **Réglages** › Recherche sur le web : activation, modèle (vide : celui de l'assistant), plafond.
- **Essais** : `WebCheck::$ai` remplace l'appel à Gemini (`tests/recherche.php`).

## 7 undecies. Site de l'association (`App\Vitrine`, www)

La même application sert le musée et le site de l'association. `Vitrine\Host` reconnaît
l'adresse demandée : celle du site (`vitrine.base_url`, par défaut www.fcsochauxretro.com) ou
un alias (`vitrine.aliases`, par défaut le domaine nu, redirigé en 301 vers www) ; jamais
l'adresse du musée (`general.base_url`). Tout le reste est le musée.

`Vitrine\Kernel::dispatch()`, dans l'ordre : `/admin…` → 302 vers le back-office du musée ;
`/api/consentement` et webhooks des paiements → `Front\Api` ; robots.txt et sitemap.xml
propres (`Vitrine\Seo`) ; aperçu de la page d'attente (`?apercu-attente`, aperçu seulement) ;
anciens liens `/?p=` → musée ; site fermé (`vitrine.open` faux par défaut) : page d'attente
503 (pages légales et documents servis, ainsi que l'inscription à la lettre et le teaser si la
page d'attente les montre) ; pages (`Kernel::routes()`) ; adresse
sans barre finale d'une page du site → 301 ; **ancienne adresse WordPress que le musée
connaît** (`App\Kernel::probe()` : page, fiche, rubrique ou redirection) → 301 vers le musée,
même site fermé ; sinon 404 du site.

- **Page d'attente** (`Vitrine\Pages::waiting()`, `templates/vitrine/waiting.php`) : la sienne,
  distincte de celle du musée (`Front\Pages::waiting()`, réglages `waiting.*`). Contenu
  `attente` du Store (`Content::waiting()` : enregistré, sinon `app/Resources/vitrine/attente.php` ;
  un titre ou un texte saisi dans les anciens réglages `vitrine.waiting_title/_text` est repris
  tant que la page n'a pas été enregistrée). Crédit de la photo (`Content::waitingCredit()`) :
  champ `image_credit`, sinon le crédit de la médiathèque, « © » ou « Crédit photo : » de tête
  retirés, affiché « Photo : … » sous la légende ; une page enregistrée avant ce champ ne prend
  le crédit de départ que si elle garde la photo de départ. Écran : pavé › Page d'attente
  (`Association::waitingEdit()`, enregistrement par `/admin/association/contenus/attente`).
  503 + `Retry-After`, `noindex`, `no-store` ; 200 dans l'aperçu
  (`/apercu-association/?apercu-attente=1`, bandeau « Modifier », la lettre y ramène).
- **Aperçu** : `/apercu-association/…` sur l'adresse du musée, réservé aux administrateurs.
  `Host::$prefix` préfixe alors tous les liens internes (`Host::url()`, `Pages::rich()` pour
  les textes saisis, redirections) ; `Site::$preview` montre les contenus « à vérifier »
  (étiquette rouge) et un bandeau ; réponses `noindex` et `no-store`.
- **Contenus** (`Vitrine\Store`, `Vitrine\Content`) : contenus de départ dans
  `app/Resources/vitrine/*.php` (livrés et mis à jour avec le code) ; dès qu'un contenu est
  enregistré dans le pavé, `data/vitrine/{nom}.json` le remplace (versions dans
  `storage/versions/vitrine/`). Pages : un fichier par page (`page-{clé}.json`), complété par
  les champs de départ ajoutés plus tard. Une actualité ou un événement « à vérifier »
  (`a_verifier`), un brouillon ou une actualité datée dans le futur ne sont pas publics ;
  un membre de l'équipe sans nom n'est pas affiché.
- **Agenda** : événements saisis + Rétro-Direct programmés au musée (`vitrine.agenda_retro`)
  + centenaire ; export iCalendar `/agenda/agenda.ics` (lignes repliées à 75 octets).
- **Formulaires** (`Vitrine\Forms`) : adhésion, bénévolat, contact (boîte
  `storage/inbox/messages/`, champ `site: association`), newsletter du musée sans session
  (résultat dans l'adresse : `?nl=code&f=formulaire`). Antispam : CSRF (sauf newsletter),
  champ piège, horodatage signé `form_ts()`, limites par IP (`vt-*`).
- **Adhésions** (`Vitrine\Membership`, `storage/vitrine/adhesions.json`) : Stripe Checkout
  (`metadata.adhesion`) ou commande PayPal (`custom_id` « A… ») avec les clés des dons ;
  statut relu chez le prestataire au retour, par les webhooks des dons (étendus :
  `Front\Donations::stripeWebhook()` et `paypalWebhook()` passent la main aux adhésions),
  ou par la tâche planifiée (`Membership::expire()`). Paiements idempotents (référence du
  prestataire). Chèque : statut `offline`, réglé dans le pavé.
- **Documents publics** (`Vitrine\Documents`) : `data/vitrine/documents/` (dépôt dans le pavé :
  PDF, images, ZIP, contrôlés par extension et type réel) et le dossier de presse livré
  (`app/Resources/vitrine/fichiers/`), servis par `/documents/{fichier}`.
- **Vidéos** (`Vitrine\Videos`) : flux RSS public de la chaîne YouTube, relu toutes les
  6 heures par la tâche `association` ; vignettes copiées dans `storage/vitrine/videos/` :
  aucune requête vers Google depuis les pages, d'où l'absence de bandeau cookies.
- **Audience** (`Vitrine\Stats`) : une ligne « heure|page » par page vue
  (`storage/vitrine/stats/`), robots exclus, rien tant que le site est fermé.
- **Gabarits** `templates/vitrine/`, styles `public/assets/css/vitrine.css` (en plus de
  `site.css`), script `public/assets/js/vitrine.js` (menu mobile, formulaire d'adhésion).
- **Pavé d'administration** `App\Admin\Association` (`/admin/association…`, dans
  `Router::ADMIN_ONLY`) : tableau de bord et `checklist()`, éditeurs génériques (schémas,
  `cleanItem()`, adresses uniques), pages (champs déduits du contenu de départ par
  `pageFields()`), adhésions, bénévoles, réglages (groupe `vitrine`, caché de l'écran
  Réglages général par `'hidden' => true`).

## 7 duodecies. Application du musée (PWA) et notifications (`public/sw.js`, `App\Front\Appli`, `App\Services\WebPush`, `App\Services\Notifications`)

Seulement le musée (`templates/layout.php`) ; le site de l'association n'a ni manifeste ni
service worker. Réglage `app.enabled` (Réglages › Application du musée) : décoché, la page
porte `data-app="0"`, `site.js` désinscrit le service worker et efface ses copies, et le
service worker lui-même se retire dès qu'il voit une page ainsi marquée.

- **Manifeste** `public/manifest.webmanifest` (nom, couleurs, icônes `assets/img/app/` tirées du
  logo vectoriel, raccourcis : Rétro-Direct, 100 moments, quiz, recherche) ; servi en
  `application/manifest+json` et revérifié à chaque fois (`.htaccess`, comme `sw.js` et
  `hors-ligne.html`).
- **Service worker** `public/sw.js` (portée `/`) : pages en réseau d'abord (préchargement des
  pages activé ; la copie gardée sert si le réseau manque, ou au bout de 6 s si elle existe et
  que l'adresse n'a pas de paramètres), cache `sr-pages` (80 pages, sans paramètres, jamais une
  réponse `private`/`no-store`) ; `/assets/` et `/media/{largeur}/` en cache d'abord (`sr-static`
  120, `sr-images` 300) ; page `hors-ligne.html` (bilingue, liste des pages gardées) gardée dès
  l'installation (`sr-shell-v1`). **Jamais gardés** : `/admin`, `/imprimeur`, `/api`, `/video`,
  `/apercu-association` (BYPASS) et `/boutique`, `/faire-un-don`, `/contribuer`, `/contact`,
  `/newsletter`, `/souvenir` (NOSTORE). Leurs pages passent par la réponse préchargée
  (`preloadResponse`) : sans cela, le navigateur les demanderait deux fois au serveur (message
  « Enregistré » perdu après une redirection).
- **Page `/appli/`** (`Front\Appli::page`, `templates/appli.php`, `js/appli.js`) : bouton
  d'installation (`beforeinstallprompt`, gardé par `site.js` dans `SR.app`), marche à suivre
  iPhone, avantages, notifications. Lien en pied de page.
- **Bandeau « Installer l'appli »** (`partials/app-banner.php`, `site.js`, réglage `app.banner`) :
  téléphone seulement (pointeur tactile, petit écran), à partir de la 2e page vue de la session
  (`sessionStorage`), 2,5 s après l'arrivée, jamais par-dessus le bandeau des cookies ni dans
  l'appli installée ; bouton « Installer » (`beforeinstallprompt`) ou, sur iPhone, les gestes
  Partager › Sur l'écran d'accueil ; « Plus tard » ou installation refusée → 30 jours
  (`localStorage` `sr-appli-plus-tard`). Absent de `/appli/`, de la boutique, des dons et des
  formulaires.
- **Page d'attente** (`templates/waiting.php`, `js/attente.js`) : manifeste et service worker
  (installable : nécessaire aux notifications sur iPhone ; la page, en 503 `no-store`, n'est
  jamais gardée) et bouton « Prévenez-moi de l'ouverture » (réglage `app.push_waiting`) :
  abonnement au sujet `nouvelles`, marqué `w` (« page d'attente »). Communauté › Notifications
  propose alors l'annonce de l'ouverture, prête en deux langues, envoyée sous la clé `ouverture`
  (une seule fois) ; la carte reste tant qu'elle n'est pas partie et que des inscrits l'attendent.

**Notifications Web Push**, sans bibliothèque (OpenSSL et cURL de PHP) :

- `WebPush` : clés VAPID P-256 créées au premier besoin (`storage/push/vapid.json`, 0600) ;
  jeton ES256 (RFC 8292) par service de notifications ; chiffrement « aes128gcm » du message
  pour chaque navigateur (RFC 8291 : ECDH avec sa clé, HKDF, AES-128-GCM, un enregistrement) ;
  envoi en parallèle (`curl_multi`, 20 à la fois) **seulement** vers les services reconnus
  (`HOSTS` : Google, Mozilla, Apple, Microsoft ; HTTPS, port 443) — jamais vers une adresse
  quelconque donnée par un navigateur. `available()` : courbe, ECDH, AES-GCM, cURL (signalé
  dans la vérification du serveur).
- `Notifications` : abonnés (`storage/push/abonnes.json` : adresse, clés, sujets, langue, dates,
  échecs ; ni nom, ni e-mail, ni IP) ; sujets `retro`, `moments`, `nouvelles`, `kit`, `jour` ;
  file d'envoi (`file.json`, lots de 400, verrou `envoi.lock`, message dans la langue de
  l'abonné) ; 404/410 → abonnement effacé ; 10 échecs sans succès depuis 60 jours → effacé ;
  historique (`envois.json`, 150 derniers, reçues, disparus, échecs, ouvertures) ; clés déjà
  envoyées (`faits.json`, 400 jours) et date de mise en route (rien d'antérieur n'est envoyé).
- Envois automatiques (`due()`, tâche planifiée `notifications`) : Rétro-Direct 30 min avant le
  coup d'envoi (jusqu'à 10 min après, urgent, durée de vie jusqu'à 30 min après le début),
  moments du centenaire à leur parution (3 jours au plus), kit souvenirs (1er au 7 du mois, à
  partir de 10 h), « Ce jour-là » (heure réglée, 2 h de fenêtre). Rien entre 21 h 30 et 8 h
  sauf Rétro-Direct.
- API publique `/api/push/…` (JSON, `Front\Appli::api`) : `cle`, `etat`, `abonner`, `sujets`,
  `desabonner`, `renouveler` (service worker, `pushsubscriptionchange`), `essai` (3 par heure
  et par abonné), `ouverture` (clic compté). L'adresse d'abonnement, impossible à deviner, sert
  de preuve. Ouverte même quand le site est en page d'attente ou derrière le mot de passe
  d'accès (`Kernel`, comme les webhooks des dons) : la page d'attente s'en sert.
- Service worker : `push` → `showNotification` (icône, badge monochrome, image, `tag` par
  sujet) ; `notificationclick` → page du musée seulement (focus d'une fenêtre ouverte, sinon
  nouvelle fenêtre) et ouverture comptée ; `pushsubscriptionchange` → réabonnement.
- Back-office : Communauté › Notifications (`Admin\Push`, administrateurs) : chiffres, envoi de
  l'équipe (aperçu, version anglaise facultative), envois automatiques prêts, historique.
- Développement : `PUSH_ALLOW_LOCAL=1` (serveur `php -S` seulement) accepte un faux service
  de notifications sur 127.0.0.1 ; `tests/notifications.php` déchiffre chaque message envoyé
  avec une implémentation indépendante et vérifie le jeton VAPID.

## 8. Back-office

- `App\Admin\Router` : connexion obligatoire (sauf connexion, premier accès, invitation,
  mot de passe oublié), jeton CSRF sur toute requête POST (champ `_csrf` ou en-tête
  `X-CSRF`), en-têtes `X-Frame-Options: DENY` et `noindex`.
- Deux niveaux (`App\Core\Auth::can()`) : l'utilisateur peut tout faire sauf la gestion
  des comptes (`users`), les réglages (`settings`), la suppression définitive (`destroy`)
  et la restauration de versions (`restore`, `backup_restore`). Écrans techniques réservés
  aux administrateurs (`Router::ADMIN_ONLY`, menu `Base::NAV`) : Assistant IA, Fiches audio
  (traitement groupé), Coûts IA, Sauvegardes, Tâches planifiées, reçus fiscaux
  (`/admin/dons/recu/…`) ; les coûts d'IA ne sont affichés qu'aux administrateurs
  (`Base::aiCost()`, `aiCostData()`). Dans `App\Admin\Donations`, l'enregistrement d'un don
  hors ligne (`manuel`), l'émission d'un reçu (`recu`) et le remboursement noté d'un don hors
  ligne (`rembourse`) sont refusés aux autres comptes, et cachés dans les écrans.
- **Formulaires** (`App\Admin\Form`) : chaque champ = libellé `.f__k` puis bloc `.f__c` (champ,
  aide, compteur) ; dans une grille `.fgrid` (colonnes fixes `.fgrid--2c`, `--3`, `--4`, qui
  passent à 2 puis 1 colonne), chaque champ occupe deux rangées partagées (`grid-template-rows:
  subgrid`) : un libellé sur deux lignes décale toute la rangée et les champs restent alignés.
  Champs écrits à la main dans les gabarits : `admin.js` (`wrapFields`) leur ajoute le bloc
  `.f__c` au chargement. Hauteur commune 44 px ; sous-parties `.fsec` ; listes en lignes
  (`rep--rows`) : libellés au-dessus de la première ligne seulement. Fiche neuve
  (`.editor.is-new`) : pas de bordure « à compléter ».
- **Favoris** (`App\Admin\Favorites`) : ligne ★ de la bande du haut (`templates/admin/layout.php`),
  liens directs propres à chaque compte (clé `favoris` de `storage/users.json`, 12 au plus,
  nom de 40 caractères). « + Ajouter un favori » ouvre une fenêtre (`admin.js`, données
  `#bo-favs` : favoris, page affichée, `choices()` = écrans de `Base::NAV` permis, créations
  `Base::createLinks()`, groupes des Réglages pour les administrateurs) ; `POST
  /admin/api/favoris` (`ajouter`, `retirer`, `renommer`, `ordre`). Adresses du back-office
  seulement (`clean()` : `/admin`, `/admin/…`, `/admin?…`, ni espace ni `//`) ; les écrans
  réservés aux administrateurs (`Base::NAV`, `Router::adminOnly()`) ne sont ni proposés ni
  gardés pour les autres comptes.
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
  enregistrement d'une fiche reprise archive d'abord son état d'origine. Enregistrer sans
  rien changer ne crée ni version ni modification (« Aucune modification ») :
  `FicheForm::apply()` garde la valeur d'origine de tout champ que la personne n'a pas
  touché, même quand le masque ne sait pas la représenter (HTML repris de l'ancien site,
  date à la seconde, date partielle non reconnue comme « juin 1978 ? », score « a.p » ou
  « (4-5 tab) », lieu de décès détaillé, date en toutes lettres et saison d'un match (sauf
  en-tête qui contredit la fiche : remplacé, voir `MatchText::header()`), ligne
  de jeu hors liste, vidéo « iframe »), et `settle()` laisse un champ vide sous sa forme
  d'origine (`null`, `""` ou liste vide) sans ajouter de champ vide. « Déjà publiée »
  (`published_once`) n'est retenu qu'au changement de statut. Vérification :
  `tests/fiche-form.php`.
- 100 moments du centenaire : la date de parution est choisie par les historiens
  (« Planifié » et sa date, ou « Publié ») et contrôlée à l'enregistrement
  (`Moments::check()` : date à venir, avant le centenaire, 100 moments datés au plus) ;
  le numéro suit l'ordre des dates (`Moments::renumber()`, voir § 7 octies ter).
- Réglages (`config/settings.php` pour la liste, `storage/settings.json` pour les
  valeurs) ; les secrets (clés API, mots de passe) sont chiffrés avec sodium
  (`storage/secret.key`) et ne sont jamais renvoyés au navigateur.

### Boutique (lot A : création des modèles)

- `app/Shop/Vector.php` : moteur de dessin. Calques (logo, texte, rectangle, rond) en mm → tracés ; rendu SVG (aperçus) et PDF vectoriel (fichier imprimeur : CMJN, format fini `TrimBox`, fonds perdus `BleedBox`, traits de coupe, mention en marge). Les textes sont convertis en contours (`Pdf\TrueType::outline()`), le PDF n'embarque ni police ni image.
- `app/Resources/shop/logo-sochaux-retro.svg` : logo redessiné en vectoriel (bleu #094687, jaune #FDC729), utilisable en deux couleurs ou en une.
- `app/Shop/Catalog.php` : supports (t-shirt, sweat, mugs, tote bag, casquette, écharpe, posters A3/A2, carte, sticker ; dimensions surchargées dans `data/collections/boutique-supports.json`) et modèles (`boutique-modeles.json`), champs à remplir par le client (`mode: client`).
- `app/Shop/Mockup.php` : aperçus vectoriels sur le produit (silhouettes, mug courbé, casquette découpée, écharpe à franges).
- `app/Admin/Shop.php` + `templates/admin/boutique/` + `public/assets/admin/boutique.{js,css}` : écrans « Boutique » (administrateurs seuls), éditeur de modèles, aperçu JSON, PDF imprimeur (`/admin/boutique/modeles/{id}/pdf`, `?rvb=1` pour un PDF RVB).
- `app/Shop/Texts.php` : banque de textes (`data/collections/boutique-textes.json`, départ : `app/Resources/shop/textes-depart.json`) ; un champ du client peut être un « choix dans une liste » (`list`) : seules les phrases validées sont acceptées. Cadre de texte (`h`, hauteur en mm) et corps minimum (`min`, pt) : `Vector::layout` réduit puis coupe en lignes ; `Vector::fits` dit si le texte tient ; `Catalog::fields` écarte les phrases trop longues (`rejected`) ; `Catalog::check` contrôle les réponses avant commande. Propositions de l'IA (Gemini, usage `boutique` des coûts IA) ajoutées « à valider ».
- Lot B (vente) : `Catalog::sale/applyOptions/positions/printPdf` (prix, choix du client), `app/Shop/Orders.php` (réglages et commandes dans `storage/shop/`, Stripe Checkout, PDF par article, e-mails, messages, remboursements ; webhook : `Front\Donations::stripeWebhook`, métadonnée `commande`), `app/Shop/ShopPages.php` + `templates/boutique/` + `css/boutique.css`, `js/boutique.js` (boutique sur le musée, /boutique/ ; www/boutique/… redirige), `app/Shop/PrinterSpace.php` (/imprimeur/ sur le musée, connexion par lien à usage unique), BO : Boutique › Commandes, Réglages.
- Lot C (gestion) : `app/Shop/Accounts.php` (tableau de bord, frais Stripe `fetchFee`, litiges `dispute` via webhook `charge.dispute.*`, rapprochement `reconcile`, alertes + e-mail quotidien `mailAlerts` dans la tâche « association », relevés mensuels PDF/CSV) ; coût de fabrication par support (`cost`) et coût d'expédition (`ship_cost`) figés sur la commande au paiement. `app/Shop/TonMatch.php` : champs `match_*` remplis d'après une date (Derived matches/scorers). BO : Boutique › Tableau de bord, Relevés imprimeur ; espace imprimeur : /imprimeur/releves.
- `app/Shop/Promos.php` : codes promo (storage/shop/promos.json), coupon Stripe à usage unique au paiement ; pastille du panier : cookie `sr_cart` lu par `js/site.js`.
- Pièces uniques (`app/Shop/Anecdotes.php`) : faits du musée (`fact`, `topicFacts`, classés du plus parlant au plus banal : records des 100 chiffres, chiffre clé, paragraphes de fiche, coulisses, bilan, matchs), sujets du client (`topics` : joueurs et matchs, `m:ID` / `p:ID`), `topicRich` (sujet sans matière → anecdote générique + note), rédaction Gemini (consigne « anecdote, pas compte rendu »), refus si nom ou nombre absent du fait (`foreignNames`, `numbers`), signature HMAC, réserve versionnée par sujet (`storage/shop/anecdotes-reserve.json`, `VERSION`), anecdotes vendues jamais reproposées. Routes : POST `/boutique/anecdote/` (CSRF, cadences, budget `anec_daily`), GET `/boutique/anecdote/sujets/?q=`.
- Poster souvenir (`app/Shop/Poster.php`, `app/Shop/PosterLayout.php`) : calque `poster` (Vector l'étend en calques), contenu d'après la fiche (`data`, `search`, `eligible`), enrichissement IA vérifié et mis en cache (`enrich`, `storage/shop/posters/{id}.json`, usage `boutique-poster`), mise en page en mosaïque (A3, 12 colonnes, mm), numéro de pièce (`number`, `storage/shop/posters-numeros.json`). Routes : GET `/boutique/poster/matchs/?q=`, POST `/boutique/poster/preparer/`.
- Formats A (`Catalog::PAPER`, `scaleFace`) : un support papier au format A sans tailles propose A4, A3, A2 ; le PDF de la commande est mis à l'échelle au format choisi. SVG d'aperçu allégé : chaque lettre dessinée une fois (`<defs>` + `<use>`), PDF inchangé.
- Équipe de Sochaux Rétro : `templates/partials/team-cards.php` + `css/team.css`, `js/team.js` (cartes recto-verso) ; association `/association/equipe/`, musée `/supporters/l-equipe-de-sochaux-retro/` (`Front\Pages::team`), données `Store::get('equipe')` (champs `mission`, `anecdote`, `text`).
- Tests : `tests/boutique.php`, `tests/boutique-commandes.php`, `tests/poster.php`, `tests/anecdotes-sujets.php`.

## 9. Tâches planifiées

Une ligne de cron toutes les 5 minutes (`php bin/console.php cron`) ; chaque tâche a sa
fréquence, l'état est dans `storage/cron.json`, un verrou empêche deux passages simultanés
(lancée depuis le back-office pendant un passage, une tâche le signale au lieu de dire
« rien à faire » ; une sauvegarde ratée est notée en échec) :
publication des fiches programmées, statistiques (et les 100 chiffres du FCSM), audience, traductions, correcteur
d'orthographe, notifications de l'appli (envois automatiques et file d'envoi), newsletter,
géolocalisation (toutes les 10 min), médiathèque, vignettes des murs de photos (tant
qu'il en manque), vignettes des vidéos (toutes les heures),
assistant IA (toutes les heures), dons (toutes les heures), plan du site (chaque jour),
site de l'association (toutes les heures : vidéos YouTube, adhésions non confirmées,
journaux d'audience), sauvegarde, reçus annuels, purges RGPD (dont adhésions abandonnées
après 30 jours, coordonnées des adhérents 3 ans après leur année, bénévoles sans suite
après 2 ans).

## 10. `storage/` (hors dépôt)

| Dossier ou fichier | Contenu | Sauvegardé |
|---|---|---|
| `media/originals/` | photos originales | le dimanche si l'option est cochée |
| `users.json`, `settings.json`, `secret.key` | comptes, réglages, clé de chiffrement | oui |
| `versions/` | historique des fiches et des collections | oui |
| `inbox/`, `newsletter/`, `dons/`, `votes/`, `counters.json`, `activity/` | messages et contributions (dont `inbox/temoignages.json`, témoignages publiés), abonnés, dons, votes, compteurs, journal d'activité | oui |
| `ai/` | index et journal de l'assistant | non (reconstructible, journal purgé) |
| `correcteur/` | résultats de la vérification de fond, corrections ignorées | non (recalculé) |
| `recherche-web/` | dernier résultat de la recherche sur le web de chaque fiche, 30 jours (§ 7 decies) | non (refait en relançant la recherche) |
| `ia/` | dépense d'IA : détail des appels, cumuls, remboursements, barème (§ 7 quater) | oui |
| `audio/` | fiches audio : texte lu et voix IA de chaque fiche, traitements groupés (§ 7 quinquies) ; `audio/pages/` : récits des pages de synthèse rédigés par l'IA ; `audio/jobs/` : fichiers d'échange temporaires | oui (sauf `jobs/`) ; les voix IA (`public/media/audio/`) avec les photos, le dimanche |
| `retro/` | Rétro-Direct : spectateurs connectés, pic et réactions de chaque direct, « J'y étais ! » par match (§ 7 sexies) | oui |
| `push/` | notifications de l'appli : clés VAPID (`vapid.json`), abonnés, file d'envoi, historique, envois déjà faits (§ 7 duodecies) | oui |
| `vitrine/` | site de l'association : adhésions, propositions de bénévolat, audience, vidéos YouTube récentes (§ 7 undecies) | oui |
| `verrous.json` | fiches et écrans ouverts en ce moment (verrou de modification) | non (temporaire) |
| `controle.json` | dernier contrôle complet (bouton « Contrôler maintenant ») : clés des alertes, nouvelles, historique | non (le contrôle suivant le refait ; sans lui, comparaison avec la référence livrée) |
| `cache/`, `sessions/`, `ratelimit/`, `logs/`, `backups/`, `import/` | fichiers techniques (dont `cache/fil-jaune.json`, records du Fil jaune, et `cache/chiffres-*.json`, les 100 chiffres) | non |

## 11. Sécurité

- Seul `public/` est exposé ; `public/.htaccess` interdit l'affichage des dossiers et
  ajoute les en-têtes de sécurité aux fichiers statiques.
- Politique CSP sur toutes les pages HTML ; jeton CSRF sur tous les formulaires.
- Mots de passe hachés en Argon2id ; cookie de session `HttpOnly`, `SameSite=Lax`,
  `Secure` en HTTPS, identifiant de session imposé refusé (`use_strict_mode`), nouveau
  jeton CSRF à la connexion ; déconnexion après 30 minutes sans activité (`Auth::IDLE`) :
  le navigateur (`admin/session.js`, délai donné par `<body data-idle>`) suit clavier, souris
  et toucher dans tous les onglets (stockage local), avertit 2 minutes avant (« Rester
  connecté »), signale une saisie sans enregistrement au plus toutes les 4 minutes
  (`/admin/api/actif`) et demande la déconnexion à l'heure dite (`/admin/api/inactif`,
  refusée si le serveur a vu une action récente, notée au journal) ; le serveur garde la date
  de la dernière action (`seen` en session) et ferme lui-même la session après le délai et
  5 minutes de marge (page fermée). Les appels automatiques (verrou des fiches, coûts de
  l'IA) portent `X-BO-Background: 1` (ou `_bg=1` pour le signal de fermeture) et ne
  prolongent pas la session. Essais : `BO_IDLE_SECONDS` (serveur de développement de PHP
  seulement) ; limitation des tentatives (connexion : 8 par compte et par
  adresse et 30 par adresse en 15 minutes, 30 par compte en une heure, débloqué par « Mot
  de passe oublié » ; une adresse IPv6 compte pour son bloc /64 ; formulaires, recherche,
  assistant, dons : quotas par adresse) ; même durée de réponse que le compte existe ou non
  (empreinte factice, e-mail de réinitialisation envoyé après la réponse).
- Liens d'invitation et de réinitialisation construits avec l'adresse du site réglée, jamais
  avec l'en-tête `Host` (sans adresse réglée, le lien n'est pas envoyé). Un compte désactivé
  ne peut pas être réinvité. Back-office ouvert sur une autre adresse que celle réglée
  (sous-domaine d'essai) : le tableau de bord et l'écran Utilisateurs le signalent aux
  administrateurs (`Base::addressMismatch()`, « www. » ignoré).
- Page d'attente et mot de passe d'avant-lancement : l'API publique est fermée aussi (sauf
  les webhooks de paiement et le consentement aux cookies).
- Adresses : caractères de contrôle retirés du chemin et des redirections (pas de
  redirection vers un autre site par `/%09/…`) ; chemins de la médiathèque contrôlés
  segment par segment (`Media::safeRel`). Une redirection avec « .. » ou un caractère
  invisible est refusée ; la vérification des redirections (Qualité) ne lit que des fichiers
  de `public/` (chemin réel contrôlé) et de la médiathèque : elle ne révèle pas si un fichier
  existe ailleurs sur le serveur. Scripts de copie des photos (`scripts/wp/`) : nom de fichier
  vérifié (`safe_media_rel()` : jamais hors du dossier, extensions de média seulement).
- Rôles : les règles réservées à l'administrateur sont appliquées côté serveur (écrans,
  API, envois de formulaires), pas seulement masquées dans le menu ; les montants des coûts
  de l'IA (bulles d'aide, éditeur, correcteur, aide en ligne, guide PDF) ne sont montrés qu'aux
  administrateurs (`Tips::withoutCosts()`, parties `admin` de l'aide).
- HTML : dictionnaire anglais nettoyé à l'enregistrement ; `safe_html()` retire aussi les
  attributs d'événement collés (`<a/onclick=…>`) et les balises `base`, `frame`, `svg`…
- Newsletter : confirmation et désinscription par un bouton (les messageries ouvrent seules
  les liens des e-mails) ; envoi sous verrou, file enregistrée après chaque e-mail.
- Exports CSV : cellules commençant par `= + - @` préfixées d'une apostrophe (`csv_safe`).
- Contributions : 3 Go de fichiers en attente au plus ; fichiers servis avec leur type
  vérifié et `nosniff`.
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
- `php tests/fiche-form.php` : masque de saisie (enregistrement sans modification qui ne
  change rien, valeurs reprises gardées, vraies modifications appliquées, moments planifiés).
- `php tests/correcteur.php` : correcteur d'orthographe (texte des champs, règles du musée,
  contrôle des propositions de Gemini simulé, mots protégés, cache, découpage).
- `php tests/couts.php` : coûts de l'IA (barème daté, calcul, cumuls, niveau gratuit,
  budget et pause, remboursements, relevé PDF, détail CSV).
- `php tests/verrou.php` : verrou de modification (prise, observation, prise de main,
  onglets multiples, libération, expiration, inactivité).
- `php tests/audio.php` : fiches audio (résumés automatiques, texte retenu, voix enregistrée,
  rangement des résultats d'un traitement groupé, coût à moitié prix, barème des voix) et pages
  de synthèse racontées (face-à-face en français et en anglais, saison, coupe, Bonal, records,
  chiffres, texte préparé pour la voix, audio désactivé ; récits de l'IA : même empreinte en
  français, en anglais et au traitement groupé, récit dépassé remplacé par l'automatique, pages
  à rédiger, demande envoyée, rangement des résultats, coût compté ; voix des pages : demande sur
  le récit rangé, rangement et lecture sur la page, ancienne voix plus jouée quand le récit
  change, voix commandées après les récits, voix des pages désactivées ; fin de voix nettoyée,
  encodeur MP3 : trames, silence, 22,05 kHz, décodage par ffmpeg s'il est là ; conversion des
  voix WAV, morceaux de voix recollés, résumés gardés d'un affichage à l'autre).
- `php tests/entete.php` : en-tête des matchs (date et tour en toutes lettres contredits,
  match en retard non signalé, journée affichée en français et en anglais, page, audio, IA
  et assistant, enregistrement qui suit la journée saisie) et garde-fou « Est-ce bien le même
  match ? » (autre adversaire, autre date, date corrigée d'un jour, même club autrement écrit,
  fiche vide, premier adversaire saisi).
- `php tests/recherche.php` : recherche sur le web (fiche décrite à Gemini, en-tête contredit
  écarté, champs vides signalés, propositions lues et reliées à leurs pages, adresse non web
  écartée, réponse illisible ou en plusieurs parties, champs mal formés, résultat gardé 30 jours
  puis effacé, coût des recherches Google).
- `php tests/perf.php` : optimisations sans changement de résultat (cache de calculs, repliement
  ASCII identique à ICU, index des apparitions, ordre des rubriques, plans de l'écran audio).
- `php tests/favoris.php` : favoris du back-office (adresses du back-office seulement, choix
  selon le rôle, favoris d'un compte filtrés et noms nettoyés).
- `php tests/session.php` : déconnexion après 30 minutes sans activité (action qui prolonge,
  appel automatique qui ne prolonge pas, marge du serveur, déconnexion demandée par le
  navigateur refusée après une action récente, avis sur la page de connexion).
- `php tests/accueil.php` : slider de l'accueil (tirage limité aux photos d'au moins
  1 200 × 600 pixels, petites photos écartées, liste gardée en cache, tirage de secours sur
  toutes les vraies photos ; signalement dans la fiche : image trop petite, absente,
  générique ou inconnue de la médiathèque).
- `php tests/retro.php` : Rétro-Direct (chronologie : buts et score, mi-temps, prolongation,
  tirs au but, score retourné, buteurs ; programme et états ; anniversaires ; spectateurs et
  réactions ; agenda .ics).
- `php tests/filjaune.php` : Fil jaune (réseau symétrique, joueurs retrouvés, chaîne la plus
  courte, liens, familles et records, défi du jour).
- `php tests/moments.php` : 100 moments (numéros dans l'ordre des dates et gelés en ligne,
  contrôle de la date, note de validation, date anniversaire, alertes, catalogue et idées
  de l'IA limitées au catalogue, doublons, idées écartées rappelées, premier jet « À
  relire », grille publique sans date).
- `php tests/photos.php` : murs de photos (crédits DR, exclus, sans auteur ; crédit et
  photographe extraits ; photos montrables seulement ; tirage, filtres et nombres ; motifs de
  la mosaïque ; pages et fragment `?partiel=1` ; case « Jamais sur les murs »).
- `php tests/souvenirs.php` : kit souvenirs (match du mois et choix des historiens, visages,
  quiz, récit du match, fiche complète en annexe), « Ils y étaient » (témoignages publiés seulement), QR code.
- `php tests/vitrine.php` : site de l'association (adresses et alias, page d'attente propre au
  site — contenu, lettre et teaser servis site fermé, compte à rebours, reprise des anciens
  réglages, aperçu —, celle du musée intacte, aperçu
  réservé, anciennes adresses vers le musée, pages et plan du site, contenus « à vérifier »
  invisibles du public, agenda iCal, liens de l'aperçu, antispam, adhésion payée par un
  webhook Stripe signé puis remboursée, capture PayPal, purges RGPD, documents, nettoyage
  des saisies du pavé, pavé réservé aux administrateurs).
- `php tests/chiffres.php` : les chiffres du FCSM (tableaux de carrière et tableaux recopiés,
  buts minute par minute, séries par blocs de saisons, quotas, passeurs, âges, mise en forme
  française et anglaise ; puis les 100 chiffres du musée et les garde-fous contre les données
  douteuses).
- `php tests/controle.php` : contrôle complet (vérifications des matchs et des fiches,
  fichiers retouchés à la main, adresses suivies comme par un visiteur, clés stables des
  alertes, orthographe et empreinte des textes, nouvelles et corrigées d'un contrôle à
  l'autre, index remis à jour, un seul contrôle à la fois) ; le fichier du dernier contrôle
  est remis en place, rien n'est écrit dans le journal.
- `php tests/inconnu.php` : « xx » de l'ancien site (lignes de la fiche d'identité,
  naissance et décès, références de match, temps forts, réactions, chiffre clé, texte riche,
  fiche anglaise, cas limites).
- `php tests/updater.php` : mises à jour en un clic (fichiers du code seulement, données et
  médias jamais touchés, `.htaccess` gardé, libellés fusionnés, suppression des fichiers retirés,
  sauvegarde et retour arrière, archive piégée refusée, API et flux Atom, pause du site,
  synchronisation : empreintes Git, fins de ligne, image abîmée, version reconnue, dossiers en
  cache, fichier retouché signalé puis remplacé, limite de l'API).
- `php tests/attente.php` : ouverture du site (installation neuve fermée, page d'attente
  pour le public et vrai site pour l'équipe connectée, bandeau, aucun lien vers le
  back-office, noindex et cache partout, robots.txt, mot de passe d'accès, site ouvert ou
  masqué aux moteurs, teaser secret ou montré, lecture par morceaux de la vidéo).
- `php tests/pdf.php` : polices du PDF et des images de partage (lettres stylisées, emoji,
  séparateurs, « ? » seulement pour une écriture absente, texte copiable exact), symboles des
  compositions, citation posée dans une liste et numérotation.
- `tests/smoke.js` (Playwright) : parcourt les pages du site et du back-office et signale
  les erreurs JavaScript et les blocages de la politique CSP.

## Reprise des années 1928-1969 (fcsmstory.com)

- `App\Services\FcsmStory` : lecture de l'API REST WordPress (pages et articles, `storage/import/fcsmstory/raw.json`),
  classement (saison, match, tournoi, article, portrait ; hors 1928-1969 écarté), découpage des pages de saison en
  matchs (en-tête « Le 20 Septembre 1942 à Montbéliard, défaite 3 à 0 Fives » et variantes, compétition, composition
  2-3-5, buteurs, récit), fusion des deux blocs d'un même match (calendrier puis récit), pied de page du site coupé.
- `App\Services\FcsmImport` : plan sans doublon (`state.json` : match à une date déjà au musée écarté, portrait d'une
  personne présente — même nom, un prénom commun — écarté), réécriture Gemini par lots de 6 en parallèle (tâche
  planifiée `reprise`, 24 éléments par passage), mesure de ressemblance (part des suites de 6 mots reprises de
  l'original, maximum `SIM_MAX`, nouvel essai avec consigne plus ferme, 3 essais), création des fiches publiées
  (`legacy.fcsmstory`, section « Sources »), rubrique de saison créée si absente, texte de saison dans la description
  de la rubrique seulement si elle est vide. Coûts IA comptés sous `import`. Écran : `App\Admin\Heritage`.

## Grands récits (`App\Services\GrandsRecits`)

Textes livrés avec le site dans `app/Resources/import/recits.php` (clé, titre avec époque, chapô, chapitres,
mots-clés d'images). `create()` crée la rubrique racine `grands-recits` si besoin, puis chaque récit absent
(article publié, `legacy.recit` = clé, chapô en première section, `{{match:AAAA-MM-JJ|texte}}` → lien vers la fiche
du match). Jamais réécrit ensuite. Mise en page : `App\Front\Recit` (`ROOTS` = infrastructures, grands-recits ;
`rootOf()`), mosaïque de la rubrique (`Mosaic::ROOTS`), menu (`Site`, si la rubrique existe). `forImage()` propose
les récits dans le rangement des archives. Test : `tests/recits.php`.

## Livre des récits (`App\Pdf\Livre`, `App\Admin\Book`, Contenus › Livre des récits, admin)

PDF prêt à imprimer des grands récits dans l'ordre chronologique : 210 × 270 mm, fonds perdus de 3 mm
(TrimBox/BleedBox sur chaque page), pages par multiple de 4. Couverture et 4e personnalisées (nom), dédicace,
numéro d'exemplaire, sommaire (dessiné à la fin, pages réservées), ouverture de décennie (page de droite),
récits en deux colonnes (un récit court laisse le suivant commencer sur la même page), bandeau courant,
folios côté extérieur, signets. Photos : jamais la presse (`GrandsRecits::press`) ; une photo n'est placée que si
sa définition suffit au cadre (`Livre::DPI` : pleine page et bandeau 250 dpi, pleine largeur 220, colonne 200),
mesurée sur l'original recadré (`measure()`, cache `storage/cache/livre/dims.json`) ; elle est recadrée et réduite
à 300 dpi (`storage/cache/livre/*.jpg`). `report()` : photos par taille, écartées, récits sans photo (bilan de la
dernière composition : `storage/livres/dernier.json`). Livre entier : ~25 s et ~80 Mo de mémoire sur le banc.
Test : `tests/livre.php`.

## Catalogue des archives (`App\Services\Catalogue`, `App\Admin\Archives`)

Contenus › Archives à ranger (`/admin/archives` ; dépôt : administrateurs ; rangement : toute l'équipe).
`app/Resources/import/catalogue-images.json.gz` : une fiche par image, sous l'empreinte MD5 du fichier d'origine
(type, rotation, titre, légende, date ou décennie, personnes, match, sujets, publication, droits, crédit, qualité,
doublon, résumé des pages, faits lus, lot et nom d'origine). Le navigateur ouvre les zip (`public/assets/vendor/jszip.min.js`,
`admin/archives.js`) et envoie chaque image (`action=deposer`) : `receive()` calcule le MD5, range l'original dans
`storage/media/originals/archives/<année>/` et la médiathèque (rotation en retouche non destructive `edit.rotate`).
`suggest()` propose le match (date ± 2 jours, adversaire) et les joueurs (nom de famille sans ambiguïté) ;
`accept()` ajoute l'image à la galerie (image principale si la fiche n'en a pas et que la photo est nette).
État : `storage/import/catalogue/state.json` (`recu`, `range`, `ecarte`). Test : `tests/catalogue.php`.

## Bilans des joueurs (`App\Services\Bilans`)

Grilles de minutes des bilans de l'association (`BIL_aaaabb.xls(x)`, feuilles `D1-J`, `CF-J`… : minutes par
match, puis sections Buts et Cartons ; « 35M (35T) = 3039 » donne les titularisations) lues une fois dans
`app/Resources/import/bilans-joueurs.json.gz` (`rows` : saison, feuille, nom, matchs, titularisations, minutes,
buts, jaunes, rouges, minutes par date ; `bilans` : lignes de la feuille BILAN, classement final).
`links()` relie un nom (« REVELLI P. », « M. MARTIN ») à une fiche personne visible : même nom de famille
(lettres seules), même initiale si donnée, époque compatible (arrivée/départ, sinon naissance) ; sinon aucun lien.
Utilisé par `Fiche::personData` (`bilan`, grands chiffres), `Explore::recordRows('minutes')`, `Explore::season`
(classement final) et `Admin\Sheets` (noms sans fiche). Test : `tests/bilans.php`.

## Import des feuilles de match (`App\Services\FeuillesImport`, `App\Admin\Sheets`)

Système › Feuilles de match (`/admin/import-feuilles`, administrateurs). Les feuilles Word de l'association
(`FM_aaaabb` officiels, `AM_aaaabb` amicaux) ont été lues une fois pour toutes dans
`app/Resources/import/feuilles-de-match.json.gz` (date, compétition, journée, lieu, stade, équipes, score, mi-temps,
affluence, arbitre, buts par équipe, buteurs sochaliens avec minutes, composition avec postes et
remplacements, entraîneur, fichier d'origine). Chaque feuille a une clé stable (`sha1` de type, date, équipes, score).

- `plan()` : rapproche chaque feuille d'un match de l'index (Sochaux impliqué, date ± 3 jours, mots communs
  du nom de l'adversaire, score égal en bonus) ; une fiche n'est prise qu'une fois. Statuts : `a-creer`,
  `a-comparer`, `sans-date`, puis `fait`, `compare`, `erreur`. État dans `storage/import/feuilles/state.json`.
- `run($max)` (verrou `run.lock`, `Fiches::batch`) : `create()` publie la fiche (saison créée au besoin par
  `FcsmImport::ensureSeason`, `legacy.feuilles` = clé) ; `compare()` envoie les écarts à
  `Trouvailles::propose()` (origine `feuilles`, source sans lien), sans toucher la fiche.
- Avancement : la page appelle `action=lot` (JSON) en boucle ; tâche planifiée `feuilles` sinon.
- Trouvailles : `ORIGINS` (sources + `feuilles`), champ `date` (fiche, texte de date, date du titre),
  sources sans URL affichées sans lien. Test : `tests/feuilles.php`.

## Trouvailles (`App\Services\Trouvailles`, `App\Admin\Finds`)

Propositions trouvées dans les archives en ligne pour les fiches de match, validées une par une
(Contenus › Trouvailles, `/admin/trouvailles` ; valider : toute l'équipe ; lancer : administrateurs).
- **Gallica** (matchs jusqu'en 1955) : SRU `text adj "Sochaux" and text adj "<adversaire>"`,
  `dc.type all "fascicule"`, **un jour à la fois** du jour du match à J+3 (sur une période, Gallica
  ne renvoie qu'un numéro par journal ; la recherche « gallica all » est floue : « sociaux » pour
  « Sochaux », d'où `adj`). Pages d'un numéro : `services/ContentSearch?query="Sochaux"` (guillemets :
  recherche exacte). Texte : `RequestDigitalElement?O=<ark>&E=ALTO&Deb=<page>` (la page
  `.texteBrut` est derrière un anti-robot), mots recollés (`HypPart1`/`SUBS_CONTENT`), passages de
  ±1 500 caractères autour de « Sochaux », ceux qui citent aussi l'adversaire d'abord. Classement :
  extrait qui cite l'adversaire, presse régionale et sportive, numéros parus après le match ; 8
  numéros, 2 pages chacun au plus. curl avec un User-Agent (403 sans), ~2 requêtes/s, une
  nouvelle tentative.
- **Web** : Gemini avec `google_search` ; seules les pages consultées (ancrage) servent de source,
  jamais le site du musée.
- Lecture par l'IA (modèle de la recherche web, usage `trouvailles`) : JSON (score, buteurs,
  composition, affluence, arbitre, stade, récit, infos, pistes, chacun avec ses sources) ; récit
  écarté s'il reprend plus de 8 % de suites de six mots des journaux.
- Propositions : `storage/trouvailles/props/{id}.json` (champ, valeur, ce que dit la fiche, type
  complément/divergence/récit/info/piste, sources, statut attente/envoye/ecarte) ; jamais deux
  fois la même valeur (sources fusionnées, écartée jamais reproposée). File et matchs fouillés :
  `storage/trouvailles/state.json` ; tâche planifiée `trouvailles` (100 s, 4 matchs au plus) et, page
  ouverte, `POST /admin/trouvailles action=avancer` (un match par requête, `admin/trouvailles.js`) ;
  verrou `storage/trouvailles/work.lock` : jamais deux fouilles en même temps.
- Envoi dans la fiche (`Fiches::save`, version) : champs du match, composition (format « NOM
  Prénom », liens aux fiches gardés), sections « Dans la presse de l'époque » / « Ce qu'en disent
  les sources », « Compléments », liens ajoutés à « Sources » (`legacy.trouvailles`).
- Test : `tests/trouvailles.php` (Gallica et Gemini simulés).

