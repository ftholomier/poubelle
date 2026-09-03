<?php
declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Levée quand un contenu a été modifié par quelqu'un d'autre entre
 * l'affichage d'un écran d'édition et son enregistrement.
 *
 * Ce n'est pas une panne : c'est le cas normal de deux personnes qui
 * travaillent en même temps. Le message porte donc ce qu'il faut faire,
 * pas ce qui a échoué.
 */
final class ConflitEcriture extends RuntimeException
{
    public function __construct(public readonly string $contenu)
    {
        parent::__construct(
            'Ce contenu a été modifié par quelqu’un d’autre pendant que vous le '
            . 'saisissiez. Rien n’a été enregistré, pour ne pas effacer son '
            . 'travail. Rechargez la page : vous y retrouverez la version à jour, '
            . 'sur laquelle reporter vos modifications.'
        );
    }
}
