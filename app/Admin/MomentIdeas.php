<?php
declare(strict_types=1);

namespace App\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Data\Activity;
use App\Data\Index;
use App\Services\Gemini;
use App\Services\MomentIdeas as Ideas;
use App\Services\Moments;

/**
 * Éditorial › 100 moments › Boîte à idées : les idées proposées par l'IA (sommaire, pistes) ou par
 * l'équipe, à retenir, modifier ou écarter, et le premier jet d'une idée retenue (fiche « À relire »).
 */
final class MomentIdeas extends Base
{
    public static function index(Request $req): Response
    {
        $all = Ideas::all();
        $state = isset(Ideas::STATES[$req->str('etat')]) || $req->str('etat') === 'toutes' ? $req->str('etat') : 'a-trier';
        $decade = ctype_digit($req->str('decennie')) ? (int) $req->str('decennie') : null;
        $counts = array_fill_keys(array_keys(Ideas::STATES), 0);
        foreach ($all as $i) {
            $counts[$i['state']] = ($counts[$i['state']] ?? 0) + 1;
        }
        $list = array_values(array_filter($all, fn ($i) => match ($state) {
            'a-trier' => in_array($i['state'], ['proposee', 'retenue'], true),
            'toutes' => true,
            default => $i['state'] === $state,
        } && ($decade === null || ($i['year'] && intdiv((int) $i['year'], 10) * 10 === $decade))));
        $taken = array_values(array_filter(array_map(fn ($r) => $r['date'] ? substr($r['date'], 0, 10) : null, Moments::all())));
        foreach ($list as &$i) {
            $i['sources_info'] = array_values(array_filter(array_map(fn ($id) => ($s = Index::get((int) $id)) ? ['id' => (int) $id, 'title' => $s['title'], 'type' => $s['type'], 'path' => $s['path']] : null, (array) $i['sources'])));
            $i['suggest'] = Moments::anniversary($i['date'] ?? null, $taken);
            $i['fiche_info'] = ($f = Ideas::ficheOf($i)) ? Moments::row(\App\Data\Fiches::get((int) $f['id']) ?? []) : null;
        }
        unset($i);
        return self::html('admin/editorial/idees', [
            'list' => $list, 'counts' => $counts, 'total' => count($all), 'state' => $state, 'decade' => $decade,
            'coverage' => Ideas::coverage(), 'runs' => array_slice(Ideas::runs(), 0, 5),
            'ready' => Gemini::ready() || Ideas::$ai !== null,
        ], ['title' => 'Boîte à idées des 100 moments', 'crumb_html' => 'Éditorial › <a href="/admin/moments">100 moments</a>', 'nav' => 'moments', 'tips' => 'moments-idees', 'help' => 'moments-idees', 'scripts' => ['admin/moments.js']]);
    }

    /** POST /admin/moments/idees (JSON) : action sur une idée, ou demande à l'IA. */
    public static function action(Request $req): Response
    {
        $in = $req->json() ?: $req->post;
        $do = (string) ($in['action'] ?? '');
        $id = (string) ($in['id'] ?? '');
        $user = self::actor();
        try {
            switch ($do) {
                case 'retenir':
                    $i = Ideas::setState($id, 'retenue', '', $user);
                    return self::json(['ok' => true, 'message' => 'Idée retenue : « ' . $i['title'] . ' ».', 'reload' => true]);
                case 'ecarter':
                    $i = Ideas::setState($id, 'ecartee', (string) ($in['raison'] ?? ''), $user);
                    return self::json(['ok' => true, 'message' => 'Idée écartée : l’IA ne la proposera plus.', 'reload' => true]);
                case 'proposer':
                    Ideas::setState($id, 'proposee', '', $user);
                    return self::json(['ok' => true, 'message' => 'Idée remise parmi les propositions.', 'reload' => true]);
                case 'modifier':
                    $i = Ideas::edit($id, (array) ($in['idee'] ?? []), $user);
                    return self::json(['ok' => true, 'message' => 'Idée enregistrée : « ' . $i['title'] . ' ».', 'reload' => true]);
                case 'ajouter':
                    $i = Ideas::add((array) ($in['idee'] ?? []), $user);
                    Activity::log($user, 'a ajouté une idée de moment', ['title' => $i['title'], 'path' => '']);
                    return self::json(['ok' => true, 'message' => 'Idée ajoutée et retenue : « ' . $i['title'] . ' ».', 'reload' => true]);
                case 'sommaire':
                case 'piste':
                case 'autre':
                    Session::release(); // une minute environ : les autres onglets restent utilisables
                    $r = match ($do) {
                        'sommaire' => Ideas::propose($user),
                        'piste' => Ideas::ask((string) ($in['piste'] ?? ''), $user),
                        default => Ideas::replace($id, $user),
                    };
                    Activity::log($user, 'a demandé des idées de moments à l’IA', ['title' => $r['added'] . ' idée(s)', 'path' => '']);
                    $msg = $r['added'] ? $r['added'] . ' idée' . ($r['added'] > 1 ? 's' : '') . ' proposée' . ($r['added'] > 1 ? 's' : '') . ' par l’IA, à trier.' : 'L’IA n’a rien proposé de nouveau.';
                    if ($r['failed']) {
                        $msg .= ' ' . count($r['failed']) . ' demande' . (count($r['failed']) > 1 ? 's' : '') . ' sans réponse (' . implode(' ; ', $r['failed']) . ') : relancez plus tard.';
                    }
                    return self::json(['ok' => true, 'message' => $msg . self::cost(), 'reload' => true]);
                case 'rediger':
                    Session::release();
                    $fid = Ideas::draft($id, $user);
                    Activity::log($user, 'a demandé le premier jet d’un moment à l’IA', ['title' => \App\Data\Fiches::get($fid)['title'] ?? '', 'path' => '']);
                    return self::json(['ok' => true, 'message' => 'Premier jet prêt, « À relire ».' . self::cost(), 'redirect' => '/admin/fiche/' . $fid]);
            }
        } catch (\InvalidArgumentException $e) {
            return self::json(['ok' => false, 'error' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            error_log('[moments] ' . $e);
            return self::json(['ok' => false, 'error' => $e->getMessage()], 502);
        }
        return self::json(['ok' => false, 'error' => 'Action inconnue.'], 400);
    }

    /** Coût de la demande (administrateurs seulement) : la dépense réelle est dans Coûts IA. */
    private static function cost(): string
    {
        return self::aiCost();
    }
}
