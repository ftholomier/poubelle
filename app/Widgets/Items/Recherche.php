<?php
declare(strict_types=1);

namespace App\Widgets\Items;

use App\Widgets\Context;
use App\Widgets\Widget;

/**
 * Recherche du musée : un petit champ « un match, un joueur, une saison… » qui ouvre la page de
 * recherche du musée (/recherche/?q=…) dans un nouvel onglet, en passant par le lien compté
 * (/w/clic/recherche/). Rien n'est cherché ni gardé dans le widget lui-même.
 * Sans script (iframe nue), le formulaire s'envoie tout seul (target="_blank") ; avec le script
 * d'intégration (iframe sans « allow-forms »), js/widget.js ouvre l'onglet (popups permis).
 */
final class Recherche extends Widget
{
    public function key(): string
    {
        return 'recherche';
    }

    public function name(string $lang = 'fr'): string
    {
        return $lang === 'en' ? 'Museum search' : 'Recherche du musée';
    }

    public function description(string $lang = 'fr'): string
    {
        return $lang === 'en' ? 'A small search box that opens the museum’s search in a new tab.'
            : 'Un petit champ de recherche qui ouvre la recherche du musée dans un nouvel onglet.';
    }

    public function height(): int
    {
        return 230;
    }

    public function data(array $opt, Context $c): ?array
    {
        return ['action' => '/recherche/', 'href' => '/recherche/', 'placeholder' => $c->tr('Un match, un joueur, une saison…', 'A match, a player, a season…')];
    }

    public function body(array $d, Context $c): string
    {
        // Le lien compté sert de modèle : « __Q__ » est remplacé par la recherche (encodée) dans widget.js,
        // qui ouvre l'onglet lui-même (clic, touche Entrée) : l'iframe de /embed.js est sandboxée sans
        // allow-forms, le formulaire ne s'y envoie pas. Sans sandbox (code iframe), il reste un repli.
        $tpl = $c->link('/recherche/?q=__Q__');
        return '<p class="w-eyebrow">' . e($c->tr('Chercher dans le musée', 'Search the museum')) . '</p>'
            . '<form class="w-search" role="search" method="get" action="' . e(Context::abs($c->path('/recherche/'))) . '" target="_blank" data-search="' . e($tpl) . '">'
            . '<label class="w-sr" for="w-q">' . e($c->tr('Rechercher', 'Search')) . '</label>'
            . '<input id="w-q" name="q" type="search" maxlength="120" required placeholder="' . e($d['placeholder']) . '" autocomplete="off">'
            . '<button type="submit" class="w-btn">' . e($c->tr('Chercher', 'Search')) . '</button></form>'
            . '<p class="w-text small">' . e($c->tr('Les résultats s’ouvrent sur le musée, dans un nouvel onglet.', 'Results open on the museum, in a new tab.')) . '</p>';
    }
}
