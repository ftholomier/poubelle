# Adresses web des clients : sous-domaine terricom.fr et domaine propre

Piste mise de côté le 28 septembre 2026, à reprendre plus tard. Rien n’est développé.

## Le besoin

Depuis leur back-office, une communauté de communes, une commune ou une entreprise peut :

- demander un sous-domaine en terricom.fr (ex. `epicerie.terricom.fr`) ;
- brancher son propre nom de domaine (ex. `epicerie.fr`) sur sa fiche publique, ou sur son mini-site si elle a
  un pack payant.

## Ce qui existe déjà

- `*.terricom.fr` arrive déjà sur l’application avec un certificat générique : un nouveau sous-domaine ne demande
  ni DNS ni certificat.
- Domaine propre d’une CC (Personnalisation → domaine) : saisie, CNAME vers `portails.terricom.fr`, bouton
  « Vérifier », certificat créé par la tâche `src/server/jobs/domains.ts` (table `territory_domains`).
- Manque : le choix du sous-domaine terricom.fr par la CC (il est fixé par l’équipe), et tout ce qui concerne les
  communes et les entreprises.

## Proposition

### Sous-domaine en terricom.fr

- Rubrique « Mon adresse web » dans les trois back-offices, avec disponibilité vérifiée en direct.
- Un registre unique des noms (CC, communes, entreprises), premier arrivé premier servi ; noms techniques
  (`www`, `api`, `console`, `pro`…) et injurieux interdits.
- Lettres sans accent, chiffres et tirets (`epicerie`, pas `épicerie`) : pas d’adresses illisibles ni
  d’usurpation par caractères ressemblants.
- Validation par l’équipe dans la console au début (éviter qu’une boutique réserve le nom d’une ville), à
  automatiser ensuite. Mise en ligne immédiate une fois validée.

### Domaine propre

- Même parcours que les CC : saisie, instructions pas à pas, « Vérifier », certificat automatique.
- Avec `www` : un CNAME. Sans `www` (`epicerie.fr`) : un CNAME est impossible, il faut un enregistrement A vers
  une adresse IP fixe de terricom, puis rediriger vers `www`. Prévoir des guides par hébergeur (OVH, Gandi,
  Ionos, Google).
- Préciser au client que sa messagerie `@epicerie.fr` n’est pas touchée.
- Affiche la fiche publique (offre gratuite) ou le mini-site (pack payant).

### Règles

- Une seule adresse officielle par contenu : le domaine propre devient l’adresse de référence, le portail garde
  la fiche et renvoie vers lui (pas de contenu en double pour Google).
- Le domaine propre garde un lien discret vers le portail du territoire.
- La connexion reste sur terricom.fr ; un lien « Gérer ma fiche » y renvoie.
- Fin d’abonnement : le domaine propre redirige vers la fiche du portail ; un sous-domaine libéré reste bloqué
  90 jours avant d’être réattribué.
- Vérification DNS obligatoire ; un domaine qui ne pointe plus vers terricom est désactivé automatiquement.
- Console : liste des demandes, état DNS et certificat, révocation.
- Au-delà de quelques centaines de domaines propres, passer à des certificats créés à la première visite.

### Découpage

1. Registre des noms, sous-domaines des CC et des communes, validation dans la console.
2. Sous-domaines des entreprises.
3. Domaines propres des entreprises (adresse sans `www`, guides par hébergeur).
4. Domaines propres des communes.

## Décisions à prendre avant de développer

- Sous-domaine gratuit pour toutes les entreprises, ou réservé aux packs payants ? Domaine propre gratuit pour
  afficher la fiche ?
- Validation par l’équipe au début, ou automatique dès le départ ?
- **Adresse d’une commune membre d’une CC : le portail de la CC filtré sur la commune, ou une page à part ?**
  (question à retenir en priorité)
