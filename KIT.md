# Kit de reprise — socle de site vitrine PHP natif

> Ce dépôt sert actuellement le site de la **mairie d’Angeot**
> (Territoire de Belfort). Le présent fichier décrit le socle lui-même, pour le
> reprendre sur un autre projet ; le site en cours, lui, est décrit dans
> [SITE.md](SITE.md).

Ce dossier est le code complet d'un site vitrine haut de gamme livré en
production. Il est fourni comme **point de départ pour un projet similaire** :
autre client, autre contenu, même architecture.

Ce fichier s'adresse autant à un développeur qu'à Claude Code. Il décrit ce
que fait le socle, pourquoi il est bâti ainsi, et ce qu'il faut changer pour
l'adapter.

---

## 0. Par où commencer

Si vous reprenez ce dossier pour bâtir un autre site :

1. Lisez ce fichier en entier — il fait dix minutes et évite les pièges du
   §10, qui sont tous des bugs réellement rencontrés.
2. Lancez le site en local (§9) et ouvrez `/admin` : le back-office est le
   meilleur inventaire de ce qui existe.
3. Suivez le §7 dans l'ordre. Ne réécrivez pas les briques de `app/Core` :
   elles sont éprouvées et documentées dans leur en-tête.
4. Respectez les conventions du §5, en particulier **le nommage français** et
   **les commentaires qui disent pourquoi**. Un fichier écrit autrement se
   repère immédiatement au milieu des autres.

Ce que le dossier **ne contient pas**, volontairement : les photos du client
(`public/assets/img/site/`), son contenu vivant (`data/*.json`), son compte
d'administration et ses clés d'API. Le site démarre malgré tout : chaque
image manquante affiche un visuel « photo à venir », et `data-modele/` amorce
le contenu à la première visite.

---

## 1. Ce que c'est

Un site vitrine multilingue avec back-office complet, en **PHP natif, sans
aucune dépendance** : ni Composer, ni base de données, ni build front. Le
contenu vit dans des fichiers JSON, le back-office les écrit.

Conçu pour l'hébergement mutualisé français (testé sur o2switch) : on dépose
les fichiers, on pointe la racine web sur `public/`, c'est en ligne.

**Pourquoi sans dépendances ?** Un site vitrine change peu et doit vivre dix
ans. Sans Composer, il n'y a rien à mettre à jour, rien qui casse, rien à
auditer. Une base de données pour douze pages de contenu ajoute une panne
possible, une sauvegarde à gérer et un export à faire — le JSON versionné
rend le même service.

---

## 2. Contraintes à ne pas casser

Ces règles ont dicté toute l'architecture. Les reprendre telles quelles.

| Règle | Raison |
|---|---|
| **Seul `public/` est exposé** ; `app/`, `config/`, `data/`, `views/`, `storage/` vivent au-dessus | Un contenu hors racine web ne peut pas être téléchargé, même en cas de mauvaise configuration Apache |
| **Aucune dépendance externe** | Rien à mettre à jour, rien qui casse |
| **Aucun secret dans le dépôt** | `data/admin/` (mot de passe SMTP, empreinte du mot de passe admin) est ignoré par git |
| **Jamais 777** | Sur mutualisé, PHP tourne sous le compte utilisateur : 755 / 644 suffisent, et certains hébergeurs refusent de servir un fichier en 777 |
| **Le site public ne contacte aucun service extérieur** | Pas de CDN, pas de police distante, pas d'API à l'affichage — vitesse, vie privée, et rien qui tombe |

---

## 3. Carte du code

```
public/index.php        Contrôleur frontal, unique point d'entrée PHP exposé
public/.htaccess        Réécriture, HTTPS, en-têtes de sécurité, cache
public/assets/          CSS, JS, polices, images (aucun build)

app/bootstrap.php       Chargeur de classes, session, configuration
app/helpers.php         e() url() asset() route() lien() t() image() absolu()
app/routes.php          Table de routage publique
app/routes-admin.php    Table de routage du back-office
app/Core/               Briques réutilisables (voir ci-dessous)
app/Controllers/        Pages publiques, API JSON
app/Admin/              Écrans du back-office
app/Admin/Blocs.php     Description des blocs de contenu, pour l'édition
app/Admin/Contenus.php  Inventaire des fichiers de contenu, partagé

views/layout.php        Gabarit public (métadonnées, JSON-LD, hreflang)
views/pages/            Une vue par page, quand la mise en page l'exige
views/partials/bloc.php Rendu d'un bloc de contenu — le cœur des pages
views/partials/sections.php  Suite de blocs, avec l'alternance des fonds
views/partials/         Fragments (en-tête, pied, cookies, langues…)
views/admin/            Back-office, avec son propre layout

data-modele/            Contenu LIVRÉ avec le code (versionné)
data/*.json             Contenu VIVANT du site, écrit par le back-office
data/seo.json           Slugs, balises, redirections 301
data/langues.json       Langues déclarées
data/traductions/*.json Traductions par langue
data/assistant/         Documents, notes et journal des conversations
data/admin/             Compte et réglages — JAMAIS versionné

outils/paquet-maj.php   Fabrique le dossier à transférer par FTP
```

### `data-modele/` et `data/` : la séparation qui protège le client

Le dépôt ne contient **aucun fichier portant le chemin du contenu vivant**.
Le contenu livré vit dans `data-modele/`, et `Content::amorcer()` ne le
recopie dans `data/` que pour un fichier qui n'y existe pas encore.

C'est ce qui rend un transfert de code inoffensif : un FTP intégral et
maladroit ne peut pas écraser ce que le client a saisi, puisque le paquet ne
contient rien à ce chemin. Vérifié en poussant l'export complet du dépôt sur
un site dont le diaporama avait été modifié — rien n'a bougé.

Si `data/` n'est pas inscriptible, le modèle est servi tel quel : une page
s'affiche même quand les droits sont à revoir.

### Le verrou optimiste : deux administrateurs à la fois

Toute écriture de contenu passe par fichier temporaire puis `rename()`, ce
qui est atomique : un visiteur qui lit pendant qu'on écrit obtient l'ancienne
version entière ou la nouvelle entière, jamais un JSON coupé en deux. C'est
pour cela que `file_put_contents` direct est proscrit sur un fichier de
contenu.

Cela ne dit rien, en revanche, de deux personnes qui éditent en même temps.
La secrétaire ouvre l'éditeur d'une page, un élu l'ouvre aussi, elle
enregistre, il enregistre : son formulaire à lui a été construit avant, il
réécrit donc l'état d'avant et le travail d'elle disparaît sans message.
`Content::save()` recopie bien la version précédente dans
`storage/sauvegardes/` avant chaque écriture, mais encore faut-il s'apercevoir
de la perte.

`App\Core\Verrou` règle cela sans qu'aucun formulaire ait à porter de champ
ni aucun contrôleur à passer d'appel :

- **à l'affichage** d'un écran d'administration (requête GET), chaque contenu
  lu laisse son empreinte — date de modification et taille — dans la session
  de celui qui regarde ;
- **à l'enregistrement** (requête POST), `Content::save()` compare l'empreinte
  du fichier tel qu'il est maintenant à celle qui avait été relevée. Si elles
  diffèrent, quelqu'un a écrit entre-temps : l'écriture est refusée par une
  `ConflitEcriture`, rattrapée dans `public/index.php`, qui renvoie
  l'administrateur sur son écran avec le message qui dit quoi faire.

Le découpage GET / POST est ce qui rend le dispositif invisible, et il tient à
une seule condition : **ne jamais relever d'empreinte pendant un POST**, sans
quoi la lecture que fait le contrôleur juste avant d'écrire rafraîchirait
l'empreinte et le verrou ne verrait plus rien. Le verrou est armé une fois,
en tête de `routes-admin.php` ; le site public n'écrit aucun contenu.

Un contenu jamais lu pendant l'affichage n'a pas d'empreinte et son écriture
passe : c'est voulu. Renommer un slug ou une photo réécrit une vingtaine de
fichiers que l'écran n'a pas montrés, et refuser ces écritures-là bloquerait
le back-office sans rien protéger.

---

### La couleur dominante : un choix de teinte, pas de palette

L'écran Apparence laisse le client choisir « la couleur de la commune ». Ce
n'est pas un jeu de variables CSS ouvert : `App\Core\Charte` ne pose jamais
la couleur telle quelle. Elle en garde la **teinte**, borne la saturation
entre 18 et 55 %, et **résout** la luminosité de chaque ton — pas à pas, par
demi-points — jusqu'à ce qu'il tienne le contraste exigé sur le fond où il
sert : 7:1 pour le foncé sur blanc, 4,5:1 pour la marque, 4,6:1 pour le clair
sur chacun des cinq fonds sombres du site.

Les neutres suivent la teinte mais gardent leur saturation et leur luminosité
d'origine : c'est ce qui empêche un rouge saturé de transformer l'ardoise en
brique. La conséquence pratique est qu'aucun contraste ne peut être cassé par
un choix du client, et `outils/verifs/couleur.py` le vérifie sur douze teintes
et quatre cas limites.

L'aperçu de l'écran refait le même calcul en JavaScript
(`public/assets/js/admin.js`) : les deux implémentations doivent rester
d'accord au bit près, sinon l'aperçu ment.

Un point d'ordre, facile à casser : le bloc `<style>` du gabarit est posé
**après** `site.css`. Les deux déclarent des jetons sur `:root`, à spécificité
égale — c'est donc la dernière lue qui gagne. Placé avant, le réglage reste
sans effet, en silence.

### Le bouton de l'assistant : cinq formes, deux couleurs, un seuil

L'écran Assistant IA laisse le client régler la bulle en bas à droite —
forme (barre, pilule, rond, pastille, onglet), fond, couleur du texte,
intitulé et taille. Le fond est libre ; la couleur du texte est une
**intention** : `App\Core\Bulle` en conserve la teinte et résout sa clarté
jusqu'à 4,5:1 sur le fond choisi, par la méthode de `Charte`. Un fond jaune et
un texte blanc donnent donc un texte jaune sombre, pas 1,26:1 — et l'écran le
dit, plutôt que de refuser d'enregistrer.

S'y ajoutent cinq **animations d'appel** — aucune, halo, rebond, balancement,
respiration —, avec leur **rythme** : la durée d'un mouvement (800 à 3 000 ms)
et le nombre de rappels (1 à 3).

Ces deux réglages ne sont pas indépendants, et c'est le point à comprendre
avant d'y toucher : **leur produit ne dépasse jamais cinq secondes.** Au-delà,
un contenu en mouvement déclenché tout seul doit pouvoir être arrêté par le
visiteur (RGAA 13.8, WCAG 2.2.2), ce qui obligerait à poser un bouton d'arrêt à
côté du bouton de discussion. Plutôt que de refuser un réglage, `Bulle` réduit
le nombre de rappels jusqu'à ce qu'il tienne — comme la couleur du texte est
corrigée plutôt que refusée — et l'écran le dit.

L'animation **se rejoue quand le visiteur s'arrête de faire défiler la page** —
c'est le moment utile, quelqu'un qui vient de s'arrêter de lire est justement
celui qui cherche quelque chose. Trois garde-fous, dans `site.js` : une
distance minimale (350 px, un tremblement de molette n'est pas un parcours),
un délai entre deux rappels (8 s), et un plafond de trois rappels après quoi le
bouton se tait pour de bon. Chaque rappel dure ce que dure l'animation, donc
respecte la même borne de cinq secondes.

La durée gouverne deux choses à la fois, la lenteur du geste et l'attente
avant qu'il ne revienne, puisqu'un cycle enchaîne sur le suivant : une mairie
qui trouve que « ça revient trop vite » ralentit, ou demande moins de rappels.
`bulle.py` mesure le produit sur les styles calculés, aux quatre coins des deux
réglages — et vérifie que la durée SERVIE est bien celle demandée, sans quoi un
jeton mal branché ferait retomber l'animation sur sa valeur livrée en silence.

Le mouvement s'arrête aussi au survol, au focus, et pour un visiteur dont le
système demande moins d'animations — par la règle générale posée en tête de
site.css, que le script vérifie dans un contexte `reduced_motion`. Le script du
site retire enfin la classe dès que le visiteur ouvre la discussion : continuer
à agiter un bouton devant quelqu'un qui s'en sert n'a pas de sens.

Deux pièges valent d'être connus avant d'y toucher :

- la taille ne descend pas sous 44 px, parce que c'est le minimum de cible
  tactile que `mise-en-page.py` fait respecter ; l'onglet, étroit par nature,
  garde ce plancher en largeur avec un `max()` ;
- l'onglet annule le retrait du conteneur pour toucher le bord de l'écran. Ce
  retrait vaut `clamp(1rem, 3vw, 1.8rem)` et passe à `.8rem` sous 520 px : il
  est donc en variable (`--assistant-marge`). Écrit deux fois, il déborde de
  trois pixels sur petit écran, et `mise-en-page.py` le refuse ;
- **le rappel au défilement annule le délai d'entrée**, et sa règle doit
  battre celle du bloc général — même nombre de classes, la plus longue
  l'emporterait et le délai resterait. Le sélecteur reprend donc celui du bloc
  et lui ajoute une classe. Cela ne se voit pas en lisant, seulement en
  mesurant ;
- **une forme collée au bord ne peut pas tourner.** L'onglet est la seule dans
  ce cas, et une rotation de 4,5° sur une étiquette de cent cinquante pixels de
  haut déplace son coin de huit pixels hors de l'écran — mesuré, pas déduit.
  Son axe de transformation passe donc au coin bas-droit, et son balancement
  devient un mouvement horizontal : elle se décolle du bord et y revient.

### Publier sur Facebook et Instagram

L'écran **Réseaux sociaux** publie sur la Page Facebook et le compte Instagram
du client. Quatre points valent d'être compris avant d'y toucher, parce
qu'aucun ne se devine.

**Tout part du serveur.** Aucun script de Meta dans la page, aucun appel depuis
le navigateur — ni celui du visiteur, ni celui de l'administrateur. C'est ce
qui garde `traceurs.py` à zéro, et une mairie n'a pas le droit de déposer les
traceurs d'un tiers chez l'administré.

**Meta impose une revue.** Tant qu'elle n'est pas accordée, seuls les comptes
déclarés testeurs dans l'application peuvent publier. Ce n'est pas contournable
et ce n'est pas un défaut : l'écran le dit plutôt que d'échouer en silence.
`DEPLOIEMENT.md` § 13 décrit la démarche complète.

**Instagram n'accepte rien sans image**, et il télécharge cette image lui-même :
elle doit être accessible en HTTPS depuis l'extérieur. Une publication
Instagram n'est donc pas essayable depuis un poste local — Facebook, si. Quand
la publication n'a pas de photo, `Vignette` en fabrique une (voir ci-dessous).

**Instagram ne sait pas programmer une publication**, Facebook si. C'est pour
cela que la file d'attente est tenue par le site : un seul mécanisme, visible
depuis le back-office, plutôt que deux dont un invisible. Elle est dépilée par
une tâche planifiée **et**, à défaut, par les visites du back-office — sur
mutualisé, un cron que personne n'a réglé ne doit pas faire disparaître les
publications en silence.

Une publication qui échoue reste en file avec son motif et est réessayée trois
fois, puis passe au journal marquée en échec. Le pire, pour une mairie, n'est
pas qu'une publication échoue : c'est qu'elle échoue sans que personne
l'apprenne.

### Les briques de `app/Core`

| Classe | Rôle | À reprendre tel quel ? |
|---|---|---|
| `Router` | Routage à motifs `{param}`, premier inscrit gagne | oui |
| `View` | Gabarits PHP dans un layout via `$slot` | oui |
| `Content` | Lecture/écriture JSON, sauvegardes versionnées, traduction à la volée | oui |
| `Seo` | Slugs modifiables, redirections 301, sitemap, robots, JSON-LD | oui, en adaptant la table `PAGES` |
| `Langues` / `Traducteur` / `TraductionAuto` | Multilingue par fichiers, traduction automatique sans clé | oui |
| `Cookies` | Catégories de consentement | oui, en adaptant les catégories |
| `Auth` / `Csrf` / `Session` | Connexion, jetons, sessions durcies | oui |
| `Mailer` | SMTP natif + repli sur `mail()` | oui |
| `Mediatheque` | Envoi d'images, redimensionnement, vignettes (GD) | oui |
| `Liste` | Opérations de liste : ajouter, déplacer, masquer, retirer | oui |
| `Deploiement` | Mise à jour par git depuis l'admin | oui |
| `Avis` | Avis Google récupérés côté serveur, mis en cache | oui, en renseignant clé et fiche |
| `Assistant` | Assistant de discussion Gemini : corpus, consigne, documents, liste dynamique des modèles | oui, en renseignant la clé, et en réécrivant `consigne()` |
| `Conversations` | Journal des échanges avec l'assistant, repérage des coordonnées, purge à 12 mois | oui |
| `Permissions` | Analyse et réparation des droits | oui |
| `Parametres` | Réglages techniques hors git | oui |
| `Charte` | Toute la palette dérivée de la couleur choisie par le client, luminosité **résolue** pour tenir les contrastes | oui |
| `Bulle` | Forme, couleurs, libellé et taille du bouton de l'assistant ; la couleur du texte y est résolue de la même façon | oui |
| `Reseaux` | Connexion OAuth à Meta et publication sur la Page Facebook et le compte Instagram | oui, en déclarant une application Meta |
| `Publications` | File d'attente et journal des envois, écritures atomiques | oui |
| `Publicateur` | L'interface que `Diffusion` attend d'un réseau : publier, lire un permalien. C'est elle qui rend la file vérifiable sans rien envoyer | oui |
| `Diffusion` | Le seul chemin d'envoi : réunit Meta, la file et la fabrique d'image | oui |
| `Vignette` | L'image carrée fabriquée quand une publication n'a pas de photo (GD) | oui, en fournissant blason PNG et police TTF |
| `Verrou` / `ConflitEcriture` | Verrou optimiste : deux administrateurs sur le même écran ne s'effacent plus | oui |

---

## 4. Le modèle de contenu

Tout part de `data/`. Trois formes :

**Pages à blocs** — la forme la plus courante. Une page est un bandeau et une
suite de blocs typés, décrits une fois dans `app/Admin/Blocs.php`, rendus par
`views/partials/bloc.php`, édités par un unique écran générique.

```json
{
  "titre": "Urbanisme",
  "hero": { "surtitre": "…", "titre": "…", "image": "assets/img/site/x.jpg" },
  "sous_titre": "Le chapô de la page.",
  "sections": [
    { "type": "cartes", "titre": "…", "items": [ … ] },
    { "type": "texte", "titre": "…", "paragraphes": ["…"], "id": "plui" },
    { "type": "encadre", "ton": "alerte", "intitule": "…", "paragraphes": ["…"] }
  ]
}
```

Ajouter un type de bloc coûte trois choses : un `case` dans `bloc.php`, une
entrée dans `Blocs::TYPES`, et sa règle CSS. **N'écrivez une vue dédiée que si
la mise en page dépend d'une donnée structurée** — un trombinoscope
hiérarchisé, une liste filtrable — jamais pour du texte suivi.

**L'alternance des fonds est calculée, pas saisie.** `sections.php` retourne le
fond à chaque bloc ; un bloc peut imposer le sien (`"fond": "sombre"`), et le
calcul reprend après lui. Confier cette règle à celui qui écrit le contenu, ce
serait la voir se casser à la première section ajoutée.

Et les deux formes héritées du socle :

**Pages fixes** — `data/pages/<clé>.json` :

```json
{
  "titre": "Nos Hébergements",
  "meta": { "description": "…" },
  "hero": { "image": "assets/img/site/x.jpg", "surtitre": "…", "titre": "…" },
  "sections": [ { "titre": "…", "paragraphes": ["…"] } ]
}
```

**Collections** — `data/<collection>.json` avec un tableau `items`, chaque
entrée portant un `slug` :

```json
{
  "intro": "…",
  "items": [
    { "slug": "comptabilite", "nom": "…", "image": "…", "actif": true, "meta": {} }
  ]
}
```

Trois conventions structurantes :

- **`slug`** identifie une fiche. C'est lui qui sert de clé de traduction, pas
  le rang : réordonner les fiches ne décale pas les traductions.
- **`actif`** absent vaut publié. Un contenu enregistré avant l'arrivée de
  cette notion reste donc visible — aucune migration à écrire.
- **Les chemins d'images** sont relatifs à `public/`. Le helper `image()`
  retombe sur un visuel « photo à venir » si le fichier manque, ce qui évite
  toute image cassée sur le site comme dans l'admin.

---

## 5. Conventions de code

- **Tout est nommé en français** : classes, méthodes, variables, routes. Le
  client lit le code, les messages d'erreur lui parlent.
- **Les commentaires disent pourquoi, jamais quoi.** Un commentaire qui
  paraphrase la ligne suivante est du bruit ; un commentaire qui explique une
  contrainte ou un piège évite une régression.
- **Échapper systématiquement** : `e()` sur toute valeur affichée.
- **CSRF sur tous les formulaires** du back-office : `Csrf::champ()` /
  `Csrf::verifier()`.
- **Écritures atomiques** : fichier temporaire puis `rename()`, jamais
  d'écriture en place.
- **CSS et JS écrits à la main**, sans build. Le JS est en ES5 tolérant, chaque
  bloc se désactive tout seul si sa cible est absente de la page.

---

## 6. Ce que le socle sait déjà faire

- Pages éditoriales, collections avec fiches, formulaire de contact avec piège
  à robots et consentement explicite
- Back-office complet : édition de tous les textes, médiathèque avec envoi par
  lot et glisser-déposer, création / réordonnancement / masquage / suppression
  des fiches, éditeur JSON avancé avec restauration de versions
- Référencement : slugs modifiables avec redirection 301 automatique,
  `sitemap.xml`, `robots.txt`, `canonical`, `hreflang`, JSON-LD
- Multilingue par préfixe d'adresse, relecture manuelle, repli sur la langue
  source. La traduction automatique essaie **Google Traduction, puis MyMemory,
  puis DeepL** : les deux premiers sont gratuits mais comptés par adresse IP
  (donc partagée sur un mutualisé, d'où des refus HTTP 429 immédiats), le
  troisième n'intervient qu'en dernier recours car son offre gratuite
  n'accorde qu'un million de caractères **pour la vie du compte**. Un compteur
  suit ce qui y a été pris.
- Consentement aux cookies conforme CNIL, avec blocage réel des traceurs
- Deux dispositions de menu (panneau latéral ou barre horizontale), au choix
  du client depuis le back-office, sur le même balisage
- Avis Google récupérés côté serveur et mis en cache : aucun appel depuis le
  navigateur du visiteur, donc aucun cookie tiers à soumettre au consentement
- Carrousel d'avis à hauteur bornée, le témoignage long défilant dans sa carte
- **Diaporama du bandeau d'accueil** : plusieurs photos en fondu croisé avec
  dérive lente, jauge de progression, et réglage complet au back-office —
  ordre par glisser-déposer, activation par interrupteur, retrait immédiat,
  ajout au clic, temps de pause, intensité du voile sombre
- **Galerie de réalisations** filtrable par catégorie, visionneuse au clavier,
  et galerie propre à chaque fiche produit ; rotation des photos au back-office
- **Assistant de discussion Gemini**, activable, cantonné à trois sources —
  contenu du site, documents envoyés (PDF, DOCX, TXT, MD), texte collé dans un
  éditeur. Aucune fonction déclarée au modèle, donc rien à inventer ailleurs.
  Conversation conservée d'une page à l'autre, journal consultable au
  back-office, repérage du téléphone ou du courriel laissé en conversation, et
  formulaire de rappel intégré
- Mise à jour du site par git depuis l'admin, avec sauvegarde préalable
- Colis de mise à jour par FTP fabriqué par `outils/paquet-maj.php`, à partir
  de la même liste que la mise à jour automatique
- Diagnostic serveur et réparation des droits

---

## 6 bis. Les écrans du back-office

| Écran | Ce qu'on y fait |
|---|---|
| Tableau de bord | État du site, raccourcis |
| Coordonnées & menu | Identité, adresses, téléphone, entrées de menu |
| Page d'accueil | Bandeau, **diaporama**, voile, chiffres clés, blocs |
| La société, Services, Valeurs, FAQ | Contenus éditoriaux et fiches |
| Réalisations | Onglets par gamme, photos cochées sur planche-contact |
| Contact | Coordonnées affichées, carte, champs du formulaire |
| Photos | Médiathèque : envoi par lot, **rotation**, suppression |
| Apparence | **Couleur de la commune**, disposition du menu, taille du logo |
| Avis Google | Clé d'API, fiche, filtres, rythme du carrousel |
| Assistant IA | Activation, clé Gemini, **liste dynamique des modèles**, **forme, couleurs, intitulé et taille du bouton**, documents, notes, question d'essai |
| Réseaux sociaux | Connexion Facebook/Instagram, composition, programmation, file et journal |
| Conversations | Journal des échanges, coordonnées repérées, purge |
| Référencement | Slugs, balises, redirections 301 |
| Langues | Langues, traduction automatique, **clé DeepL et compteur** |
| Éditeur avancé | JSON brut avec validation et restauration de version |
| Paramètres | SMTP, compte, droits, diagnostic serveur |
| Mises à jour | Déploiement git, sauvegarde et restauration |

---

## 7. Adapter à un nouveau projet

Dans l'ordre :

1. **Identité** — `data/site.json` (nom, adresse, téléphone, menu, liens de
   réservation), `public/assets/img/logo/` (logos SVG, `favicon-512.png`),
   et les variables de couleur en tête de `public/assets/css/site.css` —
   puis les mêmes teintes, éclaircies pour l'écran sombre, en tête de
   `public/assets/css/admin.css`.

   Une charte fournie en PDF vectoriel se convertit sans outil externe :
   le flux de contenu d'un PDF n'est qu'une suite de `m`, `l`, `c` et `re`,
   qui se transcrit en chemins SVG. C'est ainsi que les logos de ce site ont
   été obtenus, contours d'origine compris, plutôt que redessinés à vue.
   **Attention aux couleurs** : un PDF d'imprimeur les exprime en CMJN, et
   la conversion naïve vers le RVB ne rend pas la teinte imprimée. Reprenez
   les valeurs hexadécimales de la charte, pas celles du fichier.
2. **Pages** — la table `Seo::PAGES` liste les pages fixes et leurs slugs. En
   ajouter une : une entrée ici, un `data/pages/<clé>.json`, une route dans
   `app/routes.php`, une vue dans `views/pages/`.
3. **Collections** — `Seo::COLLECTIONS` associe une page à une collection.
   Adapter ou remplacer `services` selon le métier.
4. **Contenu** — remplacer `data-modele/` par le contenu du nouveau projet et
   vider `data/` de ses JSON : ils se recréeront à la première visite.
5. **Traductions** — supprimer `data/traductions/` et `data/langues.json`, les
   langues se recréent depuis l'admin.
6. **Textes d'interface** — les mots des gabarits passent par `t('…')`. Ils
   sont relevés automatiquement dans le code : rien à déclarer.
7. **Cookies** — adapter les catégories dans `Cookies::categories()`.
8. **Assistant** — la consigne système est dans `Assistant::consigne()` : elle
   dit au modèle de quoi il parle, sur quel ton, et quand ramener vers le
   guichet. C'est le seul endroit à réécrire pour un autre métier.
9. **Blocs** — `Blocs::TYPES` décrit les blocs de contenu et `Blocs::ICONES`
   les pictogrammes disponibles. Un métier qui demande une forme de contenu
   inédite l'ajoute là, plus un `case` dans `views/partials/bloc.php`.
10. **Inventaire** — `Contenus::tout()` déduit la liste des fichiers de contenu
    de `Seo::PAGES` et des collections. Quatre écrans s'en servent ; aucun n'en
    tient plus sa propre copie.

---

## 8. À ne jamais livrer ni versionner

- `data/admin/` — compte administrateur et mot de passe SMTP
- `storage/` — cache, sauvegardes, archives de déploiement
- `public/assets/img/site/` — photos du client
- `data/*.json` en écrasement — c'est le contenu vivant du site

Ces quatre chemins sont exclus du déploiement par git : `Deploiement::CODE`
liste explicitement ce qui est mis à jour, tout le reste est intouchable. Ce
choix est structurel, pas procédural — le contenu du client ne peut pas être
écrasé par une mise à jour, même si le dépôt contient des fichiers différents.

---

## 9. Essayer en local

```bash
php -S localhost:8080 -t public public/index.php
```

Puis `/admin` pour créer le compte administrateur. Aucun identifiant par
défaut : l'écran de première configuration s'en charge.

Sans les photos du client (`public/assets/img/site/`), le site fonctionne et
affiche le visuel « photo à venir » partout — c'est voulu.

---

## 10. Pièges rencontrés, à ne pas refaire

**Deux défauts silencieux du socle, trouvés par `alertes.py` et corrigés.** Ils
méritent la première place parce qu'ils ne se voient nulle part : pas d'erreur,
pas d'alerte dans la page, juste une donnée qui n'arrive pas.

- **`View::capture()` écartait toute donnée portant le nom d'une de ses
  variables locales.** `extract($donnees, EXTR_SKIP)` saute ce qui existe déjà :
  un contrôleur passant `'file' => [...]` voyait son tableau remplacé par le
  chemin du gabarit. Les variables locales de cette méthode sont désormais
  préfixées `$__`, ce qui ferme la question pour de bon plutôt que d'interdire
  une liste de noms que personne ne retiendra.
- **`Parametres::tout()` ignore toute section absente de `DEFAUTS`.** Elle est
  écrite dans le fichier, puis jamais relue. Le réglage paraît ne pas
  s'enregistrer et l'on cherche du côté des droits pendant une heure. **Toute
  nouvelle section de réglages doit être déclarée dans `DEFAUTS`**, ce qui
  documente au passage ses valeurs livrées.


**Trois défauts de la file de publication, du même genre : invisibles parce
qu'on ne les atteint qu'en production.**

- **`flashDonnees()` est consommé à la lecture.** La liste des Pages Facebook y
  était rangée entre l'aller et le retour du dialogue Meta ; l'écran qui
  affiche cette liste la lisait donc le premier, et le formulaire de choix
  arrivait sur une session vide. Le choix échouait toujours — sauf quand le
  compte n'administre qu'une seule Page, cas où elle est retenue sans passer
  par l'écran, et c'est pourquoi l'essai manuel n'avait rien vu. **Un flash
  sert à un message affiché une fois, jamais à un état qu'un formulaire
  relira.**
- **Deux déclencheurs valent deux envois.** La file est dépilée par la tâche
  planifiée et par les visites du back-office. Rien n'empêchait les deux de
  lire la même publication au même instant et de l'envoyer chacun de leur
  côté. Les écritures atomiques n'y peuvent rien : elles garantissent qu'un
  fichier n'est pas coupé en deux, pas qu'une ligne n'est lue qu'une fois. Il
  faut un `flock(LOCK_EX | LOCK_NB)`, et celui qui arrive second s'en va.
- **« Au moins un identifiant » n'est pas « tout est parti ».** Facebook
  accepté et Instagram refusé, la publication était retirée de la file et
  inscrite au journal comme réussie. Instagram n'était jamais retenté et
  personne ne l'apprenait. La condition de sortie de file est l'absence de
  motif d'échec, pas la présence d'un succès.

**Compter les essais ne remplace pas un délai entre eux.** Trois essais faits
dans la même minute, parce que trois écrans ont été ouverts coup sur coup,
épuisent le quota sans rien tenter de neuf. Un recul croissant — cinq minutes,
puis trente, puis deux heures — donne à la panne le temps de passer.

**Un service extérieur appelé à l'affichage d'un écran doit être bridé deux
fois.** Le dépilage greffé sur `/admin/reseaux` relançait un appel à Meta à
chaque rafraîchissement : Meta injoignable, l'écran attendait le délai réseau
avant de s'afficher — et c'est justement l'écran où l'on vient voir ce qui ne
va pas. Il faut un repos entre deux tentatives *et* un plafond sur le nombre
d'envois par affichage.

**Un texte assemblé en deux temps ne peut pas être compté.** Le lien était
ajouté au moment de préparer, le titre au moment d'envoyer, et la coupe aux
2 000 caractères tombait après les deux ; le compteur de l'écran, lui, ne
comptait que le corps du texte. On lisait « 1 990 / 2 000 » et le message
partait coupé en pleine phrase. **Un seul assemblage, une seule mesure**, et
le compteur du navigateur refait exactement le même.

**Deux points d'entrée d'une même API n'acceptent pas les mêmes champs.** Chez
Meta, `/feed` prend un `link` dont il tire un aperçu ; `/photos` ne connaît
qu'une légende. Une publication illustrée renvoyant vers le site perdait donc
son adresse — c'est-à-dire tout son objet. Vérifier champ par champ, jamais
par analogie.

**Un auditeur qui écrit dans `data/` en écrase un autre.** Le premier
`aller-retour.py` remettait `data/` à zéro pour partir d'un contenu propre.
Lancé pendant que `bulle.py` tournait — celui-ci allume l'assistant en
écrivant dans `data/admin/parametres.json` —, il a fait disparaître le bouton
que l'autre mesurait : deux écarts « bulle absente » qui ne venaient pas du
site. Sur un poste de développement, il aurait détruit le contenu saisi. Un
auditeur qui a besoin d'un contenu neuf doit prendre **son propre dossier**,
d'où la variable `APP_DATA` de `config/config.php`.

**Un inventaire écrit à la main prend du retard sans le dire.** Deux listes de
ce dépôt sont tenues à la main : les écrans du back-office que `alertes.py`
visite, et `Seo::CONTENUS`, où l'on réécrit les liens internes quand un slug
change. La première avait oublié trois écrans, dont l'écran Identité du site :
jamais visités, donc jamais mesurés. Confrontez chaque inventaire à ce qu'il
prétend inventorier — les routes déclarées, les fichiers présents — plutôt que
d'espérer que quelqu'un pense à le compléter.

**Une valeur héritée du socle survit à tout, parce qu'elle ne casse rien.** Le
`theme-color` de l'ancien site, le nom d'une menuiserie dans le `.htaccess` et
dans l'agent utilisateur, une clé `reservation` pour un bouton d'appel, un
champ « Fondatrice » portant le nom du maire : rien de tout cela ne produit
d'erreur, donc rien ne le signale. Faites la chasse au nom propre et à la
couleur écrite en dur avant de livrer, ils ne sortiront jamais d'eux-mêmes.


Cinq bugs réels de ce développement. Ils se reproduiront à l'identique sur un
projet bâti sur le même socle.

**`var` n'est pas limité au bloc.** `site.js` est une seule fonction ; un
`var panneau` écrit dans une section a écrasé celui d'une autre. Le menu est
resté `inert`, donc insensible aux clics, qui traversaient jusqu'au voile —
lequel referme le menu. Chaque section a maintenant sa propre portée.

**Un traducteur automatique traduit tout, y compris les chemins de fichiers.**
`assets/img/…` est revenu en `asset/img/…` en allemand, et toutes les photos
ont disparu. Deux garde-fous : une liste de clés techniques qui écarte toute
leur descendance, et un contrôle sur la valeur (chemin, adresse, e-mail).

**Une marge négative « compensatoire » sur un élément centré le décale.**
`left: 50%` + `translateX(-50%)` centre déjà ; un `margin-inline: -1.4rem`
censé annuler un rembourrage symétrique déplaçait le libellé de 22 px.

**Un décalage vers le bas allonge la zone défilable.** Des éléments qui entrent
en `translateY(10px)` font apparaître puis disparaître l'ascenseur pendant
l'animation. Préférer un décalage horizontal, ou un flou qui se dissipe : ni
l'un ni l'autre ne touche à la mise en page.

**`iconv('ASCII//TRANSLIT')` dépend de la locale du serveur.** Sous locale `C`
ou `POSIX`, « hébergement » devient « h?bergement » et le slug « h-bergement ».
La translittération est faite par une table explicite.

**Une règle d'affichage explicite l'emporte sur l'attribut `hidden`.** Le
panneau de consentement porte `hidden` au chargement ; lui donner un
`display: flex` en CSS le rendait visible d'emblée sur toutes les pages. Il
faut rétablir soi-même `[hidden] { display: none }` dans la portée concernée.

**Un service extérieur injoignable ralentit tout le site.** Sans temps de
repos après échec, chaque affichage de page relançait l'appel à Google et
attendait le délai réseau. Un échec est désormais horodaté dans le cache et
n'est retenté qu'une demi-heure plus tard.

**`scroll-snap: mandatory` annule une animation de défilement écrite en JS.**
Le carrousel semblait sauter d'une carte à l'autre : à chaque image, le
navigateur ramenait la piste sur son point d'ancrage le plus proche. Il faut
suspendre l'ancrage le temps du mouvement et le rendre ensuite au doigt.

**Une barre de défilement native est flottante : elle n'annonce rien.** Un
bloc borné en hauteur ne montrait aucun indice qu'il restait à lire, les
barres modernes n'apparaissant qu'en défilant. Elle est dessinée à la main,
et seulement sur les blocs qui débordent — une feuille de style ne sait pas
mesurer un débordement, il faut le repérer en JS.

**Un élément centré en absolu rencontre son voisin quand l'écran rétrécit.**
Le monogramme de l'en-tête, posé à `left: 50%`, passait par-dessus le nom de
l'entreprise sous 780 px. Le masquer coûtait l'identité visuelle ; la bonne
réponse est de le faire rentrer dans le flux et de centrer le groupe.

**Deux boutons dont la position dépend du contenu se cherchent à chaque
image.** Les flèches de la visionneuse encadraient la photo : une image
verticale les rapprochait, une panoramique les écartait. Ancrées aux bords de
la fenêtre, elles ne bougent plus.

**`backdrop-filter` interdit de calculer la couleur composite.** Vouloir
choisir la teinte d'une barre translucide pour que le rendu final atteigne un
contraste donné ne marche pas : le flou et la saturation s'appliquent avant
que la couche ne se pose. Il faut mesurer les pixels réellement peints.

**Un `<form>` imbriqué dans un autre n'existe pas en HTML.** Le formulaire
d'ajout de bloc, rendu à l'intérieur du formulaire d'édition, était écarté en
silence par le navigateur : le bouton disparaissait purement et simplement de
la page, sans erreur ni avertissement. Il faut le rendre après le formulaire
principal, et recopier la saisie en cours en champs cachés au moment du clic —
sinon cliquer « ajouter » fait perdre ce qui vient d'être tapé.

**Un `<details>` replié ignore l'ancre de l'URL.** Après un ajout, le serveur
renvoie vers `#bloc-7` ; le navigateur ne déplie pas le dépliant et l'on
retombe en haut d'une page de quinze blocs sans savoir lequel vient d'être
créé. Il faut l'ouvrir en JavaScript au chargement et à chaque `hashchange`.

**Un même formulaire lu à deux endroits finit par diverger.** L'enregistrement
et l'ajout de bloc reçoivent exactement le même formulaire ; les faire lire
chacun de leur côté a suffi à ce que le second oublie le chapô et le bandeau,
et les efface à chaque ajout. Une seule méthode lit le formulaire, les deux
l'appellent.

**Le chargement différé ne se déclenche pas sur un saut au bas de page.**
Chromium ne charge une image différée que lorsqu'elle approche du cadre : sur
une page de sept mille pixels, un `scrollTo(0, scrollHeight)` laisse dormir
tout le milieu, et l'attente qui suit expire pour rien. Il faut descendre par
paliers d'une hauteur de fenêtre.

**Supprimer un bloc de code voisin emporte parfois une méthode utilisée.**
En sortant la FAQ de la page contact, `contact()` et `contactEnvoi()` sont
partis avec — la route existait toujours, d'où une erreur 500. Après toute
suppression, croiser les méthodes appelées par les routes avec celles
réellement définies.

---

## 11. Vérifications utiles

Le développement s'est appuyé sur des mesures plutôt que sur l'œil :

- **Contraste** — parcourir toutes les pages, composer les fonds translucides
  et comparer au seuil WCAG AA (4,5:1, ou 3:1 pour le grand texte). Un audit
  qui ne compose pas les couches produit des faux positifs.
- **Animations** — échantillonner les styles calculés image par image plutôt
  que de juger sur une capture ; c'est ce qui a permis de chiffrer un saut à
  0,045 puis de vérifier qu'il tombait à 0,0007.
- **Débordement** — surveiller `scrollHeight - clientHeight` pendant une
  animation pour détecter un ascenseur qui clignote.
- **Traceurs** — compter les requêtes réseau vers les domaines tiers après un
  refus de consentement. La bonne valeur est zéro.
- **Téléphone** — parcourir toutes les pages à 320, 390 et 768 px et relever
  tout élément qui sort du cadre. Écarter les faux positifs : une piste qui
  défile horizontalement déborde par construction, et un élément à opacité
  nulle n'est pas encore révélé.
- **Cibles tactiles** — relever les liens et boutons de moins de 40 px de
  haut. La marge intérieure les agrandit sans toucher à la taille du texte ;
  `background-clip: content-box` garde la peinture sur le rectangle visible.
- **Routes** — croiser les méthodes appelées dans les tables de routage avec
  celles définies dans les contrôleurs, puis ouvrir chaque écran du
  back-office et vérifier le code HTTP.
