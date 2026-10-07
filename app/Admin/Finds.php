<?php
declare(strict_types=1);

namespace App\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Data\Activity;
use App\Data\Index;
use App\Services\Gemini;
use App\Services\Trouvailles;

/**
 * Contenus › Trouvailles : propositions trouvées dans les archives en ligne (presse ancienne de
 * Gallica, web) pour les fiches de match, à valider une par une avant envoi dans la fiche.
 * Valider ou écarter : toute l'équipe. Lancer des recherches (coût IA) : administrateurs.
 */
final class Finds extends Base
{
    private const BACK = '/admin/trouvailles';

    public static function index(Request $req): Response
    {
        $status = in_array($req->str('etat'), ['attente', 'envoye', 'ecarte', 'tout'], true) ? $req->str('etat') : 'attente';
        $origin = isset(Trouvailles::SOURCES[$req->str('source')]) ? $req->str('source') : null;
        $list = Trouvailles::listing($status, $origin);
        $page = max(1, (int) $req->str('page'));
        $per = 25;
        return self::html('admin/fiches/trouvailles', [
            'summary' => Trouvailles::summary(), 'list' => array_slice($list, ($page - 1) * $per, $per), 'total' => count($list),
            'page' => $page, 'pages' => max(1, (int) ceil(count($list) / $per)), 'status' => $status, 'origin' => $origin,
            'admin' => \App\Core\Auth::isAdmin(), 'gemini' => Gemini::ready(), 'cron' => \App\Services\Cron::state(),
        ], ['title' => 'Trouvailles (archives)', 'crumb' => 'Contenus', 'nav' => 'trouvailles', 'scripts' => ['admin/trouvailles.js']]);
    }

    public static function action(Request $req): Response
    {
        $action = $req->str('action');
        $id = (int) $req->str('match');
        $item = $req->str('item');
        $back = self::BACK . self::keep($req) . ($id ? '#m' . $id : '');
        try {
            switch ($action) {
                case 'accepter':
                    $value = array_key_exists('value', $req->post) ? $req->str('value') : null;
                    $done = Trouvailles::accept($id, $item, $value, self::actor());
                    Activity::log(self::actor(), 'a validé une trouvaille (' . $done . ')', \App\Data\Fiches::get($id));
                    return self::back($back, 'Envoyé dans la fiche : ' . $done . '.');
                case 'ecarter':
                    Trouvailles::reject($id, $item, self::actor());
                    return self::back($back, 'Proposition écartée.');
                case 'retablir':
                    Trouvailles::restore($id, $item);
                    return self::back($back, 'Proposition remise en attente.');
            }
            // Recherches : coût IA, réservées aux administrateurs.
            if ($deny = self::denyUnlessAdmin()) {
                return $deny;
            }
            $sources = array_values(array_intersect((array) ($req->post['sources'] ?? []), array_keys(Trouvailles::SOURCES))) ?: ['gallica'];
            switch ($action) {
                case 'avancer':
                    // Page ouverte : un match de la file à la fois (la tâche planifiée fait de même).
                    \App\Core\Session::release();
                    @set_time_limit(300);
                    $r = Trouvailles::work(150, 1);
                    $sum = Trouvailles::summary();
                    return \App\Core\Response::json($r + ['attente' => $sum['attente'], 'searched' => $sum['searched'], 'running' => $sum['running'], 'log' => $sum['log'][0] ?? '']);
                case 'match':
                    $mid = self::matchId($req->str('fiche'));
                    if (!$mid) {
                        return self::back(self::BACK, null, 'Fiche de match introuvable : collez son adresse (/matchs/…) ou son numéro.');
                    }
                    session_write_close();
                    @set_time_limit(300);
                    $r = Trouvailles::search($mid, $sources);
                    Activity::log(self::actor(), 'a fouillé les archives pour un match', \App\Data\Fiches::get($mid));
                    return self::back(self::BACK . '#m' . $mid, $r['new'] . ' proposition(s) nouvelle(s) pour ce match' . ($r['messages'] ? ' (' . implode(' ; ', $r['messages']) . ')' : '') . '.');
                case 'essai':
                case 'lancer':
                    $from = max(1900, min(2100, (int) $req->str('de')));
                    $to = max($from, min(2100, (int) $req->str('a')));
                    session_write_close();
                    @set_time_limit(300);
                    $ids = Trouvailles::candidates($from, $to, $req->str('incomplets') !== '', $req->str('refaire') !== '');
                    if (!$ids) {
                        return self::back(self::BACK, 'Aucun match à fouiller entre ' . $from . ' et ' . $to . ' (déjà fouillés, ou complets).');
                    }
                    if ($action === 'essai') {
                        Trouvailles::start(array_slice($ids, 0, 3), $sources);
                        return self::back(self::BACK, 'Essai lancé sur 3 matchs : gardez cette page ouverte, elle les fouille l’un après l’autre (une à deux minutes chacun) ; les propositions s’affichent ensuite ci-dessous.');
                    }
                    $n = Trouvailles::start($ids, $sources);
                    Activity::log(self::actor(), 'a lancé la recherche dans les archives pour ' . count($ids) . ' match(s) (' . $from . '-' . $to . ')', null);
                    return self::back(self::BACK, count($ids) . ' match(s) mis dans la file (' . $n . ' au total) : la tâche planifiée les fouille quelques-uns à chaque passage. Les propositions arrivent ici au fil de l’eau.');
                case 'pause':
                    Trouvailles::pause();
                    return self::back(self::BACK, 'Recherche en pause.');
                case 'reprendre':
                    Trouvailles::start([], Trouvailles::state()['sources']);
                    return self::back(self::BACK, 'Recherche relancée.');
                case 'vider':
                    Trouvailles::clearQueue();
                    return self::back(self::BACK, 'File vidée.');
            }
        } catch (\Throwable $e) {
            return self::back($back, null, $e->getMessage());
        }
        return self::back(self::BACK, null, 'Action inconnue.');
    }

    /** Filtres de la liste gardés après une action. */
    private static function keep(Request $req): string
    {
        $q = array_filter(['etat' => $req->str('etat'), 'source' => $req->str('source'), 'page' => $req->str('page')], fn ($v) => $v !== '');
        return $q ? '?' . http_build_query($q) : '';
    }

    /** Fiche de match désignée par son numéro ou son adresse. */
    private static function matchId(string $s): ?int
    {
        $s = trim($s);
        if (preg_match('/^\d+$/', $s)) {
            $e = Index::get((int) $s);
        } else {
            $path = (string) (parse_url($s, PHP_URL_PATH) ?? '');
            if (preg_match('#^/admin/fiche/(\d+)#', $path, $m)) {
                $e = Index::get((int) $m[1]);
            } else {
                $path = '/' . trim(preg_replace('#^/en/#', '/', $path) ?? $path, '/') . '/';
                $e = Index::byPath($path);
            }
        }
        return $e && ($e['type'] ?? '') === 'match' ? (int) $e['id'] : null;
    }
}
