# Compte habitant facultatif, par lien de connexion sécurisé

Piste mise de côté le 28 septembre 2026, à reprendre plus tard. Rien n’est développé.

## Aujourd’hui

Un habitant n’a pas de compte : aucune inscription ne lui est proposée. Tout ce qu’il fait sur le portail
fonctionne sans compte.

| Usage                                                   | Sans compte                                             |
| ------------------------------------------------------- | ------------------------------------------------------- |
| Rechercher, carte, fiches, agenda, campagnes            | Accès libre                                             |
| Lettre du territoire                                    | Adresse email, double validation, désinscription        |
| « Suivre » un commerce (offre Communication)            | Adresse email, double validation                        |
| Écrire, formulaire personnalisé, demande de rendez-vous | Formulaire, adresse pour la réponse                     |
| Candidature à une offre d’emploi                        | Formulaire avec CV                                      |
| Passeport des circuits (tampons)                        | Mémorisé dans le navigateur (cookie), sans donnée perso |
| Agenda                                                  | Abonnement iCal                                         |

Avantages : aucun frein, peu de données personnelles (RGPD), pas de mots de passe d’habitants au support.

Limites : passeport perdu en changeant d’appareil ; abonnements éparpillés (pas de page qui les liste) ; pas de
favoris ni d’alertes (« nouveaux commerces dans ma commune »).

## Proposition

- Compte **facultatif** : tout ce qui marche sans compte continue de marcher sans compte.
- **Sans mot de passe** : l’habitant saisit son adresse et reçoit un **lien de connexion sécurisé** par email
  (usage unique, durée courte, lié à l’adresse ; même mécanique de jetons que la réinitialisation de mot de passe).
- Usages :
  1. retrouver le passeport des circuits sur tous ses appareils (rattacher le passeport du navigateur au compte
     à la première connexion) ;
  2. voir et gérer sur une page ses abonnements (lettre du territoire, commerces suivis) ;
  3. garder ses commerces favoris ;
  4. recevoir des alertes sur sa commune (nouveaux commerces, événements, offres d’emploi).
- Pour la collectivité : nombre d’habitants inscrits à son portail, comme indicateur.

## À décider avant de développer

- Compte rattaché à un territoire, ou valable sur tous les portails terricom ?
- Où se connecter : sur le portail (domaine du territoire ou domaine propre) ou sur terricom.fr ? (voir
  [adresses-web.md](adresses-web.md) : la session de terricom.fr n’est pas visible sur les autres domaines)
- Durée de conservation d’un compte inactif et suppression automatique (RGPD).
