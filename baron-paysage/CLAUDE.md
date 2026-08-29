# Consignes de travail sur ce dépôt

Ce dépôt est un **site vitrine PHP natif avec back-office complet**, et il
sert de modèle pour en produire d'autres à l'identique. Si votre tâche est de
créer un site pour un nouveau client, **lisez `NOUVEAU-SITE.md` d'abord** :
c'est la recette pas à pas, et elle vous évitera de redécouvrir ce qui est
déjà tranché.

---

## Ordre de lecture

| Quand | Quoi |
|---|---|
| **Nouveau site à produire** | `NOUVEAU-SITE.md` — la recette complète, du premier fichier à la mise en ligne |
| Comprendre l'architecture | `KIT.md` — carte du code, conventions, et **§ 10 : les pièges déjà rencontrés** |
| Comprendre les choix de ce site-ci | `SITE.md` — arbitrages de contenu et de design |
| Mettre en ligne | `DEPLOIEMENT.md` |
| Maquetter avant de développer | `CRM-MAQUETTES.md` + `maquettes/` |

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
- **CSS et JS écrits à la main.** Le JS est en ES5 tolérant, et chaque bloc se
  désarme seul si sa cible est absente de la page.

## Conventions de code

- **Tout est nommé en français** : classes, méthodes, variables, routes,
  clés JSON. `Antispam::verifier()`, `$valeurs['localite']`, `/demander-un-devis`.
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

## Ne jamais versionner

`data/admin/` (compte et mot de passe SMTP), `data/*.json` et `data/pages/`
(contenu vivant), `storage/` (cache et sauvegardes). Le contenu de départ vit
dans `data-modele/`, qui lui est versionné, et se recopie tout seul dans
`data/` à la première lecture.

---

## La boucle qualité — non négociable

Le niveau de ce projet ne vient pas du goût mais de la mesure. **Trois
auditeurs, à faire passer avant de déclarer une tâche finie** :

```bash
php -S 127.0.0.1:8081 -t public &

python3 outils/verifs/contraste.py       # contraste réel de chaque texte
python3 outils/verifs/mise-en-page.py    # débordement, cibles, titres, alt
python3 outils/verifs/traceurs.py        # aucune requête tierce sans accord
```

Chacun sort en code 1 s'il trouve quelque chose. **Zéro écart, zéro souci,
zéro hôte tiers** : c'est la définition de « fini » ici, pas une option.

Ce que ces scripts ont attrapé et qu'aucun œil n'avait vu : un texte à 4,36:1
sur le fond crème, la couleur de marque à 2,16:1 sur l'anthracite, un
sur-titre à 2,04:1 sur une photo de bandeau, des cibles tactiles à 26 px.

**N'affaiblissez jamais un auditeur pour faire passer une page.** Si un
résultat vous semble faux, c'est probablement un vrai faux positif — les
quatre pièges de mesure connus sont documentés dans `NOUVEAU-SITE.md`, § « Les
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
