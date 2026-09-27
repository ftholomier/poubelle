# Site commercial terricom.fr

Site de présentation de terricom, écrit pour les élus des communautés de communes et des communes. Il met en
avant le territoire, la place de chaque commune, l’animation et la communauté, et prend le contre-pied des places
de marché locales. **Aucun tarif n’y figure** : la proposition se fait en rendez-vous.

Le site est en HTML statique, sans dépendance à l’exécution. Pour le mettre en ligne, il suffit de déposer le
contenu de `public/` sur n’importe quel hébergement : serveur web, stockage objet ou CDN.

## Pages

| Page                    | Rôle                                                                            |
| ----------------------- | ------------------------------------------------------------------------------- |
| `index`                 | Accueil : la promesse, les quatre publics, la différence, le film               |
| `elus`                  | Pour les élus : fierté du territoire, ce qu’ils pourront montrer et dire         |
| `solution`              | Les quatre espaces : portail, communauté de communes, mairies, entreprises      |
| `communes`              | La place de chaque commune, l’espace mairie, commencer par une commune          |
| `entreprises`           | Ce que reçoivent les entreprises, sans vente en ligne imposée ni commission     |
| `difference`            | Pourquoi les places de marché locales ont échoué, ce que terricom fait autrement |
| `demonstration`         | Démonstration sur le Haut-Doubs, film, formulaire de demande                    |
| `accompagnement`        | De la démonstration au lancement, puis l’animation toute l’année                |
| `confiance`             | Données, hébergement, RGPD, accessibilité                                       |
| `questions`, `contact`  | Questions fréquentes, contact                                                   |
| `mentions-legales`, `confidentialite`, `credits`, `404` | Pages légales et utilitaires                    |

## Organisation

```text
pages/            contenu de chaque page (en-tête de métadonnées en commentaire HTML)
outils/           construire.py (gabarit commun), medias.py (images et vidéo), verifier.mjs (contrôles)
public/           site construit, prêt à déployer (versionné)
sources/          médias bruts, non versionnés (captures PNG, photos, teaser)
```

`construire.py` enveloppe chaque page dans le gabarit commun : en-tête, menu, pied de page, SEO, Open Graph et
JSON-LD. Il écrit aussi `sitemap.xml`, remplace les raccourcis et pose les espaces insécables du français.

Raccourcis disponibles :

- `{{shot:nom|adresse|alt}}` : capture dans un cadre de navigateur
- `{{phone:nom|alt}}` : capture dans un cadre de téléphone
- `{{photo:nom|alt}}` : photo avec son crédit
- `{{img:chemin|alt|classe}}` : image
- `{{icon:nom}}` : icône
- `{{cta}}` : bandeau d’appel final
- `{{credits}}` : liste des crédits photo

## Régénérer

```bash
# 1. Captures de l'application (depuis terricom/, serveur de production lancé avec le fond de carte réel)
bash scripts/teaser/preparer.sh
node scripts/teaser/captures-site.mjs            # → ../site-terricom/sources/captures
npm run db:reset                                 # retire les préparations de capture

# 2. Médias (Pillow ; FFMPEG=chemin de ffmpeg pour la vidéo)
python3 outils/medias.py

# 3. Pages
python3 outils/construire.py

# 4. Contrôles : liens, ressources, débordements et erreurs (bureau 1440 px, mobile 390 px), captures pleine page
node outils/verifier.mjs --captures /tmp/site
```

## Contenus

- Les captures montrent la démonstration construite sur les données publiques des Lacs et Montagnes du
  Haut-Doubs. Cette démonstration n’engage pas cette collectivité, et le site le précise.
- Les contenus d’exemple sont signalés « de démonstration » ; aucun contenu inventé n’est attribué à une entreprise
  réelle.
- Les photos viennent de Wikimedia Commons, sous licence libre ; leurs crédits sont sur la page Crédits.
- Les formulaires ouvrent la messagerie avec la demande préremplie (`mailto:bonjour@terricom.fr`). Aucune donnée
  n’est envoyée à un serveur.

## À compléter avant la mise en ligne

- Mentions légales : les champs `[à compléter]` (raison sociale, SIREN, siège, directeur de la publication,
  hébergeur).
- Formulaires : les brancher, si souhaité, sur un service d’envoi.
- Mesure d’audience : à ajouter, sans cookie de préférence, en mettant la page Confidentialité à jour.
