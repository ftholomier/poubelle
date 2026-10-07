<?php
declare(strict_types=1);

namespace App\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Data\Activity;
use App\Services\FcsmImport;
use App\Services\FcsmStory;

/**
 * Système › Reprise 1928-1969 : import des saisons, matchs, tournois, articles et portraits de
 * fcsmstory.com, réécrits par Gemini (FcsmImport). Réservé aux administrateurs.
 */
final class Heritage extends Base
{
    public static function index(Request $req): Response
    {
        if ($r = self::denyUnlessAdmin()) {
            return $r;
        }
        $state = FcsmImport::state();
        $filter = $req->str('etat');
        $items = array_values($state['items']);
        if ($filter === 'tentes') {
            // Éléments déjà tentés (créés, en erreur, ou à refaire avec un message), les plus récents en tête.
            $items = array_values(array_filter($items, fn ($it) => (int) ($it['tries'] ?? 0) > 0));
        } elseif ($filter !== '') {
            $items = array_values(array_filter($items, fn ($it) => ($it['status'] ?? '') === $filter));
        }
        usort($items, fn ($a, $b) => [self::rank($a['kind']), $a['key']] <=> [self::rank($b['kind']), $b['key']]);
        return self::html('admin/system/heritage', [
            'state' => $state, 'summary' => FcsmImport::summary($state), 'items' => array_slice($items, 0, 600), 'filter' => $filter,
            'photos' => $state['items'] ? \App\Services\FcsmPhotos::summary() : null,
            'gemini' => \App\Services\Gemini::ready(), 'fetched' => \App\Core\JsonStore::read(FcsmStory::$dir . '/raw.json', [])['at'] ?? null,
        ], ['title' => 'Reprise 1928-1969', 'crumb' => 'Système', 'nav' => 'reprise']);
    }

    private static function rank(string $kind): int
    {
        return ['season' => 0, 'match' => 1, 'match-page' => 2, 'tournament' => 3, 'article' => 4, 'player' => 5][$kind] ?? 9;
    }

    /** POST : analyser (télécharger + plan), essai (3 matchs), lancer, pause, traiter (un lot maintenant). */
    public static function action(Request $req): Response
    {
        if ($r = self::denyUnlessAdmin()) {
            return $r;
        }
        $back = '/admin/reprise-1928-1969';
        @set_time_limit(300);
        try {
            switch ($req->str('action')) {
                case 'analyser':
                    FcsmStory::fetch();
                    $s = FcsmImport::plan();
                    Activity::log(self::actor(), 'a analysé fcsmstory.com (reprise 1928-1969)', null);
                    return self::back($back, count($s['items']) . ' éléments au plan. Rien n’est encore créé : faites l’essai, puis lancez.');
                case 'essai':
                    if (!\App\Services\Gemini::ready()) {
                        return self::back($back, null, 'Clé Gemini absente (Réglages › Assistant IA).');
                    }
                    $pick = [];
                    foreach (FcsmImport::state()['items'] as $it) {
                        if ($it['kind'] === 'match' && ($it['status'] ?? '') === 'a-faire' && mb_strlen((string) ($it['data']['report'] ?? '')) > 600) {
                            $pick[] = $it['key'];
                        }
                        if (count($pick) >= 3) {
                            break;
                        }
                    }
                    $r = FcsmImport::run(3, $pick);
                    $msg = 'Essai : ' . $r['done'] . ' fiche(s) créée(s), ' . $r['close'] . ' réécrite(s) car trop proches, ' . $r['errors'] . ' erreur(s).';
                    return $r['errors'] && !$r['done']
                        ? self::back($back . '?etat=tentes', null, $msg . ' ' . implode(' · ', array_slice($r['messages'], 0, 3)))
                        : self::back($back . '?etat=tentes', $msg . ' Ouvrez les fiches ci-dessous pour juger le style.');
                case 'lancer':
                    FcsmImport::start(true, self::actor());
                    return self::back($back, 'Import lancé : la tâche planifiée traite un lot toutes les 5 minutes. Vous pouvez fermer cette page.');
                case 'pause':
                    FcsmImport::start(false, self::actor());
                    return self::back($back, 'Import mis en pause.');
                case 'traiter':
                    $r = FcsmImport::run(12);
                    return self::back($back . '?etat=tentes', $r['done'] . ' fiche(s) créée(s), ' . $r['left'] . ' restante(s).' . ($r['messages'] ? ' Erreurs : ' . implode(' · ', array_slice($r['messages'], 0, 3)) : ''));
                case 'photos':
                    $r = \App\Services\FcsmPhotos::run(120);
                    Activity::log(self::actor(), 'a importé ' . $r['done'] . ' photo(s) de presse (reprise 1928-1969)', null);
                    return self::back($back, $r['done'] . ' photo(s) ajoutée(s) aux fiches' . ($r['left'] ? ', ' . $r['left'] . ' en attente de leur fiche' : '') . '.'
                        . ($r['messages'] ? ' Erreurs : ' . implode(' · ', array_slice($r['messages'], 0, 3)) : ''));
                case 'relancer':
                    FcsmImport::retry();
                    return self::back($back, 'Les éléments en erreur sont remis à faire.');
            }
        } catch (\Throwable $e) {
            return self::back($back, null, $e->getMessage());
        }
        return self::back($back);
    }
}
