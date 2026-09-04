# Déploiement sur o2switch

## Avant de commencer : il n'y a pas de base de données

Le site n'utilise **ni MySQL, ni SQL, ni phpMyAdmin**. Tout le contenu est
stocké dans des fichiers JSON sous `data/`. Il n'y a donc aucun fichier
`.sql` à importer, ni identifiants de base à renseigner.

Ce que ça change concrètement :

- **Sauvegarder le site** = copier les dossiers `data/` et
  `public/assets/img/site/`. Rien d'autre.
- **Dupliquer le site** (préprod, test) = copier l'arborescence.
- **Restaurer une bêtise** = le back-office garde les 20 dernières versions
  de chaque fichier de contenu, restaurables en un clic.
- Aucun risque de perte de connexion à la base, aucune migration à gérer.

Les seuls réglages techniques (SMTP, destinataire des demandes) se saisissent
**dans le back-office**, écran *Paramètres* — pas dans un fichier.

---

## 1. Où poser les fichiers

Le point critique : **seul le dossier `public/` doit être accessible depuis
le web**. Le reste (`app/`, `config/`, `data/`, `views/`, `storage/`) contient
le contenu, le compte administrateur et les mots de passe SMTP.

### Méthode recommandée — racine web déplacée

Envoyez tout le projet dans votre espace, **à côté** de `public_html` :

```
/home/VOTRECOMPTE/
├── public_html/          ← ne sert plus, laissez-le vide
├── angeot/                        ← le projet complet
│   ├── app/
│   ├── config/
│   ├── data/
│   ├── public/           ← c'est CE dossier qui doit être la racine web
│   ├── storage/
│   └── views/
```

Puis dans **cPanel → Domaines** (ou *Domaines additionnels* / *Sous-domaines*
selon le cas), modifiez la **racine du document** du domaine et pointez-la
sur `angeot/public`.

### Méthode de repli — tout dans public_html

Si vous ne pouvez pas déplacer la racine web, envoyez le projet dans
`public_html/`. Des fichiers `.htaccess` de protection sont déjà présents
dans `app/`, `config/`, `data/`, `views/`, `storage/` et `tools/` : ils
bloquent tout accès direct.

**Cette méthode est moins sûre** — elle repose entièrement sur Apache. Après
installation, vérifiez que `https://votredomaine.fr/data/site.json` renvoie
bien une erreur 403. Si le fichier s'affiche, **arrêtez tout** et repassez à
la méthode recommandée : votre mot de passe SMTP serait lisible publiquement.

---

## 2. Réglages cPanel

**PHP 8.1 minimum** (8.2 ou 8.3 conseillé) via *MultiPHP Manager*.

Extensions nécessaires, actives par défaut chez o2switch — à vérifier dans
*Sélectionner une version de PHP → Extensions* :

| Extension | Rôle |
|---|---|
| `gd` | redimensionnement des photos |
| `fileinfo` | contrôle des fichiers envoyés |
| `mbstring` | textes accentués |
| `openssl` | connexion SMTP chiffrée |

**Taille des envois** — par défaut o2switch autorise largement de quoi
envoyer des photos, mais si l'écran *Paramètres* signale un problème,
augmentez dans *MultiPHP INI Editor* :

```
upload_max_filesize = 16M
post_max_size = 20M
```

---

## 3. Permissions

```bash
find . -type d -exec chmod 755 {} \;
find . -type f -exec chmod 644 {} \;
chmod 640 data/admin/* 2>/dev/null
```

Chez o2switch, PHP tourne sous votre propre compte : `755` sur un dossier
suffit donc à le rendre inscriptible, et `644` sur un fichier à le rendre
modifiable. **N'utilisez jamais `777`** — plusieurs hébergements mutualisés
refusent de servir un fichier accessible en écriture à tout le monde, et
renvoient une erreur 500.

`data/admin/` contient le mot de passe SMTP et l'empreinte du compte : `640`,
pour qu'aucun autre compte du serveur ne puisse le lire.

Vous n'aurez normalement à taper ces commandes qu'une seule fois : l'écran
*Paramètres → Droits d'accès* détecte les anomalies et les corrige d'un clic,
sans SSH.

---

## 4. Premier lancement

1. Ouvrez **`https://votredomaine.fr/`** — le site doit s'afficher.
2. Ouvrez **`https://votredomaine.fr/admin`**.

### Il n'y a pas d'identifiants par défaut

C'est volontaire : un mot de passe livré par défaut est la première chose
qu'un robot essaie. À la place, la toute première visite de `/admin` affiche
un écran **Première configuration** où vous créez vous-même le compte
(identifiant libre, mot de passe de 12 caractères minimum).

Cet écran **disparaît définitivement** dès que le compte existe : personne ne
peut le rejouer pour créer un second compte.

> **Faites-le tout de suite après la mise en ligne.** Tant que le compte
> n'existe pas, n'importe quel visiteur tombant sur `/admin` pourrait le
> créer à votre place.

Vous changerez identifiant et mot de passe quand vous voulez depuis
*Paramètres → Compte administrateur* (le mot de passe actuel est exigé).

Le compte est stocké dans `data/admin/compte.json`, mot de passe **haché en
bcrypt** — il n'est jamais lisible, même en ouvrant le fichier.

---

## 5. Configurer l'envoi des e-mails

Le formulaire de contact n'envoie rien tant que ce n'est pas réglé.

1. Créez une adresse dans **cPanel → Comptes de messagerie**, par exemple
   `contact@votredomaine.fr`.
2. Dans le back-office, allez dans **Paramètres**.
3. **Destinataire des demandes** : l'adresse qui recevra les messages du
   formulaire.
4. **Serveur d'envoi (SMTP)** : cochez *Envoyer les e-mails via SMTP* et
   renseignez les valeurs o2switch :

| Champ | Valeur |
|---|---|
| Serveur | `votredomaine.fr` (ou `mail.votredomaine.fr`) |
| Port | `587` |
| Chiffrement | STARTTLS |
| Identifiant | l'adresse complète, ex. `contact@votredomaine.fr` |
| Mot de passe | celui du compte de messagerie |
| Adresse expéditrice | la même adresse |

> Port `465` avec *SSL/TLS* fonctionne aussi. L'adresse expéditrice **doit**
> appartenir à votre domaine, sinon les messages partiront en indésirables.

5. Enregistrez, puis utilisez **Tester l'envoi**. En cas d'échec, dépliez
   *Détail de la dernière tentative* : le dialogue complet avec le serveur y
   figure (mots de passe masqués), ce qui montre exactement où ça bloque.

Sans SMTP, le site retombe sur la fonction `mail()` de PHP — ça marche
parfois, mais les messages finissent souvent en spam. **Configurez le SMTP.**

Les demandes arrivent avec le visiteur en `Reply-To` : vous répondez
directement depuis votre boîte, la réponse part chez lui.

---

## 6. Vérifier que tout va bien

L'écran **Paramètres** se termine par un **Diagnostic du serveur** qui
contrôle version de PHP, extensions, droits d'écriture, exposition du dossier
`data/` et taille maximale des envois. Tout doit être au vert.

Puis, à la main :

- [ ] Le site s'affiche, le menu fonctionne
- [ ] Une fiche de service s'ouvre depuis le menu et depuis la page « Nos services »
- [ ] Les photos s'affichent
- [ ] Le bouton *Prendre rendez-vous* mène bien à la page Contact
- [ ] Le formulaire de contact envoie un message qui arrive
- [ ] `https://votredomaine.fr/data/site.json` renvoie une **erreur 403**
- [ ] `/admin` demande une connexion
- [ ] Une modification dans le back-office apparaît sur le site
- [ ] `/sitemap.xml` liste bien les pages, `/robots.txt` s'affiche

---

## 7. Référencement

L'écran **Référencement** du back-office regroupe tout ce que Google lit.

**Adresses des pages.** Chaque page a son slug — la partie de l'adresse après
le nom de domaine. Écrivez-le en toutes lettres : « Aménagement paysager
Montbéliard » est enregistré comme `amenagement-paysager-montbeliard`. Accents,
majuscules, espaces, ponctuation et apostrophes sont convertis, et une adresse
complète collée depuis le navigateur est ramenée à son chemin. Un aperçu
affiche le résultat pendant la frappe, et le message de confirmation rappelle
l'adresse retenue.

Le modifier crée automatiquement une **redirection
permanente (301)** depuis l'ancienne adresse, réécrit les liens du menu et des
blocs d'accueil, et fait suivre les sous-pages : renommer `/nos-services` en
`/prestations` redirige aussi `/nos-services/comptabilite`. Aucun lien déjà
partagé ou indexé ne se casse. Les fiches de service ont la même
mécanique dans la section *Fiches*.

L'accueil fait exception : il est servi à la racine, son adresse n'est pas
modifiable.

**Titres et descriptions.** Laissés vides, ils reprennent le titre de la page
suivi du nom du domaine, et la description du contenu. Un aperçu montre le
rendu dans les résultats Google, et un compteur signale les longueurs qui
seront coupées (60 caractères pour le titre, 158 pour la description).

**Indexation.** La case générale *Autoriser les moteurs de recherche* coupe
tout le site d'un coup — utile pendant les travaux, **à recocher à la mise en
ligne**, sinon `robots.txt` interdira tout et le site restera invisible. Page
par page, décocher retire la page du plan du site et demande aux moteurs de ne
pas l'afficher.

**Ce que le site publie tout seul :**

| Adresse | Contenu |
|---|---|
| `/sitemap.xml` | plan du site : pages indexables et fiches en ligne |
| `/robots.txt` | règles d'exploration, avec le lien vers le plan |

Les fiches hors ligne et les pages non indexables en sortent automatiquement.
Chaque page publie aussi sa balise `canonical`, ses balises de partage
(Facebook, WhatsApp, e-mail) et ses **données structurées JSON-LD** décrivant
la société (type `AccountingService`, avec ses horaires et ses prestations), le service affiché, et le fil
d'Ariane.

**À faire une fois le site en ligne :** déclarez `https://votredomaine.fr/sitemap.xml`
dans [Google Search Console](https://search.google.com/search-console) — c'est
ce qui accélère la prise en compte du nouveau site et remonte les erreurs
d'exploration.

---

## 8. Ajouter, masquer, supprimer

Deux listes se gèrent de la même façon, chacune depuis son écran :

| Écran | Ce que vous pilotez |
|---|---|
| **Services** | les fiches de prestation, chacune avec sa propre page |
| **Valeurs** | les valeurs de la société |

Partout, la même logique :

- **Ajouter** crée l'élément **hors ligne**. Il n'apparaît pas sur le site tant
  que vous ne l'avez pas publié : vous pouvez donc le préparer tranquillement.
- **Retirer du site** le sort du site sans rien perdre — utile pour un service
  en cours de rédaction. Il reste modifiable dans l'admin, et son adresse
  directe renvoie une page introuvable.
- **Supprimer** l'efface. **Les photos, elles, restent dans la médiathèque**, et
  la version précédente du fichier reste restaurable depuis l'Éditeur avancé :
  une suppression malencontreuse se rattrape.
- **Monter / descendre** change l'ordre d'affichage sur le site.

Un service ajouté apparaît automatiquement dans le sous-menu de la rubrique
« Nos services », en pied de page et sur la page d'accueil : il n'y a aucun
menu à mettre à jour à la main.

### Les photos

Toutes les images vivent dans l'écran **Photos**. Envoyez-les par lot : chacune
est redimensionnée, allégée et dotée d'une vignette automatiquement — inutile de
les préparer avant.

Chaque écran d'édition propose ensuite de choisir une photo dans cette
médiathèque, sur vignette plutôt que sur nom de fichier. Préférez des images
**en format paysage, 1600 px de large au minimum** pour les bandeaux : elles
occupent toute la largeur de l'écran.

L'écran Photos indique, sous chaque image, les pages qui l'utilisent. Supprimer
une photo encore employée demande une confirmation explicite : sans elle, la
page afficherait le visuel « photo à venir » sans que rien ne l'explique.

---

## 9. Choisir la disposition du menu

L'écran **Apparence** bascule la navigation entre deux dispositions, sans
toucher au contenu :

| Disposition | Ce que voit le visiteur |
|---|---|
| **Menu latéral (burger)** | Un bouton à trois barres ouvre un panneau qui glisse depuis la gauche. Le logo reste au centre de l'en-tête. |
| **Menu horizontal en haut** | Les rubriques sont affichées côte à côte dans l'en-tête, avec sous-menus déroulants. Le logo passe à gauche. |

Le changement est immédiat et réversible : les deux dispositions affichent les
mêmes rubriques, réglées dans *Coordonnées & menu*.

**Sur téléphone et petite tablette** (en dessous de 1080 px de large), la barre
horizontale ne tiendrait pas : le menu latéral reprend automatiquement la main,
quelle que soit la disposition choisie. C'est voulu, et il n'y a rien à régler
pour cela.

Ce réglage est enregistré dans `data/admin/parametres.json`, qui n'est pas
versionné : une mise à jour du code ne peut pas le remettre à sa valeur
d'origine.

---

## 10. Les avis Google

L'écran **Avis Google** affiche sur le site les avis déposés sur la fiche Google
de la société.

### Comment ça marche

Les avis sont récupérés **par le serveur**, puis conservés dans un fichier de
cache pendant douze heures. Conséquences, toutes voulues :

- le navigateur de vos visiteurs ne contacte **jamais** Google : aucun cookie
  tiers n'est déposé, et rien n'est à soumettre au consentement ;
- la page s'affiche à la même vitesse que si les avis étaient écrits en dur ;
- si Google devient injoignable, les derniers avis enregistrés restent
  affichés — et le site ne retente pas l'appel avant une demi-heure, pour ne
  pas ralentir les pages ;
- quand il n'y a rien à afficher, la section disparaît d'elle-même plutôt que
  de laisser un bloc vide.

Les photos de profil des auteurs ne sont volontairement pas reprises : elles
sont hébergées par Google, et les afficher rétablirait l'appel au domaine tiers
que tout le reste s'applique à éviter. Les initiales de l'auteur en tiennent
lieu.

### Obtenir la clé d'API

1. Ouvrez [console.cloud.google.com](https://console.cloud.google.com/) avec le
   compte Google de la société.
2. Créez un projet (ou reprenez-en un existant).
3. Dans *API et services → Bibliothèque*, activez **Places API (New)**.
   Attention : « Places API » sans « (New) » est l'ancienne interface, que ce
   site n'utilise pas.
4. Associez un compte de facturation au projet. Google l'exige même pour un
   usage gratuit ; le quota mensuel offert couvre très largement deux appels
   par jour.
5. Dans *API et services → Identifiants*, créez une **clé d'API**.
6. Restreignez-la : *Restrictions relatives aux API* → **Places API (New)**
   uniquement. Ne mettez **pas** de restriction par site web ou par adresse IP :
   l'appel part du serveur, pas du navigateur.

### Renseigner la société

Collez la clé dans l'écran **Avis Google**, puis :

- si vous connaissez l'identifiant de votre fiche (Place ID), saisissez-le ;
- sinon, utilisez la **recherche** en bas de l'écran : tapez « Mairie d’Angeot,
  Mathay », et cliquez sur *Utiliser cette fiche* dans les
  résultats. L'identifiant se reporte tout seul.

Cochez *Afficher les avis Google sur le site*, enregistrez : les avis sont
récupérés dans la foulée et l'écran affiche ce qui a été reçu.

**Note minimale affichée** : les avis en dessous du seuil ne sont pas repris sur
le site. Gardez à l'esprit que cela ne les efface pas de votre fiche Google
publique.

### Présentation

Trois réglages, dans le bloc *Présentation* du même écran :

| Réglage | Effet |
|---|---|
| **Temps de pause** | Secondes pendant lesquelles un avis reste affiché avant que le carrousel n'avance. Les avis défilent de la droite vers la gauche, puis reviennent au début d'un seul mouvement une fois le dernier atteint. **0 arrête le défilement automatique** ; les flèches, les pastilles et le glissement du doigt continuent de fonctionner. Hors 0, la valeur est ramenée entre 3 et 30 secondes. |
| **Date de parution** | Masque la date sous le nom de l'auteur. Utile quand les avis les plus élogieux datent un peu : la date reste visible sur votre fiche Google, mais n'attire plus l'œil sur le site. |
| **Nombre total d'avis** | Coché : « 4,8 ★★★★★ sur 27 avis Google ». Décoché : « 4,8 ★★★★★ avis Google » — plus aucun nombre, seule la provenance subsiste. Utile tant que les avis sont peu nombreux. |

Le défilement s'interrompt dès que le visiteur survole le carrousel, y place le
curseur au clavier ou y pose le doigt : un avis ne se dérobe jamais en cours de
lecture. Il s'arrête également quand l'onglet passe en arrière-plan, et ne
démarre pas du tout chez un visiteur ayant demandé à son système de réduire les
animations.

Le nombre de pastilles suit les positions réellement atteignables, et non le
nombre d'avis : avec quatre avis dont trois tiennent à l'écran, la piste ne
s'arrête qu'à deux endroits, donc deux pastilles.

### Si ça ne marche pas

L'écran affiche le motif exact du refus de Google, et ce qu'il faut corriger.
Les trois cas courants :

- *Clé d'API refusée* : clé mal recopiée, API « Places API (New) » non activée,
  ou facturation non associée au projet.
- *Fiche introuvable* : le Place ID ne correspond à aucun établissement.
  Utilisez la recherche pour le retrouver.
- *Quota dépassé* : rare avec un rafraîchissement toutes les douze heures. Les
  avis déjà enregistrés restent affichés.

---

## 11. Cookies et mesure d'audience

Un bandeau de consentement s'affiche à la première visite. Il suit les
recommandations de la CNIL : **refuser est aussi simple qu'accepter** — les deux
boutons ont le même poids visuel — et **rien n'est déposé avant un choix
explicite**. Fermer le panneau sans choisir ne vaut pas accord : le bandeau
revient.

Trois catégories :

| Catégorie | Contenu |
|---|---|
| **Nécessaires** | session, sécurité des formulaires, mémorisation du choix. Toujours actifs, non désactivables. |
| **Mesure d'audience** | le script de statistiques, chargé *seulement* après accord. |
| **Contenus externes** | plan d'accès, vidéos et autres contenus hébergés ailleurs. |

Le choix est conservé six mois dans un cookie de première partie. Le lien
**Gestion des cookies**, en bas de chaque page, rouvre le panneau à tout moment.

### Activer la mesure d'audience

*Paramètres → Mesure d'audience* : collez votre identifiant Google Analytics
(`G-XXXXXXXXXX`, dans *Administration → Flux de données*). Laissez le champ vide
et **aucun traceur n'est chargé**, quel que soit le choix du visiteur.

L'adresse IP est anonymisée. Le script est écrit dans la page en
`<script type="text/plain">` : il reste du texte inerte tant que le visiteur n'a
pas accepté la catégorie, et n'est activé qu'ensuite. Aucune requête ne part
vers Google avant cela.

### Ajouter plus tard un contenu externe

Pour une carte ou une vidéo, enveloppez-la ainsi — elle ne se chargera qu'avec
l'accord du visiteur, et affichera sinon un bouton qui lui laisse le choix :

```html
<div data-cookies-contenu="externes">
  <template><iframe src="https://..." title="Plan d'accès"></iframe></template>
  <div class="cookies-substitut">
    <p>Ce contenu est hébergé par un autre site.</p>
    <button class="btn btn--or" type="button" data-cookies-reglages>Autoriser</button>
  </div>
</div>
```

---

## 12. Site en plusieurs langues

Le français est la langue du site : c'est lui que vous éditez partout. Chaque
autre langue en est une traduction, servie sur sa propre adresse —
`votredomaine.fr/en/nos-services` pour l'anglais.

### Ajouter une langue

*Langues → Ajouter une langue*, saisissez son code (`en`, `de`, `nl`, `es`…).
La langue est créée **hors ligne** : personne ne la voit encore.

1. Ouvrez-la, cliquez **Traduire les N textes manquants**. Comptez une
   quarantaine de secondes pour l'ensemble du site.
2. **Relisez.** Une traduction automatique se trompe souvent sur les noms
   propres — « Angeot » ne doit pas être traduit. Corrigez
   directement dans les champs, puis *Enregistrer les traductions*.
3. Mettez la langue **en ligne**. Un sélecteur FR / EN apparaît alors dans
   l'en-tête, et le plan du site déclare les deux versions.

Un champ de traduction laissé vide affiche le texte français : une phrase
ajoutée plus tard côté français reste lisible, elle n'apparaît pas en blanc.
Relancez *Traduire les textes manquants* pour la rattraper — cette commande
ne touche jamais ce que vous avez corrigé à la main.

### Ce qu'il faut savoir sur la traduction automatique

Elle passe par le point d'entrée public de Google Traduction : **gratuit et
sans inscription**, mais non documenté par Google, il peut se limiter ou
changer sans préavis. C'est sans conséquence pour le site : la traduction
**n'a lieu qu'une fois**, au moment où vous cliquez, et le résultat est
enregistré sur le serveur. Les pages `/en` sont de vraies pages servies depuis
vos fichiers — aucun visiteur ne déclenche d'appel vers Google.

Si le bouton échoue un jour, deux recours : relancer plus tard, ou traduire à
la main dans les mêmes champs. Le site continue de fonctionner dans tous les cas.

### Référencement des langues

C'est automatique : chaque page déclare ses équivalents (`hreflang`), la
balise `canonical` pointe sur la bonne version, et `sitemap.xml` liste toutes
les adresses dans toutes les langues en ligne. Google comprend qu'il s'agit
d'une même page traduite, et non de doublons.

Les redirections restent dans la langue demandée : `/en/a-propos` mène à
`/en/la-societe`, pas à la version française.

### Retirer une langue

*Mettre hors ligne* la retire du site sans rien perdre : `/en` renvoie une page
introuvable, le sélecteur disparaît, les traductions restent. *Supprimer*
efface aussi les traductions.

---

## 13. Publier sur Facebook et Instagram

Le back-office peut publier sur la Page Facebook et le compte Instagram de la
commune, depuis l'écran **Réseaux sociaux**. Rien ne part sans qu'on l'ait
demandé, et tout part du serveur : aucun code de Meta n'est chargé sur le
site, aucun traceur n'est déposé chez les visiteurs.

Il y a un préalable, et il prend du temps : **Meta exige une application
déclarée, et une revue avant d'accorder la publication.** Comptez de quelques
jours à quelques semaines. Ce n'est pas un défaut du site, c'est la règle de
Meta pour tout le monde.

### Ce qu'il faut avoir

- une **Page Facebook** (pas un profil personnel) administrée par la mairie ;
- pour Instagram, un **compte professionnel ou créateur**, rattaché à cette
  Page. Un compte Instagram personnel ne peut pas publier par l'API, et
  l'écran le dira. La conversion se fait dans les réglages d'Instagram, en
  quelques minutes et sans perdre les publications existantes.

### Créer l'application Meta

1. Aller sur `developers.facebook.com`, se connecter avec le compte qui
   administre la Page, puis **Mes applications → Créer une application**.
2. Choisir le cas d'usage **« Autre »**, puis le type **« Entreprise »**.
3. Dans **Paramètres → Général**, relever l'**identifiant de l'application**
   et la **clé secrète**. Ce sont eux qu'on saisit dans l'écran Réseaux
   sociaux du back-office.
4. Ajouter le produit **« Connexion Facebook »**, puis, dans ses paramètres,
   coller dans **URI de redirection OAuth valides** l'adresse que l'écran
   Réseaux sociaux affiche — exactement, à la lettre. Elle ressemble à :

   ```
   https://mairie-angeot.fr/admin/reseaux/retour
   ```

   Une adresse qui diffère d'un caractère, ou en `http` au lieu de `https`,
   fait échouer la connexion avec un message de Meta peu bavard.

### Demander la revue

Dans **Vérification de l'application**, demander ces permissions :

| Permission | Ce qu'elle sert |
|---|---|
| `pages_show_list` | lister les Pages, pour en choisir une |
| `pages_read_engagement` | lire le nom de la Page et son compte lié |
| `pages_manage_posts` | publier sur la Page |
| `instagram_basic` | retrouver le compte Instagram de la Page |
| `instagram_content_publish` | publier sur ce compte |

Meta demande une capture ou une vidéo montrant l'usage. Filmez l'écran
Réseaux sociaux : la connexion, la rédaction d'un message, la publication.
C'est exactement ce qu'il veut voir.

**Vérifiez les noms de ces permissions le jour où vous faites la demande.**
Meta a renommé en 2025 les autorisations d'un *autre* parcours — celui où l'on
se connecte directement avec un compte Instagram, où `instagram_basic` est
devenu `instagram_business_basic`. Ce site n'emprunte pas ce parcours : il
passe par la connexion Facebook et par le jeton de la Page, pour lequel les
noms ci-dessus restent ceux en vigueur. Le doute est peu coûteux à lever et la
liste tient en une constante, `Reseaux::PERMISSIONS` : si l'écran de demande
ne reconnaît pas l'un de ces noms, c'est là qu'il faut le corriger.

**En attendant la revue, tout fonctionne déjà** pour les comptes déclarés
**testeurs** ou **administrateurs** de l'application (rubrique *Rôles*).
Ajoutez-y le compte de la personne qui gère la Page : elle pourra publier
avant même que la revue n'aboutisse.

### Connecter les comptes

Écran **Réseaux sociaux** du back-office :

1. saisir l'identifiant et la clé secrète, puis **Enregistrer l'application** ;
2. cliquer sur **Connecter Facebook** et accepter les autorisations ;
3. si le compte administre plusieurs Pages, choisir celle de la commune.

Le compte Instagram rattaché est détecté tout seul. S'il n'y en a pas, l'écran
le dit et seule la publication Facebook est proposée : ce n'est pas une panne.

### La tâche planifiée

Une publication programmée est retenue par le site, pas par Meta — Instagram
ne sait pas attendre une date. Il faut donc quelque chose qui la fasse partir
à l'heure. Dans cPanel, **Tâches Cron**, ajouter la ligne que l'écran Réseaux
sociaux affiche, du genre :

```
*/15 * * * *  wget -q -O /dev/null "https://mairie-angeot.fr/taches/reseaux?cle=…"
```

Cette adresse contient une clé qui vaut mot de passe : elle n'a pas à être
partagée, et elle ne fait rien d'autre que dépiler la file.

**Si le cron n'est pas réglé, rien n'est perdu** : les publications
programmées partent dès que quelqu'un ouvre le back-office, et l'écran affiche
combien étaient en retard. Mais elles partent en retard — le cron est là pour
ça.

### Ce qui peut échouer, et ce que ça veut dire

| Message | Ce qu'il faut faire |
|---|---|
| « L'application n'a pas encore la permission de publier » | La revue n'est pas accordée : ajoutez le compte comme testeur dans l'application, ou attendez la revue |
| « La connexion à Facebook a expiré ou été révoquée » | Reconnecter la Page depuis l'écran. Arrive si le mot de passe du compte a changé |
| « Instagram télécharge l'image lui-même » | L'image doit être accessible en HTTPS depuis l'extérieur. Une publication Instagram n'est pas essayable depuis un poste local |
| « Aucun compte Instagram professionnel » | Le compte Instagram n'est pas professionnel, ou pas rattaché à la Page |

Une publication qui échoue **reste dans la file** et est réessayée, en
espaçant les essais : cinq minutes, puis trente, puis deux heures. Au bout de
quatre essais elle passe au journal, marquée en échec avec son motif : rien ne
disparaît en silence.

**Un seul réseau en échec ne fait pas tout échouer.** Si Facebook accepte et
qu'Instagram refuse, la publication Facebook reste faite, et seule celle
d'Instagram retourne en file. Le message de l'écran le dit, et le journal
n'inscrit la publication comme réussie que lorsque les deux sont partis.

**Deux dépilages ne peuvent pas se chevaucher.** Le cron et l'ouverture du
back-office peuvent tomber à la même seconde ; un verrou fait que le second
s'en va sans rien faire, et l'adresse du cron répond alors simplement
`occupe`. Ce n'est pas une erreur : c'est que l'autre travaille.

### Une sécurité de plus à activer chez Meta

Dans **Paramètres → Avancé** de l'application, l'option **« Exiger la preuve
du secret de l'application »** (*Require App Secret*) peut être activée : le
site signe déjà chacun de ses appels avec le secret, comme Meta le
recommande. Une fois l'option active, un jeton recopié ne suffit plus à
publier sur la Page — il faut aussi le secret, qui ne quitte jamais le
serveur.

---

## 14. Points de vigilance

**HTTPS** — le `.htaccess` force la redirection vers HTTPS. Activez le
certificat SSL gratuit dans *cPanel → SSL/TLS Status* avant la mise en ligne,
sinon vous obtiendrez une boucle de redirection.

**Sauvegarde** — copiez `data/` et `public/assets/img/site/` régulièrement.
C'est tout le site. o2switch fait aussi des sauvegardes automatiques via
JetBackup.

**Ne modifiez pas les fichiers JSON à la main sur le serveur** si le
back-office peut le faire : il crée une sauvegarde à chaque enregistrement,
pas vous. Si vous devez vraiment intervenir, passez par *Éditeur avancé* —
il valide le JSON avant d'écrire.

**`data/admin/`** contient le compte et le mot de passe SMTP. Ce dossier est
exclu de git et ne doit jamais être partagé ni versionné.

---

## 15. Tester en local avant d'envoyer

```bash
php -S localhost:8080 -t public public/index.php
```

Puis `http://localhost:8080`. La redirection HTTPS du `.htaccess` ne
s'applique pas au serveur intégré de PHP, et `localhost` en est de toute
façon exempté.

---

## 16. Mettre à jour le site par FTP

Une fois le site en ligne, deux natures de fichiers cohabitent : le **code**,
qui vient du dépôt, et l'**état vivant** du site, qui n'existe que sur le
serveur. La règle tient en une phrase :

> Le dépôt fait foi pour le code, le serveur fait foi pour le contenu.

Cette règle n'est pas qu'une consigne : **le dépôt ne contient aucun fichier
portant le même chemin que le contenu du client.** Le contenu livré vit dans
`data-modele/`, et n'est recopié dans `data/` que pour un fichier qui n'y
existe pas encore. Un transfert, même intégral et maladroit, ne peut donc
pas écraser ce que le client a saisi : les photos du diaporama, les textes,
les réalisations restent en place.

### À ne jamais écraser

| Chemin | Contenu |
|---|---|
| `data/admin/` | Compte administrateur et mot de passe SMTP |
| `data/*.json` et `data/pages/*.json` | Tout le contenu édité au back-office (absents du dépôt) |
| `public/assets/img/site/` | Photos envoyées au back-office |
| `storage/` | Sauvegardes de contenu et compteur anti-force-brute |

Écraser `data/admin/` fait perdre l'accès au back-office **et** les réglages
d'envoi. Écraser `data/` remet le contenu dans son état d'origine et annule
tout ce que le client a saisi.

### À transférer à chaque mise à jour

Plutôt que de cocher les bons dossiers dans FileZilla à chaque fois — une
case de trop sur `data/` et le contenu du client repart à zéro — fabriquez
le colis, puis transférez-le en entier :

```
php outils/paquet-maj.php
```

Le script crée un dossier `paquet-maj/` ne contenant que le code. Vous
transférez son **contenu** à la racine du site, en écrasant, sans plus rien
avoir à sélectionner. `data/`, `public/assets/img/site/` et `storage/` n'y
figurent pas, donc restent intacts sur le serveur.

Il travaille à partir de la même liste que la mise à jour automatique du
back-office (`Deploiement::CODE`) : les deux voies transfèrent exactement
les mêmes chemins, et cette liste ne peut pas dériver de la documentation.

Si vous préférez sélectionner à la main, voici cette liste :

```
app/                         public/assets/img/logo/
config/                      public/assets/img/ui/
views/                       data/.htaccess
public/index.php             data/assistant/.htaccess
public/.htaccess             storage/.htaccess
public/assets/css/           README.md
public/assets/js/            DEPLOIEMENT.md
public/assets/fonts/
```

Les trois `.htaccess` sont des fichiers de sécurité, pas du contenu : ils
interdisent l'accès direct aux dossiers qui les portent.

### Avant chaque mise à jour

Téléchargez `data/` en local — quelques centaines de kilo-octets, et c'est
la totalité du contenu du site. Un transfert interrompu au mauvais moment
devient alors sans conséquence.

### Quand une version change la structure du contenu

Si une évolution ajoute une page ou un champ dans les fichiers JSON, ne
transférez pas `data/` pour autant : ajoutez la clé manquante depuis
*Éditeur avancé*, qui valide le JSON avant d'écrire et sauvegarde la version
précédente. Ces cas sont signalés au moment de la livraison.

### Après le transfert

Videz le cache de votre navigateur, ou ouvrez le site en navigation privée :
les CSS et JS portent une empreinte de version, mais les gabarits peuvent
rester en cache côté navigateur. Puis vérifiez l'écran *Paramètres* — le
diagnostic confirme que les droits d'écriture n'ont pas bougé.

---

## 17. Mises à jour automatiques par git (recommandé)

Le FTP de la section 16 reste possible, mais l'écran **Mises à jour** du
back-office fait la même chose sans risque d'erreur de manipulation : il ne
peut pas toucher au contenu, parce qu'il ne remplace qu'une liste fermée de
chemins de code.

### Installer le dépôt une fois

Cette étape se fait en SSH (o2switch fournit un accès SSH dans cPanel), ou
via *cPanel → Git™ Version Control*. Deux situations selon que le site est
déjà installé ou non.

**Cas 1 — le site n'est pas encore en place.** C'est le cas le plus simple :
tout vient du dépôt, en une commande.

```bash
cd ~
git clone --branch claude/redevelop-chapelle-website-xsnkxd \
  https://github.com/ftholomier/poubelle.git angeot
cd angeot
mkdir -p storage/cache data/admin
```

Puis pointez la racine du document du domaine sur `angeot/public`
(section 1).

**Cas 2 — le site est déjà en place** (envoyé par FTP). On greffe le dépôt
par dessus, sans toucher au contenu. Les commandes sont **enchaînées par
`&&`** à dessein : si le dossier n'existe pas ou n'est pas celui du site,
rien ne s'exécute.

```bash
cd ~/angeot && test -f app/bootstrap.php && \
git clone --branch claude/redevelop-chapelle-website-xsnkxd \
  https://github.com/ftholomier/poubelle.git depot-temporaire && \
mv depot-temporaire/.git . && rm -rf depot-temporaire && \
git checkout -- app config views tools public/index.php public/.htaccess \
  public/assets/css public/assets/js public/assets/fonts \
  public/assets/img/logo public/assets/img/ui \
  data/.htaccess storage/.htaccess README.md DEPLOIEMENT.md
```

> Cette liste est exactement celle de `Deploiement::CODE`, que l'écran
> *Mises à jour* applique ensuite. Pour l'obtenir sans risque de la recopier
> de travers :
> `php -r 'require "app/bootstrap.php"; echo implode(" ", App\Core\Deploiement::cheminsCode()), "\n";'`

La dernière commande aligne le code sur le dépôt. Elle liste explicitement
les chemins de code : `data/` et `public/assets/img/site/` n'y figurent pas,
donc le contenu déjà saisi et les photos restent intacts.

> **Ne lancez jamais ces commandes depuis votre répertoire personnel.**
> Sans le garde-fou ci-dessus, un `cd` qui échoue laisse les commandes
> suivantes s'exécuter dans `~` : votre répertoire personnel devient un
> dépôt git et se retrouve rempli des fichiers du site. Voir *Réparer une
> installation dans le mauvais dossier* en fin de section.

> **L'adresse du dépôt est celle qui finit par `.git`**, pas celle affichée
> dans la barre du navigateur. Une adresse en `/tree/<branche>` sert à
> naviguer sur GitHub et n'est pas clonable.

Le contenu déjà saisi reste intact ; git le verra simplement comme
« modifié » par rapport au dépôt, ce qui est le comportement attendu et
n'a aucune conséquence.

**Dépôt public** : rien de plus à faire, le serveur clone directement.
Les secrets ne sont de toute façon pas dans le dépôt — `data/admin/`, qui
contient le mot de passe SMTP et l'empreinte du compte, en est exclu.

**Dépôt privé** : le serveur doit pouvoir s'authentifier. Deux options, au
choix — une clé SSH de déploiement (`ssh-keygen` sur le serveur, clé publique
ajoutée en *Deploy key* côté dépôt), ou une adresse HTTPS contenant un jeton
d'accès en lecture seule. L'écran affiche l'adresse du dépôt sans le jeton.

### Utiliser

1. **Mises à jour → Vérifier les mises à jour** : le site interroge le dépôt
   et liste ce qui n'est pas encore installé, avec la description de chaque
   changement.
2. **Appliquer la mise à jour** : une archive du contenu et des photos est
   créée, puis seuls les fichiers de code sont remplacés.

### Ce que la mise à jour remplace, et ce qu'elle ne touche jamais

| Remplacé | Jamais touché |
|---|---|
| `app/` `config/` `views/` `tools/` | `data/` (tout le contenu) |
| `public/index.php` `public/.htaccess` | `data/admin/` (compte, réglages SMTP) |
| `public/assets/css` `js` `fonts` `img/logo` | `public/assets/img/site/` (photos) |
| `data/.htaccess` `storage/.htaccess` | `storage/` (sauvegardes) |
| `README.md` `DEPLOIEMENT.md` | |

`data/.htaccess` et `storage/.htaccess` sont dans la liste des fichiers
remplacés : ce sont des protections, pas du contenu.

Si une version modifie aussi des fichiers de contenu dans le dépôt, l'écran
le signale avant d'appliquer — ces modifications sont **ignorées**, et le
champ éventuellement attendu s'ajoute depuis l'Éditeur avancé.

### Revenir en arrière

Chaque mise à jour archive `data/` et les photos dans
`storage/deploiements/` (les 10 dernières sont conservées). Le bouton
**Restaurer** remet le contenu de l'archive choisie, sans toucher au code —
et archive l'état courant au passage.

Pour revenir en arrière sur le **code**, il suffit de repointer le dépôt sur
la version précédente en SSH (`git checkout <référence>`), le contenu restant
de toute façon hors de portée de git.

### Si l'écran affiche un blocage

- *proc_open désactivée* : l'hébergement interdit l'exécution de commandes.
  Rien à faire côté site, il faut passer par FTP.
- *git introuvable* : demander l'activation à l'hébergeur.
- *dossier .git absent* : le site a été installé par FTP, suivre la marche à
  suivre affichée à l'écran.
- *authentification refusée* : dépôt privé sans clé ni jeton, voir plus haut.

---

## 18. Droits d'accès

Trois choses défont les droits sans prévenir : un transfert FTP (qui applique
les réglages du client FTP), le `umask` du serveur (qui rabote les droits des
fichiers créés par PHP), et git (qui ne mémorise que le bit exécutable).

Le site s'en occupe seul :

- Les fichiers écrits par le back-office reçoivent leurs droits
  explicitement, quel que soit le `umask` du serveur.
- Chaque mise à jour réaligne les droits des fichiers récupérés.
- Les archives de sauvegarde sont en `640` : elles contiennent `data/admin/`,
  donc le mot de passe SMTP.

### Les cibles

| Élément | Droits |
|---|---|
| Dossiers | `755` |
| Fichiers | `644` |
| `data/admin/`, archives de `storage/deploiements/` | `640` |
| Scripts exécutables | `755` |

### En cas de doute

*Paramètres → Droits d'accès* liste les anomalies détectées :

- accessible en écriture à tout le monde (`777`, `666`…)
- fichier sensible lisible par tout le monde
- dossier qui devrait être inscriptible et ne l'est pas

Le bouton **Réparer les droits** remet tout l'arbre aux valeurs ci-dessus. Si
des refus apparaissent, c'est que les fichiers concernés appartiennent à un
autre compte système — dans ce cas seulement, il faut passer par SSH.

### Réparer une installation dans le mauvais dossier

Si les commandes ont été lancées depuis `~`, le répertoire personnel contient
maintenant un dossier `.git` et une partie des fichiers du site. Le site n'y
fonctionne pas — le contenu et les photos manquent — et le dépôt git à cet
endroit est gênant : toute commande git lancée depuis `~` par la suite
s'appliquerait à l'ensemble du répertoire personnel.

Vérifiez d'abord ce qui s'y trouve :

```bash
cd ~ && ls -la
```

Vous devez y voir `app`, `config`, `data`, `public`, `storage`, `tools`,
`views` et `.git`, à côté de vos dossiers habituels (`public_html`, `mail`,
`etc`, `logs`…). **Ces sept dossiers et le `.git` sont les seuls à retirer** —
ne touchez à rien d'autre :

```bash
cd ~ && rm -rf .git app config data public storage tools views
```

Reprenez ensuite au **cas 1** ci-dessus.
