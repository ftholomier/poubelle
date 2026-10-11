# Projet « Musée en ligne » : un musée pour chaque club

Ce document résume les échanges d'octobre 2026 sur l'idée de proposer le musée du FCSM à d'autres clubs, de foot ou d'autres sports.
Rien n'est encore développé : c'est la feuille de route.

## 1. L'idée

On réutilise le code du musée de Sochaux pour créer d'autres musées : un par club, de football ou d'un autre sport.
Le moteur est déjà générique : fiches, index, recherche, médiathèque, back-office, imports, audio, compositions, boutique.
Ce qui est propre à Sochaux, c'est l'habillage et les contenus.

### Ce qui est lié à Sochaux dans le code aujourd'hui

Mesures faites sur 443 fichiers PHP :

| Terme | Occurrences | Fichiers |
|---|---|---|
| « Sochaux » | environ 1 200 | 200 |
| « FCSM » | 330 | 102 |
| « Bonal » | 316 | 63 |
| « Lion » | 298 | 93 |
| « 1928 » | 137 | 61 |
| « Peugeot » | 75 | 20 |

S'y ajoutent :
- les identifiants Transfermarkt (750) et footballdatabase (19-sochaux), écrits en dur dans `Compos.php` ;
- les couleurs, le logo et la typographie dans `site.css` ;
- des contenus rédigés en dur : les 100 moments, la frise, le centenaire, le teaser et la boutique.

## 2. L'architecture retenue

**Une seule installation pour tous les clubs.** Un seul code, et un dossier de données par club (`clubs/sochaux/`, `clubs/xxx/`…). Le club est reconnu au nom de domaine.

**Un back-office commun :**
- un sélecteur de club en haut de l'admin, pour passer d'un musée à l'autre sans se reconnecter ;
- trois rôles :
  - **super-admin** (nous) : voit tous les clubs, crée un nouveau musée, fait les mises à jour une fois pour tous ;
  - **admin de club** : ne voit que son musée ;
  - **historien** ou **bénévole** du club ;
- des données strictement séparées : sauvegarde, déménagement ou suppression d'un club seul ;
- un tableau de bord commun de l'activité de tous les musées.

## 3. Le chantier technique

| Étape | Contenu | Durée estimée |
|---|---|---|
| 1. Profil club | `club.json` : noms, sigle, fondation, stade, ville, mascotte, couleurs, logo, identifiants des sources, réseaux, boutique. Les textes en dur sont remplacés par `Club::get()` | ~1 semaine |
| 2. Thème | Couleurs en variables CSS générées depuis le profil, logo et typographie interchangeables | 2-3 jours |
| 3. Sport | Un fichier de règles par sport : postes, effectif, format du score (essais, quart-temps…), compétitions, vocabulaire | 1-3 semaines selon le sport |
| 4. Multi-clubs | Dossiers par club, choix du club au domaine, sélecteur et rôles dans l'admin | 2-3 jours |
| 5. Démarrage d'un musée | Assistant de création, imports (WordPress, CSV, compositions Transfermarkt du club), modules éditoriaux optionnels | ~1 semaine |

Il faut compter **3 à 4 semaines pour un deuxième club de foot**, puis 1 à 3 semaines de plus pour un autre sport.
Le musée de Sochaux continue de tourner pendant ce temps : il devient « le profil Sochaux ».

## 4. Tout s'adapte au club, y compris la boutique

Tout ce qui est dynamique se règle sur le profil du club.

**La boutique** propose les mêmes produits, mais avec le logo, les couleurs et le nom du nouveau musée :
- le moteur vectoriel, les modèles, les posters match et joueur, la carte du carnet et les PDF HD prennent l'identité du club ;
- la banque de textes est propre à chaque club ;
- les prix, l'imprimeur et le compte Stripe peuvent être propres à chaque club, ou communs.

**Les autres modules s'adaptent de la même façon** :
- Rétro-Direct, Ce jour-là, Il y a N ans, Chiffre du jour ;
- les 100 moments, les murs de photos, les notifications, l'appli (PWA : nom et icônes du club) ;
- la newsletter, la page d'attente et les teasers.

Ils sont activables ou désactivables par club.

## 5. Le remplissage automatique d'un nouveau musée (étape 2)

L'objectif est d'éviter de fouiller le web et les archives à la main. Le musée est créé vide, puis rempli automatiquement.

**Les sources du texte :**
1. **les sites que tu fournis**, aspirés comme l'ancien WordPress de Sochaux : le site du club, mais aussi d'autres sites (supporters, historiens, anciens, presse locale, associations…) ;
2. **une recherche autonome sur le web**, sans liste fournie, pour compléter ce qui manque : presse ancienne (Gallica, Retronews en accès libre), Wikipédia, Transfermarkt, footballdatabase, sites de statistiques, archives municipales et départementales en ligne, blogs d'historiens. Le service Trouvailles existe déjà ;
3. **des trous comblés en priorité** : saisons sans fiche, matchs sans compo ou sans score, joueurs sans biographie. Le back-office liste ce qui manque encore ;
4. **la vérification croisée des faits** (dates, scores, compositions) entre ces sources. Un fait trouvé sur une seule source est marqué « à confirmer ».

**Les images sont obligatoirement libres de droits**, même si on en trouve peu :
- seulement le domaine public, Wikimedia Commons sous licence CC (avec le crédit affiché), Gallica libre, ou les photos fournies par le club ;
- la licence et l'auteur sont enregistrés pour chaque image, et rien n'est importé sans licence vérifiée ;
- sinon, la photo par défaut du musée est utilisée.

**Une réécriture complète**, pour ne pas être de la simple copie :
- chaque fiche est entièrement rédigée à neuf : titre, chapô, paragraphes, intertitres et mise en page propres au musée ;
- seuls les faits sont repris (les faits ne sont protégés par aucun droit), jamais la formulation ;
- un contrôle de similarité avec la source bloque toute phrase trop proche ; il existe déjà pour FCSM Story ;
- un style naturel et varié : phrases de longueurs inégales, vocabulaire du club, anecdotes, pas de tournures répétitives. Le but est un score de détection « IA » le plus bas possible, mais aucun outil ne peut garantir un score donné, car ces détecteurs sont peu fiables ;
- les sources sont conservées en interne, et affichées en bibliographie si on le souhaite. C'est ce qui protège en cas de question.

**La relecture humaine :** les fiches arrivent « à relire » dans le back-office, comme les premiers jets des 100 moments. Rien n'est publié sans validation.

**Ce qu'il faut respecter :**
- les sites fournis ou trouvés servent de sources de faits : on ne reprend ni leurs textes ni leurs photos ;
- on respecte les sites qui interdisent l'aspiration ;
- les données personnelles (anciens joueurs encore vivants) restent limitées à leur carrière sportive.

## 6. La charge serveur

Mesures faites sur Sochaux (copie locale, 2 950 fiches) :

| Poste | Taille | Fichiers |
|---|---|---|
| Photos d'origine | 4,9 Go | 12 700 |
| Photos redimensionnées (7 tailles) | 2,4 Go | 41 000 |
| Cache des pages | 1,4 Go | 4 700 |
| Compositions (sources) | 0,5 Go | 3 600 |
| Archives d'import | 0,35 Go | 3 000 |
| Fiches | 85 Mo | 5 900 |

Temps de calcul : index 12,6 s, données calculées 4,2 s, recherche 2,4 s.

Sur le serveur o2switch (statistiques cPanel) :
- disque 5,65 Go, illimité ;
- mémoire 337 Mo sur 48 Go ;
- processeur 0 %, 7 processus sur 400 ;
- bande passante illimitée ;
- pas de plafond de fichiers (inodes) affiché.

**Conclusion :**
- un musée comme Sochaux, c'est environ 6 Go sur le serveur ;
- l'offre o2switch actuelle suffit pour plusieurs musées ;
- il faut décaler les tâches cron de chaque club ;
- on passera à un VPS seulement en cas de gros trafic.

**LSCache :** à ne pas activer pour l'instant. Le musée a son propre cache, et le plugin est fait pour les CMS. Plus tard, c'est le code du musée qui le pilotera :
- les pages publiques sont mises en cache ;
- l'admin ne l'est jamais ;
- le cache d'une fiche est vidé à son enregistrement ;
- les blocs du jour sont rafraîchis à minuit.

On récupère aussi environ 3 Go sans rien perdre :
- vider le cache plus souvent ;
- supprimer les archives d'import ;
- créer la taille 1600 px à la demande.

## 7. Le modèle : un SaaS « musée en ligne »

**1. Service géré** (pour commencer) :
- nous créons, importons et paramétrons le musée du club ;
- le club paie une installation et un abonnement ;
- c'est possible juste après le chantier technique.

**2. SaaS en libre-service** (plus tard, s'il y a la demande) :
- inscription en ligne, abonnements Stripe, essai gratuit et suspension en cas d'impayé ;
- imports simples et aide à la prise en main ;
- CGV et contrat RGPD (nous devenons sous-traitant des données du club) ;
- 2 à 3 mois de plus. Ça vaut le coup à partir de 5 à 10 clubs intéressés.

Revenus possibles : abonnement, commission sur les ventes de la boutique de chaque club, installation et import.

## 8. Prochaines étapes

1. Le chantier multi-clubs : profil club, thème, back-office commun.
2. Le remplissage automatique (étape 2 ci-dessus).
3. Un club pilote, idéalement d'un autre sport.
4. Une présentation commerciale de l'offre pour démarcher.
5. Le libre-service, si la demande est là.

## 9. Premier club : Paris Rétro

- Le nom retenu est **Paris Rétro**, sur le domaine `parisretro.com`. « PSG » est une marque renommée, risquée avec une boutique.
- Le sous-titre est descriptif : « le musée rétro des supporters du Paris Saint-Germain ». Le pied de page porte la mention « site non officiel, sans lien avec le club ».
- Le logo est dans `docs/clubs/logo-paris-retro.svg` : même écusson que Sochaux rétro, tour Eiffel, 1970. Il est généré par `docs/clubs/psg.py`.
- En attendant le domaine, le musée est consultable sur un sous-domaine technique (ex. `paris.pafl1370.odns.fr`), fermé par la page d'attente.

### Les étapes de développement

| # | Étape | Résultat visible | Durée |
|---|---|---|---|
| 1 | Profil club et extraction des textes « Sochaux » | Sochaux identique, mais piloté par `club.json` | 1 sem. |
| 2 | Thème (couleurs, logo, polices) | Paris Rétro en bleu, rouge et blanc | 2-3 j |
| 3 | Multi-clubs (dossiers par club, choix au domaine) | Les deux musées tournent sur le même code | 2-3 j |
| 4 | Back-office commun (sélecteur, rôles, super-admin) | Passage d'un musée à l'autre en un clic | 3-4 j |
| 5 | Boutique par club (logo, textes, Stripe, imprimeur) | Boutique Paris Rétro | 2-3 j |
| 6 | Assistant de création d'un musée | Paris Rétro créé vide | 2 j |
| 7 | Remplissage automatique (sites fournis, web, images libres, réécriture) | Fiches « à relire » dans le back-office | 1-2 sem. |
| 8 | Relecture, mise en ligne, `parisretro.com` | Ouverture | selon la relecture |
