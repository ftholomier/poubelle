# Baron Paysage — site vitrine

Site de **Baron Paysage**, paysagiste à Mathay et Montbéliard : conception,
aménagement paysager, entretien d'espaces verts, élagage et taille, dans le
Pays de Montbéliard, le Territoire de Belfort, le Plateau Maîchois et le
Plateau de Blamont.

PHP 8.1+, **aucune dépendance** : ni Composer, ni base de données, ni build
front. Le contenu vit dans des fichiers JSON qu'un back-office complet écrit.
Conçu pour l'hébergement mutualisé français.

---

## Les documents

**Pour produire un autre site sur ce modèle :**

- **[CLAUDE.md](CLAUDE.md)** — les consignes de travail sur ce dépôt.
  Chargé automatiquement par Claude Code ; à lire en premier de toute façon.
- **[NOUVEAU-SITE.md](NOUVEAU-SITE.md)** — la recette complète : le système de
  design en entier, l'itinéraire en dix étapes, les auditeurs, et les pièges
  déjà payés. C'est le document à suivre.

**Pour comprendre ou reprendre celui-ci :**

- **[SITE.md](SITE.md)** — ce site-ci : arborescence, charte, contenu repris
  du site précédent, ce qui reste à renseigner avant la mise en ligne.
- **[KIT.md](KIT.md)** — le socle technique, ses conventions et **les pièges
  rencontrés pendant son développement**.
- **[DEPLOIEMENT.md](DEPLOIEMENT.md)** — mise en ligne, droits, FTP, mises à
  jour par git, dépannage.
- **[CRM-MAQUETTES.md](CRM-MAQUETTES.md)** + `maquettes/` — le système de
  design distillé en trois pages HTML/CSS autonomes, pour maquetter avant de
  développer.

---

## Démarrer en trente secondes

```bash
php -S localhost:8080 -t public public/index.php
```

Puis `http://localhost:8080` pour le site, `/admin` pour créer le compte
administrateur — il n'y a aucun identifiant par défaut.

Le contenu de `data-modele/` se recopie tout seul dans `data/` à la première
visite : le site s'affiche complet, sans aucune manipulation préalable.

---

## Les trois auditeurs

La qualité de ce site ne vient pas du goût mais de la mesure. Trois scripts,
qui sortent en code 1 s'ils trouvent quelque chose :

```bash
php -S 127.0.0.1:8081 -t public &

python3 outils/verifs/contraste.py       # contraste réel de chaque texte
python3 outils/verifs/mise-en-page.py    # débordement, cibles, titres, alt
python3 outils/verifs/traceurs.py        # aucune requête tierce sans accord
```

Zéro écart, zéro souci, zéro hôte tiers : c'est la définition de « fini »
ici. Ce qu'ils ont attrapé et qu'aucun œil n'avait vu est listé dans
`NOUVEAU-SITE.md`.

---

## Ce que ce dépôt ne contient pas

Volontairement : le compte d'administration (`data/admin/`), le contenu
vivant du site une fois édité (`data/*.json`), et les clés d'API — Gemini
pour l'assistant, Google pour les avis, DeepL pour la traduction. Le site
fonctionne sans, chaque fonction concernée se retirant d'elle-même tant
qu'elle n'est pas configurée.
