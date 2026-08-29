# Produire un nouveau site sur ce modèle

Recette complète, destinée à Claude Code, pour livrer un site vitrine
identique à celui-ci — design, ergonomie et technique — pour un autre client,
sans repartir de zéro et sans repayer les erreurs déjà payées.

**Lisez `CLAUDE.md` avant celui-ci** : il porte les contraintes qu'aucune
tâche ne peut casser. `KIT.md` reste la carte du code ; ce document-ci est
l'itinéraire.

---

## 0. Ce que vous allez livrer

Un site vitrine complet, en PHP natif, **sans aucune dépendance** — ni
Composer, ni base de données, ni build front — avec :

- 8 à 12 pages publiques dont une collection éditable (les « prestations ») ;
- un back-office complet : contenu, photos, référencement, avis, traductions,
  paramètres, sauvegardes ;
- deux formulaires protégés (contact et devis), une barrière de consentement
  aux contenus tiers, un assistant de discussion optionnel ;
- multilingue, et déployable par FTP sur un mutualisé à 3 € par mois.

**Comptez une journée** si vous suivez cet itinéraire, contre plusieurs si
vous redécouvrez les arbitrages.

---

## 1. Ce qu'il faut obtenir du client avant d'écrire une ligne

| Élément | Sans quoi | Où ça atterrit |
|---|---|---|
| **Charte graphique** (PDF vectoriel de préférence) | Rien de crédible | Les jetons CSS, les logos SVG |
| **Logos** : horizontal, vertical, emblème seul | L'en-tête et le pied | `public/assets/img/logo/` |
| **30 à 60 photos de chantiers**, pas de banque d'images | Le site sonne faux | `public/assets/img/site/` |
| **Liste des prestations**, avec le vocabulaire du métier | Des généralités | La collection `services` |
| **Coordonnées, zone d'intervention, horaires** | Le référencement local | `data/site.json` |
| **Chiffres de preuve** : années, effectif, rayon | La bande d'indicateurs | `data/pages/accueil.json` |
| **Avis clients** (fiche Google) | La preuve sociale | Admin → Avis Google |

**Trois questions à poser d'emblée**, parce que la réponse change la
structure :

1. **Menu groupé ou à plat ?** Si le client a plus de six rubriques, il faut
   grouper les prestations sous une entrée dépliante.
2. **Une page « devis » séparée du contact ?** Oui si le métier engage un
   déplacement — les deux intentions ne se mélangent pas.
3. **Assistant de discussion ?** Le module est prêt ; il ne demande qu'une clé
   Gemini, qui peut venir plus tard.

**Une charte en PDF se convertit sans outil externe.** Le flux de contenu d'un
PDF n'est qu'une suite de `m`, `l`, `c`, `re` : cela se transcrit en chemins
SVG, contours d'origine compris, plutôt que de redessiner à vue. **Piège** :
un PDF d'imprimeur exprime ses couleurs en CMJN, et la conversion naïve vers
le RVB ne rend pas la teinte imprimée. **Reprenez les hexadécimaux écrits dans
la charte, jamais ceux extraits du fichier** — sur ce projet, la conversion
naïve donnait un vert fluo au lieu du `#689B71` de la charte.

---

## 2. Le système de design, en entier

C'est la partie à reproduire **à l'identique**. Elle tient dans le bloc
`:root` de `public/assets/css/site.css`, et rien ailleurs ne porte de couleur
en dur — préservez cette propriété.

### 2.1 Les jetons

```css
:root {
  /* La couleur de charte. Elle sert aux filets, aux icônes, aux accents.
     Elle ne porte JAMAIS de texte si elle ne tient pas son contraste. */
  --vert: #689b71;

  /* Quatre variantes, une par contrainte de contraste. Ce ne sont pas des
     nuances décoratives : chacune répond à un fond où la couleur de charte
     échoue. */
  --vert-fonce: #4e7a58;   /* aplats sous texte blanc — 4,9:1 */
  --vert-texte: #3f6449;   /* petit texte sur crème — 6,1:1 */
  --vert-clair: #8fbe99;   /* accents sur l'ardoise — 6,0:1 */
  --vert-barre: #c2e2c8;   /* survols sur la barre translucide — 4,98:1 */

  /* Neutres */
  --encre: #24363f;        /* titres */
  --texte: #3a4a53;        /* corps */
  --texte-doux: #5e6e76;   /* chapôs, légendes — 4,9:1 sur le crème */
  --ligne: #dfdbd1;
  --fond: #ffffff;
  --fond-teinte: #f6f4ee;  /* alternance de sections */
  --ardoise: #24363f;      /* sections sombres, pied */
  --anthracite: #262c30;   /* barre collante, servie translucide */

  /* Une seule famille : titrage et texte se distinguent par la graisse et
     l'interlettrage, pas par la police. */
  --titre: "Montserrat", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto,
           Helvetica, Arial, sans-serif;
  --texte-police: var(--titre);

  --largeur: 1280px;
  --gouttiere: clamp(1.5rem, 5vw, 4.5rem);
  --entete-h: 96px;
  --entete-h-compact: 72px;
  --rayon: 3px;            /* boutons */
  --rayon-carte: 0;        /* photos et cartes : franc */
  --transition: .38s cubic-bezier(.25, .8, .25, 1);
}
```

**Comment dériver les quatre variantes** pour une autre couleur de marque, à
appliquer mécaniquement en ne touchant qu'à la luminosité HSL — la teinte et
la saturation restent celles de la charte, si bien que le client reconnaît sa
couleur :

```
--x-fonce   = la couleur assombrie jusqu'à 4,5:1 avec #fff
--x-texte   = la couleur assombrie jusqu'à 4,5:1 sur --fond-teinte
--x-clair   = la couleur éclaircie jusqu'à 4,5:1 sur --ardoise
--x-barre   = encore un cran plus clair, pour la barre translucide
```

**Le dernier mérite une explication**, sans quoi on le supprime en croyant
simplifier : la barre collante est translucide et floutée. Au-dessus d'une
section claire, son fond composité monte à `rgb(86,90,93)` — mesuré au pixel,
pas déduit. `--vert-clair` n'y tiendrait que 3,3:1. C'est le seul endroit du
site où il faut une cinquième valeur.

### 2.2 L'échelle typographique

Fluide : chaque taille est un `clamp()` entre le téléphone et le grand écran.
Aucun point de rupture typographique à gérer.

| Rôle | Taille | Graisse |
|---|---|---|
| Titre de bandeau | `clamp(2.3rem, 5.6vw, 4.15rem)` | 300 |
| Titre de section | `clamp(2rem, 4vw, 3.15rem)`, `max-width: 22ch` | 300 |
| Titre de carte | `1.42rem` | 300 |
| Sur-titre | `.72rem`, capitales, `letter-spacing: .22em`, filet devant | 600 |
| Chapô | `1.1–1.2rem`, couleur `--texte-doux` | 300 |
| Corps | `1rem`, interligne `1.75` | 400 |
| Bouton | `.76rem`, capitales, `letter-spacing: .14em` | 600 |

Deux détails qui font l'essentiel de l'effet :

- **Le titrage est en graisse 300, pas 600.** Contre-intuitif, et c'est
  pourtant ce qui sépare un site d'architecte d'un site de plombier. La taille
  porte ; la graisse n'a pas à s'en mêler.
- **Le titre de section est bridé à 22 caractères.** Au-delà, l'œil perd la
  ligne et le titre cesse d'être une accroche.

### 2.3 Le rythme et l'alternance

```
section          padding: clamp(3.6rem, 8vw, 6.6rem) 0
section__tete    max-width: 760px, margin-bottom: 3rem
grilles          gap: clamp(1.5rem, 3vw, 2.4rem)
```

L'alternance des fonds est une **règle**, pas une option :

```
bandeau photo → indicateurs sombres → blanc → teinté → SOMBRE → blanc → teinté → bande d'appel → pied
```

Jamais deux sections de même fond à la suite. Le passage par une section
sombre au milieu de la page est ce qui l'empêche de sembler interminable.

### 2.4 Les composants disponibles

| Classe | Ce que c'est |
|---|---|
| `.heros` / `.heros--page` | Bandeau plein écran / bandeau de page intérieure (460 px) |
| `.indicateurs` | Bande sombre de 4 chiffres |
| `.cartes` + `.carte-service` | Grille de cartes photo + texte, pastille d'icône chevauchant la photo |
| `.duo` | Image + texte côte à côte, filet décalé derrière la photo |
| `.points` | Liste numérotée à pastilles, dans un `.duo` |
| `.citation` | Bande sombre, une phrase centrée |
| `.etapes` | Étapes numérotées |
| `.galerie` | Grille de photos légendées, filtrable |
| `.implantations` | Adresses avec plan sous consentement |
| `.avis` | Carrousel d'avis Google |
| `.bande-cta` | Bande d'appel à l'action, en clôture de page |
| `.reveler` | Apparition au défilement, sur n'importe quel bloc |

### 2.5 Le mouvement

Trois règles, toutes issues de mesures image par image :

1. **N'animez que `transform` et `opacity`.** Un `filter` ou un changement
   d'échelle oblige le navigateur à refaire la trame de la couche. Sur ce
   projet, remplacer un `scaleX` par un `translateX` a fait tomber le coût
   d'une animation de trois points d'images lentes à zéro.
2. **Une courbe d'accélération CSS s'applique entre chaque paire d'étapes**,
   pas à l'animation entière. Une trajectoire en quatre étapes est donc quatre
   élans freinés bout à bout — de l'à-coup au sens propre. Séparez le
   déplacement (deux étapes, une courbe) de l'opacité (paliers, en linéaire),
   en **deux animations** sur le même élément.
3. **Un `filter` posé dans un élément que surplombe un `backdrop-filter`**
   fait repartir le flou d'arrière-plan à chaque repeinte : 15 % des images
   perdues, contre 6 % sans.

Et toujours `@media (prefers-reduced-motion: reduce)`, où tout s'arrête.

### 2.6 Le cadrage des photos

Un client fournit presque toujours des photos **en paysage**. Un cadre
portrait 4/5 n'en garde que la moitié centrale, ce qui coupe systématiquement
la personne quand elle n'est pas au milieu du cliché — c'est arrivé ici, le
visage du dirigeant était tranché en deux.

```css
.duo__media img {
  aspect-ratio: 1 / 1;            /* carré, pas 4/5 */
  object-fit: cover;
  object-position: right 30%;     /* du côté où se tient le sujet */
}
```

**Et un piège de structure** : ne donnez jamais `height: 100%` à un cadre dont
le parent est dimensionné par `aspect-ratio`. C'est une référence circulaire,
que le navigateur résout en retombant sur la hauteur naturelle de l'image —
une photo en portrait fait alors trois fois la hauteur prévue et disloque la
grille. Le cadre doit être en `position: absolute; inset: 0`.

---

## 3. L'itinéraire, étape par étape

### Étape 1 — Partir du dépôt

```bash
cp -r lachapelle-sous-chaux/ nouveau-site/ && cd nouveau-site
rm -rf .git data/pages data/*.json data/admin storage/cache/* storage/sauvegardes/*
rm -rf public/assets/img/site/* public/assets/doc/* data-modele/*
git init && git add -A && git commit -m "Socle repris du site de Lachapelle-sous-Chaux"
php -S localhost:8080 -t public public/index.php
```

`data/` se recrée seul depuis `data-modele/` à la première visite. `/admin`
crée le compte administrateur au premier passage.

### Étape 2 — L'identité

1. `data-modele/site.json` : nom, adresse, téléphone, e-mail, réseaux, menu.
2. `public/assets/img/logo/` : `logo-<marque>.svg`, `-clair.svg`,
   `embleme-*.svg`, `favicon-512.png`, `apple-touch-icon.png`.
3. Le bloc `:root` de `public/assets/css/site.css` (voir § 2.1), **puis les
   mêmes teintes éclaircies** en tête de `public/assets/css/admin.css` — le
   back-office est sur fond sombre, les valeurs du site n'y tiennent pas.

### Étape 3 — Les pages

La table `Seo::PAGES` dans `app/Core/Seo.php` liste les pages fixes et leurs
slugs. Pour chaque page :

```
Seo::PAGES                     une entrée : clé, slug, nom
data-modele/pages/<clé>.json   le contenu, en blocs
app/routes.php                 une route vers $pages->simple('<clé>')
ContenuController::GROUPES     la page apparaît dans l'éditeur du back-office
```

**Pas de vue, pas d'écran d'édition** : une page est une suite de blocs typés,
rendus par `views/partials/bloc.php` et édités par l'écran générique. C'est ce
qui fait tomber le coût d'une page de quatre fichiers à un seul.

N'écrivez une vue dédiée que si la mise en page dépend d'une donnée structurée
— un trombinoscope hiérarchisé, une liste filtrable par famille, une
médiathèque de documents groupée par année. Sur le site de la commune, cinq
vues dédiées suffisent pour trente-deux pages.

Les slugs sont modifiables depuis le back-office : changer un slug change la
route, sans toucher au code.

**Redirections** : si le client avait un site, relevez ses URL et ajoutez-les
à la table de redirections 301 en bas de `app/routes.php`. Une refonte qui
perd son référencement acquis coûte plus cher qu'elle ne rapporte.

### Étape 4 — La collection

`Seo::COLLECTIONS` associe une page à une collection. Ici c'est `services`
(les prestations) : le client peut en ajouter, renommer, dépublier depuis le
back-office, et la fiche apparaît alors d'elle-même dans le menu, le pied de
page, la page d'accueil et les filtres de la galerie.

Adaptez le nom de la collection au métier, ou remplacez-la.

### Étape 5 — Le contenu

Remplacez `data-modele/` par le contenu du nouveau client. Reprenez le
vocabulaire du métier tel qu'il le dit : les matériaux, les gestes, les
essences. **Nommer précisément est ce qui distingue un professionnel d'un
annuaire.**

Les photos vont dans `public/assets/img/site/`, avec une version `-mini`
pour les vignettes. Écrivez un `alt` qui décrit la scène, jamais le nom du
fichier — l'auditeur de mise en page refuse les seconds.

### Étape 6 — Les formulaires et l'anti-spam

`app/Core/Antispam.php` protège les deux formulaires sans aucun réglage :
champ piège, jeton d'horloge signé (refus sous trois secondes), quota de cinq
messages par heure et par adresse (comptés sur les messages **partis**, pour
ne pas enfermer dehors qui s'est trompé cinq fois).

**Pas de reCAPTCHA** : c'est un traceur soumis au consentement. Le site
soumettant les contenus tiers à l'accord du visiteur, reCAPTCHA ne se
chargerait pas pour qui refuse les cookies — et le formulaire refuserait son
envoi. La protection tomberait sur les visiteurs les plus soucieux de leur vie
privée. Un étage facultatif **Cloudflare Turnstile** s'ajoute par deux clés
dans Paramètres, et reste éteint tant qu'elles sont vides.

### Étape 7 — Les contenus tiers

Tout ce qui vient d'un autre domaine — plan d'accès, vidéo, police distante —
dort dans un `<template>` et n'est monté qu'au consentement :

```html
<div class="…__plan" data-cookies-contenu="externes">
  <div class="…__attente">… bouton data-cookies-accepter="externes" …</div>
  <template><iframe src="…"></iframe></template>
</div>
```

**Piège déjà payé** : au consentement, le script remplace le conteneur par le
contenu du `<template>`. L'iframe perd donc les proportions que portait ce
conteneur et retombe à la hauteur par défaut d'une iframe, 150 px. **L'iframe
doit porter son propre `aspect-ratio`**, identique à celui du bloc d'attente —
sans quoi le plan se réduit à une bande et la page saute.

### Étape 8 — L'assistant (facultatif)

La consigne système est dans `Assistant::consigne()`. C'est le seul endroit à
réécrire pour un autre métier : elle dit au modèle de quoi il parle, sur quel
ton, et vers quoi ramener.

**Pour un service public, trois clauses sont non négociables** et ne figuraient
pas dans la version commerciale : les secours passent avant tout renvoi vers
une page si la question évoque un danger ; l'assistant ne demande jamais de
numéro de sécurité sociale, de coordonnées bancaires ni de copie de pièce
d'identité ; il ne prend pas parti sur les décisions de l'assemblée
délibérante.

### Étape 9 — Les audits, jusqu'à zéro

```bash
php -S 127.0.0.1:8081 -t public &
python3 outils/verifs/contraste.py
python3 outils/verifs/mise-en-page.py
python3 outils/verifs/traceurs.py
python3 outils/verifs/bandeau.py
python3 outils/verifs/entete.py
```

Voir § 4. **Tant qu'ils ne sont pas à zéro, le site n'est pas fini.**

### Étape 10 — La mise en ligne

`DEPLOIEMENT.md` couvre tout : FTP, droits, mises à jour par git, dépannage.
Et `SITE.md` § « À renseigner avant la mise en ligne » liste ce qui reste à la
main du client (SMTP, clés d'API, horaires).

---

## 4. Les auditeurs

Cinq scripts dans `outils/verifs/`, qui sortent en code 1 s'ils trouvent
quelque chose — branchables tels quels dans une chaîne d'intégration.

| Script | Ce qu'il mesure |
|---|---|
| `contraste.py` | Le contraste réel de chaque texte, à 390, 768 et 1440 px |
| `mise-en-page.py` | Débordement horizontal, cibles tactiles < 44 px, unicité du `h1` et hiérarchie des titres, `alt` présents et non recopiés, lien d'évitement — à 320, 390, 768, 1024 et 1440 px |
| `traceurs.py` | Requêtes tierces après refus du consentement (bonne valeur : zéro), et vérification que l'accord débloque bien le plan |
| `bandeau.py` | Le texte du bandeau d'accueil sur **chaque** photo du diaporama forcée à son tour, à 390, 768 et 1440 px |
| `entete.py` | L'en-tête aux **bornes du réglage de taille du logo**, dans les deux modes de barre et les deux dispositions de menu : débordement, déformation, chevauchement du burger, cible tactile |

### Pourquoi deux auditeurs de plus, pour un diaporama et un curseur

Le diaporama d'accueil tire sa photo au hasard. `contraste.py` n'en mesure
donc qu'une par passage : le résultat dépend du tirage, une page passe un jour
et échoue le lendemain sans qu'une ligne ait bougé, et l'écart réel se cache
derrière un « ok » chanceux. `bandeau.py` supprime le hasard — il force chaque
photo, masque le texte, échantillonne le pire pixel de la zone occupée. Sur ce
site il a trouvé deux photos à 4,41 et 4,43:1 qu'une trentaine de passages de
`contraste.py` n'avaient jamais tirées ensemble.

La correction n'a pas été de baisser le seuil mais de monter le voile du
bandeau, un réglage de back-office : 82 → 92. Pire cas après correction :
5,53:1.

`entete.py` généralise ce raisonnement, et c'est la règle à retenir : **tout
réglage laissé au client doit avoir son auditeur qui en force les bornes.** La
taille du logo est un curseur de 36 à 120 px, avec deux modes de barre et deux
dispositions de menu ; les quatre autres scripts n'en mesurent qu'une
combinaison sur vingt-quatre, celle du jour. Aux bornes, celui-ci a trouvé un
logo servi écrasé — `img{max-width:100%}` rogne la largeur sans toucher à la
hauteur, d'où `object-fit: contain` — et une cible tactile à 40 px sur la
barre défilée. Deux défauts qu'aucun réglage ne peut vouloir, et que la mairie
aurait découverts en ligne.

### Comment `contraste.py` mesure

Deux méthodes, parce qu'aucune ne suffit :

1. **par composition** — on remonte les ancêtres en aplatissant les couches
   translucides jusqu'à un fond opaque. Exact et rapide, aveugle sur photo ;
2. **par échantillonnage des pixels peints** — on masque le texte, on capture,
   on lit le fond réel. Seule méthode valable sur un bandeau photographique ou
   sous une barre floutée.

On retient **le pire pixel** de la zone, jamais la moyenne : un titre dont un
seul mot passe sur une éclaircie est illisible sur ce mot-là.

### Sept pièges de mesure, tous rencontrés

Un auditeur naïf rend des résultats faux **dans les deux sens**, ce qui est
pire qu'aucun audit — et les trois derniers de cette liste produisaient à eux
seuls plus de cent faux écarts sur ce site. Si vous adaptez ces scripts,
gardez tous ces points :

1. **Un aplat trouvé sur `<body>` ne prouve rien.** Le corps de page est
   opaque : une chaîne d'ancêtres entièrement translucide finit toujours par y
   trouver du blanc, et l'on croit mesurer un texte sur blanc alors qu'une
   photo est peinte entre les deux.
2. **Jamais de capture pleine page.** Chromium l'obtient en agrandissant la
   fenêtre : un `min-height: 88vh` passe de 880 à 4 700 px et tout ce qui suit
   le bandeau se décale de milliers de pixels. Capturez par tranches de la
   hauteur de fenêtre, en défilant, et **effacez la barre collante** pour les
   tranches basses — elle recouvre le haut de chaque capture.
3. **Neutralisez l'animation d'entrée AVANT de relever les boîtes.** Un
   `translateX(-14px)` d'apparition suffit à faire pointer les coordonnées à
   côté du bloc une fois la page figée.
4. **Laissez le temps au masque de s'appliquer.** Poser `color: transparent`
   juste avant la capture, sans attendre, fait échantillonner les lettres
   elles-mêmes : tout texte clair et dense — un menu — ressort faussement
   illisible.
5. **Neutralisez `scroll-behavior: smooth`.** C'est une animation :
   `scrollTo` rend la main aussitôt et l'on capture pendant le trajet. Mesuré
   ici : 2 000 px demandés, 339 atteints — tout l'échantillonnage tombait
   plusieurs écrans à côté. Ajoutez `html{scroll-behavior:auto !important}` au
   masque, et relisez le `scrollY` réel après chaque saut (en bas de page il
   est borné).
6. **Attendez que les photos soient peintes.** Sans elles, le fond
   échantillonné est le blanc de la page : un titre blanc sur un bandeau non
   encore chargé ressort à 1,00:1. Et **descendez la page d'abord**, sinon on
   attend indéfiniment les images différées qui ne sont jamais entrées dans le
   cadre.
7. **`alt=""` n'est pas un oubli.** C'est la déclaration correcte d'une image
   décorative — un logo posé à côté du nom écrit en toutes lettres. Seul un
   attribut *absent* est un défaut. De même, un fichier nommé d'après son
   sujet donne un `alt` légitime qui ressemble au nom du fichier : ce qui
   trahit un `alt` recopié, c'est sa forme (un slug sans espace, une extension
   restée dedans), pas sa ressemblance.

Et une règle de conduite : **n'affaiblissez jamais un seuil pour faire passer
une page.** Corrigez la page, ou corrigez le script en expliquant pourquoi.

---

## 5. Les pièges déjà payés

Ceux qui coûtent une demi-journée si on les redécouvre. `KIT.md` § 10 en
tient la liste longue ; voici les plus chers.

**Design et lisibilité**

- La couleur de charte ne porte presque jamais de texte blanc. Sur ce projet :
  3,2:1 en aplat, 2,16:1 sur l'anthracite de la barre. D'où les variantes.
- Sur une photo de bandeau, **le sur-titre passe en blanc** et ne garde sa
  couleur que sur son filet. La teinte claire de marque n'y tenait que 2,04:1
  sur les éclaircies du cliché.
- Le texte d'un bandeau se met en **blanc plein**, jamais à 90 % : le dixième
  manquant coûte un point de contraste, ce qui suffit à passer sous le seuil.
- Une légende posée sur un dégradé garde **un plancher dense sur toute la
  hauteur des lettres** et ne s'éteint qu'au-dessus. Un dégradé qui descend
  jusqu'à la transparence sous le texte le laisse à 3,7:1 sur un cliché clair.
- Les cibles tactiles se règlent par `min-height: 44px`, pas par un
  rembourrage deviné — un rembourrage se calcule sur une hauteur de ligne qui
  varie avec la police et retombe toujours un ou deux pixels sous la cible.
  Mesuré ici : 42 et 43 px là où la règle visait 44. Une case à cocher fait
  exception : elle se tape par son étiquette, c'est donc l'étiquette qu'il
  faut mesurer.

**Technique**

- `data/` est le contenu vivant, `data-modele/` le contenu livré. Ne jamais
  écraser le premier lors d'une mise à jour : `Content::amorcer()` ne recopie
  qu'à la première lecture.
- Une écriture de contenu se fait par fichier temporaire puis `rename()`.
  Un `file_put_contents` interrompu laisse un JSON tronqué, donc un site mort.
- Une adresse fournie par le back-office et destinée à un `src` d'iframe passe
  par `filter_var(..., FILTER_VALIDATE_URL)`, sinon elle finit en requête
  sortante arbitraire.
- Le quota anti-spam compte les messages **partis**, pas les tentatives :
  compter les échecs enferme dehors le visiteur qui s'est trompé d'adresse.
- Les géocodeurs publics (Nominatim, api-adresse.data.gouv.fr) sont souvent
  bloqués depuis un hébergement mutualisé. Le plan se fait par
  `maps.google.com/maps?q=<adresse>&output=embed`, derrière le consentement,
  avec un lien « Itinéraire » toujours disponible qui, lui, ne dépose rien.

**Méthode**

- Un `pkill -f "php -S ..."` peut tuer son propre shell, la ligne de commande
  composée contenant le motif. Utilisez la forme `(commande &)`.
- Ne jugez pas une animation sur une capture : relevez les intervalles entre
  images. C'est ce qui a permis de chiffrer un à-coup, puis de vérifier qu'il
  avait disparu.

---

## 6. Ce qui se copie tel quel, ce qui se réécrit

| Se copie sans y toucher | Se réécrit à chaque projet |
|---|---|
| `app/Core/` en entier (Router, View, Content, Seo, Cookies, Csrf, Auth, Mailer, Antispam, Mediatheque, Liste, Deploiement, Avis, Assistant, Conversations, Permissions, Parametres, Langues) | Le bloc `:root` des deux CSS |
| `app/Admin/` en entier — les écrans sont génériques | `data-modele/` en entier |
| `app/Admin/Blocs.php`, sauf les pictogrammes | `Blocs::ICONES` : les pictos du métier |
| `views/partials/` (en-tête, pied, cookies, icônes, galerie) | `Seo::PAGES` et `Seo::COLLECTIONS` |
| La structure de `public/assets/css/site.css` après `:root` | Les logos et les photos |
| `public/assets/js/site.js` | `Assistant::consigne()` |
| `outils/verifs/` | Les redirections 301 de l'ancien site |

**Le rapport est de l'ordre de neuf pour un** : l'essentiel du code ne bouge
pas d'un client à l'autre. Ce qui change tient dans les jetons, le contenu et
la table des pages.

Mesuré sur le passage du site de paysagiste à celui de la commune : le socle
`app/Core/` n'a bougé que sur trois points — les données structurées
(`LandscapingBusiness` → `GovernmentOrganization`), la lecture des horaires
jour par jour, et la consigne de l'assistant. Tout le reste est du contenu, des
jetons, des blocs et une table de pages.

---

## 7. Vérification finale avant livraison

```bash
# le site répond partout — la liste se lit dans le plan du site, donc
# aucune adresse n'est oubliée quand la table Seo::PAGES grossit
curl -s http://127.0.0.1:8081/sitemap.xml \
  | grep -o '<loc>[^<]*</loc>' | sed 's#</\?loc>##g' \
  | while read -r u; do
      printf '%-40s %s\n' "$u" "$(curl -s -o /dev/null -w '%{http_code}' "$u")"
    done | grep -v ' 200$'   # ne doit rien afficher

# aucune alerte PHP
curl -s http://127.0.0.1:8081/ | grep -ci "warning\|notice\|fatal"   # → 0

# les cinq auditeurs
python3 outils/verifs/contraste.py && \
python3 outils/verifs/mise-en-page.py && \
python3 outils/verifs/traceurs.py && \
python3 outils/verifs/bandeau.py && \
python3 outils/verifs/entete.py
```

Puis, à la main, ce qu'aucun script ne voit :

- le formulaire part réellement (SMTP réglé, message reçu, `Reply-To` correct) ;
- le back-office enregistre et relit chaque écran ;
- le bandeau cookies refuse, accepte, et se souvient ;
- le site s'affiche correctement avec les images non chargées (connexion lente).

---

## En une phrase

Le socle ne bouge pas ; ce qui change tient dans vingt lignes de jetons, un
dossier de contenu et une table de pages — et la qualité vient des cinq
auditeurs, pas du goût.
