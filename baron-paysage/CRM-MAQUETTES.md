# Produire trois maquettes à partir du site d'un prospect

Ce document s'adresse au développeur — et au modèle — d'un CRM de prospection
qui doit, à partir du code et du visuel du site existant d'une entreprise,
proposer **trois pages HTML/CSS** : une page d'accueil, une page « à propos »
et une page « prestations ».

Il décrit un système de design éprouvé sur un site réel, la marche à suivre
pour l'adapter à un prospect, et les règles qui font la différence entre une
maquette qui impressionne et une maquette qui fait « gabarit gratuit ».

---

## 1. Ce que contient l'archive

```
baron-paysage/
├── CRM-MAQUETTES.md        ← ce document
├── SITE.md                 ← le dossier du site réel : décisions et arbitrages
├── KIT.md                  ← l'architecture du socle PHP dont il est issu
├── DEPLOIEMENT.md
│
├── maquettes/              ← LE LIVRABLE POUR LE CRM
│   ├── socle.css           ← le système de design, autonome, ~600 lignes
│   ├── socle.js            ← 60 lignes : barre collante + révélation
│   ├── accueil.html        ← maquette 1/3
│   ├── a-propos.html       ← maquette 2/3
│   ├── prestations.html    ← maquette 3/3
│   ├── verif-contraste.py     ← audit des contrastes
│   ├── verif-mise-en-page.py  ← audit débordements, cibles, titres, alt
│   └── img/                ← photos de démonstration
│
├── app/  views/  public/  data-modele/     ← le site complet en PHP
└── ...
```

Deux niveaux de lecture :

- **Pour brancher le CRM**, seul `maquettes/` compte. C'est du HTML et du CSS
  purs : aucune dépendance, aucun build, aucun framework. Ouvrez
  `maquettes/accueil.html` dans un navigateur, ça marche.
- **Pour comprendre d'où viennent les choix**, `SITE.md` et le code PHP. Le
  socle de maquettage en est la distillation.

---

## 2. Ce que le CRM doit produire, et pourquoi ça marche

Le pari est simple : un prospect qui reçoit trois pages à son nom, avec ses
photos et ses couleurs, ne compare pas une prestation à une autre — il se
projette. Encore faut-il que les pages soient bonnes.

Ce qui fait qu'elles le sont tient en cinq points, et aucun n'est une question
de goût :

1. **Une seule famille typographique**, déclinée par la graisse et
   l'interlettrage. Deux polices mal accordées coûtent plus cher qu'elles ne
   rapportent, et un modèle en choisit rarement deux qui s'accordent.
2. **Des photos franches, jamais arrondies.** Un angle arrondi sur une
   photographie signe le gabarit gratuit. Les boutons, eux, ont 3 px.
3. **Une alternance stricte** blanc / teinté / sombre, avec un même rythme
   vertical. C'est elle qui donne l'impression de « site cher » avant même
   qu'on ait lu un mot.
4. **Des contrastes mesurés, pas estimés.** C'est le point où presque toutes
   les maquettes générées échouent — voir § 6, c'est le plus important de ce
   document.
5. **Du mouvement discret et gratuit pour le processeur.** Une animation qui
   saccade fait plus de mal qu'une page immobile.

---

## 3. La chaîne, en quatre temps

```
   Site du prospect              Extraction            Coulage            Livrable
  ┌──────────────────┐      ┌────────────────┐   ┌──────────────┐   ┌──────────────┐
  │  HTML + CSS      │      │ 1 couleur      │   │ bloc :root   │   │ accueil      │
  │  photos          │ ───► │ 1 police       │──►│ de socle.css │──►│ à-propos     │
  │  textes          │      │ 8-12 photos    │   │ + 3 gabarits │   │ prestations  │
  │  coordonnées     │      │ le vocabulaire │   │              │   │              │
  └──────────────────┘      └────────────────┘   └──────────────┘   └──────────────┘
                                                        │
                                                        ▼
                                              ┌────────────────────┐
                                              │ CORRECTION DES     │  ← § 6
                                              │ CONTRASTES         │     jamais sauter
                                              └────────────────────┘
```

### Temps 1 — Extraire du site du prospect

Ce qu'il faut aller chercher, et rien d'autre :

| À extraire | Où le trouver | Ce qu'on en fait |
|---|---|---|
| **La couleur de marque** | Le logo (SVG de préférence), puis les boutons et les liens du CSS | Devient `--marque` |
| **La police** | `font-family` du `body` dans le CSS, ou le `<link>` Google Fonts | Devient `--police`. Si elle n'est pas disponible en webfont libre, prendre la plus proche |
| **8 à 12 photos** | Les balises `<img>`, en préférant les grandes dimensions ; éviter les logos et les pictogrammes | Bandeaux, cartes, galerie |
| **Le vocabulaire métier** | Les titres `<h1>`–`<h3>` et les listes de prestations | Les noms de prestations, les matériaux, les gestes |
| **Les preuves** | Chiffres, années d'expérience, nombre de salariés, avis | La bande d'indicateurs |
| **Les coordonnées** | Pied de page, page contact | En-tête, pied, bande d'appel |
| **La zone d'intervention** | Souvent dans le pied ou le référencement local | Le sur-titre du bandeau |

**Ce qu'il ne faut surtout pas reprendre :** la mise en page du prospect, ses
ombres, ses dégradés, ses arrondis. C'est précisément ce qu'on lui propose de
remplacer. On ne reprend que sa **matière** : couleur, police, photos, mots.

### Temps 2 — Choisir une couleur, en déduire quatre

C'est l'étape que les générateurs bâclent. Une couleur de marque ne suffit
pas : selon le fond sur lequel on la pose, elle ne tient pas le contraste.

Sur le projet Baron, le vert de charte `#689b71` était parfait pour un filet
et illisible partout ailleurs : **3,2:1** en aplat sous du texte blanc, **2,16:1**
sur l'anthracite de la barre. Il a fallu en dériver quatre variantes, chacune
répondant à un besoin précis :

| Jeton | Rôle | Contrainte |
|---|---|---|
| `--marque` | Filets, icônes, accents, bordures | Aucune : elle ne porte pas de texte |
| `--marque-fonce` | Aplats portant du **texte blanc** (boutons pleins, pastilles) | ≥ 4,5:1 avec le blanc |
| `--marque-texte` | **Petit texte** de marque sur fond clair (sur-titres, liens) | ≥ 4,5:1 sur le fond teinté |
| `--marque-claire` | Accents sur **fond sombre** (pied de page, bandes) | ≥ 4,5:1 sur `--sombre` |

**Recette de dérivation**, à appliquer mécaniquement :

```
--marque-fonce   = --marque assombri jusqu'à obtenir 4,5:1 avec #fff
--marque-texte   = --marque assombri jusqu'à obtenir 4,5:1 avec --fond-teinte
--marque-claire  = --marque éclairci jusqu'à obtenir 4,5:1 avec --sombre
--marque-voile   = --marque à ~8 % d'opacité sur blanc, aplaties en hex
```

Assombrir/éclaircir se fait en HSL en ne touchant qu'à la luminosité : la
teinte et la saturation restent celles de la charte, si bien que le prospect
reconnaît sa couleur.

### Temps 3 — Couler dans le socle

Un seul bloc à réécrire dans `socle.css` : `:root`. Tout le reste s'accorde
par héritage. Il n'y a aucune couleur en dur ailleurs dans la feuille —
c'est une propriété à préserver si vous la faites évoluer.

### Temps 4 — Remplir les trois gabarits

Voir § 5. Chaque section porte un commentaire HTML qui dit son rôle et
pourquoi elle est là.

---

## 4. Le système de design

### Les jetons

```css
:root {
  --marque: #b4573a;          /* la couleur du prospect */
  --marque-fonce: #9a4229;    /* aplats portant du texte clair */
  --marque-texte: #8f3d26;    /* petit texte sur fond clair */
  --marque-claire: #e8a68e;   /* accents sur fond sombre */

  --encre: #2b2724;           /* titres */
  --texte: #46403b;           /* corps */
  --texte-doux: #6b625b;      /* chapôs, légendes */
  --ligne: #e2ddd6;
  --fond: #ffffff;
  --fond-teinte: #faf7f3;     /* sections alternées */
  --sombre: #2b2724;          /* citations, bandes, pied */

  --police: "Montserrat", ...;
  --largeur: 1280px;
  --gouttiere: clamp(1.5rem, 5vw, 4.5rem);
  --entete-h: 96px;
  --entete-h-compact: 72px;
  --rayon: 3px;               /* boutons */
  --rayon-carte: 0;           /* photos et cartes : franc */
  --transition: .38s cubic-bezier(.25, .8, .25, 1);
}
```

### L'échelle typographique

Elle est **fluide** : chaque taille est un `clamp()` qui interpole entre le
téléphone et le grand écran. Aucun point de rupture typographique à gérer.

| Rôle | Taille | Graisse | Remarque |
|---|---|---|---|
| Titre de bandeau | `clamp(2.3rem, 5.6vw, 4.15rem)` | 300 | La légèreté fait le haut de gamme |
| Titre de section | `clamp(2rem, 4vw, 3.15rem)` | 300 | Bridé à **22 caractères** de large |
| Titre de carte | `1.42rem` | 300 | |
| Sur-titre | `.72rem` | 600 | Capitales, interlettrage `.22em`, précédé d'un filet |
| Chapô | `1.1–1.2rem` | 300 | Couleur `--texte-doux` |
| Corps | `1rem` / interligne `1.75` | 400 | |
| Bouton | `.76rem` | 600 | Capitales, interlettrage `.14em` |

Deux détails qui font beaucoup :

- **Le titrage est en graisse 300, pas 600.** Contre-intuitif, et pourtant
  c'est ce qui distingue un site d'architecte d'un site de plombier. La taille
  porte, la graisse n'a pas à s'en mêler.
- **Le titre de section est bridé à 22 caractères** (`max-width: 22ch`).
  Au-delà, l'œil perd la ligne et le titre cesse d'être une accroche.

### Le rythme vertical

```
section          padding: clamp(3.6rem, 8vw, 6.6rem) 0
section__tete    margin-bottom: 3rem, max-width 760px
grilles          gap: clamp(1.5rem, 3vw, 2.4rem)
```

Et l'alternance des fonds, qui est une **règle**, pas une option :

```
bandeau photo → indicateurs sombres → blanc → teinté → SOMBRE → blanc → teinté → bande d'appel → pied
```

Jamais deux sections de même fond à la suite. Le passage par une section
sombre au milieu de la page est ce qui l'empêche de sembler interminable.

### Les composants

| Classe | Ce que c'est | Où s'en servir |
|---|---|---|
| `.heros` | Bandeau plein écran, photo + voile dégradé | Accueil |
| `.heros--page` | Le même en 460 px de haut | Pages intérieures |
| `.indicateurs` | Bande sombre de 4 chiffres | Sous le bandeau |
| `.cartes` / `.carte` | Grille de 3 (ou 2) cartes photo + texte | Prestations |
| `.duo` | Image + texte côte à côte, avec filet décalé | Présentation, détail |
| `.points` | Liste numérotée à pastilles | Dans un `.duo` |
| `.citation` | Bande sombre, une phrase centrée | Respiration au milieu |
| `.etapes` | Trois étapes numérotées | Méthode, déroulé |
| `.galerie` | Grille de photos légendées au survol | Réalisations, matériaux |
| `.bande-cta` | Bande sombre d'appel à l'action | Clôture de chaque page |
| `.pied` | Pied de page en 4 colonnes | Partout |
| `.reveler` | Apparition au défilement | Sur n'importe quel bloc |

---

## 5. Les trois pages

Elles ne se distinguent pas par leur décor mais par **la question à laquelle
elles répondent**. C'est ce qui doit guider le remplissage.

### Page d'accueil — « est-ce que je suis au bon endroit ? »

Le visiteur arrive de Google, ne connaît pas l'entreprise et décidera en
quatre secondes. La page doit dire le métier, le territoire et le niveau de
gamme avant tout le reste.

| # | Section | Contenu | Pourquoi |
|---|---|---|---|
| 1 | `.heros` | Sur-titre « métier + ville », titre de 5 à 7 mots, deux boutons | La seule phrase que lira un visiteur pressé |
| 2 | `.indicateurs` | 4 chiffres | La preuve avant l'argument. **Jamais plus de 4** : au-delà, aucun ne se retient |
| 3 | `.cartes` ×3 | Les trois métiers, chacun avec un vrai chantier en photo | **Trois, pas quatre.** Une page d'accueil trie, elle ne catalogue pas |
| 4 | `.duo` | L'entreprise et son dirigeant, + 2 `.points` | Le moment où la page devient quelqu'un |
| 5 | `.citation` | Une phrase du dirigeant | Respiration sombre, et une voix |
| 6 | `.galerie` ×6 | Réalisations récentes | La preuve visuelle |
| 7 | `.bande-cta` | Devis + téléphone | |

**Le piège :** vouloir tout mettre. Si le prospect a huit prestations, la page
d'accueil en montre trois et renvoie vers la page prestations.

### Page « à propos » — « à qui ai-je affaire ? »

Elle ne vend pas, elle rassure. L'ordre compte : la personne d'abord, la
méthode ensuite, les preuves enfin.

| # | Section | Contenu | Pourquoi |
|---|---|---|---|
| 1 | `.heros--page` | Photo d'équipe, titre situant le territoire | Situer, pas retenir |
| 2 | `.duo` | **Le portrait du dirigeant**, cadre 4/5, + une citation en exergue | La première chose qu'on cherche ici, c'est un visage |
| 3 | `.indicateurs` | Les mêmes chiffres qu'en accueil | La répétition ancre — c'est voulu |
| 4 | `.etapes` ×3 | Comment se déroule un chantier | Répond à « et ensuite, il se passe quoi ? », la question qui retient la main sur le bouton |
| 5 | `.cartes--2` | Deux engagements, avec photo | Deux blocs larges valent mieux que quatre slogans étroits |
| 6 | `.citation` | | |
| 7 | `.bande-cta` | | |

**Le piège :** l'historique chronologique (« fondée en 1998, nous avons… »).
Personne ne le lit. Ce qui se lit, c'est une personne, une méthode, des
chiffres.

### Page « prestations » — « est-ce qu'ils font ce dont j'ai besoin ? »

Le visiteur sait ce qu'il veut et vérifie. Cette page **liste**, et
précisément : les matériaux sont nommés, les gestes aussi. Les généralités y
sont contre-productives.

| # | Section | Contenu | Pourquoi |
|---|---|---|---|
| 1 | `.heros--page` | | |
| 2 | `.cartes--2` ×4 | Les prestations, deux par ligne | Ici on peut dépasser trois : le visiteur est venu lire |
| 3 | `.duo` + `.points` | **Une prestation en détail.** À dupliquer, en alternant le côté de la photo (`.duo--inverse`) | Le détail est ce qui distingue un professionnel d'un annuaire |
| 4 | `.galerie` légendée | Les matériaux, chacun nommé dans sa légende | C'est ce qu'un devis comparera |
| 5 | `<details>` ×3 | Questions fréquentes : délai, prix, urbanisme | Lève les objections avant qu'elles ne servent de prétexte à partir |
| 6 | `.bande-cta` | | |

**Le piège :** les pictogrammes génériques. Une carte de prestation montre un
chantier réel du prospect, sinon elle ne prouve rien. S'il manque des photos,
mieux vaut trois cartes documentées que six vides.

---

## 6. Les règles non négociables

Elles viennent toutes d'un défaut réellement rencontré et mesuré sur ce
projet. Ce sont elles qui séparent une maquette crédible d'une maquette
qu'un professionnel refusera.

### Le contraste, mesuré et non estimé

**C'est le point où l'immense majorité des maquettes générées échouent.** Un
modèle qui choisit « du gris clair sur du crème » produit quelque chose de
joli sur son écran et illisible en plein soleil — et hors la loi pour un site
public.

Trois mesures ont dû être corrigées sur ce projet, toutes trouvées par un
audit automatique et aucune à l'œil :

| Défaut trouvé | Mesuré | Corrigé en |
|---|---|---|
| Texte doux sur fond teinté | 4,36:1 | Assombri à 4,9:1 |
| Vert de marque en texte sur fond sombre | 2,16:1 | Variante claire, 6,0:1 |
| Sur-titre clair sur photo de bandeau | 2,04:1 | Blanc + filet coloré, 8,1:1 |

**Deux façons de mesurer, et il faut les deux.**

1. **Par composition**, pour le texte sur aplat : on remonte les ancêtres en
   composant les couches translucides jusqu'à un fond opaque.
2. **Par échantillonnage des pixels peints**, pour le texte sur photo ou
   derrière un flou : on masque le texte, on capture la page, et on lit le
   fond réel sous chaque bloc. On retient **le pire pixel** de la zone, pas la
   moyenne — un titre dont un seul mot passe sur une éclaircie est illisible
   sur ce mot-là.

Le script fourni (`maquettes/verif-contraste.py`) fait les deux : la
composition tranche quand elle le peut, l'échantillonnage prend le relais
sinon.

**Les seuils** (WCAG AA) : 4,5:1 pour le texte courant, 3:1 au-delà de 24 px
ou de 18,66 px en gras.

#### Quatre pièges de mesure, tous rencontrés en écrivant ce script

Ils font qu'un auditeur naïf rend des résultats faux — dans les deux sens, ce
qui est pire qu'aucun audit.

1. **Un aplat trouvé sur `<body>` ne prouve rien.** Le corps de page est
   opaque : une chaîne d'ancêtres entièrement translucide finit toujours par y
   trouver du blanc, et l'on croit mesurer un texte sur blanc alors qu'une
   photo est peinte entre les deux. Si le seul aplat opaque est celui du
   corps, il faut échantillonner.
2. **Jamais de capture pleine page.** Chromium l'obtient en agrandissant la
   fenêtre : un `min-height: 88vh` passe de 880 à 4 700 px et tout ce qui suit
   le bandeau se décale de milliers de pixels. Capturez par tranches de la
   hauteur de fenêtre, en défilant.
3. **Neutralisez les animations d'entrée AVANT de relever les boîtes.** Un
   `translateX(-14px)` d'apparition suffit à faire pointer les coordonnées à
   côté du bloc une fois la page figée.
4. **Échantillonnez la boîte de contenu, pas la boîte de bordure**, et effacez
   la barre fixe pour les tranches basses. Une légende a souvent 2,6 rem de
   rembourrage haut où le dégradé n'est pas encore dense — mais où aucune
   lettre n'est tracée ; et une barre collante recouvre le haut de chaque
   capture.

### Le cadrage des photos

Un prospect fournit presque toujours des photos **en paysage**. Un cadre
portrait 4/5 n'en garde que la moitié centrale — ce qui coupe
systématiquement la personne quand elle n'est pas au milieu du cliché.

Sur ce projet, la photo du dirigeant (932 × 591) était tranchée en deux par
un cadre 4/5 centré. Correction : cadre **carré**, et `object-position` calé
du côté où se tient le sujet.

```css
.duo__media img {
  aspect-ratio: 1 / 1;            /* et non 4/5 */
  object-fit: cover;
  object-position: right 30%;     /* du côté du sujet */
}
```

**Règle pour le générateur :** détecter l'orientation de chaque photo. Si elle
est en paysage et destinée à un cadre plus étroit qu'elle, ne jamais laisser
`object-position: center` par défaut.

### Le mouvement

Trois constats, tous mesurés image par image :

1. **Une courbe d'accélération CSS s'applique entre chaque paire d'étapes**,
   pas à l'animation entière. Une trajectoire en quatre étapes est donc quatre
   élans freinés bout à bout — de l'à-coup au sens propre. **Séparez le
   déplacement (deux étapes, une courbe) de l'opacité (paliers, en linéaire),
   en deux animations sur le même élément.**
2. **N'animez que `transform` et `opacity`.** Un `filter` ou un changement
   d'échelle oblige le navigateur à refaire la trame de la couche. Sur ce
   projet, remplacer un `scaleX` par un `translateX` a fait passer le coût de
   l'animation de 3 points d'images lentes à zéro.
3. **Un `filter` posé dans un élément que surplombe un `backdrop-filter`**
   fait repartir le flou d'arrière-plan à chaque repeinte. Mesuré : 15 % des
   images perdues, contre 6 % sans.

Et toujours :

```css
@media (prefers-reduced-motion: reduce) { /* tout s'arrête */ }
```

### Le reste

- **Aucun débordement horizontal**, à vérifier à 320, 390, 768, 1024 et
  1440 px. Un `scrollWidth > clientWidth` sur mobile est rédhibitoire.
- **Aucune cible tactile sous 44 px** de haut sous `@media (hover: none)`.
- **Un seul `<h1>` par page**, et la hiérarchie `h2`/`h3` respectée.
- **Un `alt` sur chaque photo**, décrivant la scène et non le fichier.
- **Un lien d'évitement** en premier élément focalisable.

---

## 7. Le prompt à donner au modèle générateur

À coller tel quel dans le système, en y injectant les données extraites.

```
Tu produis trois maquettes HTML pour une entreprise, à partir du système de
design fourni dans socle.css. Tu ne modifies socle.css QUE dans son bloc
:root. Tu n'ajoutes aucune bibliothèque, aucun framework, aucun build.

DONNÉES DU PROSPECT
  Nom          : {{nom}}
  Métier       : {{metier}}
  Ville / zone : {{zone}}
  Couleur      : {{hex}}
  Police       : {{police}}
  Photos       : {{liste avec dimensions et description}}
  Prestations  : {{liste}}
  Chiffres     : {{liste}}
  Coordonnées  : {{tel, email, adresse}}

RÈGLES
1. Dérive quatre variantes de la couleur de marque et VÉRIFIE chaque
   contraste avant de l'écrire : --marque-fonce ≥ 4,5:1 avec le blanc,
   --marque-texte ≥ 4,5:1 sur le fond teinté, --marque-claire ≥ 4,5:1 sur
   le fond sombre.
2. Sur une photo de bandeau, le sur-titre est BLANC et seul son filet porte
   la couleur. Le texte du bandeau est en blanc plein, jamais à 90 %.
3. Toute photo en paysage placée dans un cadre plus étroit reçoit un
   object-position calé du côté du sujet. Jamais « center » par défaut.
4. Accueil : 3 cartes de prestation maximum, 4 indicateurs maximum.
   Prestations : autant de cartes que nécessaire, mais chacune avec une
   photo réelle et des matériaux nommés.
5. Alternance des fonds obligatoire, jamais deux sections identiques à la
   suite, une section sombre au milieu de chaque page.
6. Titrage en graisse 300. Titres de section bridés à 22 caractères.
7. Un seul <h1> par page. Un alt descriptif sur chaque photo. Un lien
   d'évitement en tête de body.
8. Si une donnée manque, RETIRE la section plutôt que d'inventer un chiffre
   ou un témoignage. Une section en moins vaut mieux qu'une preuve fausse.

SORTIE
  Trois fichiers complets : accueil.html, a-propos.html, prestations.html,
  plus le bloc :root modifié. Rien d'autre.
```

Le point 8 mérite d'être défendu auprès du client final : un CRM qui invente
« 15 ans d'expérience » ou un avis client fabrique une fausse déclaration au
nom du prospect. Mieux vaut une page de moins.

---

## 8. Vérifier avant d'envoyer

Deux scripts Playwright, dans `maquettes/`, qui fonctionnent tels quels.
Ils rendent un code de sortie non nul s'ils trouvent quelque chose : branchez-
les directement dans une chaîne d'intégration.

```bash
cd maquettes && python3 -m http.server 8090 &

python3 verif-contraste.py     # contraste de chaque texte, 3 largeurs
python3 verif-mise-en-page.py  # débordement, cibles tactiles, titres, alt
```

`verif-mise-en-page.py` contrôle cinq points à cinq largeurs (320 à 1440) :
débordement horizontal et qui le cause, cibles tactiles sous 44 px, unicité du
`<h1>` et hiérarchie des titres, `alt` présent et non réduit au nom du
fichier, lien d'évitement.

Une maquette n'est envoyable qu'à **zéro écart** sur les deux. Sur ces trois
maquettes, les audits ont trouvé — après correction des faux positifs — cinq
défauts réels qu'aucun œil n'avait vus :

| Défaut | Mesuré | Correction |
|---|---|---|
| Sur-titre du bandeau sur photo | 2,04:1 | Blanc, filet coloré |
| Texte du bandeau à 90 % d'opacité | 3,58:1 | Blanc plein + voile renforcé |
| Légendes de galerie sur clichés clairs | 3,69:1 | Plancher dense dans le dégradé |
| Numéro de téléphone sur tactile | 26 px | `min-height: 44px` |
| Liens de pied et `summary` sur tactile | 31–36 px | idem |

**Branchez-les dans le CRM comme une porte, pas comme un rapport** : une
maquette qui ne passe pas ne part pas au prospect, elle repart au modèle avec
la liste des écarts. C'est la boucle qui fait la qualité, pas le prompt.

---

## 9. Aller plus loin : le site complet

Le reste de l'archive est le site réel dont ce socle est extrait — un site
vitrine en PHP natif, sans base de données, sans Composer, sans build, avec
un back-office complet. S'il vous intéresse comme cible de production (le
prospect signe, il faut livrer un vrai site), lisez `KIT.md` pour
l'architecture et `SITE.md` pour les décisions.

Les points qui peuvent servir directement au CRM :

- **Le contenu vit en JSON**, écrit par le back-office. Transformer une
  maquette en site revient à remplir des fichiers JSON, pas à recoder.
- **La protection des formulaires** (`app/Core/Antispam.php`) : piège, jeton
  d'horloge signé, quota horaire. Sans tiers, donc sans consentement à
  demander — contrairement à reCAPTCHA, qui est un traceur et cesse de
  fonctionner pour qui refuse les cookies.
- **La barrière de consentement** pour les contenus tiers (plans, vidéos),
  qui monte les iframes depuis un `<template>` et ne lance aucune requête
  avant accord.

---

## En une phrase

Le système tient dans un bloc `:root` de vingt lignes et trois gabarits ; ce
qui fait la qualité, c'est la boucle de vérification qui refuse une maquette
dont un seul texte descend sous 4,5:1.
