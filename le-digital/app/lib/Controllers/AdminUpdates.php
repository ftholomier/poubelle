<?php
declare(strict_types=1);

namespace LD\Controllers;

use LD\Auth;
use LD\Http;
use LD\Router;
use LD\Secrets;
use LD\Settings;
use LD\Store;
use LD\Updater;

/**
 * Back-office › Mises à jour : version installée, dernière version sur GitHub, changements,
 * mise à jour en un clic et retour arrière. Voir LD\Updater.
 */
final class AdminUpdates
{
    public static function routes(Router $r): void
    {
        $g = [Admin::class, 'guard'];
        $r->get('/admin/mises-a-jour', $g([self::class, 'page']));
        $r->post('/admin/mises-a-jour/reglages', $g([self::class, 'saveSettings'], true));
        $r->post('/admin/api/maj/verifier', $g([self::class, 'check'], true));
        $r->post('/admin/api/maj/appliquer', $g([self::class, 'apply'], true));
        $r->post('/admin/api/maj/retour', $g([self::class, 'rollback'], true));
    }

    public static function page(): never
    {
        // Vérification sur GitHub au plus une fois par heure à l'ouverture de la page.
        $check = Updater::configured() ? Updater::check() : (Updater::state()['check'] ?? null);
        Admin::view('updates', [
            'check'     => $check,
            'installed' => Updater::installed(),
            'available' => Updater::available() !== null,
            'history'   => array_slice(Updater::history(), 0, 12),
            'backups'   => Updater::backups(),
            'cfg'       => Updater::settings() + ['token' => Secrets::masked('github_token')],
            'env'       => [
                'curl'     => function_exists('curl_init'),
                'zip'      => class_exists(\ZipArchive::class),
                'writable' => is_writable(LD_APP) && is_writable(LD_PUBLIC) && is_writable(LD_STORAGE),
            ],
        ], 'Mises à jour');
    }

    private static function by(): string
    {
        $u = Auth::user();
        return (string) (($u['name'] ?? '') ?: ($u['email'] ?? 'Back-office'));
    }

    public static function check(): never
    {
        $c = Updater::check(true);
        if (!empty($c['error'])) {
            Http::fail('Vérification impossible : ' . $c['error'], 422);
        }
        Http::sendJson(['ok' => true, 'available' => Updater::available() !== null, 'commits' => count($c['commits'] ?? [])]);
    }

    public static function apply(): never
    {
        $by = self::by();
        session_write_close();
        try {
            $r = Updater::apply($by);
        } catch (\Throwable $e) {
            Store::appendLog('update', ['event' => 'error', 'message' => $e->getMessage()]);
            Http::fail($e->getMessage(), 500);
        }
        $n = count($r['updated']) + count($r['deleted']);
        Http::sendJson(['ok' => true, 'updated' => count($r['updated']), 'deleted' => count($r['deleted']), 'kept' => $r['kept'],
            'message' => $n ? 'Site mis à jour : ' . $n . ' fichier' . ($n > 1 ? 's' : '') . ' remplacé' . ($n > 1 ? 's' : '') . '.' : 'Le site était déjà à jour.']);
    }

    public static function rollback(): never
    {
        $by = self::by();
        $backup = Http::str('backup', 120);
        session_write_close();
        try {
            $r = Updater::rollback($backup, $by);
        } catch (\Throwable $e) {
            Http::fail($e->getMessage(), 500);
        }
        Http::sendJson(['ok' => true, 'message' => 'Version précédente remise en place (' . $r['restored'] . ' fichier' . ($r['restored'] > 1 ? 's' : '') . ').']);
    }

    public static function saveSettings(): never
    {
        $repo = trim((string) ($_POST['repo'] ?? ''));
        $branch = trim((string) ($_POST['branch'] ?? ''));
        $path = trim(str_replace('\\', '/', (string) ($_POST['path'] ?? '')), '/ ');
        if (preg_match('#github\.com/([\w.-]+/[\w.-]+?)(?:\.git)?(?:/|$)#', $repo, $m)) {
            $repo = $m[1]; // URL du dépôt collée telle quelle
        }
        if ($repo !== '' && !preg_match('#^[\w.-]+/[\w.-]+$#', $repo)) {
            Admin::back('/admin/mises-a-jour', 'err', 'Dépôt invalide : « propriétaire/nom », par exemple ftholomier/poubelle.');
        }
        if ($branch !== '' && !Updater::validRef($branch)) {
            Admin::back('/admin/mises-a-jour', 'err', 'Nom de branche invalide.');
        }
        if ($path !== '' && !Updater::validPath($path)) {
            Admin::back('/admin/mises-a-jour', 'err', 'Dossier invalide (ex. : le-digital, ou vide si le site est à la racine du dépôt).');
        }
        $token = trim((string) ($_POST['token'] ?? ''));
        if ($token !== '') {
            if (!preg_match('/^[\w-]{20,255}$/', $token)) {
                Admin::back('/admin/mises-a-jour', 'err', 'Ce jeton ne ressemble pas à un jeton GitHub (github_pat_… ou ghp_…).');
            }
            Secrets::set('github_token', $token);
        } elseif (!empty($_POST['token_clear'])) {
            Secrets::delete('github_token');
        }
        $s = Store::read('content/settings.json', []);
        $s['updates'] = ['repo' => $repo, 'branch' => $branch ?: 'main', 'path' => $path, 'auto_check' => !empty($_POST['auto_check'])];
        Settings::save($s);
        $c = Updater::configured() ? Updater::check(true) : null;
        if ($c && !empty($c['error'])) {
            Admin::back('/admin/mises-a-jour', 'err', 'Réglages enregistrés, mais la vérification a échoué : ' . $c['error']);
        }
        Admin::back('/admin/mises-a-jour', 'ok', 'Réglages enregistrés.' . ($c ? ' Connexion à GitHub vérifiée.' : ''));
    }
}
