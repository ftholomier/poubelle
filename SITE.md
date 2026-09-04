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

### La mairie peut en changer

Ce bleu est la valeur livrée, pas une valeur figée : l'écran **Apparence →
Couleur de la commune** laisse la mairie la choisir. Ce qu'elle choisit est
une **teinte**, pas une palette — `App\Core\Charte` en dérive les cinq
variantes et les neutres, en résolvant la luminosité de chacune jusqu'à ce
qu'elle tienne le contraste exigé sur le fond où elle sert. Aucun choix ne
peut donc rendre un texte illisible, et `outils/verifs/couleur.py` le mesure
sur douze teintes de la roue plus quatre cas limites.

La dérivation reproduit la palette réglée à la main ci-dessus à un centième
de rapport près : le bleu livré n'est pas une exception au calcul, c'en est
le résultat.

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

**La mairie peut publier sur Facebook et Instagram depuis le back-office.**
Un écran « Réseaux sociaux » connecte la Page et le compte Instagram de la
commune (`app/Core/Reseaux.php`), et publie soit un message libre, soit une
actualité, un évènement d'agenda ou un document repris du site. Une case dans
l'éditeur d'actualité permet aussi de publier à l'enregistrement, décochée par
défaut.

Trois contraintes de Meta ont commandé la conception, et aucune ne se
contourne : la publication exige une **revue** de l'application ; Instagram
n'accepte **rien sans image** et télécharge cette image lui-même ; Instagram ne
sait pas **programmer** une publication. D'où, dans l'ordre : un écran qui dit
ce qui manque au lieu d'échouer, une fabrique d'image
(`app/Core/Vignette.php`), et une file d'attente tenue par le site, dépilée par
une tâche planifiée — et à défaut par les visites du back-office, parce qu'un
cron non réglé ne doit pas faire disparaître les publications en silence.

Tout part du serveur : aucun code de Meta n'est chargé sur le site, et
`traceurs.py` reste à zéro.

**Le journal d'erreurs de PHP est entré dans la boucle qualité.** Un neuvième
auditeur, `outils/verifs/alertes.py`, parcourt les 51 pages et les 29 écrans du
back-office et lit ce que PHP dit tout bas. Il a trouvé trois défauts
silencieux le jour où il a existé — voir § 9.

**Le bouton de l'assistant est devenu réglable, et mesuré pour la première
fois.** Forme (barre, pilule, rond, pastille, onglet), fond, couleur du texte,
intitulé et taille se règlent dans l'écran Assistant IA (`app/Core/Bulle.php`).
Le fond est libre ; la couleur du texte garde sa teinte et voit sa clarté
résolue jusqu'à 4,5:1 sur ce fond, comme la charte. S'y ajoutent cinq
**animations d'appel** — aucune, halo, rebond, balancement, respiration —, avec
leur rythme : la durée d'un mouvement et le nombre de rappels, dont le produit
ne dépasse jamais cinq secondes. Elles se rejouent quand le visiteur
s'arrête de faire défiler la page — trois fois au plus, à huit secondes
d'intervalle au moins. Le survol, le focus, l'ouverture de la discussion ou le
réglage système « moins d'animations » suffisent à les éteindre. `outils/verifs/bulle.py` force les cinq formes, les bornes de taille,
six couples de couleurs dont quatre volontairement illisibles, les cinq
animations, les quatre coins du rythme et le rappel au défilement —
251 réglages.

Cet auditeur-là a immédiatement trouvé ce qu'aucun des autres ne
pouvait voir : **le libellé livré était à 2,57:1.** La bulle composait l'encre
sur la couleur de marque depuis toujours, et l'assistant étant éteint tant
qu'aucune clé n'est renseignée, elle n'était dans aucune des pages mesurées.
Le libellé est maintenant blanc — 5,44:1 sur le bleu d'Angeot.

Deux auditeurs ont dû être corrigés dans la foulée, pour un artefact et non
pour un défaut : la bulle est un survol fixe, et le fond échantillonné sous un
paragraphe qui passe derrière elle est le sien. `contraste.py` la masque donc
avant de capturer — ce qui ne coûte aucune mesure, son fond étant opaque — et
`bandeau.py` l'ajoute à la liste des survols qu'il efface déjà.

**La couleur de la commune est devenue un réglage.** La charte était écrite
en dur dans la feuille de style ; elle se dérive maintenant d'une seule couleur
choisie dans l'écran Apparence (`app/Core/Charte.php`), avec un aperçu qui
refait le calcul en JavaScript avant d'enregistrer. Le socle exige qu'un
réglage laissé au client ait son auditeur qui en force les bornes :
`outils/verifs/couleur.py` est ce sixième auditeur.

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
   ils sont repris du site précédent et sont votés par délibération. Tant
   qu'une délibération ne les confirme pas, ils engagent la commune sur des
   montants qu'elle n'a peut-être plus votés.
5. **Refaire passer chaque fiche de démarche devant service-public.fr, et
   noter la date de ce contrôle.** Les fiches ont été écrites à partir des
   règles en vigueur en 2026 ; les pièces, les seuils et les délais changent
   d'une année à l'autre, et une fiche périmée envoie un administré au guichet
   avec le mauvais dossier. Le contrôle vaut moins par son résultat que par sa
   date : sans date, personne ne sait si la fiche a douze mois ou six ans.
   Inscrire cette date dans le résumé de la fiche, ou dans le registre que
   tient le secrétariat, et la refaire tous les ans.

**Important :**

6. **Les permanences du conciliateur de justice** (fiche « Saisir un
   conciliateur »). Elles changent : la page invite déjà à vérifier par
   téléphone, mais un contrôle annuel évite un déplacement pour rien.
7. **Les jours de collecte des déchets.** Le calendrier du Grand Belfort est
   réédité chaque année ; la page décrit le rythme habituel et renvoie au
   calendrier en vigueur, qui fait foi.
8. **Les publications.** La page « Publications » décrit le *Tambour Macot* et
   les *Angeot Info* mais ne porte aucun PDF : les déposer via Contenu →
   Documents, famille « publications ».
9. **L'agenda.** Sept rendez-vous récurrents y figurent avec des dates
   plausibles reprises du rythme habituel des associations. **Les confirmer
   auprès de chaque association avant la mise en ligne** — une date fausse est
   pire qu'une date absente.
10. **Les contacts d'associations.** Les noms et adresses sont ceux publiés par
   l'ancien site ; demander à chaque association si elle les maintient.

**Souhaitable :**

11. **Compléter l'album photos** avec des vues récentes du village : les
    panoramas manquent, et ce sont eux qui portent le bandeau d'accueil.
12. **Déposer les comptes-rendus manquants.** Deux séances annoncées par
    l'ancien site n'avaient pas de PDF en ligne (17 août 2021, 1ᵉʳ avril 2025).
13. **Rendre le back-office utilisable sur téléphone.** Il l'est sur écran et
    sur tablette — les cibles tactiles y font 44 px depuis la mesure du
    back-office par `mise-en-page.py --admin`. Sous 768 px, en revanche, le
    panneau latéral de 250 px ne se replie pas et la page déborde
    latéralement. C'est un chantier à part : un menu repliable, et les listes
    de contenu rendues en cartes plutôt qu'en lignes.
14. **Renseigner l'hébergeur** dans les mentions légales, § « Hébergement »,
    une fois le contrat pris.

---

## 9. Ce qui a été mesuré

Les onze auditeurs de `outils/verifs/` sont à zéro. Ce n'est pas une formalité :
ils mesurent le contraste réel de chaque texte peint à trois largeurs, les
débordements et les cibles tactiles à cinq largeurs, l'absence de toute requête
tierce avant consentement, le texte du bandeau sur chacune des six photos du
diaporama forcée à son tour et le voile forcé à sa borne basse, l'en-tête aux deux bornes du réglage de taille
du logo dans les deux modes de barre et les deux dispositions de menu, le
site entier sous seize couleurs de commune différentes, et le bouton de
l'assistant sous deux cent cinquante et un réglages, l'image fabriquée pour
Instagram sous seize couleurs, le journal d'erreurs de PHP sur les 51 pages
publiques comme sur les 29 écrans du back-office, ce que devient une
publication quand un seul des deux réseaux répond, et ce que rend chaque écran
d'édition quand on l'enregistre sans rien changer.

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
  ne se devine ;
- **le libellé de la bulle de l'assistant à 2,57:1**, hérité du socle et
  jamais mesuré parce que l'assistant est éteint par défaut ;
- **trois paragraphes d'histoire de la commune qui ne s'affichaient pas**, sur
  la page d'accueil : le contenu était écrit en texte riche là où le
  back-office écrit un tableau de paragraphes, et le gabarit parcourait la
  chaîne caractère par caractère. La page paraissait simplement un peu courte ;
- **le même malentendu en sens inverse sur les fiches d'association**, où le
  gabarit parcourait comme un tableau un champ déclaré en texte riche ;
- **une donnée qui n'arrivait jamais à son gabarit** parce qu'elle s'appelait
  `file`, nom d'une variable locale de `View::capture()` : `extract()` en mode
  `EXTR_SKIP` l'écartait sans un mot.

Les trois derniers ont été trouvés par `alertes.py` en trois minutes, et deux
étaient là depuis des semaines. Aucun ne se voyait dans la page.

**La règle qui en découle vaut pour toute modification :** un réglage laissé à
la mairie doit avoir son auditeur qui en force les bornes. Un curseur dont on
n'a mesuré que la valeur livrée est un défaut en attente, découvert en ligne
par la mairie.
