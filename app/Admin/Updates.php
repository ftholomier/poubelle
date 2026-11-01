<?php
declare(strict_types=1);

namespace App\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Services\Updater;

/**
 * Système › Mises à jour (administrateurs) : version installée, dernière version sur GitHub,
 * changements, application en un clic, retour arrière. Voir App\Services\Updater.
 */
final class Updates extends Base
{
    public static function index(Request $req): Response
    {
        if ($deny = self::denyUnlessAdmin()) {
            return $deny;
        }
        // Vérification sur GitHub (une fois par heure au plus) : le reste du back-office n'attend pas.
        \App\Core\Session::release();
        return self::html('admin/system/updates', [
            'check' => Updater::check(), 'installed' => Updater::installed(), 'available' => Updater::available() !== null,
            'history' => array_slice(Updater::history(), 0, 10), 'backups' => Updater::backups(),
            'repo' => Updater::repo(), 'branch' => Updater::branch(),
        ], ['title' => 'Mises à jour', 'crumb' => 'Système', 'nav' => 'majs']);
    }

    /**
     * GET /admin/mises-a-jour/progression : avancement de la mise à jour (jauge), interrogé chaque
     * seconde par la page pendant la mise à jour puis pendant le recalcul qui suit. Ces appels
     * déclenchent aussi le recalcul (après la réponse, comme toute page).
     */
    public static function progress(Request $req): Response
    {
        if ($deny = self::denyUnlessAdmin()) {
            return $deny;
        }
        \App\Core\Session::release();
        $step = Updater::progress();
        $since = Updater::refreshing();
        if ($since !== null && ($step['phase'] ?? '') !== 'recalcul') {
            // Marque posée, recalcul pas encore commencé (il démarre à la page suivante, 5 s après).
            $step = ['phase' => 'recalcul', 'pct' => 2, 'label' => 'Recalcul : démarrage…', 'at' => time()];
        }
        $res = self::json(['running' => Updater::running(), 'refreshing' => $since, 'step' => $step]);
        $res->headers['Cache-Control'] = 'no-store';
        return $res;
    }

    /** POST /admin/mises-a-jour : verifier, appliquer, restaurer. */
    public static function action(Request $req): Response
    {
        if ($deny = self::denyUnlessAdmin()) {
            return $deny;
        }
        session_write_close();
        try {
            switch ($req->str('action')) {
                case 'verifier':
                    $c = Updater::check(true);
                    if ($c['error']) {
                        return self::back('/admin/mises-a-jour', null, 'Vérification impossible : ' . $c['error']);
                    }
                    return self::back('/admin/mises-a-jour', Updater::available() ? 'Une nouvelle version est disponible.' : 'Le site est à jour.');
                case 'appliquer':
                    $r = Updater::apply(self::actor());
                    $n = count($r['updated']) + count($r['deleted']);
                    $msg = $n || $r['merged']
                        ? 'Mise à jour appliquée : ' . count($r['updated']) . ' fichier(s) remplacé(s) ou ajouté(s)' . ($r['deleted'] ? ', ' . count($r['deleted']) . ' supprimé(s)' : '') . ($r['merged'] ? ', ' . $r['merged'] . ' libellé(s) anglais ajouté(s)' : '') . '.'
                        : 'Le code était déjà identique à cette version : rien remplacé, aucun cache vidé (le site n’est pas ralenti).';
                    if (($r['via'] ?? '') === 'fichiers' && $n) {
                        $msg .= ' Seuls les fichiers changés ont été téléchargés.';
                    }
                    if ($r['kept']) {
                        $msg .= ' Gardé(s) car modifié(s) sur le serveur : ' . implode(', ', $r['kept']) . '.';
                    }
                    return self::back('/admin/mises-a-jour', $msg);
                case 'restaurer':
                    $r = Updater::rollback($req->str('sauvegarde'), self::actor());
                    return self::back('/admin/mises-a-jour', 'Retour arrière effectué : ' . $r['restored'] . ' fichier(s) remis en place.');
            }
        } catch (\Throwable $e) {
            error_log('[mise à jour] ' . $e->getMessage());
            return self::back('/admin/mises-a-jour', null, $e->getMessage());
        }
        return self::back('/admin/mises-a-jour', null, 'Action inconnue.');
    }
}
