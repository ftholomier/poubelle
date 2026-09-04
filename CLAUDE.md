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
(contenu vivant), `storage/` (cache et sauvegardes). Le contenu de départ vit
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

Le niveau de ce projet ne vient pas du goût mais de la mesure. **Cinq
auditeurs, à faire passer avant de déclarer une tâche finie** :

```bash
php -S 127.0.0.1:8081 -t public &

python3 outils/verifs/contraste.py       # contraste réel de chaque texte
python3 outils/verifs/mise-en-page.py    # débordement, cibles, titres, alt
python3 outils/verifs/traceurs.py        # aucune requête tierce sans accord
python3 outils/verifs/bandeau.py         # chaque photo du diaporama, une à une
python3 outils/verifs/entete.py          # l'en-tête aux bornes du réglage du logo
```

Chacun sort en code 1 s'il trouve quelque chose. **Zéro écart, zéro souci,
zéro hôte tiers** : c'est la définition de « fini » ici, pas une option.

Les deux derniers existent pour la même raison : **les autres ne mesurent
qu'une configuration à la fois.** `contraste.py` ne voit qu'une photo de
diaporama par passage, puisqu'il est tiré au hasard ; aucun ne voit le site
avec un autre réglage de taille de logo que celui du jour. Une page peut donc
passer un jour et échouer le lendemain sans qu'une ligne ait bougé.
`bandeau.py` force chaque photo, `entete.py` force les deux bornes du réglage
du logo dans les deux modes et les deux dispositions de menu — 96 mesures.

C'est rentable : le premier a trouvé deux photos à 4,4:1, le second un logo
servi écrasé au-delà de 100 px et une cible tactile à 40 px une fois la barre
défilée. Aucun œil ne les avait vus.

**Règle qui en découle : tout réglage laissé à la mairie doit avoir son
auditeur qui en force les bornes.** Un curseur dont on n'a mesuré que la valeur
livrée est un défaut en attente, découvert en ligne par la mairie.

Ce que ces scripts ont attrapé et qu'aucun œil n'avait vu : un texte à 4,36:1
sur le fond crème, la couleur de marque à 2,16:1 sur l'anthracite, un
sur-titre à 2,04:1 sur une photo de bandeau, des cibles tactiles à 26 px, un
saut de titre h1 → h3 sur une fiche de démarche, et le blanc à 90 %
d'opacité du chapô de bandeau — 4,05:1 là où le blanc plein donne 4,9:1.

**N'affaiblissez jamais un auditeur pour faire passer une page.** Si un
résultat vous semble faux, c'est probablement un vrai faux positif — les
sept pièges de mesure connus sont documentés dans `NOUVEAU-SITE.md`, § « Les
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
