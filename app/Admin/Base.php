<?php
declare(strict_types=1);

namespace App\Admin;

use App\Core\Auth;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Data\Derived;
use App\Front\Site;
use App\Services\Backup;

/**
 * Outils communs aux écrans du back-office : rendu dans la coque (barre latérale,
 * en-tête, onglets), messages flash, contrôle des droits, réponses JSON.
 */
class Base
{
    /** Menu latéral : groupe => [clé, libellé, adresse, réservé admin ?] */
    public const NAV = [
        'Pilotage' => [
            ['dash', 'Tableau de bord', '/admin', false],
            ['qualite', 'Qualité', '/admin/qualite', false],
            ['journal', 'Journal', '/admin/journal', false],
        ],
        'Contenus' => [
            ['matchs', 'Matchs', '/admin/matchs', false],
            ['personnes', 'Personnes', '/admin/personnes', false],
            ['articles', 'Articles & pages', '/admin/articles', false],
            ['objets', 'Objets (réserves)', '/admin/objets', false],
            ['referentiels', 'Saisons, adversaires, lieux', '/admin/referentiels', false],
            ['medias', 'Médiathèque', '/admin/medias', false],
        ],
        'Éditorial' => [
            ['accueil', 'Accueil & bandeau', '/admin/accueil', false],
            ['moments', '100 moments', '/admin/moments', false],
            ['rubriques', 'Rubriques & menus', '/admin/rubriques', false],
            ['redirections', 'Redirections', '/admin/redirections', false],
            ['attente', 'Page d’attente', '/admin/page-attente', false],
        ],
        'Interactif' => [
            ['interactif', 'Quiz, frise, carte…', '/admin/interactif', false],
            ['onze', 'Onze & album', '/admin/onze', false],
        ],
        'Communauté' => [
            ['contributions', 'Contributions', '/admin/contributions', false],
            ['messages', 'Messages', '/admin/messages', false],
            ['newsletter', 'Newsletter', '/admin/newsletter', false],
            ['dons', 'Dons', '/admin/dons', false],
        ],
        'Système' => [
            ['traductions', 'Traductions EN', '/admin/traductions', false],
            ['assistant', 'Assistant IA', '/admin/assistant', false],
            ['utilisateurs', 'Utilisateurs', '/admin/utilisateurs', true],
            ['reglages', 'Réglages', '/admin/reglages', true],
            ['sauvegardes', 'Sauvegardes', '/admin/sauvegardes', false],
            ['taches', 'Tâches planifiées', '/admin/taches', false],
        ],
        'Aide' => [
            ['aide', 'Guide d’utilisation', '/admin/aide', false],
        ],
    ];

    /**
     * Rend un écran dans la coque du back-office.
     * $meta : title, crumb, nav (clé active), tabs [[libellé, url, actif, compteur]], bare (sans coque)
     */
    public static function page(string $tpl, array $vars, array $meta): string
    {
        $vars['flash'] = $vars['flash'] ?? Session::takeFlash();
        $body = View::render($tpl, $vars + ['user' => Auth::user()]);
        if (!empty($meta['bare'])) {
            return View::render('admin/bare', ['content' => $body, 'meta' => $meta]);
        }
        // Les messages (« Enregistré », erreurs) s'affichent dans la coque, sauf si l'écran les montre lui-même.
        $flash = !empty($meta['own_flash']) ? [] : $vars['flash'];
        return View::render('admin/layout', ['content' => $body, 'meta' => $meta, 'user' => Auth::user(), 'badges' => self::badges(), 'side' => self::sideStatus(), 'flash' => $flash]);
    }

    public static function html(string $tpl, array $vars, array $meta, int $status = 200): Response
    {
        return Response::html(self::page($tpl, $vars, $meta), $status);
    }

    /** Redirection avec message (« ok » ou « error »). */
    public static function back(string $url, ?string $ok = null, ?string $error = null): Response
    {
        if ($ok) {
            Session::flash('ok', $ok);
        }
        if ($error) {
            Session::flash('error', $error);
        }
        return Response::redirect($url, 303);
    }

    public static function json(mixed $data, int $status = 200): Response
    {
        return Response::json($data, $status);
    }

    /** Écran réservé aux administrateurs : renvoie une réponse 403 si besoin. */
    public static function denyUnlessAdmin(): ?Response
    {
        if (Auth::isAdmin()) {
            return null;
        }
        return self::html('admin/message', ['title' => 'Accès réservé', 'text' => 'Cette action est réservée aux administrateurs du back-office.', 'back' => '/admin'], ['title' => 'Accès réservé'], 403);
    }

    public static function actor(): array
    {
        return Auth::actor() ?? ['name' => 'Inconnu'];
    }

    /** Compteurs du menu (contributions, messages, alertes). */
    public static function badges(): array
    {
        static $b = null;
        if ($b !== null) {
            return $b;
        }
        $b = [];
        $b['contributions'] = count(array_filter(\App\Admin\Community::contributionList(), fn ($c) => ($c['status'] ?? 'nouveau') === 'nouveau'));
        $b['messages'] = count(array_filter(\App\Admin\Community::messageList(), fn ($m) => ($m['status'] ?? 'nouveau') === 'nouveau'));
        $high = 0;
        foreach (Derived::get()['quality'] ?? [] as $q) {
            if (($q['sev'] ?? '') === 'haute') {
                $high++;
            }
        }
        $b['qualite'] = $high;
        return $b;
    }

    private static function sideStatus(): array
    {
        $last = Backup::list()[0] ?? null;
        $ok = $last && strtotime($last['at']) > time() - 36 * 3600;
        return ['backup' => $last, 'backup_ok' => $ok, 'days' => Site::daysToCentenary()];
    }

    /** Date relative lisible (« il y a 2 h », « hier », « 12/09 »). */
    public static function ago(?string $iso): string
    {
        if (!$iso || !($ts = strtotime($iso))) {
            return '—';
        }
        $d = time() - $ts;
        return match (true) {
            $d < 60 => 'à l’instant',
            $d < 3600 => 'il y a ' . intdiv($d, 60) . ' min',
            $d < 86400 && date('Y-m-d', $ts) === date('Y-m-d') => 'aujourd’hui ' . date('H:i', $ts),
            date('Y-m-d', $ts) === date('Y-m-d', strtotime('-1 day')) => 'hier ' . date('H:i', $ts),
            $d < 300 * 86400 => date('d/m', $ts),
            default => date('d/m/Y', $ts),
        };
    }

    public static function initials(string $name): string
    {
        $parts = preg_split('/[\s.-]+/u', trim($name)) ?: [];
        $i = '';
        foreach (array_slice(array_filter($parts), 0, 2) as $p) {
            $i .= mb_strtoupper(mb_substr($p, 0, 1));
        }
        return $i ?: '?';
    }

    public static function size(int $bytes): string
    {
        return match (true) {
            $bytes >= 1 << 30 => number_format($bytes / (1 << 30), 2, ',', ' ') . ' Go',
            $bytes >= 1 << 20 => number_format($bytes / (1 << 20), 1, ',', ' ') . ' Mo',
            $bytes >= 1024 => number_format($bytes / 1024, 0, ',', ' ') . ' Ko',
            default => $bytes . ' o',
        };
    }
}
