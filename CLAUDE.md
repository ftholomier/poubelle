# Consignes de travail sur ce dépôt

Ce dépôt sert le **site officiel de la mairie d’Angeot** —
PHP natif, back-office complet, aucune dépendance — et sert aussi de modèle
pour en produire d'autres à l'identique. Si votre tâche est de créer un site
pour une autre commune, **lisez `NOUVEAU-SITE.md` d'abord** : c'est la recette
pas à pas, et elle vous évitera de redécouvrir ce qui est déjà tranché.

---

## Ordre de lecture

| Quand | Quoi |
|---|---|
| **Nouveau site à produire** | `NOUVEAU-SITE.md` — la recette complète, du premier fichier à la mise en ligne |
| Comprendre l'architecture | `KIT.md` — carte du code, conventions, et **§ 10 : les pièges déjà rencontrés** |
| Comprendre les choix de ce site-ci | `SITE.md` — arbitrages, charte, et **ce qui reste à renseigner par la mairie** |
| Mettre en ligne | `DEPLOIEMENT.md` |

Ne réécrivez pas ces documents pour une tâche ponctuelle. Mettez-les à jour
quand vous changez ce qu'ils décrivent.

---

## Contraintes techniques à ne jamais casser

Elles ne sont pas des préférences : le socle est conçu pour l'hébergement
mutualisé français, où rien de tout cela n'est disponible.

- **Aucune dépendance.** Pas de Composer, pas de `node_modules`, pas de base
  de données, pas d'étape de build. Si une bibliothèque semble nécessaire,
  c'est presque toujours qu'il manque cinquante lignes de PHP.
- **PHP 8.1+**, `declare(strict_types=1)` en tête de chaque fichier.
- **`public/` est le seul dossier exposé.** Rien d'autre ne doit être
  atteignable par une URL.
- **Le contenu vit en JSON**, écrit par le back-office. Jamais de contenu en
  dur dans un gabarit.
- **Une page est une suite de blocs typés.** Les types sont décrits une fois
  dans `app/Admin/Blocs.php` et rendus par `views/partials/bloc.php` : ajouter
  une page ne coûte qu'un fichier JSON, une entrée dans `Seo::PAGES` et une
  ligne de route. N'écrivez une vue dédiée que si la mise en page dépend d'une
  donnée structurée — le trombinoscope du conseil, la liste filtrable des
  démarches — jamais pour du texte suivi.
- **CSS et JS écrits à la main.** Le JS est en ES5 tolérant, et chaque bloc se
  désarme seul si sa cible est absente de la page.

## Conventions de code

- **Tout est nommé en français** : classes, méthodes, variables, routes,
  clés JSON. `Antispam::verifier()`, `$valeurs['adresse']`, `/demande-en-ligne`.
- **Les commentaires disent pourquoi, jamais quoi.** Un commentaire qui
  paraphrase la ligne suivante est du bruit. Un commentaire qui explique
  qu'on a mesuré 2,16:1 et qu'il a fallu une variante assombrie a de la
  valeur — c'est ce qui empêche quelqu'un de « simplifier » la correction six
  mois plus tard.
- **`e()` sur toute sortie**, sans exception.
- **`Csrf::champ()` dans chaque formulaire d'administration**, et
  `Csrf::verifier()` en tête de chaque traitement.
- **Écritures atomiques** : fichier temporaire puis `rename()`. Jamais de
  `file_put_contents` direct sur un fichier de contenu.
- **Les textes d'interface passent par `t('…')`** — ils sont relevés
  automatiquement pour la traduction, rien à déclarer.

## Ce qu'un site de service public exige en plus

Ces règles ne s'appliquaient pas au site commercial dont vient le socle. Elles
s'appliquent ici, et une modification qui les casse est un défaut.

- **Ne publiez jamais une règle administrative que vous n'avez pas vérifiée.**
  Une démarche change de pièces, de seuils et de délais d'une année à l'autre ;
  une fiche périmée envoie un administré au guichet avec le mauvais dossier.
  Le site précédent décrivait l'urbanisme d'avant 2012 et les procurations
  d'avant 2022 — c'est ce qu'il a fallu réécrire en entier.
- **N'affirmez jamais un service que la mairie ne rend pas.** La fiche
  « Carte d'identité » dit que la commune n'a pas de dispositif de recueil,
  parce que c'est vrai d'une commune de sept cents habitants ; le texte
  d'origine, recopié d'un modèle, affirmait le contraire.
- **Les urgences passent avant tout le reste**, y compris avant un renvoi vers
  une page du site : c'est écrit dans la consigne de l'assistant, et cela vaut
  pour tout ce qu'on ajoute.
- **Aucune requête tierce sans consentement.** C'est vrai partout, mais une
  mairie n'a pas le droit de déposer les cookies d'un autre avant que
  l'administré ait dit oui. L'auditeur `traceurs.py` le mesure.

## Ne jamais versionner

`data/admin/` (compte et mot de passe SMTP), `data/*.json` et `data/pages/`
(contenu vivant), `data/frequentation/` (pages vues), `storage/` (cache et
sauvegardes). Le contenu de départ vit
dans `data-modele/`, qui lui est versionné, et se recopie tout seul dans
`data/` à la première lecture.

Après avoir essayé le back-office en local, **remettez `data/` à zéro** avant
de committer : un aller-retour dans un formulaire y laisse le contenu modifié,
et c'est `data-modele/` qui doit rester la source.

```bash
rm -rf data/pages data/*.json data/admin storage/cache/* storage/sauvegardes/*
```

---

## La boucle qualité — non négociable

Le niveau de ce projet ne vient pas du goût mais de la mesure. **Onze
auditeurs, à faire passer avant de déclarer une tâche finie** :

```bash
php -S 127.0.0.1:8081 -t public &

python3 outils/verifs/contraste.py       # contraste réel de chaque texte
python3 outils/verifs/mise-en-page.py    # débordement, cibles, titres, alt, superpositions
python3 outils/verifs/mise-en-page.py --admin id:mdp   # les mêmes, sur le back-office
python3 outils/verifs/traceurs.py        # aucune requête tierce sans accord
python3 outils/verifs/bandeau.py         # chaque photo du diaporama, voile au plancher
python3 outils/verifs/entete.py          # l'en-tête aux bornes du réglage du logo
python3 outils/verifs/couleur.py         # le site sous chaque teinte de la roue
python3 outils/verifs/bulle.py           # le bouton de l’assistant sous chacun de ses réglages
python3 outils/verifs/vignette.py        # l’image fabriquée pour Instagram, sous chaque couleur
python3 outils/verifs/alertes.py         # ce que PHP dit tout bas, et que la page ne montre pas
php     outils/verifs/file.php           # la file de publication quand un seul réseau répond
python3 outils/verifs/aller-retour.py    # enregistrer sans rien changer : le JSON ne doit pas maigrir
```

Les deux derniers ne pilotent aucun navigateur : `file.php` mesure ce que
devient une publication quand un seul réseau répond, `aller-retour.py` ce que
rend un écran d'édition quand on l'enregistre sans rien changer.

**Ne les lancez pas en parallèle sans y penser.** Plusieurs écrivent dans
`data/` pour forcer un réglage — `couleur.py` la teinte, `bulle.py` l'assistant
—, et deux qui s'y croisent se mesurent l'un l'autre. `aller-retour.py`, qui a
besoin d'un contenu neuf, prend pour cela un dossier à lui (`APP_DATA`) plutôt
que de faire le ménage dans celui du dépôt.

Chacun sort en code 1 s'il trouve quelque chose. **Zéro écart, zéro souci,
zéro hôte tiers** : c'est la définition de « fini » ici, pas une option.

Les six derniers existent pour deux raisons. Les quatre premiers, parce que
**les autres ne mesurent qu'une configuration à la fois** ; les deux derniers,
parce qu'ils regardent ailleurs — une image fabriquée, et le journal d'erreurs
du serveur. `contraste.py` ne voit qu'une photo de
diaporama par passage, puisqu'il est tiré au hasard ; aucun ne voit le site
avec un autre réglage de taille de logo, ni avec une autre couleur de commune,
que ceux du jour. Une page peut donc passer un jour et échouer le lendemain
sans qu'une ligne ait bougé. `bandeau.py` force chaque photo, `entete.py`
force les deux bornes du réglage du logo dans les deux modes et les deux
dispositions de menu — 96 mesures —, `couleur.py` force douze teintes
réparties sur la roue plus quatre cas limites (gris sans saturation, rouge
saturé, presque noir, presque blanc), et `bulle.py` force les cinq formes du
bouton de l'assistant, ses bornes de taille, six couples de couleurs, ses cinq
animations d'appel, les quatre coins de leur rythme et le rappel au
défilement. `vignette.py` mesure l'image carrée fabriquée pour Instagram sous
chaque couleur de commune, en lisant ses pixels.

**`alertes.py` est d'une autre nature, et c'est le plus utile des onze.** Il
lit le **journal d'erreurs de PHP**, que personne ne lisait : une alerte ne
sort pas dans la page, puisque `display_errors` est éteint en production — et
doit l'être. La vérification que le socle proposait, `curl … | grep -ci
"warning"`, ne mesurait donc que ce qui ne devrait jamais arriver. Le jour où
ce script a existé, il a trouvé en trois minutes trois défauts silencieux :
deux sections de contenu qui ne s'affichaient pas du tout, et une donnée
passée à un gabarit qui n'y arrivait jamais.

**Règle qui en découle : ce qui ne se voit pas dans la page doit être mesuré
là où il se voit.** Le journal du serveur en fait partie, la file de
publication aussi — d'où `file.php` —, et ce qu'un formulaire du back-office
renvoie au disque, d'où `aller-retour.py`. Ni l'un ni l'autre n'ouvre de page.

**`aller-retour.py` mesure un enregistrement, pas un affichage.** Un écran
d'édition qui s'ouvre bien peut très bien vider une clé au moment de
l'enregistrer : il suffit qu'un `name=` du gabarit ne corresponde plus à ce
que le contrôleur relit. Rien n'est signalé, la valeur arrive simplement vide.
Le script rejoue chaque formulaire tel que la page le rend, sans rien changer,
et compte les valeurs non vides du JSON avant et après. **Ajoutez-y tout écran
d'édition que vous créez** : un écran absent de sa liste n'est pas vérifié.

**`file.php` mesure une décision, pas un rendu.** Ce que fait la file quand
Facebook accepte et qu'Instagram refuse ne s'affiche nulle part : il faut
publier vraiment pour le voir, donc personne ne le voit jamais. La branche
était fausse — la publication était retirée de la file et inscrite au journal
comme réussie, Instagram n'était jamais retenté, et la mairie croyait avoir
publié partout. Une doublure tient lieu de Meta, aucune requête ne sort, et
les vingt-huit mesures tiennent en deux secondes. **Retenez-en la règle : une
branche qu'on ne peut atteindre qu'en production doit avoir sa doublure, sans
quoi elle n'est jamais exercée.**

`bulle.py` a une raison de plus d'exister : **les autres ne voient jamais
ce bouton.** L'assistant est éteint tant qu'aucune clé n'est renseignée, donc
la bulle n'est pas dans la page qu'ils mesurent. C'est ce trou qui a laissé
passer, pendant toute la vie du socle, un libellé à 2,57:1 — l'encre sur la
couleur de marque. Retenez-en la règle générale : **un réglage qui décide de
la PRÉSENCE d'un élément cache cet élément aux auditeurs**, et il faut alors
un script qui l'allume pour le mesurer.

C'est rentable : le premier a trouvé deux photos à 4,4:1, le deuxième un logo
servi écrasé au-delà de 100 px et une cible tactile à 40 px une fois la barre
défilée, le dernier le libellé de la bulle à 2,57:1. Aucun œil ne les avait
vus.

**Le back-office se mesure aussi.** Il ne l'était pas : `mise-en-page.py` ne
parcourait que le site public, et c'est là qu'un défaut a vécu tranquillement.
Une pastille de code faite pour occuper sa propre ligne, posée au fil d'un
paragraphe, recouvrait la ligne du dessus sur deux écrans — 46 px de haut dans
une ligne de 22. Le rembourrage vertical d'une boîte **en ligne** ne pousse pas
la ligne : il déborde. `mise-en-page.py --admin` relève désormais ce cas
partout, et il a trouvé du même coup une centaine de cibles tactiles sous
44 px dans le back-office, jusque-là non mesurées.

Il l'est aux mêmes cinq largeurs que le site, **téléphone compris**. Le
panneau latéral se replie en tiroir sous 900 px, et le passage à 320 px a
demandé de lever quatre causes de débordement, toutes classiques et toutes
invisibles sur un grand écran :

- **`min-width: 0`** sur ce qui doit pouvoir rétrécir. Un enfant de flex ou de
  grille refuse de descendre sous la largeur minimale de son contenu tant
  qu'on ne le lui dit pas. C'est de loin la cause la plus fréquente.
- **`minmax(min(190px, 100%), 1fr)`** et jamais `minmax(190px, 1fr)` : une
  piste de grille garde son minimum même quand la fenêtre est plus étroite
  qu'elle, et pousse la page dehors.
- **`fieldset { min-inline-size: 0 }`** : la feuille du navigateur y met
  `min-content`, qu'aucune largeur ne surcharge. Bizarrerie historique du
  HTML, pas un choix du socle.
- **Une grille à colonnes fixes ne se resserre pas.** Les six colonnes d'une
  vue de diaporama deviennent une rangée qui se replie sous 640 px.

Retenez la liste : elle couvre à peu près tout ce qui fait défiler une page
latéralement sur un téléphone.

**Règle qui en découle : tout réglage laissé à la mairie doit avoir son
auditeur qui en force les bornes.** Un curseur dont on n'a mesuré que la valeur
livrée est un défaut en attente, découvert en ligne par la mairie. C'est ce qui
a rattrapé le voile du bandeau : réglable de 0 à 100 et mesuré à sa seule
valeur du jour, il laissait la mairie servir un titre blanc sur une photo
claire. `bandeau.py` force désormais la borne basse sur chaque photo, et le
curseur ne descend plus en dessous.

Ce que ces scripts ont attrapé et qu'aucun œil n'avait vu : un texte à 4,36:1
sur le fond crème, la couleur de marque à 2,16:1 sur l'anthracite, un
sur-titre à 2,04:1 sur une photo de bandeau, des cibles tactiles à 26 px, un
saut de titre h1 → h3 sur une fiche de démarche, et le blanc à 90 %
d'opacité du chapô de bandeau — 4,05:1 là où le blanc plein donne 4,9:1.

**N'affaiblissez jamais un auditeur pour faire passer une page.** Si un
résultat vous semble faux, c'est probablement un vrai faux positif — les
dix pièges de mesure connus sont documentés dans `NOUVEAU-SITE.md`, § « Les
auditeurs ». Corrigez le script en expliquant pourquoi, ne relevez pas le
seuil.

---

## Démarrer

```bash
php -S localhost:8080 -t public public/index.php
```

`/admin` crée le compte administrateur au premier passage : il n'y a aucun
identifiant par défaut. `data-modele/` se recopie seul dans `data/`, le site
s'affiche complet sans manipulation.

## Vérifier une page vite fait

```bash
php -l views/pages/accueil.php                    # syntaxe
curl -s localhost:8080/ | grep -ci "warning\|fatal\|notice"   # doit rendre 0
```

## Vérifier le back-office

Ouvrir un écran ne suffit pas : ce qui casse, c'est l'enregistrement. Un champ
mal nommé vide une clé sans rien signaler. Après toute modification d'un écran
d'édition, **enregistrez sans rien changer et comparez le JSON avant / après** :
il ne doit pas maigrir.

`outils/verifs/aller-retour.py` le fait tout seul, sur les écrans déclarés en
tête du script — il rejoue le formulaire tel que la page le rend, ce qui est le
seul moyen de voir qu'un `name=` du gabarit ne correspond plus à ce que le
contrôleur relit. **Ajoutez-y tout écran d'édition que vous créez** : un écran
absent de sa liste n'est pas vérifié, et c'est exactement ainsi que le défaut
revient.
