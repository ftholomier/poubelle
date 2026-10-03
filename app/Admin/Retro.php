<?php
declare(strict_types=1);

namespace App\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Data\Activity;
use App\Data\Fiches as Store;
use App\Data\Index;
use App\Services\RetroDirect;

/**
 * Interactif › Rétro-Direct : programme des matchs rejoués en direct (date, heure du coup
 * d'envoi, présentation), anniversaires ronds proposés, public des directs passés.
 */
final class Retro extends Base
{
    public const DAYS = [30, 90, 180, 365];

    public static function index(Request $req): Response
    {
        $now = time();
        $prog = RetroDirect::program($now);
        foreach ($prog as &$e) {
            $e['stats'] = $e['state'] === 'avenir' ? null : RetroDirect::stats($e['id'], $e['date']);
        }
        unset($e);
        $days = in_array((int) $req->str('jours'), self::DAYS, true) ? (int) $req->str('jours') : 90;
        $upcoming = array_values(array_filter($prog, fn ($e) => $e['state'] !== 'termine'));
        $past = array_reverse(array_values(array_filter($prog, fn ($e) => $e['state'] === 'termine')));
        $edit = null;
        if ($req->str('modifier') !== '') {
            [$eid, $edate] = array_pad(explode('|', $req->str('modifier'), 2), 2, '');
            foreach ($prog as $e) {
                if ($e['id'] === (int) $eid && $e['date'] === $edate) {
                    $edit = $e;
                }
            }
        }
        return self::html('admin/collections/retro', [
            'upcoming' => $upcoming, 'past' => array_slice($past, 0, 30), 'edit' => $edit,
            'suggestions' => RetroDirect::suggestions($days, $now, 40), 'days' => $days, 'now' => $now,
            'peak' => $past ? max(array_map(fn ($e) => $e['stats']['peak'], $past)) : 0,
            'etais' => array_sum(array_map(fn ($e) => RetroDirect::etais($e['id']), $prog)),
        ], ['title' => 'Rétro-Direct', 'crumb' => 'Interactif', 'nav' => 'retro', 'scripts' => ['admin/retro.js']]);
    }

    /** POST /admin/retro-direct : programmer (id, date, heure, présentation) ou retirer (id, date). */
    public static function action(Request $req): Response
    {
        $back = '/admin/retro-direct' . (in_array((int) $req->str('jours'), self::DAYS, true) ? '?jours=' . (int) $req->str('jours') : '');
        $action = $req->str('action');
        $id = (int) $req->str('id');
        $date = $req->str('date');
        $s = $id ? Index::get($id) : null;
        if (!$s || $s['type'] !== 'match') {
            return self::back($back, null, 'Choisissez un match dans la liste proposée.');
        }
        $label = trim(($s['m']['home'] ?? '') . ' – ' . ($s['m']['away'] ?? '') . ' (' . substr((string) ($s['m']['date'] ?? ''), 0, 4) . ')');
        if ($action === 'retirer') {
            if (!RetroDirect::remove($id, $date, self::actor())) {
                return self::back($back, null, 'Ce direct n’est plus au programme.');
            }
            Activity::log(self::actor(), 'a retiré du Rétro-Direct', ['title' => $label, 'path' => $s['path']]);
            return self::back($back, 'Direct retiré du programme : ' . $label . '.');
        }
        if ($action !== 'programmer') {
            return self::back($back, null, 'Action inconnue.');
        }
        $time = RetroDirect::validTime($req->str('heure')) ?? '';
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !checkdate((int) substr($date, 5, 2), (int) substr($date, 8, 2), (int) substr($date, 0, 4))) {
            return self::back($back, null, 'Date du direct invalide.');
        }
        if ($time === '') {
            return self::back($back, null, 'Heure du coup d’envoi invalide (format 20:00).');
        }
        $old = $req->str('ancienne_date');
        $start = strtotime("$date $time");
        if ($start < time() - 120 && $old === '') {
            return self::back($back, null, 'Cette date est déjà passée : choisissez une date à venir.');
        }
        if (!Index::visible($s)) {
            return self::back($back, null, 'Ce match n’est pas publié : publiez sa fiche avant de programmer son direct.');
        }
        if (!RetroDirect::playable(Store::get($id) ?? [])) {
            return self::back($back, null, 'Cette fiche n’a pas assez de temps forts datés (' . RetroDirect::MIN_EVENTS . ' au moins, avec leur minute) pour un direct. Complétez le résumé minute par minute dans la fiche.');
        }
        // Le même match à une autre date et à la même heure qu'un autre direct : on prévient sans bloquer.
        $clash = null;
        foreach (RetroDirect::program() as $e) {
            if (!($e['id'] === $id && $e['date'] === ($old ?: $date)) && $start < $e['end'] && $e['start'] < $start + RetroDirect::duration($s) + RetroDirect::AFTER) {
                $clash = $e;
            }
        }
        if ($old !== '' && $old !== $date) {
            RetroDirect::remove($id, $old, self::actor());
        }
        RetroDirect::add($id, $date, $time, $req->str('intro'), self::actor(), $req->str('intro_en'));
        Activity::log(self::actor(), $old !== '' ? 'a modifié le Rétro-Direct' : 'a programmé au Rétro-Direct', ['title' => $label, 'path' => $s['path']]);
        $when = date_fr($date, true) . ' à ' . (int) substr($time, 0, 2) . ' h' . (substr($time, 3) === '00' ? '' : ' ' . substr($time, 3));
        return self::back($back, ($old !== '' ? 'Direct modifié : ' : 'Direct programmé : ') . $label . ', ' . lcfirst($when) . '.'
            . ($clash ? ' Attention : il croise le direct de ' . ($clash['s']['m']['home'] ?? '') . ' – ' . ($clash['s']['m']['away'] ?? '') . '.' : ''));
    }
}
