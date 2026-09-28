# Site commercial terricom.fr

Site de présentation de terricom, écrit pour les élus des communautés de communes et des communes de toute la
France. Il met en avant le territoire, la place de chaque commune, l’animation et la communauté, et prend le
contre-pied des places de marché locales. **Aucun tarif n’y figure** : la proposition se fait en rendez-vous.

Le message est national. Les Lacs et Montagnes du Haut-Doubs n’apparaissent que comme territoire de
démonstration, et toujours présentés comme tels. Les photos d’ambiance viennent de toute la France : Périgord,
Alsace, Bretagne, Corrèze, Lot, Berry, Provence et Haut-Doubs.

**L’application en action.** Les écrans sont montrés en train d’être utilisés, en boucles vidéo courtes et muettes :

- une recherche d’habitant ;
- une campagne préparée par l’assistant ;
- l’accueil des nouvelles entreprises ;
- l’espace d’une mairie ;
- un commerçant qui publie ;
- le téléphone.

Chaque boucle ne se charge qu’à l’approche et se met en pause hors de l’écran. Avec « réduire les animations »,
rien ne démarre seul. Le film de présentation aux élus (1 min 54, `terricom/docs/teaser/terricom-elus.mp4`) est
intégré à l’accueil et à la page Démonstration.

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
outils/           construire.py (gabarit commun), medias.py (images et film), animations.py (boucles vidéo),
                  verifier.mjs (contrôles)
public/           site construit, prêt à déployer (versionné)
sources/          médias bruts, non versionnés (captures PNG, photos, teaser)
```

`construire.py` enveloppe chaque page dans le gabarit commun : en-tête, menu, pied de page, SEO, Open Graph et
JSON-LD. Il écrit aussi `sitemap.xml`, remplace les raccourcis et pose les espaces insécables du français.

Raccourcis disponibles :

- `{{shot:nom|adresse|alt}}` : capture dans un cadre de navigateur
- `{{phone:nom|alt}}` : capture dans un cadre de téléphone
- `{{anim:nom|adresse|description}}` : l’application en action (boucle vidéo), dans un cadre de navigateur
- `{{animphone:nom|description}}` : la même chose dans un cadre de téléphone
- `{{photo:nom|alt|lieu}}` : photo avec son crédit ; le nom du lieu est facultatif
- `{{img:chemin|alt|classe}}` : image
- `{{icon:nom}}` : icône
- `{{cta}}` : bandeau d’appel final
- `{{credits}}` : liste des crédits photo

## Régénérer

```bash
# 1. Captures et scènes de l'application (depuis terricom/, serveur de production lancé avec le fond de carte
#    réel, voir terricom/docs/teaser/README.md)
bash scripts/teaser/preparer.sh
node scripts/teaser/captures-site.mjs            # captures fixes → ../site-terricom/sources/captures
node scripts/teaser/scenes-site.mjs              # scènes filmées → .teaser/clips/site-*
npm run db:reset                                 # retire les préparations de capture

# 2. Médias (Pillow ; FFMPEG=chemin de ffmpeg pour les vidéos)
python3 outils/medias.py                         # photos, captures, film
TEASER_DIR=../terricom/.teaser python3 outils/animations.py   # boucles vidéo et images d'attente

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
- Les photos viennent de Wikimedia Commons, sous licence libre. Leurs crédits figurent sous chaque photo et sur la
  page Crédits.
- Les formulaires (démonstration et contact) sont envoyés à la plateforme terricom : `POST /api/site/demonstration`
  et `POST /api/site/contact` (code dans `terricom/src/app/api/site`). La demande d’une collectivité devient une
  affaire du suivi commercial de la console, ou s’ajoute à l’affaire déjà ouverte pour la même adresse.
  L’équipe est prévenue sur `SALES_EMAIL` (ftholomier@gmail.com ; une réponse part directement à l’expéditeur), et l’expéditeur reçoit un
  accusé de réception. Si la plateforme ne répond pas, le formulaire ouvre la messagerie avec la demande
  préremplie : rien n’est perdu.
- L’adresse de la plateforme est fixée à la construction : `TERRICOM_API` (par défaut
  `https://terricom.fr/api/site`). Côté plateforme, `SITE_ORIGINS` liste les adresses autorisées à envoyer les
  formulaires (par défaut `https://terricom.fr,https://www.terricom.fr`). Si le site et la plateforme partagent le
  domaine terricom.fr, le serveur web du site doit transmettre `/api/` à la plateforme.

## À compléter avant la mise en ligne

- Mentions légales : les champs `[à compléter]` (raison sociale, SIREN, siège, directeur de la publication,
  hébergeur).
- Mesure d’audience : à ajouter, sans cookie de préférence, en mettant la page Confidentialité à jour.
