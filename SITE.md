# Le site de la commune d'Angeot

Ce qui a été décidé pour **ce** site-ci, et pourquoi. `KIT.md` décrit le code,
`NOUVEAU-SITE.md` la recette générale ; ce document-ci ne parle que d'Angeot.

Il sert deux lecteurs : celui qui reprendra le site dans deux ans et se
demandera pourquoi telle rubrique existe, et la mairie, qui trouvera au § 8 la
liste de ce qu'il lui reste à renseigner.

---

## 1. D'où vient le contenu

Le site précédent, `mairie-angeot.fr`, était servi par la plateforme Moduliti :
une trentaine de pages numérotées du type
`/page/4546_conseil-municipal-mairie-dangeot-90150-…php`, un module d'album
photo en accordéon, un slider et un module « info à la une » qui s'ouvrait en
surimpression à la première visite.

Tout le contenu rédactionnel en a été repris, réordonné et réécrit. Les faits
non vérifiables ont été retirés ; les faits vérifiables ont été vérifiés :

| Donnée | Source |
|---|---|
| 354 habitants (recensement 2023), densité 54 hab./km² | Insee, via l'article encyclopédique de la commune |
| 6,56 km², altitude 360–417 m | idem |
| Code Insee 90002, canton de Grandvillars, arrondissement de Belfort | idem |
| Gentilé « Angelois » | idem |
| Blason « d'argent à deux lions affrontés de sable » | armorial des communes du Territoire de Belfort |
| Histoire : château des comtes de Ferrette, destruction en 1635, Haut-Rhin jusqu'en 1871, séisme du 14 avril 1725, paroisse de dix villages au XIVᵉ | idem |
| Église Saint-Sébastien 1840-1843, clocher refait à partir de 1862, incendie de janvier 2014 | idem, et le site précédent |
| Coordonnées, horaires, équipe municipale, comités, tarifs de la salle | le site précédent |
| Comptes-rendus, budgets | les PDF publiés par la mairie, repris tels quels |

**Ce qui a été délibérément écarté.** Les fiches « démarches » de l'ancien site
décrivaient les aides de l'ANAH « en 2022 », des permanences de conciliateur
susceptibles d'avoir changé, et recopiaient des règles administratives datées.
Une fiche périmée envoie un administré au guichet avec le mauvais dossier :
les douze fiches ont donc été réécrites autour de ce qui ne bouge pas — *qui*
est compétent, *ce que la mairie fait ou ne fait pas*, *où aller sinon* — et
renvoient à `service-public.fr` pour les pièces et les délais, qui changent
d'une année à l'autre. Les permanences du conciliateur sont conservées avec
la mention explicite qu'elles sont à vérifier par téléphone.

---

## 2. L'arborescence

Trente-trois pages fixes, deux collections (démarches, actualités), trois
listes de documents (comptes-rendus, budgets, publications).

Cinq rubriques de premier niveau, contre six sur l'ancien site :

- **La mairie** — équipe municipale, commissions & comités, comptes-rendus,
  délibérations & arrêtés, budget, publications, urbanisme
- **Démarches** — les douze fiches, les démarches en ligne, les services de
  l'État, le CCAS
- **Le village** — histoire & patrimoine, salle Camille, bois & forêts,
  associations, album photos
- **Actualités** — actualités, agenda, info à la une
- **Au quotidien** — déchets, vie scolaire, intercommunalité, liens utiles,
  numéros utiles

**Trois arbitrages de structure méritent d'être expliqués :**

1. **« Salle Camille » est une page à part entière**, et non un paragraphe de
   la page « village ». C'est, avec les démarches, ce qu'on vient chercher :
   la capacité, le tarif, le montant de la caution. L'ancien site répondait à
   ces trois questions sur une page intitulée « Salle Camille » et renvoyait
   la disponibilité vers un module de réservation séparé ; les deux sont
   réunis.

2. **« Bois & forêts » reste une rubrique**, alors qu'un site de commune de
   cette taille l'aurait volontiers fondue dans « vie pratique ». À Angeot,
   l'affouage, les ventes de bois et la lutte contre les scolytes concernent
   directement les habitants, et le comité bois et forêt est l'un des plus
   actifs. La rubrique existe donc, et une fiche « démarche » distincte décrit
   l'inscription à l'affouage.

3. **Les délibérations et les arrêtés ont leur page, sans les publier.** Le
   site précédent mettait en ligne cent trente-neuf délibérations en PDF, une
   par fichier, sans index lisible. La page explique désormais ce qu'est une
   délibération, ce qu'est un arrêté, où les consulter et comment en obtenir
   copie — ce qui est le vrai besoin. Les registres restent consultables en
   mairie, comme la loi le prévoit.

**Redirections.** Les dix-huit identifiants numériques de l'ancien site sont
redirigés en 301 vers leur page correspondante, par une route à joker sur
`/page/{identifiant}_…` : le libellé qui suit l'identifiant a changé plusieurs
fois pour une même page, seul l'identifiant fait foi. Les huit pages `.php` de
la racine (`albumphoto.php`, `reservation.php`, `ml.php`…) et une trentaine
d'adresses courtes plausibles au clavier sont également redirigées. Un
identifiant inconnu tombe sur le plan du site plutôt que sur une erreur.

---

## 3. La charte

Le blason est **d'argent à deux lions affrontés de sable** : blanc et noir.
Le noir pur, en aplat de plusieurs centaines de pixels, durcit une page de
service public au lieu de la poser — la charte prend donc un **bleu ardoise**,
qui est ce sable réchauffé, et garde l'argent pour les fonds.

```
--bleu        #456d8a   H 205° S 33 % L 41 %   5,52:1 sur blanc · 5,01:1 sur le crème
--bleu-fonce  #37586f   L 33 %                 survols et dégradés — 7,53:1 avec le blanc
--bleu-texte  #3d607a   L 36 %                 petit texte sur le teinté — 6,08:1
--bleu-clair  #7ca2bd   L 61 %                 accents sur l'ardoise — 5,11:1
--bleu-barre  #c4d5e1   L 83 %                 survols de la barre translucide — 4,63:1
```

Teinte et saturation ne bougent jamais d'une variante à l'autre : seule la
luminosité varie, si bien que la commune reconnaît sa couleur partout.

**Une différence avec le socle mérite d'être notée**, sans quoi on croira à
une erreur. Le vert du site dont vient ce socle ne tenait que 3,72:1 sur le
blanc : ses quatre variantes servaient à rattraper un contraste manqué. Ce
bleu-ci est assez sombre pour porter du texte tel quel. `--bleu-fonce` n'est
donc pas là pour atteindre un seuil mais pour donner un cran de survol
franchement perceptible ; `--bleu-clair` et `--bleu-barre`, eux, restent
indispensables, parce que la couleur de marque tombe à 2,51:1 sur l'ardoise et
à 1,26:1 sur la barre translucide.

L'ardoise `#1c2f3b` est la même teinte assombrie à 22 % de luminosité : elle
prolonge le blason au lieu de le contredire, et donne 13,82:1 avec le blanc.

---

## 4. Le logo

Six fichiers dans `public/assets/img/logo/` : le blason seul et le logo
complet, chacun en version pour fond clair et pour fond sombre, plus une
disposition verticale — et deux favicons.

**Le blason est le dessin officiel**, celui publié sur Wikimedia Commons par
Blazooner sous licence CC BY-SA 4.0. Les armes elles-mêmes appartiennent à la
commune ; la licence porte sur ce dessin-là, et impose d'en citer l'auteur.
L'attribution figure dans les mentions légales, § « Crédits iconographiques » :
**si le fichier est remplacé, retirer la ligne correspondante.**

Le fichier d'origine venait d'Inkscape et portait ses métadonnées d'éditeur,
une description héritée d'un tout autre blason, et des attributs dans des
espaces de noms non déclarés — de quoi rendre la SVG illisible par le
navigateur une fois extraite. Il est donc élagué à la lecture : ne survivent
que les nœuds et attributs du SVG pur, plus `xlink`. Ses identifiants de
dégradés sont préfixés `bl-`, parce qu'ils deviennent globaux au document dès
qu'on imbrique le blason dans un logo.

**Deux pièges à ne pas redécouvrir :**

- Le préfixage des identifiants se fait **sur l'arbre**, pas par substitution
  de texte : `showgrid="false"` contient la chaîne `id="false"`, qu'une
  expression régulière naïve réécrit joyeusement.
- Le blason est imbriqué dans les logos comme une `<svg>` interne avec son
  propre `viewBox`. C'est ce qui évite d'avoir à retoucher une seule
  coordonnée du dessin d'origine.

Le mot « ANGEOT » est composé en Montserrat 500 avec 10 unités
d'interlettrage, et **figé en chemins**. C'est délibéré : une SVG chargée dans
un `<img>` n'hérite pas des polices de la page, et un `@font-face` relatif à
l'intérieur d'elle ne résout pas de façon fiable selon le contexte de
chargement. Le script qui a produit ces chemins est conservé hors dépôt ; pour
refaire le lettrage, il suffit de `fontTools` et de la police du site.

Le favicon est le blason **sur fond ardoise**, et non sur son argent d'origine :
à seize pixels dans une barre d'onglets déjà blanche, un écu blanc disparaît.

**Le logo d'Angeot est plus large que celui du socle** — 3,08:1 contre 2,35:1,
parce que le mot est court et l'écu presque carré. À la borne haute du réglage
de taille, il touchait le burger sur un écran de 320 px. La largeur disponible
se calcule désormais dans la feuille de style plutôt qu'elle ne se devine, et
`object-fit: contain` réduit le dessin au lieu de l'écraser. Le plafond vaut
pour tout logo que la mairie déposerait ensuite, pas seulement pour celui-ci.

---

## 5. Les photos

Trente-neuf photographies dans `public/assets/img/site/`, chacune avec sa
vignette `-mini`.

- **Trente-quatre viennent du site précédent** : les albums photo de la mairie
  (fleurissement, inauguration des salles de mairie, concerts de Pyxis,
  décorations de Noël, commémorations, marche populaire, vide-grenier) et les
  fichiers du site (salle Camille, affouage, bureaux d'associations). Elles
  sont l'œuvre des élus et des bénévoles ; l'ancien site indiquait que le
  contenu des albums est libre de droit.
- **Cinq viennent de Wikimedia Commons** et restent sous licence CC BY-SA :
  la rue principale, l'église Saint-Sébastien et son intérieur, la vue du
  village depuis l'église, la mairie-école. **Leur attribution est obligatoire**
  et figure dans les mentions légales, § « Crédits iconographiques », au même
  endroit que celle du blason. Si l'une d'elles est remplacée, retirer la ligne
  correspondante.

Chaque `alt` décrit la scène, jamais le nom du fichier — l'auditeur de mise en
page refuse les seconds.

**Le diaporama d'accueil** tire au hasard parmi six vues. Il est réglé à un
voile de 92 % : c'est la valeur qu'exige `bandeau.py`, qui mesure le texte du
bandeau sur chaque photo forcée à son tour. Ne pas le baisser sans repasser
l'auditeur — le pire cas est ce qui compte, pas la photo du jour.

---

## 6. Ce qui a été ajouté au socle

**Un verrou optimiste sur les contenus** (`app/Core/Verrou.php`). Le socle
écrivait déjà par fichier temporaire puis `rename()`, ce qui garantit qu'aucun
visiteur ne lit un JSON tronqué. Mais rien n'empêchait deux administrateurs
d'écraser mutuellement leur saisie : la secrétaire ouvre l'éditeur d'une page,
un élu l'ouvre aussi, elle enregistre, il enregistre — et le travail d'elle
disparaît sans message.

Le verrou relève l'empreinte de chaque contenu lu pendant l'affichage d'un
écran (requête GET) et refuse l'écriture (requête POST) si le fichier a bougé
depuis. Aucun formulaire n'a de champ à porter, aucun contrôleur d'appel à
passer : c'est le découpage GET / POST qui fait tout le travail. Le conflit
lève `ConflitEcriture`, rattrapé dans `public/index.php`, qui renvoie
l'administrateur sur son écran avec le message qui dit quoi faire.

**`<sup>` est entré dans la liste blanche du texte riche.** Les ordinaux
abrégés — XIII<sup>e</sup> siècle, 1<sup>er</sup> avril — sont du texte courant
dans une prose municipale, et la liste blanche les aplatissait en « XIIIe »,
qu'un lecteur d'écran prononce mal. La balise ne porte aucun attribut : elle
n'ouvre rien.

**Les fiches d'association acceptent une photo et le rôle de chaque contact.**
Le contenu repris de l'ancien site portait déjà ces informations — « Président »,
« Trésorière », et une photo par association. Ni la vue ni l'écran d'édition ne
les connaissaient : elles disparaissaient au premier enregistrement, sans
message. Elles sont désormais déclarées dans `ContenuController::LISTES` et
rendues par la vue.

**Trois débordements horizontaux, tous de la même famille.** Une adresse de
courriel est un mot qu'aucune césure ordinaire ne coupe. `overflow-wrap:
break-word`, posé sur le corps, la coupe à la peinture mais ne réduit pas sa
largeur *min-content* — et c'est cette largeur-là qu'un conteneur flex ou
grille respecte. D'où trois correctifs : la règle sur le corps, un `min-width:
0` sur les cartes d'association et sur leurs contacts, et un `overflow-wrap:
anywhere` sur les liens qui portent une adresse. Avant : 39 px de débordement
sur un écran de 320.

**Les auditeurs lisent désormais le plan du site.** `contraste.py`,
`mise-en-page.py` et `traceurs.py` portaient une liste de pages écrite en dur,
qui se périme dès qu'une page est ajoutée ou qu'un slug change depuis le
back-office — et une page non mesurée est exactement celle où l'écart passera.
Ils interrogent maintenant `/sitemap.xml`, produit par `Seo::PAGES` : la liste
ne peut plus diverger de ce que le site sert.

---

## 7. Ce qui reste éteint

Trois modules du socle ne demandent qu'une clé pour s'allumer, et restent
invisibles tant qu'elle est vide :

- **l'assistant de discussion** (clé Gemini) — sa consigne est déjà écrite pour
  Angeot : elle dit que la mairie n'établit ni carte d'identité ni passeport,
  que les déchets relèvent du Grand Belfort, l'école du Syndicat du Tilleul, et
  que les secours passent avant tout renvoi vers une page ;
- **les avis Google** (clé Google) — sans grand intérêt pour une mairie, mais
  le module est là ;
- **la traduction automatique** (clé DeepL).

Aucun n'émet la moindre requête tant qu'il n'est pas configuré : `traceurs.py`
le vérifie.

---

## 8. À renseigner avant la mise en ligne

C'est la liste de ce que le site ne peut pas deviner. Elle est courte, et
chaque ligne se règle depuis le back-office.

**Indispensable :**

1. **Le compte administrateur.** Le premier passage sur `/admin` le crée. Il
   n'y a aucun identifiant par défaut.
2. **Le SMTP** (Paramètres → Courriel). Sans lui, les deux formulaires
   n'envoient rien. Vérifier ensuite qu'un message part réellement et que le
   `Reply-To` porte bien l'adresse du visiteur.
3. **Vérifier l'équipe municipale.** Le conseil publié est celui de la
   mandature 2020-2026, tel que l'affichait le site précédent. Les élections
   de mars 2026 ayant eu lieu, la composition est à mettre à jour dès que la
   nouvelle équipe est installée : Contenu → Conseil municipal, puis
   Commissions & comités.
4. **Vérifier les tarifs de la salle Camille** et les montants de caution :
   ils sont repris du site précédent et sont votés par délibération.

**Important :**

5. **Les permanences du conciliateur de justice** (fiche « Saisir un
   conciliateur »). Elles changent : la page invite déjà à vérifier par
   téléphone, mais un contrôle annuel évite un déplacement pour rien.
6. **Les jours de collecte des déchets.** Le calendrier du Grand Belfort est
   réédité chaque année ; la page décrit le rythme habituel et renvoie au
   calendrier en vigueur, qui fait foi.
7. **Les publications.** La page « Publications » décrit le *Tambour Macot* et
   les *Angeot Info* mais ne porte aucun PDF : les déposer via Contenu →
   Documents, famille « publications ».
8. **L'agenda.** Sept rendez-vous récurrents y figurent avec des dates
   plausibles reprises du rythme habituel des associations. **Les confirmer
   auprès de chaque association avant la mise en ligne** — une date fausse est
   pire qu'une date absente.
9. **Les contacts d'associations.** Les noms et adresses sont ceux publiés par
   l'ancien site ; demander à chaque association si elle les maintient.

**Souhaitable :**

10. **Compléter l'album photos** avec des vues récentes du village : les
    panoramas manquent, et ce sont eux qui portent le bandeau d'accueil.
11. **Déposer les comptes-rendus manquants.** Deux séances annoncées par
    l'ancien site n'avaient pas de PDF en ligne (17 août 2021, 1ᵉʳ avril 2025).
12. **Renseigner l'hébergeur** dans les mentions légales, § « Hébergement »,
    une fois le contrat pris.

---

## 9. Ce qui a été mesuré

Les cinq auditeurs de `outils/verifs/` sont à zéro. Ce n'est pas une formalité :
ils mesurent le contraste réel de chaque texte peint à trois largeurs, les
débordements et les cibles tactiles à cinq largeurs, l'absence de toute requête
tierce avant consentement, le texte du bandeau sur chacune des six photos du
diaporama forcée à son tour, et l'en-tête aux deux bornes du réglage de taille
du logo dans les deux modes de barre et les deux dispositions de menu.

Ce qu'ils ont attrapé sur ce site-ci, et qu'aucun œil n'avait vu :

- **un bouton invisible.** Le renommage des jetons de couleur avait aussi
  renommé la classe `.btn--vert` en `.btn--bleu` dans la feuille de style,
  tandis que dix gabarits écrivaient toujours `btn--vert` : le bouton
  « Écrire à la mairie » de la bande d'appel se retrouvait sans fond, texte
  sombre sur fond sombre, à **1,14:1** ;
- **une citation blanche sur blanc**, à 1,00:1. Le bloc « citation » se compose
  en blanc sans exception ; posé sur un fond clair par l'alternance, il
  disparaissait. Le fond ne lui est plus laissé au choix ;
- **la bande « En ce moment » à 4,43:1**, parce qu'elle servait en aplat un
  jeton défini pour du petit texte sur crème ;
- **le sur-titre de la bande d'appel à 4,03:1** sur le plus clair des fonds
  sombres du site ;
- **la fonction d'un élu à 4,42:1** : la tuile translucide à 5 % d'opacité
  composait un fond trop clair d'un cheveu ;
- **le logo touchant le burger** aux quatre bornes du réglage de taille, en
  disposition latérale sur 320 et 390 px — le logo d'Angeot est plus large que
  celui du socle, et la largeur disponible se calcule désormais plutôt qu'elle
  ne se devine.

**La règle qui en découle vaut pour toute modification :** un réglage laissé à
la mairie doit avoir son auditeur qui en force les bornes. Un curseur dont on
n'a mesuré que la valeur livrée est un défaut en attente, découvert en ligne
par la mairie.
