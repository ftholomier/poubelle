# RGPD

## Rôles

| Traitement                                                                                                   | Responsable     | terricom                                                                 |
| ------------------------------------------------------------------------------------------------------------ | --------------- | ------------------------------------------------------------------------ |
| Portail du territoire : fiches, newsletter, messages, candidatures, rendez-vous, circuits, mesure d’audience | la collectivité | sous-traitant (article 28) : convention de traitement annexée au contrat |
| Comptes des professionnels et des agents, facturation des options, site terricom.fr, démonstrations          | terricom        | responsable de traitement                                                |

## Clients abonnés d’une entreprise (offre Communication)

L’entreprise est responsable du traitement de ses contacts clients ; terricom en est le sous-traitant (conditions
générales de vente). La collectivité n’y a pas accès : ni affichage dans le back-office, ni export.

| Traitement                        | Données                                              | Base légale                                                | Conservation (purge automatique)                                    |
| --------------------------------- | ---------------------------------------------------- | ---------------------------------------------------------- | ------------------------------------------------------------------- |
| Abonnement depuis la fiche        | email, nom facultatif, texte et date du consentement | consentement (double confirmation par email)               | non confirmé : 30 jours ; désinscription conservée pour la garantir |
| Ajout manuel par le professionnel | email, nom facultatif, attestation d’accord          | consentement recueilli par l’entreprise, attesté à l’ajout | jusqu’à la désinscription ou la suppression par l’entreprise        |
| Lettres de l’entreprise           | destinataires, ouvertures, clics                     | consentement                                               | statistiques agrégées sur la lettre ; 4 envois au plus par 30 jours |

Une personne désinscrite ne peut pas être réinscrite par l’entreprise : elle seule peut se réabonner depuis la
fiche. L’export CSV des contacts (offre Communication) est journalisé dans l’audit (catégorie RGPD).

## Registre des traitements (portail d’un territoire)

| Traitement                                                         | Données                                                                              | Base légale                                                        | Conservation (purge automatique)                              |
| ------------------------------------------------------------------ | ------------------------------------------------------------------------------------ | ------------------------------------------------------------------ | ------------------------------------------------------------- |
| Fiches des établissements                                          | données publiques SIRENE, contenus publiés par les professionnels                    | mission d’intérêt public / intérêt légitime (animation économique) | tant que l’établissement est actif ; opposition possible      |
| Lettre d’information                                               | email, commune, centres d’intérêt, preuve du consentement                            | consentement (double confirmation)                                 | inscription non confirmée : 30 jours ; sans ouverture : 3 ans |
| Messages aux professionnels                                        | identité, coordonnées, message                                                       | consentement                                                       | 3 ans (champ `purge_after`)                                   |
| Formulaires personnalisés des professionnels (devis, réservation…) | identité, coordonnées, réponses                                                      | consentement                                                       | 3 ans, avec la messagerie (champ `purge_after`)               |
| Candidatures                                                       | identité, coordonnées, CV, message                                                   | mesures précontractuelles                                          | 2 ans, CV supprimé avec la candidature                        |
| Demandes de rendez-vous                                            | identité, coordonnées, créneau                                                       | mesures précontractuelles                                          | 12 mois                                                       |
| Passeport des circuits                                             | jeton anonyme, étapes tamponnées                                                     | exécution du service                                               | 12 mois après la dernière utilisation                         |
| Mesure d’audience                                                  | pages vues, provenance, appareil ; empreinte quotidienne non réversible, sans cookie | intérêt légitime (exemption CNIL)                                  | 13 mois, puis agrégats anonymes                               |
| Revendication                                                      | SIRET, fonction, justificatif (Kbis)                                                 | intérêt légitime (prévention de la fraude)                         | justificatif supprimé 12 mois après la décision               |
| Journal d’audit                                                    | auteur, action, empreinte d’IP                                                       | obligation de sécurité                                             | 12 mois                                                       |
| Emails transactionnels                                             | destinataire, contenu                                                                | exécution du service                                               | 12 mois                                                       |

Les durées sont centralisées dans `src/lib/constants.ts` (`RETENTION`) et appliquées chaque nuit par la tâche
`maintenance.purge` (`src/server/jobs/tasks.ts`).

## Droits des personnes

- Titulaires d’un compte : export JSON (accès, portabilité) et suppression du compte en libre-service
  depuis « Mon compte ».
- Autres personnes (abonnés, auteurs de messages, candidats) : la demande reçue par la collectivité ou par
  le délégué à la protection des données est enregistrée dans la console (Audit & sécurité), avec échéance
  d’un mois ; l’exploitant génère l’export JSON de toutes les données liées à l’adresse, ou procède à
  l’effacement (suppression des abonnements, contacts et candidatures avec leur CV, anonymisation des
  messages et rendez-vous) ; chaque étape est journalisée.
- Désinscription en un clic de toute lettre (lien et en-tête `List-Unsubscribe`).

## Sous-traitants ultérieurs

Hébergement et stockage objet en France ; service d’envoi d’emails ; prestataire de paiement (options des
professionnels) ; fournisseur du modèle d’IA (Anthropic) pour les assistants — les textes transmis ne
contiennent pas de données personnelles des habitants, le repli sans IA reste disponible et l’IA peut être
désactivée par territoire (module « Assistant IA »). Les transferts éventuels hors UE sont encadrés par les
clauses contractuelles types.

## Sécurité et violations

Voir [sécurité](securite.md). En cas de violation, terricom informe la collectivité sans délai injustifié et
l’assiste pour la notification à la CNIL (72 h) et, le cas échéant, l’information des personnes.
