# Le site de Lachapelle-sous-Chaux

Ce document décrit **ce site-ci** : ce qui a été repris du site précédent, ce
qui a été réécrit, les arbitrages de contenu et de design, et **ce qui reste à
renseigner par la mairie avant la mise en ligne**.

Pour le socle technique, voir `KIT.md`. Pour produire un autre site sur le même
modèle, `NOUVEAU-SITE.md`.

---

## 1. D'où vient ce site

Il remplace un site WordPress réalisé par des étudiants du département MMI de
l'IUT Nord-Franche-Comté, en ligne depuis 2023. Tout ce qui suit en a été
repris : le contenu, les photos, les documents, et l'arborescence.

**Ce qui a été gardé tel quel** : l'histoire de la commune, la composition du
conseil et des commissions, les associations et leurs contacts, les numéros
utiles, les coordonnées, les horaires, l'organisation des rubriques.

**Ce qui a été réécrit** : toutes les fiches de démarche. Le site précédent
décrivait le droit de l'urbanisme dans les termes d'avant 2012 — surface hors
œuvre brute et nette, seuil d'architecte à 170 m², permis valable deux ans — et
les élections dans ceux d'avant 2022 : tribunal d'instance, mandataire
obligatoirement inscrit dans la même commune, procuration sans téléservice.
Republier cela aurait envoyé des administrés au guichet avec le mauvais
dossier. Les fiches disent désormais la surface de plancher et l'emprise au
sol, les seuils de 20 et 40 m², l'architecte à 150 m², le permis valable trois
ans prorogeable deux fois, et la procuration par maprocuration.gouv.fr.

**Ce qui a été ajouté** : une page d'accessibilité (obligatoire pour un site
public), une politique de confidentialité écrite pour ce site — celle qui était
en ligne était le texte d'exemple de WordPress, qui parlait de commentaires et
de Gravatar —, un agenda, une page « Écrire à la mairie » avec objet fermé, et
un plan du site qui se construit tout seul.

**Ce qui a été retiré** : les pages restées à l'état de « page en cours de
rédaction » (budgets, livret, parascolaire, restauration, transports) ont été
fondues dans des pages qui disent quelque chose. Leurs anciennes adresses
redirigent vers la section correspondante.

---

## 2. La charte

Les deux couleurs sont celles des deux arcs du logo de la commune, relevées au
pixel sur le fichier d'origine : **#4D9179** et **#7FC576**.

Le vert de marque ne tient que 3,72:1 sur blanc et 3,44:1 sur l'ardoise :
assez pour un aplat, un filet ou un grand chiffre, jamais pour du texte
courant. Quatre variantes en dérivent, à teinte et saturation constantes —
seule la luminosité bouge, si bien que la commune reconnaît sa couleur :

| Jeton | Valeur | Mesure |
|---|---|---|
| `--vert` | `#4d9179` | la couleur de marque : filets, icônes, pictos, grands chiffres |
| `--vert-fonce` | `#45826c` | aplats sous texte blanc — 4,50:1 |
| `--vert-texte` | `#366655` | petit texte sur le teinté — 6,05:1 |
| `--vert-clair` | `#7fc576` | l'arc clair du logo, tel quel — 6,18:1 sur l'ardoise |
| `--vert-barre` | `#c3e2d5` | barre collante translucide — 5,03:1 sur son fond composité |

**L'ardoise est un vert de sapin (`#1d3730`) et non un bleu-gris.** Elle
prolonge l'identité du village au lieu de la contredire, et donne 12,79:1 avec
le blanc.

Le titrage est en graisse 300. C'est contre-intuitif et c'est pourtant ce qui
sépare un site tenu d'un site d'annuaire : la taille porte, la graisse n'a pas
à s'en mêler.

### Les logos

Le logo de la commune n'existait qu'en PNG. Il a été **vectorisé** plutôt que
redessiné : classement de chaque pixel vers la couleur la plus proche, tracé
des contours par marching squares, simplification de Douglas-Peucker. Les
courbes des arcs et le dessin des lettres sont donc ceux d'origine, au pixel
près, dans des fichiers de sept à vingt-sept kilo-octets.

Cinq fichiers en sortent : `logo-lachapelle.svg` et sa version claire,
`embleme-lachapelle.svg` et sa version claire, `logo-lachapelle-vertical.svg`.
Le favicon et l'icône iOS sont l'emblème posé sur l'ardoise : les quatre arcs
seuls, en filet, disparaissent à 16 px sur le fond blanc d'un onglet.

---

## 3. L'arborescence

Sept rubriques, trente-deux adresses publiques.

| Rubrique | Pages |
|---|---|
| **La mairie** | La mairie, conseil municipal, commissions & comités, comptes-rendus, budget communal, urbanisme |
| **Démarches** | Démarches (13 fiches), démarches en ligne, services de l'État, CCAS, écrire à la mairie |
| **Vie scolaire** | Une page unique : inscription, regroupement pédagogique, livret, périscolaire, restauration, transport |
| **Le village** | Le village, histoire, associations |
| **Actualités** | Actualités (collection), agenda, Flash Info |
| **Vie pratique** | Vie pratique, gérer mes déchets, eau & assainissement, intercommunalité, numéros utiles |
| **Contact** | Contact, et les pages de service : mentions légales, confidentialité, accessibilité, plan du site |

**Le contenu vivant a sa propre rubrique**, et c'est un arbitrage, pas un
rangement. Actualités, agenda et Flash Info étaient au fond du sous-menu de
« Le village », en quatrième, cinquième et sixième position — c'est-à-dire
invisibles. Or c'est le seul contenu qui distingue un site de mairie tenu à
jour d'une plaquette imprimée, et un administré arrive presque toujours par un
moteur de recherche sur une fiche de démarche, jamais par l'accueil. Ils
forment donc une rubrique de premier niveau, et le bandeau « En ce moment »
(§ 5) les rappelle sur toutes les pages.

**Deux collections** ont des adresses propres : `demarches` (treize fiches) et
`actualites`. Les autres listes — élus, commissions, associations, numéros,
documents, agenda, services de l'État — sont des listes sans page individuelle.

**Ni le menu des démarches ni celui des actualités ne se remplissent tout
seuls.** Douze fiches dans un menu déroulant sont illisibles, et douze titres
d'actualités le seraient plus encore ; trois ou quatre rubriques mènent à la
bonne page. Le drapeau `auto: false` de l'entrée de menu dit précisément cela,
et les autres collections gardent leur sous-menu qui se tient à jour tout
seul.

---

## 4. Les pages sont faites de blocs

C'est le choix structurant de ce site. Une mairie publie une quinzaine de pages
qui se ressemblent : un texte, une liste de liens, un tableau d'horaires,
quelques contacts, des PDF à télécharger. Écrire une vue par page reviendrait à
recopier quinze fois la même chose, et à toucher au code pour ajouter une page.

Chaque page est donc une suite de **blocs typés**, décrits une fois dans
`app/Admin/Blocs.php` et rendus par `views/partials/bloc.php` :

`texte` · `duo` (image et texte) · `cartes` · `liens` · `contacts` ·
`documents` · `etapes` · `tableau` · `encadre` · `citation` · `chiffres` ·
`photo` · `carte`

Ajouter une page coûte un fichier JSON, une entrée dans `Seo::PAGES` et une
ligne de route. Ajouter une forme de contenu coûte un `case` dans le gabarit,
une entrée dans la table des blocs, et sa règle CSS.

**L'alternance des fonds est calculée, pas saisie.** La règle du système de
design — jamais deux sections de même fond à la suite — se casse dès qu'on la
confie à celui qui écrit le contenu : il ajoute une section, oublie de
retourner le fond de la suivante, et la page se met à sembler interminable. Un
bloc peut imposer son fond, et le calcul reprend après lui.

---

## 5. Ce qui a changé dans le socle

Le socle vient d'un site vitrine commercial. Sept adaptations, au-delà du
contenu :

1. **Les données structurées** décrivent une `GovernmentOrganization` et un
   `CityHall`, non un commerce. Les horaires sont lus **jour par jour** : une
   mairie de village n'ouvre pas selon un rythme régulier, et publier « du
   lundi au vendredi de 8 h à 12 h » ferait venir des gens devant une porte
   fermée. Le lecteur d'horaires accepte donc « Lundi 8h-11h et 12h-15h ·
   Mardi 8h-11h · … » et rend une spécification par plage.

2. **Le formulaire de devis** est devenu « Écrire à la mairie », avec un objet
   pris dans une liste fermée. C'est lui qui décide du service qui traitera la
   demande, et il est repris dans l'objet du courriel : une formulation libre
   obligerait le secrétariat à deviner.

3. **La consigne de l'assistant** a été réécrite. Trois ajouts propres au
   service public : la priorité absolue aux secours si la question évoque un
   danger, le refus de demander un numéro de sécurité sociale ou des
   coordonnées bancaires, et la neutralité sur les décisions du conseil.

4. **Le module d'avis Google est conservé**, activable depuis le back-office
   comme aujourd'hui : la mairie a une fiche Google et pourra vouloir les
   afficher. Il reste éteint tant qu'aucune clé n'est saisie.

5. **Le back-office est passé de vingt écrans dédiés à trois écrans
   génériques** — pages, fiches de collection, listes — plus les écrans propres
   (accueil, contact, demande, conseil municipal). Un formulaire par page
   aurait voulu dire vingt-huit formulaires, et un vingt-neuvième le jour où la
   commune ajoute une rubrique.

6. **L'inventaire des contenus est partagé.** Quatre écrans en avaient chacun
   leur copie — éditeur avancé, médiathèque, traductions, tableau de bord — et
   une page ajoutée n'apparaissait que dans celui qu'on pensait à mettre à
   jour. Il se déduit désormais de la table des pages.

7. **Le menu se saisit sur deux niveaux**, une sous-entrée décalée de deux
   espaces. L'ancien éditeur aplatissait le menu à l'enregistrement, ce qui
   aurait détruit les cinq sous-menus du site.

8. **Les démarches se filtrent, elles ne défilent plus.** La page portait un
   sommaire de familles pointant vers des ancres : un lien qui paraît filtrer
   et ne fait que descendre. Les autres familles restaient sous les yeux, on
   perdait sa position, et chaque clic ajoutait une entrée dans l'historique —
   le bouton Précédent devenait inutilisable. Choisir une famille ne montre
   désormais qu'elle, sans que la page bouge d'un pixel.

   Le filtre passe par l'adresse — `/demarches?famille=urbanisme` — et pas
   seulement par le JavaScript : la sélection est partageable, s'ajoute aux
   favoris, survit à un rechargement, et le serveur sait filtrer seul si le
   script ne se charge pas. Toutes les fiches restent dans le HTML, masquées :
   un moteur de recherche voit la page entière, et l'adresse canonique reste
   `/demarches`. Les anciennes adresses en ancre, imprimées dans le Flash
   Info, sont traduites en filtre à l'arrivée.

9. **Le contenu vivant est visible partout.** Le bandeau « En ce moment »,
   sous le bandeau photo de chaque page, rappelle en trois entrées la dernière
   actualité, le prochain rendez-vous et le dernier Flash Info. Il est posé
   dans `views/partials/hero-page.php`, le bandeau partagé par les seize vues
   de page : une page ajoutée demain l'aura sans qu'on y pense. L'accueil en
   est exempté — sa section « La vie du village » fait déjà le travail, en
   plus grand, et les deux à trois cents pixels d'écart auraient fait doublon.
   Le bandeau se retire de lui-même si la commune n'a rien à annoncer.

   Le tri des trois contenus vit dans `App\Core\Vivant`, et nulle part
   ailleurs. Il servait au seul contrôleur des pages ; le bandeau en avait
   besoin à son tour, et la règle « un événement reste à venir tout le jour de
   sa date » aurait fini par exister en deux versions légèrement différentes —
   celle qui fait disparaître la brocante du dimanche le dimanche matin, quand
   c'est précisément le moment où l'on vérifie l'heure.

10. **Les dates se saisissent au calendrier.** Agenda, documents, actualités :
   tous les champs de date sont des `<input type="date">`, donc le calendrier
   du navigateur — celui que la personne connaît déjà, sans bibliothèque ni
   requête. Le serveur normalise ce qu'il reçoit et refuse une date qui
   n'existe pas : un 31 février a la bonne forme et fausserait le tri de
   l'agenda, qui compare des chaînes.

11. **La taille du logo est réglable** (Apparence → Taille du logo), de 36 à
   120 px, avec un aperçu du vrai logo à la vraie taille dans une barre à la
   vraie hauteur. Deux comportements au choix, parce qu'ils ne servent pas la
   même chose :

   - **la barre suit le logo** — l'air autour de lui reste constant, la barre
     s'épaissit avec lui, rien ne se chevauche jamais ;
   - **le logo déborde** — la barre garde sa hauteur et un grand logo la
     dépasse par le bas, sur la photo du bandeau. C'est plus marquant, et cela
     évite une barre épaisse qui mangerait le premier écran. Le débordement
     s'arrête au premier défilement : passé le haut de page la barre devient
     opaque, et ce qui la dépasserait tomberait sur le texte.

   Le réglage tient dans un seul jeton CSS, `--logo-ref`, posé sur `<html>` :
   toutes les autres hauteurs — barre, barre défilée, rembourrage haut du
   bandeau — en descendent par calcul. À 52 px, la valeur livrée, les hauteurs
   obtenues sont au pixel celles de la maquette d'origine.

---

## 6. Les documents PDF

Les dix-sept PDF de l'ancien site pèsent 71 Mo, parce que ce sont des maquettes
InDesign exportées sans sous-échantillonnage : chaque photo y est stockée à
300 dpi pour une page lue à l'écran. Ils ont été **recompressés à 21 Mo** en
réencodant les seules images en JPEG 1400 px — le texte, vectoriel, n'est pas
touché. Un bulletin de 16 Mo tombe à 4,4 Mo, et le plus lourd des
comptes-rendus à 644 Ko.

Le poids est **annoncé avant le clic** sur chaque lien de téléchargement : une
mairie de village se consulte souvent depuis un téléphone en fond de vallée.

---

## 7. Les auditeurs

Les cinq scripts de `outils/verifs/` passent à zéro sur les trente-deux
pages, à cinq largeurs d'écran. Ce qu'ils ont attrapé pendant ce
développement :

- **Un saut de titre h1 → h3** sur la fiche « Carte d'identité » : l'encadré
  ouvrait la page et son intitulé était un `h3`. Il est passé en `h2`.
- **Une attente d'images qui expirait** sur les pages longues. L'auditeur de
  contraste sautait d'un coup au bas de page pour déclencher le chargement
  différé ; or Chromium ne le déclenche que pour les images qui approchent du
  cadre, et sur une page de sept mille pixels tout le milieu restait dormant.
  Il descend maintenant par paliers. Ce n'est pas un seuil qu'on abaisse :
  c'est une mesure qui devient juste.
- **Le chapô du bandeau en blanc à 90 %**, exactement le piège que la
  documentation du socle décrit : 4,05:1 à 390 px, 4,9:1 en blanc plein. Il est
  passé en blanc plein.
- **Deux photos du diaporama sous le seuil**, à 4,41 et 4,43:1 — invisibles
  pour `contraste.py`, qui n'en voit qu'une par passage puisque le diaporama
  tire au hasard. D'où le quatrième auditeur, `bandeau.py`, qui les force
  toutes à leur tour. Correction : le voile du bandeau, un réglage de
  back-office, est monté de 82 à 92. Pire cas mesuré ensuite, toutes photos et
  toutes largeurs confondues : **5,53:1**.
- **Un logo servi écrasé** au-delà d'une centaine de pixels, sur un écran de
  320 : la règle globale `img{max-width:100%}` rognait sa largeur sans toucher
  à sa hauteur — 1,94 de rapport au lieu de 2,34. Corrigé par
  `object-fit: contain` : il se réduit désormais au lieu de se déformer.
- **La cible tactile du logo à 40 px** une fois la barre défilée, sous 780 px.
  Elle reposait sur un rembourrage calculé, exactement le piège que
  `NOUVEAU-SITE.md` documente ; elle repose maintenant sur un `min-height`.
  Ces deux-là ne sortent qu'à d'autres réglages que celui livré : c'est le
  cinquième auditeur, `entete.py`, qui les a trouvés.

Et deux pièges de développement, tous deux prévus par la documentation du socle
et rencontrés quand même :

- **Un `<form>` imbriqué dans un autre n'existe pas en HTML.** Le formulaire
  d'ajout de bloc, rendu à l'intérieur du formulaire d'édition, était écarté en
  silence par le navigateur : le bouton disparaissait de la page. Il est
  désormais rendu après, et le JavaScript y recopie la saisie en cours.
- **Une capture pleine page de Chromium ment.** Elle agrandit la fenêtre, et un
  bandeau en `min-height: 88vh` passe de 880 à plusieurs milliers de pixels :
  la première mesure de la page d'accueil annonçait 27 000 px de haut. On
  capture par tranches, en défilant.

---

## 8. À renseigner avant la mise en ligne

Ce qui suit demande une information que seule la mairie détient. Le site
fonctionne sans, mais ces points doivent être vérifiés avant de basculer le
domaine.

### Indispensable

| Quoi | Où | Pourquoi |
|---|---|---|
| **Réglages SMTP** | Admin → Paramètres | Sans eux, les deux formulaires ne partent pas. Un bouton d'essai est prévu sur l'écran. |
| **Destinataire des demandes** | Admin → Paramètres | À défaut, elles partent sur `mairie.lsc@wanadoo.fr`, l'adresse saisie dans Coordonnées. |
| **Hébergeur** | Page Mentions légales | La page dit « hébergé en France, coordonnées disponibles sur demande ». Remplacer par le nom et l'adresse réels de l'hébergeur : c'est une mention obligatoire. |
| **Mairie équipée la plus proche** | Fiche « Carte d'identité et passeport » | La fiche dit que Lachapelle-sous-Chaux n'a pas de dispositif de recueil et renvoie au site de l'ANTS. Si la mairie connaît les communes équipées les plus proches, les nommer fait gagner un appel à chaque demande. |

### À confirmer

- **Les horaires du secrétariat.** Ceux du site sont repris de la page contact
  de l'ancien site ; trois autres pages en donnaient trois versions
  différentes. Ceux publiés ici sont : lundi 8 h-11 h et 12 h-15 h, mardi
  8 h-11 h, mercredi 8 h 30-11 h et 14 h-16 h, jeudi et vendredi 8 h-12 h,
  permanence des élus le samedi 10 h-12 h.
- **La composition du conseil et des commissions**, datée de novembre 2020.
- **Les contacts des associations** : deux d'entre elles n'avaient aucun
  contact publié.
- **L'agenda**, dont les entrées livrées sont les rendez-vous récurrents du
  village avec des dates d'exemple. Elles se remplacent depuis Admin → Agenda.
- **Le budget** : la page décrit le fonctionnement budgétaire et annonce que
  les documents seront mis en ligne. Il reste à y déposer le budget primitif et
  le compte administratif.

### Facultatif

- **Clé Gemini** pour l'assistant de discussion (Admin → Assistant IA). Éteint
  tant qu'elle est vide.
- **Clé Google et identifiant de fiche** pour les avis (Admin → Avis Google).
  Éteint tant qu'ils sont vides.
- **Identifiant de mesure d'audience** (Admin → Paramètres). Chargé uniquement
  après consentement.
- **Réseaux sociaux** : les trois champs sont vides. Les blocs correspondants
  se retirent d'eux-mêmes.
- **Cloudflare Turnstile** : deux clés dans Paramètres ajoutent un étage
  anti-spam. Les barrières natives — champ piège, jeton d'horloge signé, quota
  par adresse — protègent seules en attendant.

---

## 9. Ce qui n'a pas été fait

- **Aucun audit d'accessibilité par un tiers.** La déclaration le dit
  explicitement plutôt que d'annoncer un taux de conformité qui n'a pas été
  mesuré. Les critères automatisables sont vérifiés à chaque mise en ligne ;
  les autres — navigation au lecteur d'écran, cohérence des intitulés de lien
  hors contexte — demandent un audit humain.
- **Les PDF ne sont pas accessibles.** Ils sont produits par des outils
  bureautiques et ne comportent ni structure de titres ni texte de
  remplacement. La page Accessibilité l'indique et propose une alternative :
  demander le contenu au secrétariat.
- **Le multilingue est en place mais aucune langue n'est publiée.** Le module
  reste activable depuis le back-office.
