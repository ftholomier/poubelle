<?php
declare(strict_types=1);

namespace App\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Data\Activity;
use App\Data\Index;
use App\Services\Compos;

/**
 * Contenus › Contrôle des compositions : compare les feuilles de match du musée à Transfermarkt,
 * pari-et-gagne.com, footballdatabase.eu, worldfootball.net, FCSM Story et à la presse d'époque.
 * Les écarts partent dans Trouvailles. Lancer (coût IA, requêtes externes) : administrateurs ;
 * le bouton de la fiche contrôle une seule fiche et reste ouvert aux historiens.
 */
final class LineupCheck extends Base
{
    private const BACK = '/admin/compositions';

    public static function index(Request $req): Response
    {
        $state = Compos::state();
        $bySeason = [];
        foreach (Index::published('match') as $e) {
            $s = (string) ($e['m']['season'] ?? '') ?: 'sans saison';
            $bySeason[$s] ??= ['total' => 0, 'done' => 0, 'added' => 0];
            $bySeason[$s]['total']++;
            if ($d = $state['done'][(string) $e['id']] ?? null) {
                $bySeason[$s]['done']++;
                $bySeason[$s]['added'] += (int) $d['added'];
            }
        }
        krsort($bySeason);
        $recent = $state['done'];
        uasort($recent, fn ($a, $b) => strcmp((string) $b['at'], (string) $a['at']));
        $recent = array_slice($recent, 0, 30, true);
        $titles = [];
        foreach (array_keys($recent) as $id) {
            $titles[$id] = Index::get((int) $id)['title'] ?? ('Fiche ' . $id);
        }
        return self::html('admin/fiches/compositions', [
            'state' => $state, 'seasons' => $bySeason, 'recent' => $recent, 'titles' => $titles,
            'relay' => (string) \App\Core\Settings::get('compos.relay', ''), 'admin' => \App\Core\Auth::isAdmin(), 'gemini' => \App\Services\Gemini::ready(), 'cron' => \App\Services\Cron::state(),
        ], ['title' => 'Contrôle des compositions', 'crumb' => 'Contenus', 'nav' => 'compositions']);
    }

    public static function action(Request $req): Response
    {
        if ($deny = self::denyUnlessAdmin()) {
            return $deny;
        }
        $sources = array_values(array_intersect((array) ($req->post['sources'] ?? []), array_keys(Compos::SOURCES)));
        try {
            switch ($req->str('action')) {
                case 'lancer':
                    $ids = Compos::candidates($req->str('saison'), $req->str('refaire') !== '');
                    if (!$ids) {
                        return self::back(self::BACK, 'Aucun match à contrôler (déjà contrôlés ? cochez « Recontrôler »).');
                    }
                    if (!$sources) {
                        return self::back(self::BACK, null, 'Cochez au moins une source.');
                    }
                    $n = Compos::start($ids, $sources);
                    Activity::log(self::actor(), 'a lancé le contrôle des compositions pour ' . count($ids) . ' match(s)', null);
                    return self::back(self::BACK, count($ids) . ' match(s) mis dans la file (' . $n . ' nouveaux) : la tâche planifiée les contrôle quelques-uns à chaque passage. Les écarts arrivent dans Trouvailles.');
                case 'tester':
                    @set_time_limit(300);
                    $lines = [];
                    foreach (Compos::probe() as $site => [$ok, $d]) {
                        $lines[] = ($ok ? '✓ ' : '✗ ') . $site . ' : ' . $d;
                    }
                    return self::back(self::BACK, 'Accès du serveur aux sites — ' . implode(' · ', $lines));
                case 'relais':
                    $relay = trim($req->str('relay'));
                    if ($relay !== '' && (!preg_match('#^https://#', $relay) || !str_contains($relay, '{url}'))) {
                        return self::back(self::BACK, null, 'L’adresse du relais doit commencer par https:// et contenir {url}.');
                    }
                    \App\Core\Settings::save(['compos.relay' => $relay]);
                    return self::back(self::BACK, $relay === '' ? 'Service relais retiré.' : 'Service relais enregistré : relancez le contrôle d’un match pour l’essayer.');
                case 'pause':
                    Compos::stop();
                    return self::back(self::BACK, 'Contrôle en pause.');
                case 'reprendre':
                    Compos::start([], Compos::state()['sources']);
                    return self::back(self::BACK, 'Contrôle relancé.');
                case 'vider':
                    Compos::stop(true);
                    return self::back(self::BACK, 'File vidée.');
                case 'avancer':
                    \App\Core\Session::release();
                    @set_time_limit(300);
                    $msg = Compos::tick(60);
                    $st = Compos::state();
                    return Response::json(['message' => $msg, 'queue' => count($st['queue']), 'on' => (bool) $st['on']]);
            }
        } catch (\Throwable $e) {
            return self::back(self::BACK, null, $e->getMessage());
        }
        return self::back(self::BACK, null, 'Action inconnue.');
    }

    /** Bouton de la fiche : contrôle de cette seule fiche, avec toutes les sources. */
    public static function fiche(Request $req, int $id): Response
    {
        $back = '/admin/fiche/' . $id . '#compo';
        try {
            \App\Core\Session::release();
            @set_time_limit(300);
            $f = $req->files['tm_html'] ?? null;
            $html = $f && ($f['error'] ?? 1) === UPLOAD_ERR_OK && ($f['size'] ?? 0) < 8_000_000 ? (string) file_get_contents($f['tmp_name']) : null;
            $r = Compos::check($id, $html !== null ? ['transfermarkt'] : null, $html);
            Compos::remember($id, $r);
            Activity::log(self::actor(), 'a contrôlé la composition d’un match', \App\Data\Fiches::get($id));
        } catch (\Throwable $e) {
            return self::back($back, null, $e->getMessage());
        }
        $notes = implode(' · ', array_map(fn ($s) => Compos::SOURCES[$s['key']] . ' : ' . $s['note'], $r['sources']));
        return $r['added']
            ? self::back('/admin/trouvailles?source=compos#m' . $id, $r['added'] . ' écart(s) envoyé(s) dans Trouvailles, à valider ci-dessous. ' . $notes)
            : self::back($back, 'Aucun écart trouvé. ' . $notes);
    }
}
