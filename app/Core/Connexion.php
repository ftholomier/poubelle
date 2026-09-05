<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Une clé et un modèle : de quoi appeler Gemini une fois.
 *
 * Le socle appelle le modèle depuis deux endroits, avec le même compte mais
 * pas les mêmes besoins.
 *
 * L'assistant du site répond à des visiteurs, souvent, et relit son corpus à
 * chaque question : il lui faut un modèle rapide et peu coûteux, et une
 * réponse en trois phrases n'en demande pas plus.
 *
 * Le conseiller du back-office relit le site ENTIER — cent vingt mille
 * caractères — pour en tirer un bilan classé. C'est le genre de travail où un
 * modèle plus capable se voit immédiatement, et il n'est demandé que quelques
 * fois par mois : le surcoût ne se mesure pas à l'échelle d'une commune.
 *
 * D'où cet objet, qui laisse choisir le modèle par usage tout en gardant une
 * seule clé à renseigner. Il est volontairement pauvre : il ne sait pas
 * appeler, il dit avec quoi appeler. C'est `Assistant::generer()` qui s'en
 * sert.
 */
final class Connexion
{
    public function __construct(
        public readonly string $cle,
        public readonly string $modele,
    ) {
    }

    public function utilisable(): bool
    {
        return $this->cle !== '';
    }
}
