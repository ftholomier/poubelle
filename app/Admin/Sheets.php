<?php
declare(strict_types=1);

namespace App\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Data\Activity;
use App\Data\Fiches;
use App\Services\FeuillesImport;

/**
 * Système › Feuilles de match : import des feuilles de match de l'association (FeuillesImport).
 * Les matchs absents sont créés ; les écarts avec une fiche existante partent dans Trouvailles.
 * Réservé aux administrateurs.
 */
final class Sheets extends Base
{
    public static function index(Request $req): Response
    {
        if ($r = self::denyUnlessAdmin()) {
            return $r;
        }
        $state = FeuillesImport::state();
        $sheets = [];
        foreach (FeuillesImport::sheets() as $s) {
            $sheets[$s['key']] = $s;
        }
        // Dernières fiches traitées et erreurs, pour juger sur pièces.
        $recent = [];
        foreach ($state['items'] as $k => $it) {
            if (in_array($it['status'], ['fait', 'compare', 'erreur'], true) && isset($sheets[$k])) {
                $recent[] = ['at' => (string) ($it['at'] ?? ''), 'status' => $it['status'], 'fiche' => $it['fiche'] ?? null, 'props' => (int) ($it['props'] ?? 0), 'why' => $it['why'] ?? null, 'sheet' => $sheets[$k]];
            }
        }
        usort($recent, fn ($a, $b) => strcmp($b['at'], $a['at']));
        $recent = array_slice($recent, 0, 60);
        foreach ($recent as &$r) {
            $r['title'] = $r['fiche'] && ($d = Fiches::get((int) $r['fiche'])) ? $d['title'] : null;
            $r['path'] = $r['fiche'] && isset($d) && $d ? $d['path'] : null;
        }
        unset($r);
        $seasons = [];
        foreach ($state['items'] as $k => $it) {
            $d = (string) ($sheets[$k]['date'] ?? '');
            if (preg_match('/^(\d{4})-(\d{2})/', $d, $m)) {
                $y = (int) $m[1] - ((int) $m[2] < 7 ? 1 : 0);
                $seasons[$y][$it['status']] = ($seasons[$y][$it['status']] ?? 0) + 1;
            }
        }
        ksort($seasons);
        return self::html('admin/system/feuilles', [
            'state' => $state, 'summary' => FeuillesImport::summary($state), 'recent' => $recent, 'seasons' => $seasons, 'total' => count($sheets),
        ], ['title' => 'Feuilles de match', 'crumb' => 'Système', 'nav' => 'feuilles', 'scripts' => ['admin/feuilles.js']]);
    }

    /** POST : analyser, essai, lancer, pause, lot (JSON, appelé par la page), relancer. */
    public static function action(Request $req): Response
    {
        if ($r = self::denyUnlessAdmin()) {
            return $r;
        }
        $back = '/admin/import-feuilles';
        @set_time_limit(300);
        try {
            switch ($req->str('action')) {
                case 'analyser':
                    $s = FeuillesImport::summary(FeuillesImport::plan());
                    Activity::log(self::actor(), 'a analysé les feuilles de match', null);
                    return self::back($back, $s['a-creer'] . ' match(s) à créer, ' . $s['a-comparer'] . ' déjà au musée à comparer. Rien n’est encore écrit : faites l’essai, puis lancez.');
                case 'essai':
                    if (!FeuillesImport::state()['items']) {
                        FeuillesImport::plan();
                    }
                    $r = FeuillesImport::run(10);
                    return self::back($back, 'Essai : ' . $r['created'] . ' fiche(s) créée(s), ' . $r['compared'] . ' comparée(s), ' . $r['props'] . ' proposition(s) envoyée(s) dans Trouvailles. Ouvrez-les ci-dessous.'
                        . ($r['messages'] ? ' Erreurs : ' . implode(' · ', array_slice($r['messages'], 0, 3)) : ''));
                case 'lancer':
                    FeuillesImport::start(true);
                    Activity::log(self::actor(), 'a lancé l’import des feuilles de match', null);
                    return self::back($back, 'Import lancé : il avance tant que cette page est ouverte, et par la tâche planifiée sinon.');
                case 'pause':
                    FeuillesImport::start(false);
                    return self::back($back, 'Import mis en pause.');
                case 'lot':
                    $st = FeuillesImport::state();
                    if (empty($st['running'])) {
                        return self::json(['running' => false, 'left' => FeuillesImport::summary($st)['left']]);
                    }
                    $r = FeuillesImport::run(60);
                    if (!$r['left']) {
                        FeuillesImport::start(false);
                    }
                    $sum = FeuillesImport::summary();
                    return self::json($r + ['running' => $r['left'] > 0, 'done' => $sum['fait'], 'compared_total' => $sum['compare'], 'props_total' => $sum['props'], 'busy' => in_array('un autre lot est déjà en cours', $r['messages'], true)]);
                case 'relancer':
                    FeuillesImport::retry();
                    return self::back($back, 'Les erreurs sont remises à faire.');
            }
        } catch (\Throwable $e) {
            return $req->str('action') === 'lot' ? self::json(['error' => $e->getMessage()], 500) : self::back($back, null, $e->getMessage());
        }
        return self::back($back);
    }
}
