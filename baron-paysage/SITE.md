# Baron Paysage — le site

Refonte du site `jardins-baron.com`, sous la nouvelle identité **Baron
Paysage** (charte de novembre 2025). Textes et photographies repris du site
précédent, revus selon les demandes du client et étoffés là où le contenu
manquait.

Bâti sur le socle décrit dans **[KIT.md](KIT.md)** — architecture et
conventions inchangées.

---

## Pages

```
/                                   Accueil
/a-propos                           Benjamin Baron et l'entreprise
/nos-prestations                    Les quatre métiers
/nos-prestations/conception         Conception
/nos-prestations/amenagement-paysager   Aménagement paysager
/nos-prestations/entretien          Entretien
/nos-prestations/elagage-taille     Élagage & taille
/realisations                       Galerie filtrable, 36 photos légendées
/nos-valeurs                        Créativité · Écoute · Exigence
/faq                                Onze questions fréquentes
/demander-un-devis                  Le formulaire
/contact                            Les deux implantations et leurs plans
/mentions-legales
```

**Conception et Entretien étaient des pages fixes** sur le site précédent ;
ce sont maintenant des fiches de la collection « prestations ». Le client
peut donc en ajouter une, la renommer ou la dépublier depuis le back-office :
elle apparaît alors d'elle-même dans le menu, dans le pied de page, sur la
page d'accueil et dans les filtres de la galerie.

**Devis et contact sont deux pages**, comme sur le site précédent. Elles ne
répondent pas à la même intention — trouver une adresse d'un côté, engager un
projet de l'autre — et les mêler dilue la page qui convertit. Le formulaire
vit donc sur `/demander-un-devis`, et `/contact` porte les coordonnées et les
deux plans d'accès.

### Anciennes adresses

Les huit adresses du site WordPress précédent sont redirigées en 301 vers
leur équivalent, plus une douzaine d'adresses courtes plausibles au clavier
(`/entretien`, `/devis`, `/conception`…). Elles sont listées dans
`app/routes.php` ; les slugs restent modifiables depuis l'écran
**Référencement**, qui crée alors la redirection correspondante.

---

## Charte

Reprise du document *Charte graphique BARON — Novembre 2025*, sans écart.

| Rôle | Jeton | Valeur |
|---|---|---|
| Ardoise de la marque — fonds sombres, titres, texte courant | `--ardoise` `--encre` | `#24363F` |
| Vert de la marque — filets, pictos, chiffres, accents | `--vert` | `#689B71` |
| Aplats portant du texte clair (boutons) | `--vert-fonce` | `#4E7A58` |
| Petit texte vert sur crème (sur-titres, liens) | `--vert-texte` | `#3F6449` |
| Accents posés sur l'ardoise | `--vert-clair` | `#8FBE99` |
| Crème des sections teintées | `--fond-teinte` | `#F6F4EE` |
| Titrage et texte courant | | Montserrat, auto-hébergée |

Les deux couleurs de la charte sont utilisées telles quelles partout où elles
peuvent l'être. Une seule contrainte les suit : **le vert `#689B71` ne tient
que 3,2:1 sur blanc et 3,9:1 sur l'ardoise** — assez pour un aplat, un filet
ou un grand titre, jamais pour du texte courant. Trois teintes en dérivent, à
teinte constante, chacune mesurée pour son usage : `--vert-fonce` porte du
blanc à 4,9:1, `--vert-texte` se lit sur le crème à 6,1:1, `--vert-clair` sur
l'ardoise à 6,0:1. Pour un vert strictement uniforme au prix de la
lisibilité, il suffit d'écrire `#689B71` dans ces trois jetons, en tête de
`public/assets/css/site.css`.

**Une seule police**, Montserrat, comme le prévoit la charte. La hiérarchie
vient de la graisse : 200 pour la citation, 300 pour les grands titres, 400
pour le texte courant, 600 pour les sur-titres et les boutons, avec un
interlettrage large sur les capitales. C'est ce déliement qui donne le
registre, là où un autre site aurait ajouté une seconde famille.

### La barre de navigation

En haut de page, la barre est **transparente** : le bandeau va jusqu'au bord
de l'écran et le logo se pose dessus dans sa version claire, sur un dégradé
sombre qui garantit sa lisibilité quelle que soit la photo.

Une fois la page défilée, elle se réduit et s'installe sur un **anthracite
translucide et flouté** (`#262C30` à 78 %). Elle reste sombre d'un bout à
l'autre : le logo, les libellés et le numéro ne changent jamais de couleur,
et rien ne clignote au passage.

Les 78 % ne sont pas choisis à l'œil. Le flou mélange les pixels alentour
*avant* que la couche ne se pose : aucune formule ne prédit le résultat
peint. Relevé au pixel sur près de 120 000 points, à huit pages et six
positions de défilement, le fond de la barre monte au plus clair à
`rgb(86,90,93)` — le blanc y tient **6,96:1**. C'est ce relevé qui a fait
apparaître un jeton de plus, `--vert-barre` (`#C2E2C8`) : le vert clair des
survols n'y tenait que 3,3:1, celui-ci y tient 4,98:1.

### Les logos

Les trois logos sont des **contours vectoriels extraits des PDF de la
charte**, pas des redessins : `logo-baron.svg` (horizontal, pour l'en-tête),
`logo-baron-clair.svg` (le même pour fond ardoise), `embleme-baron.svg` et
`embleme-baron-clair.svg` (le monogramme seul), `logo-baron-vertical.svg`
(le lockup officiel).

Le lockup horizontal a été **recomposé** : la charte de novembre 2025 ne
fournit que la version verticale, et un en-tête de site a besoin d'une
version large. L'emblème et les mots viennent des mêmes contours ; les
proportions sont relevées sur le lockup horizontal officiel, qui porte
l'ancien nom « PAYSAGISTE ».

Sur fond ardoise, le B passe en crème et la nervure en vert. Ce n'est pas un
choix esthétique : la feuille du logo est un **évidement**, elle emprunte la
couleur du fond. Un monogramme vert posé sur l'ardoise perdrait sa feuille.

---

## Ce qui a changé par rapport au site précédent

Demandes du client, reprises une à une :

| Demande | Ce qui a été fait |
|---|---|
| Modifier la charte graphique et le logo | Charte de novembre 2025 appliquée intégralement, logos extraits des PDF |
| Rafraîchir le diaporama et la présentation | Diaporama de cinq vues en fondu croisé avec dérive lente et jauge de progression. Réglable au back-office : ordre par glisser-déposer, activation par vue, temps de pause, et une case **Ordre aléatoire** qui tire une suite différente à chaque visite |
| Intégrer les avis Google | Module prêt : les avis sont récupérés **par le serveur** et mis en cache, donc aucun cookie tiers. La section reste masquée tant que la clé et la fiche ne sont pas saisies |
| Renvoyer simplement vers les réseaux sociaux | Bloc « Suivre sur… » en toutes lettres dans le pied, marques reprises dans la barre du bas et sur la page contact |
| La feuille du B du curseur, dans le bon sens et la bonne couleur | Le curseur décoratif a été retiré : l'entrée active est marquée par un filet vert sous le libellé, qui ne dépend d'aucune image |
| L'entreprise s'appelle BARON Paysage | Nom, logos, mentions légales et référencement à jour. Le nom commercial est « Baron Paysage », la raison sociale reste S.A.R.L. Les Jardins Baron |
| `jardins-baron.com` doit continuer de fonctionner | Redirection à faire chez l'hébergeur : voir « Le domaine » plus bas |
| « Spécialistes de l'aménagement paysager depuis plus de 25 ans » | Titre de la section prestations, à l'identique |
| « Nous apportons notre expérience au service de vos envies » | Chapeau de la même section |
| Supprimer les 3 descriptions sous Créativité / Écoute / Exigence | L'accueil ne montre plus que le picto et le mot. Les textes n'ont pas disparu : la page `/nos-valeurs` les développe |
| Moderniser les 3 images de ces onglets | Pictos SVG au trait, dessinés dans la charte : une feuille, une oreille, un cordeau à plomb |
| Ajouter la citation « Toutes les fleurs de l'avenir… » | Bloc dédié sur l'ardoise, entre la présentation et les valeurs |
| Pied de page : rafraîchir les 3 images | Les trois cartes sont maintenant les fiches de prestation, illustrées de vraies photos de chantier |
| À propos : nouveau texte de Benjamin Baron | Repris mot pour mot, grammaire corrigée |
| À propos : photo à changer | L'actuelle est en place en attendant ; à remplacer depuis **Admin → Photos** |
| Conception : nouveau texte | Repris intégralement, développé en quatre sections |
| Réalisations : une description par photo | 21 légendes du client appliquées photo par photo, 4 photos retirées comme demandé. Les légendes s'éditent au back-office |
| Demander un devis : ajouter un champ localité | Champ obligatoire, repris dans l'e-mail de la demande |
| Contact : les deux cartes côte à côte | Deux implantations côte à côte, chacune avec son plan et son lien d'itinéraire |

### Contenu ajouté

Le client a laissé la main pour étoffer. Ont été ajoutés, à partir de ce que
disait déjà le site précédent :

- une page **Questions fréquentes** (onze questions : devis, urbanisme,
  budget, délais, crédit d'impôt, déchets verts, contrats d'entretien) ;
- une fiche **Aménagement paysager** et une fiche **Élagage & taille**, qui
  n'existaient pas et qui portent pourtant la moitié du métier ;
- une page **Nos valeurs** développée, et des **repères chiffrés** sur
  l'accueil et la page « À propos » ;
- les **quatre étapes** qui suivent l'envoi du formulaire de devis : c'est la
  question qui retient la main sur le bouton d'envoi ;
- un pied de page qui **nomme les communes** du secteur, pour le
  référencement local.

---

## Les photos

Toutes les photographies viennent du site précédent, retaillées aux mêmes
largeurs et qualités que la médiathèque du back-office (1920 px en q82,
vignette 640 px en q78) : une photo livrée et une photo envoyée par le client
sont indiscernables.

Les **21 légendes** de la galerie « Aménagements paysagers » sont celles du
client, appliquées photo par photo. Les quatre photos qu'il a marquées « à
supprimer » ne sont pas dans le dépôt.

La galerie compte quatre catégories : *Aménagement paysager* (21), *Le
chantier* (7), *Entretien* (5), *Élagage & taille* (3). Trois d'entre elles
correspondent à une prestation et prennent son nom ; « Le chantier » n'a pas
de page produit et se déclare dans `data/realisations.json`, sous la clé
`noms`.

---

## Le domaine

Le client souhaite `baron-paysage.com`, et que `jardins-baron.com` continue
de fonctionner. Rien à faire dans le code : c'est une redirection à poser
chez l'hébergeur.

1. Le nouveau domaine devient le domaine principal, et sert le site.
2. L'ancien est ajouté en domaine secondaire, avec une **redirection 301
   vers le nouveau, en conservant le chemin** — de sorte que
   `jardins-baron.com/realisations` arrive sur `baron-paysage.com/realisations`
   et non sur la page d'accueil.
3. Les adresses WordPress du site précédent sont déjà redirigées par le site
   lui-même : la chaîne complète tient en deux sauts.

Une redirection 301 transmet le référencement acquis. Elle doit rester en
place indéfiniment : les liens posés depuis dix ans sur des annuaires et des
pages de partenaires ne seront jamais corrigés.

---

## À renseigner avant la mise en ligne

| Quoi | Où |
|---|---|
| Réglages SMTP et destinataire du formulaire | Admin → Paramètres |
| Adresse e-mail définitive, si elle change avec le domaine | Admin → Coordonnées & menu |
| Horaires d'ouverture (absents du site précédent) | Admin → Coordonnées & menu |
| Nouvelle photo de Benjamin Baron | Admin → Photos, puis Admin → Page « À propos » |
| Clé d'API Google et fiche Google Business, pour les avis | Admin → Avis Google |
| Clé d'API Gemini, si l'assistant doit être activé | Admin → Assistant IA |
| Hébergeur du site (mentions légales) | Admin → Éditeur avancé → `pages/mentions-legales` |

Tant qu'une photo manque, le visuel « photo à venir » s'affiche à sa place :
le site reste présentable, aucune image cassée.

---

## Vérifications faites

Mesurées plutôt que jugées à l'œil, comme le demande le KIT :

- **Contraste** — toutes les pages parcourues, les fonds translucides
  composés couche par couche et comparés au seuil WCAG AA. Aucun écart.
  Les textes du bandeau, posés sur une photo, ont été relevés **au pixel
  peint** — aucune formule ne prédit le rendu d'un texte clair sur un
  cliché : le pire cas relevé est 8,5:1.
- **Téléphone** — toutes les pages à 320, 390 et 768 px. Aucun débordement
  horizontal.
- **Cibles tactiles** — tous les liens et boutons relevés sous `hover: none`.
  Aucune cible de moins de 40 px. La règle porte sur le pointeur, pas sur la
  largeur : une tablette de 768 px est aussi imprécise qu'un téléphone.
- **Traceurs** — requêtes réseau comptées après un refus de consentement, sur
  quatre pages. Zéro requête vers un domaine tiers.
- **Routes** — les treize adresses publiques, les vingt écrans du
  back-office et les redirections 301 ouverts un à un, code HTTP vérifié.

---

## Correspondance des noms internes

Le socle nomme ses deux collections `services` et `valeurs`, et sa page de
société `la-societe`. Les clés internes ont été conservées : elles
n'apparaissent nulle part côté visiteur — les adresses et les libellés
viennent de `Seo::PAGES` et de `data/` — et les renommer aurait touché des
dizaines de fichiers pour un gain nul.

| Clé interne | Ce que c'est sur le site | Adresse |
|---|---|---|
| `services` | Les prestations | `/nos-prestations` |
| `valeurs` | Créativité, écoute, exigence | `/nos-valeurs` |
| `la-societe` | La page de présentation | `/a-propos` |
| `devis` | Le formulaire | `/demander-un-devis` |
| `realisations` | La galerie de chantiers | `/realisations` |
