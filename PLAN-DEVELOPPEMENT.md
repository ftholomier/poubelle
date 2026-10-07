# Plan de développement · « tout construire » dans le prototype Synapse

But : construire dans le prototype **toutes les idées** de l'infographie (`synapse-offre/`) et de nos échanges,
de la prospection jusqu'à l'acte. Le prototype sert à tester et à montrer le parcours complet. Le vrai
développement se fera ensuite dans le logiciel métier (voir `PASSATION.md`).

Chaque lot porte un code (ex. **2.3**).

## État au 7 octobre 2026 : tout est construit dans le prototype

Toutes les phases ont été développées, testées de bout en bout (15 scénarios automatisés, `visite-immo/tests/`)
et poussées. Détail de ce qui est réel, simulé ou reporté :

| Lot | État |
|---|---|
| 0.1 Validation en réel | ⏳ **à faire avec vous** : vraie clé Gemini, vrai téléphone, vrai SMTP, vrais services publics |
| 0.2 → 0.7 Fondations | ✅ modules, tâches automatiques (cron), notifications push, liens clients, navigation du bas |
| 1.1 → 1.8 Visite et mandat | ✅ dossier technique, avis de valeur, lecture des papiers, pièces et relances, espace vendeur, signature (intégrée, **firma.dev**, BoldSign, API générique), photos, home staging, **estimation en temps réel (DVF, élargie aux communes voisines dans les petites communes)** |
| 1.9 Plan 2D | ✅ croquis d'après les pièces citées · ⛔ scan LiDAR : impossible en PWA (appli native) |
| 2.1, 2.3 → 2.5 Commercialisation | ✅ page du bien, visuels, vidéo, rapprochement et alertes |
| 2.2 Multidiffusion | ✅ flux XML + **rendu Leboncoin fidèle** (téléphone / ordinateur, contrôle de conformité) · ⏳ diffusion réelle : compte pro ou multidiffuseur, logiciel métier |
| 3.1 → 3.6 Acquéreurs et visites | ✅ fiches dictées, agenda + ICS, assistant 24 h/24 sur la page du bien, bon de visite, retours, point du vendredi |
| 3.3 (suite) | ⏳ lecture de la boîte e-mail de l'agence (IMAP) et SMS/WhatsApp : non construits (compte requis) |
| 4.1 → 4.7 Compromis à l'acte | ✅ offres, LCB-FT, notaires, échéancier, relances, facture, commission, avis Google |
| 5.1 → 5.3 Prospection | ✅ logements F/G, rues actives, courriers et boîtage, suivi · ⏳ envoi postal en ligne : non branché |
| 6.1 → 6.5 Au quotidien | ✅ Aujourd'hui, briefing, bilan d'appel, commande vocale, tableau de bord |
| 7.1 → 7.4 Réseau | ✅ tête de réseau, coaching 90 jours, contacts partagés, formation ALUR, juriste |
| 8.1 RGPD | ✅ conservation de l'audio, export des données d'une personne, registre simplifié, **journal des accès** (écran admin, CSV, purge automatique) |
| 8.2 Démo | ✅ jeu de démonstration (Paramètres) |
| 8.3 Passation | ✅ `PASSATION.md` à jour |
| Ajout · Suivi du projet | ✅ tableau de cases en temps réel (visite, rédigé, mandat, diffusion et CRM, vente), pourcentage « opérationnel », coach de captation pendant l'enregistrement, export CRM (JSON) · ⏳ envoi au CRM : vrai logiciel |


---

## Règles du jeu (valables pour tous les lots)

1. **On reste en PHP natif + fichiers JSON**, sans framework ni Composer. On ajoute une couche d'accès aux données
   pour que le passage à une base de données soit mécanique.
2. **Chaque service extérieur se règle dans l'appli** (Paramètres → Connecteurs), comme la clé Gemini. Pas de compte
   ou pas de clé → **mode simulation**, clairement affiché. La démo reste ainsi complète de bout en bout.
3. **Données publiques gratuites en priorité** : adresse (BAN), cadastre (IGN), risques (Géorisques), DPE (ADEME),
   ventes (DVF), gel des avoirs (DG Trésor).
4. **L'IA propose, l'agent valide.** Chaque information garde sa source (`ia`, `dialogue`, `document`, `agent`,
   `donnée publique`), avec citation quand c'est possible.
5. **Chaque nouveau document est un vrai PDF** aux couleurs de Synapse, et chaque envoi est un vrai e-mail.
6. **Fin de chaque lot** : tests automatiques (Playwright avec des services simulés), mise à jour de `PASSATION.md`,
   commit et push. **Vous validez avant de passer au lot suivant.**
7. **On mesure le temps réellement gagné** pour remplacer les estimations de l'infographie par des chiffres réels.

Taille indicative de chaque lot : **S** (petit), **M** (moyen), **L** (gros).

---

## Phase 0 · Fondations (indispensable avant le reste)

Aujourd'hui l'appli tourne autour d'une « visite ». Pour tout construire, elle doit devenir un petit CRM centré
sur **le bien et son mandat**.

| Lot | Contenu | Taille |
|---|---|---|
| **0.1** | **Validation en réel** de l'existant : vraie clé Gemini (transcription, génération, conversation vocale), iPhone et Android, vrai envoi d'e-mail. Correction de ce qui casse. *Besoin : votre clé et un essai sur votre téléphone.* | S |
| **0.2** | **Nouveau modèle de données** : Biens (le dossier), Contacts (vendeurs, acquéreurs, notaires, courtiers, syndics), Mandats, Visites d'acquéreurs, Offres et ventes, Tâches et relances, Rendez-vous, Documents. Couche `store.php` unique. Les visites actuelles sont reprises automatiquement dans le nouveau modèle. | L |
| **0.3** | **Nouvelle navigation** : barre du bas *Aujourd'hui · Biens · [micro] · Contacts · Agenda*. Le gros bouton micro reste au centre : on dicte, l'IA range l'information au bon endroit. | M |
| **0.4** | **Moteur d'automatismes** : `cron.php` (tâche planifiée, ou déclenchement à chaque visite de l'appli si l'hébergeur n'a pas de cron). File de travaux : transcription et génération passent en arrière-plan. C'est la base des relances, des comptes rendus du vendredi et du briefing. | M |
| **0.5** | **Notifications** sur le téléphone (Web Push, en PHP natif) + rappel par e-mail. | M |
| **0.6** | **Page Connecteurs** : état de chaque service (réel / simulation / non configuré), bouton « tester ». | S |
| **0.7** | **Lien sécurisé sans mot de passe** pour les clients (vendeur, acquéreur, notaire) : un lien par personne et par dossier, avec expiration. C'est la base des espaces vendeur et acquéreur. | M |

---

## Phase 1 · Visite et mandat (étape 02 de l'infographie)

Compléter ce qui existe : en sortant de la visite, le dossier est complet, signé et vérifié.

| Lot | Contenu | Taille |
|---|---|---|
| **1.1** | **Dossier technique depuis l'adresse** : géolocalisation (BAN), parcelles et surface cadastrale (IGN), risques naturels et technologiques (Géorisques), DPE déjà enregistré (ADEME). Remplit les champs avec la source « donnée publique ». | M |
| **1.2** | **Avis de valeur** : ventes comparables du quartier (DVF) + caractéristiques du bien → fourchette de prix argumentée par l'IA, carte des ventes, **PDF avis de valeur** Synapse à remettre au vendeur. | M |
| **1.3** | **L'IA lit les papiers du vendeur** : photo ou PDF de l'acte, de la taxe foncière, des PV d'AG, des diagnostics, de la pièce d'identité. Elle remplit origine de propriété, cadastre, lots, charges et état civil, avec la source « document ». | M |
| **1.4** | **Liste des pièces et relances automatiques** : pièces requises selon le bien (copropriété, maison, location…), suivi reçu ou manquant, relance par e-mail du vendeur (J+2, J+5…) avec lien de dépôt. | M |
| **1.5** | **Espace vendeur** (lien 0.7) : dépôt des pièces, documents signés, visites réalisées, comptes rendus, retours des acquéreurs. | M |
| **1.6** | **Signature électronique sur place** : signature au doigt sur le téléphone, horodatage, empreinte du document, fichier de preuve, PDF signé. Mandat, bordereau de rétractation, avis de valeur. Connecteur Yousign en option pour la signature « avancée ». Le mandat signé est inscrit au registre automatiquement. | L |
| **1.7** | **Photos** : prise de vue dans l'appli, l'IA reconnaît la pièce, trie, écarte les floues, propose l'ordre de l'annonce, corrige la lumière et redresse. | M |
| **1.8** | **Home staging virtuel** (Gemini image) : pièce vide ou encombrée → pièce meublée ou désencombrée, avec la mention obligatoire « image retouchée / aménagement virtuel » incrustée. | S |
| **1.9** | **Plan 2D** : dimensions dictées pièce par pièce pendant la visite → croquis de plan généré (SVG/PDF) avec surfaces. *Le vrai scan au LiDAR n'est pas possible en PWA : il faudra une appli native, à prévoir dans le vrai développement.* | M |

---

## Phase 2 · Commercialisation (étape 03)

| Lot | Contenu | Taille |
|---|---|---|
| **2.1** | **Page vitrine du bien** sur le domaine Synapse : photos, description, mentions légales, DPE/GES, formulaire de contact et demande de visite. Lien à partager partout. | M |
| **2.2** | **Multidiffusion** : flux d'export au format standard des portails + aperçus internes (Leboncoin déjà fait, autres portails en simulation, sans leurs logos). *La vraie diffusion demande des contrats avec les portails ou un multidiffuseur : elle passera par le logiciel métier.* | M |
| **2.3** | **Réseaux sociaux** : visuels carrés et story aux couleurs Synapse générés à partir des photos, textes adaptés (Instagram, Facebook, LinkedIn), partage en un geste depuis le téléphone. | M |
| **2.4** | **Courte vidéo du bien** générée dans le navigateur (diaporama animé, titres, prix, logo), prête pour Reels et TikTok. | M |
| **2.5** | **Rapprochement acquéreurs ↔ biens** : chaque nouveau mandat est comparé aux recherches des acquéreurs, avec un score. Alerte à l'agent et e-mail à l'acquéreur avec le lien vitrine. | M |

---

## Phase 3 · Acquéreurs et visites (étape 04)

| Lot | Contenu | Taille |
|---|---|---|
| **3.1** | **Fiche acquéreur dictée** : critères, budget, apport, financement, délai, bien à vendre ou non. | S |
| **3.2** | **Agenda** : rendez-vous dans l'appli + **abonnement calendrier** (lien ICS) visible dans Google Agenda ou Apple Calendrier sans connexion compliquée. Créneaux disponibles définis par l'agent. | M |
| **3.3** | **Assistant acquéreurs 24 h/24** : répond aux messages (formulaire et chat de la page vitrine, boîte e-mail de l'agence), répond aux questions sur le bien à partir du dossier, qualifie (budget, financement, délai), propose des créneaux libres et réserve la visite. SMS/WhatsApp si un compte Brevo ou Twilio est configuré. L'agent garde la main sur tout. | L |
| **3.4** | **Bon de visite signé** sur le téléphone avant la visite (lien 1.6). | S |
| **3.5** | **Retour de visite dicté en 30 secondes** → avis de l'acquéreur, points positifs et négatifs, intérêt, relance programmée. | S |
| **3.6** | **Compte rendu de commercialisation au vendeur chaque vendredi**, automatique : visites, retours, contacts, actions de la semaine, conseil (prix, présentation). L'agent peut relire avant l'envoi ou laisser partir automatiquement. | M |

---

## Phase 4 · Du compromis à l'acte (étape 05)

| Lot | Contenu | Taille |
|---|---|---|
| **4.1** | **Offre d'achat** dictée → PDF d'offre, signature de l'acquéreur, transmission et acceptation du vendeur (signature 1.6). | M |
| **4.2** | **Contrôle anti-blanchiment (LCB-FT)** : lecture de la pièce d'identité par l'IA, vérification sur le registre national des gels des avoirs, questions PPE et origine des fonds, fiche de vigilance PDF, registre. | M |
| **4.3** | **Dossier pour le notaire assemblé automatiquement** : fiche de renseignements (parties, bien, prix, honoraires, conditions), toutes les pièces, envoi en un clic et espace notaire (lien 0.7). | M |
| **4.4** | **Échéancier de la vente** : rétractation SRU de 10 jours, obtention du prêt, conditions suspensives, date de l'acte. Calcul automatique des dates, alertes. | M |
| **4.5** | **Relances automatiques** de l'acquéreur, du courtier et du notaire selon l'échéancier, avec historique. | S |
| **4.6** | **Facture d'honoraires + note de commission de l'agent** générées à l'acte : calcul de la part de l'agent selon les paliers de l'offre (80 / 90 / 95 % sur 12 mois glissants). | M |
| **4.7** | **Demande d'avis Google** envoyée au client après l'acte, avec relance. | S |

---

## Phase 5 · Prospection (étape 01)

| Lot | Contenu | Taille |
|---|---|---|
| **5.1** | **Carte des vendeurs potentiels** sur le secteur de l'agent : logements classés F/G (ADEME), biens non vendus depuis longtemps (DVF), ventes récentes autour. | L |
| **5.2** | **Courrier personnalisé** pour chaque adresse ciblée (angle DPE, valeur estimée, ventes voisines), PDF prêt à imprimer. Envoi postal par un service en ligne en option. *Le courrier remplace le démarchage téléphonique, qui demande désormais le consentement préalable de la personne.* | M |
| **5.3** | **Suivi des actions de prospection** : courriers envoyés, retours, conversion en estimation puis en mandat. | S |

> On ne récupère pas les annonces de particuliers sur les portails (« pige ») : leurs conditions d'utilisation l'interdisent.

---

## Phase 6 · Au quotidien (étape 06)

| Lot | Contenu | Taille |
|---|---|---|
| **6.1** | **Écran « Aujourd'hui »** : rendez-vous, relances dues, nouveaux contacts, échéances, pièces manquantes, actions proposées par l'IA. | M |
| **6.2** | **Briefing du matin à écouter** (voix du navigateur) : le résumé de la journée en une minute, dans la voiture. | S |
| **6.3** | **Bilan d'appel dicté** : « J'ai eu Mme Martin, elle baisse à 330 000, rappel jeudi » → contact mis à jour, prix modifié, tâche et relance créées. | M |
| **6.4** | **Commande vocale partout** : le micro central comprend « crée un rendez-vous », « relance le notaire », « envoie le compte rendu » et l'exécute après confirmation. | M |
| **6.5** | **Tableau de bord de l'agent** : honoraires sur 12 mois glissants, palier atteint et reste à faire, mandats, ventes en cours, **temps gagné mesuré**. | M |

---

## Phase 7 · Réseau Synapse (l'accompagnement de l'offre)

| Lot | Contenu | Taille |
|---|---|---|
| **7.1** | **Espace animateur / tête de réseau** : vue de tous les agents, activité, mandats, ventes, palier de chacun. | M |
| **7.2** | **Coaching des 90 premiers jours** : programme pas à pas, objectif « premier mandat en 30 jours », suivi par le coach. | M |
| **7.3** | **Contacts partagés dans le réseau** : un acquéreur ou un vendeur hors secteur est transmis à l'agent du secteur, avec suivi. | M |
| **7.4** | **Formation loi ALUR** : suivi des 14 h par an et attestations. **Question au juriste** : formulaire avec le dossier joint. | S |

---

## Phase 8 · Qualité, conformité et passation

| Lot | Contenu | Taille |
|---|---|---|
| **8.1** | **RGPD** : consentement à l'enregistrement tracé, durées de conservation (audio, pièces d'identité), export et suppression des données d'une personne, journal des accès. | M |
| **8.2** | **Scénario de démo complet** : un jeu de données fictif qui traverse toutes les étapes, de la prospection à l'acte, pour présenter l'outil en 10 minutes. | M |
| **8.3** | **Passation finale** : `PASSATION.md` complet, export zip, liste de ce qui est réel ou simulé, recommandations pour le logiciel métier. | S |

---

## Ordre proposé et pourquoi

**0 → 1 → 3 → 2 → 4 → 6 → 5 → 7 → 8**

- La **phase 0** est obligatoire : sans le nouveau modèle de données et le moteur d'automatismes, chaque idée serait
  bricolée à part.
- On suit ensuite **la vie d'un mandat** : rentrer le mandat (1), le faire visiter (3), le diffuser (2), le vendre (4).
  À la fin de chaque phase, un morceau du parcours est démontrable de bout en bout.
- **Au quotidien (6)** vient après, parce que le briefing, les relances et le tableau de bord se nourrissent de tout
  le reste.
- La **prospection (5)** et le **réseau (7)** sont les plus indépendants : on peut les avancer plus tôt si vous voulez
  les montrer en priorité (par exemple aux futurs agents).

---

## Ce dont j'ai besoin de votre part

| Sujet | Question | Ma recommandation |
|---|---|---|
| Gemini | Une vraie clé pour le lot 0.1 | Indispensable |
| Documents Synapse | Vos ~10 documents (mandats, bon de visite, offre d'achat, compte rendu…) | Envoyez-les en zip avant la phase 1 : ils servent de modèles à 1.6, 3.4, 4.1, 4.3 |
| Signature | Quel service ? | **Réponse : firma.dev** (branché) ; BoldSign en alternative (projet Qualiopi) ; signature intégrée pour tester sans compte |
| SMS / WhatsApp | Compte Brevo ou Twilio, ou e-mail seulement ? | E-mail seulement au début, SMS en option |
| Portails | Simulation seulement ? | Oui : la vraie diffusion passera par le logiciel métier |
| Courrier postal | Envoi en ligne ou PDF à imprimer ? | PDF à imprimer d'abord |
| Hébergement | Le serveur de test permet-il une tâche planifiée (cron) ? | Sinon je prévois le déclenchement automatique |
| Avis Google | Le lien vers la fiche Google de Synapse | À fournir pour 4.7 |
| Registre des mandats | Dans l'appli ou dans le logiciel métier ? | Dans l'appli pour le prototype, un seul registre dans le vrai système |

---

## Points de vigilance

- **Juridique** : mandat, bon de visite, offre d'achat et fiche de vigilance LCB-FT à faire relire par un juriste avant
  tout usage réel. Le home staging virtuel doit toujours être signalé. La signature « au doigt » est une signature
  électronique simple : elle est valable, mais une signature avancée (Yousign) prouve mieux en cas de litige.
- **Coûts IA** : chaque nouvelle fonction affiche son coût dans le compteur existant ; on garde les modèles les moins
  chers par défaut.
- **Le prototype reste un prototype** : fichiers JSON, un seul serveur, pas de montée en charge. Le but est de valider
  les usages et de mesurer le temps gagné, pas de mettre en production.
