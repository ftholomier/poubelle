# Baron Paysage — site vitrine

Site de **Baron Paysage**, paysagiste à Mathay et Montbéliard : conception,
aménagement paysager, entretien d'espaces verts, élagage et taille, dans le
Pays de Montbéliard, le Territoire de Belfort, le Plateau Maîchois et le
Plateau de Blamont.

PHP 8.1+, **aucune dépendance** : ni Composer, ni base de données, ni build
front. Le contenu vit dans des fichiers JSON qu'un back-office complet écrit.
Conçu pour l'hébergement mutualisé français.

---

## Les trois documents

- **[SITE.md](SITE.md)** — ce site-ci : arborescence, charte, contenu repris
  du site précédent, ce qui reste à renseigner avant la mise en ligne.
- **[DEPLOIEMENT.md](DEPLOIEMENT.md)** — mise en ligne, droits, FTP, mises à
  jour par git, dépannage.
- **[KIT.md](KIT.md)** — le socle technique, ses conventions et **les pièges
  rencontrés pendant son développement**. À lire pour reprendre le code.

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

## Ce que ce dépôt ne contient pas

Volontairement : le compte d'administration (`data/admin/`), le contenu
vivant du site une fois édité (`data/*.json`), et les clés d'API — Gemini
pour l'assistant, Google pour les avis, DeepL pour la traduction. Le site
fonctionne sans, chaque fonction concernée se retirant d'elle-même tant
qu'elle n'est pas configurée.
