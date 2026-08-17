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
├── etangfourchu/         ← le projet complet
│   ├── app/
│   ├── config/
│   ├── data/
│   ├── public/           ← c'est CE dossier qui doit être la racine web
│   ├── storage/
│   └── views/
```

Puis dans **cPanel → Domaines** (ou *Domaines additionnels* / *Sous-domaines*
selon le cas), modifiez la **racine du document** du domaine et pointez-la
sur `etangfourchu/public`.

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
- [ ] Une fiche hébergement et une fiche étang s'ouvrent
- [ ] Les photos s'affichent
- [ ] Les boutons *Réserver* ouvrent bien Reservit
- [ ] Le formulaire de contact envoie un message qui arrive
- [ ] `https://votredomaine.fr/data/site.json` renvoie une **erreur 403**
- [ ] `/admin` demande une connexion
- [ ] Une modification dans le back-office apparaît sur le site
- [ ] `/sitemap.xml` liste bien les pages, `/robots.txt` s'affiche

---

## 7. Référencement

L'écran **Référencement** du back-office regroupe tout ce que Google lit.

**Adresses des pages.** Chaque page a son slug — la partie de l'adresse après
le nom de domaine. Écrivez-le en toutes lettres : « Hébergements Territoire de
Belfort » est enregistré comme `hebergements-territoire-de-belfort`. Accents,
majuscules, espaces, ponctuation et apostrophes sont convertis, et une adresse
complète collée depuis le navigateur est ramenée à son chemin. Un aperçu
affiche le résultat pendant la frappe, et le message de confirmation rappelle
l'adresse retenue.

Le modifier crée automatiquement une **redirection
permanente (301)** depuis l'ancienne adresse, réécrit les liens du menu et des
blocs d'accueil, et fait suivre les sous-pages : renommer `/hebergements` en
`/nos-hebergements` redirige aussi `/hebergements/le-gite`. Aucun lien déjà
partagé ou indexé ne se casse. Les fiches (hébergements, étangs) ont la même
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
le domaine, l'hébergement affiché avec son tarif et sa capacité, et le fil
d'Ariane.

**À faire une fois le site en ligne :** déclarez `https://votredomaine.fr/sitemap.xml`
dans [Google Search Console](https://search.google.com/search-console) — c'est
ce qui accélère la prise en compte du nouveau site et remonte les erreurs
d'exploration.

---

## 8. Ajouter, masquer, supprimer

Trois listes se gèrent de la même façon, chacune depuis son écran :

| Écran | Ce que vous pilotez |
|---|---|
| **Accueil** → *Photos du bandeau* | les photos du diaporama d'accueil |
| **Hébergements** | les fiches d'hébergement |
| **Boutique** | les produits en vente directe |

Partout, la même logique :

- **Ajouter** crée l'élément **hors ligne**. Il n'apparaît pas sur le site tant
  que vous ne l'avez pas mis en ligne : vous pouvez donc le préparer
  tranquillement. (Une photo ajoutée au bandeau, elle, est visible aussitôt.)
- **Masquer / mettre hors ligne** le retire du site sans rien perdre — utile
  pour un produit de saison ou un hébergement en travaux. Il reste modifiable
  dans l'admin, et son adresse directe renvoie une page introuvable.
- **Supprimer** l'efface définitivement. **Les photos, elles, restent dans la
  médiathèque** : rien n'est perdu côté images.
- **Monter / descendre** change l'ordre d'affichage sur le site.

Deux garde-fous vous empêchent de vider une page par mégarde : le bandeau
d'accueil garde toujours au moins une photo affichée, et sa dernière photo ne
peut pas être retirée — remplacez-la plutôt.

### Le diaporama du bandeau

Avec une seule photo, le bandeau se comporte comme avant : la photo s'affiche
avec un lent mouvement d'approche. Dès la deuxième, il devient un diaporama —
fondu enchaîné, mouvement d'approche sur chaque photo, et des repères cliquables
sous le bandeau.

**Ordre des photos** : glissez les vignettes pour les réordonner. Les flèches
↑ ↓ font la même chose, au clavier ou sans JavaScript.

**Temps d'affichage** : réglable de 3 à 30 secondes dans *Bandeau principal*.
Le mouvement d'approche s'ajuste tout seul — il garde la même vitesse quel que
soit le temps choisi, et se termine exactement quand la photo s'efface.

Préférez des photos **en format paysage, 1920 px de large au minimum** : elles
occupent tout l'écran. Le site les redimensionne et les optimise à l'envoi.

Le diaporama s'arrête de lui-même quand le visiteur a demandé à son système de
réduire les animations, et la première photo reste affichée si JavaScript est
indisponible : la page ne se casse dans aucun cas.

---

## 9. Cookies et mesure d'audience

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

## 10. Points de vigilance

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

## 11. Tester en local avant d'envoyer

```bash
php -S localhost:8080 -t public public/index.php
```

Puis `http://localhost:8080`. La redirection HTTPS du `.htaccess` ne
s'applique pas au serveur intégré de PHP, et `localhost` en est de toute
façon exempté.

---

## 12. Mettre à jour le site par FTP

Une fois le site en ligne, deux natures de fichiers cohabitent : le **code**,
qui vient du dépôt, et l'**état vivant** du site, qui n'existe que sur le
serveur. La règle tient en une phrase :

> Le dépôt fait foi pour le code, le serveur fait foi pour le contenu.

### À ne jamais écraser

| Chemin | Contenu |
|---|---|
| `data/admin/` | Compte administrateur et mot de passe SMTP |
| `data/*.json` et `data/pages/*.json` | Tout le contenu édité au back-office |
| `public/assets/img/site/` | Photos envoyées au back-office |
| `storage/` | Sauvegardes de contenu et compteur anti-force-brute |

Écraser `data/admin/` fait perdre l'accès au back-office **et** les réglages
d'envoi. Écraser `data/` remet le contenu dans son état d'origine et annule
tout ce que le client a saisi.

### À transférer à chaque mise à jour

```
app/
config/
views/
public/index.php
public/.htaccess
public/assets/css/
public/assets/js/
public/assets/fonts/
public/assets/img/logo/
```

Dans FileZilla, sélectionnez ces éléments uniquement. `data/`,
`public/assets/img/site/` et `storage/` restent intacts sur le serveur.

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

## 13. Mises à jour automatiques par git (recommandé)

Le FTP de la section 9 reste possible, mais l'écran **Mises à jour** du
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
git clone --branch claude/web-address-analysis-lw79mp \
  https://github.com/ftholomier/poubelle.git etangfourchu
cd etangfourchu
mkdir -p storage/cache data/admin
```

Puis pointez la racine du document du domaine sur `etangfourchu/public`
(section 1).

**Cas 2 — le site est déjà en place** (envoyé par FTP). On greffe le dépôt
par dessus, sans toucher au contenu. Les commandes sont **enchaînées par
`&&`** à dessein : si le dossier n'existe pas ou n'est pas celui du site,
rien ne s'exécute.

```bash
cd ~/etangfourchu && test -f app/bootstrap.php && \
git clone --branch claude/web-address-analysis-lw79mp \
  https://github.com/ftholomier/poubelle.git depot-temporaire && \
mv depot-temporaire/.git . && rm -rf depot-temporaire && \
git checkout -- app config views tools public/index.php public/.htaccess \
  public/assets/css public/assets/js public/assets/fonts public/assets/img/logo \
  data/.htaccess storage/.htaccess
```

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

## 14. Droits d'accès

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
