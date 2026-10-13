<?php
declare(strict_types=1);

namespace App\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Data\Activity;
use App\Data\Index;
use App\Data\OfficialPlayers;

/**
 * Éditorial › Match officiel : la sous-rubrique calculée « Match officiel » (Nos lions › Joueurs).
 * La liste vient du code (config/clubs/<club>/match-officiel.php) ; cet écran montre ce qui est
 * retrouvé et permet d'ajouter ou de retirer un joueur sans toucher au code (réservé aux administrateurs).
 */
final class Officials extends Base
{
    private const BACK = '/admin/match-officiel';

    public static function index(Request $req): Response
    {
        if ($r = self::denyUnlessAdmin()) {
            return $r;
        }
        if (!OfficialPlayers::enabled()) {
            return self::back('/admin', null, 'Pas de liste « Match officiel » pour ce musée.');
        }
        $res = OfficialPlayers::resolve();
        $adj = OfficialPlayers::adjustments();
        $name = function (int $id): array {
            $s = Index::get($id);
            return ['id' => $id, 'title' => $s ? (string) $s['title'] : 'Fiche n° ' . $id . ' (introuvable)', 'path' => $s ? (string) $s['path'] : ''];
        };
        return self::html('admin/editorial/match-officiel', [
            'listed' => count(OfficialPlayers::rows()),
            'shown' => OfficialPlayers::count(),
            'missing' => $res['missing'],
            'added' => array_map($name, $adj['add']),
            'removed' => array_map($name, $adj['remove']),
        ], ['title' => 'Match officiel', 'crumb' => 'Éditorial', 'nav' => 'match-officiel']);
    }

    /** POST : ajouter, retirer, annuler (un ajout ou un retrait). */
    public static function save(Request $req): Response
    {
        if ($r = self::denyUnlessAdmin()) {
            return $r;
        }
        if (!OfficialPlayers::enabled()) {
            return self::back('/admin');
        }
        $adj = OfficialPlayers::adjustments();
        $action = $req->str('action');
        $id = self::fiche($req->str('fiche'));
        if ($id === null) {
            return self::back(self::BACK, null, 'Fiche introuvable : indiquez un joueur publié (titre proposé, adresse ou numéro de fiche).');
        }
        $s = Index::get($id);
        $title = (string) ($s['title'] ?? $id);
        switch ($action) {
            case 'ajouter':
                $adj['remove'] = array_values(array_diff($adj['remove'], [$id]));
                if (!OfficialPlayers::has($id)) {
                    $adj['add'][] = $id;
                }
                $msg = $title . ' figure dans la liste.';
                break;
            case 'retirer':
                $adj['add'] = array_values(array_diff($adj['add'], [$id]));
                if (OfficialPlayers::has($id)) {
                    $adj['remove'][] = $id;
                }
                $msg = $title . ' est retiré de la liste.';
                break;
            case 'annuler':
                $adj['add'] = array_values(array_diff($adj['add'], [$id]));
                $adj['remove'] = array_values(array_diff($adj['remove'], [$id]));
                $msg = 'Réglage annulé pour ' . $title . ' : la liste livrée s’applique.';
                break;
            default:
                return self::back(self::BACK);
        }
        OfficialPlayers::saveAdjustments($adj['add'], $adj['remove'], self::actor());
        Activity::log(self::actor(), 'a ajusté la liste « Match officiel »', ['id' => $id, 'title' => $title]);
        return self::back(self::BACK, $msg);
    }

    /** Numéro, adresse (complète ou non) ou titre exact d'une personne publiée. */
    private static function fiche(string $v): ?int
    {
        $v = trim($v);
        if ($v === '') {
            return null;
        }
        $s = null;
        if (preg_match('/^\d+$/', $v)) {
            $s = Index::get((int) $v);
        } else {
            $path = preg_match('#^https?://[^/]+(/.*)?$#i', $v, $m) ? ($m[1] ?? '/') : $v;
            $s = Index::byPath('/' . trim(parse_url($path, PHP_URL_PATH) ?: '', '/') . '/');
            if (!$s) {
                foreach (Index::published('personne') as $p) {
                    if (mb_strtolower((string) $p['title']) === mb_strtolower($v)) {
                        $s = $p;
                        break;
                    }
                }
            }
        }
        return $s && $s['type'] === 'personne' && Index::visible($s) ? (int) $s['id'] : null;
    }
}
