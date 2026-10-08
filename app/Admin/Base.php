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
use App\Services\EditLock;

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
            ['stats', 'Statistiques', '/admin/statistiques', true],
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
            ['trouvailles', 'Trouvailles (archives)', '/admin/trouvailles', false],
            ['archives', 'Archives à ranger', '/admin/archives', false],
            ['livre', 'Livre des récits', '/admin/livre', true],
            ['maillots', 'Maillots du livre', '/admin/maillots', false],
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
            ['retro', 'Rétro-Direct', '/admin/retro-direct', false],
            ['quizlive', 'Quiz du club-house', '/admin/quiz-club-house', false],
            ['souvenirs', 'Kit souvenirs', '/admin/souvenirs', false],
            ['murs', 'Murs de photos', '/admin/murs-photos', false],
        ],
        'Communauté' => [
            ['contributions', 'Contributions', '/admin/contributions', false],
            ['messages', 'Messages', '/admin/messages', false],
            ['newsletter', 'Newsletter', '/admin/newsletter', false],
            ['notifications', 'Notifications', '/admin/notifications', true],
            ['carnets', 'Carnets du supporter', '/admin/carnets', false],
            ['dons', 'Dons', '/admin/dons', false],
        ],
        'Boutique' => [
            ['boutique', 'Tableau de bord', '/admin/boutique', true],
            ['boutique-commandes', 'Commandes', '/admin/boutique/commandes', true],
            ['boutique-releves', 'Relevés imprimeur', '/admin/boutique/releves', true],
            ['boutique-promos', 'Codes promo', '/admin/boutique/promos', true],
            ['boutique-modeles', 'Modèles', '/admin/boutique/modeles', true],
            ['boutique-textes', 'Banque de textes', '/admin/boutique/textes', true],
            ['boutique-reglages', 'Réglages', '/admin/boutique/reglages', true],
            ['boutique-supports', 'Supports', '/admin/boutique/supports', true],
        ],
        'Site de l’association' => [
            ['asso', 'Tableau de bord', '/admin/association', true],
            ['asso-attente', 'Page d’attente', '/admin/association/attente', true],
            ['asso-contenus', 'Contenus', '/admin/association/contenus/pages', true],
            ['asso-adhesions', 'Adhésions', '/admin/association/adhesions', true],
            ['asso-benevoles', 'Bénévoles', '/admin/association/benevoles', true],
            ['asso-reglages', 'Réglages du site', '/admin/association/reglages', true],
        ],
        'Système' => [
            ['traductions', 'Traductions EN', '/admin/traductions', false],
            ['assistant', 'Assistant IA', '/admin/assistant', true],
            ['audio', 'Fiches audio', '/admin/audio', true],
            ['reprise', 'Reprise 1928-1969', '/admin/reprise-1928-1969', true],
            ['feuilles', 'Feuilles de match', '/admin/import-feuilles', true],
            ['couts', 'Coûts IA', '/admin/couts-ia', true],
            ['utilisateurs', 'Utilisateurs', '/admin/utilisateurs', true],
            ['reglages', 'Réglages', '/admin/reglages', true],
            ['sauvegardes', 'Sauvegardes', '/admin/sauvegardes', true],
            ['taches', 'Tâches planifiées', '/admin/taches', true],
            ['majs', 'Mises à jour', '/admin/mises-a-jour', true],
        ],
        'Aide' => [
            ['aide', 'Guide d’utilisation', '/admin/aide', false],
        ],
    ];

    /** Liens du bouton « + Nouveau » (et des favoris, rubrique « Créer »). */
    public static function createLinks(bool $admin): array
    {
        $new = [
            ['Fiche match', '/admin/fiche/nouvelle/match'],
            ['Personne', '/admin/fiche/nouvelle/personne'],
            ['Article', '/admin/fiche/nouvelle/article'],
            ['Page', '/admin/fiche/nouvelle/page'],
            ['Objet des réserves', '/admin/fiche/nouvelle/objet'],
            ['Moment du centenaire', '/admin/fiche/nouvelle/moment'],
            ['Question de quiz', '/admin/collection/quiz#nouveau'],
            ['Message du bandeau', '/admin/accueil#bandeau'],
        ];
        if ($admin) {
            $new[] = ['Inviter un utilisateur', '/admin/utilisateurs#inviter'];
        }
        return $new;
    }

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

    /** Clé de verrou d'un écran nommé (« ecran:rubrique-annees-90 »). */
    public static function lockKey(string $prefix, string $name): string
    {
        return $prefix . substr(trim((string) preg_replace('/[^a-z0-9_-]+/', '-', strtolower($name)), '-'), 0, 40);
    }

    /** Pour les bandeaux du verrou de modification : nom, « 10 h 12 » (début), minutes sans activité. */
    public static function lockInfo(array $h): array
    {
        $since = (int) $h['since'];
        $day = date('Y-m-d', $since);
        $when = match (true) {
            $day === date('Y-m-d') => date('G \h i', $since),
            $day === date('Y-m-d', strtotime('-1 day')) => 'hier ' . date('G \h i', $since),
            default => 'le ' . date('d/m', $since),
        };
        return ['name' => (string) $h['name'], 'since' => $when, 'idle' => max(0, intdiv(time() - (int) $h['active'], 60))];
    }

    /**
     * Quelqu'un d'autre modifie ce contenu ($key, voir EditLock) : phrase à afficher, sinon null.
     * Les enregistrements sont alors refusés tant que la personne n'a pas « pris la main ».
     */
    public static function lockMessage(string $key, string $what = 'cette fiche'): ?string
    {
        $u = Auth::actor();
        $h = $u ? EditLock::holder($key, (string) $u['id']) : null;
        if (!$h) {
            return null;
        }
        $i = self::lockInfo($h);
        return $i['name'] . ' modifie ' . $what . ' depuis ' . $i['since'] . ' : vos modifications ne peuvent pas être enregistrées tant que vous n’avez pas pris la main.';
    }

    /** Réponse 423 d'un enregistrement refusé (verrou tenu par quelqu'un d'autre), sinon null. */
    public static function lockedJson(string $key, string $what = 'cette fiche'): ?Response
    {
        $m = self::lockMessage($key, $what);
        return $m === null ? null : self::json(['ok' => false, 'locked' => $m, 'error' => $m], 423);
    }

    /** « Coût : 0,32 centime. » après une action qui a fait appel à Gemini (vide sinon ; coûts visibles des administrateurs seulement). */
    public static function aiCost(): string
    {
        $c = \App\Services\AiCosts::request();
        return $c['calls'] && Auth::isAdmin() ? ' Coût : ' . $c['label'] . '.' : '';
    }

    /** Coût des appels à Gemini de la requête, pour les réponses JSON (administrateurs seulement). */
    public static function aiCostData(): ?array
    {
        return Auth::isAdmin() ? \App\Services\AiCosts::request() : null;
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

    /**
     * Back-office ouvert sur une autre adresse que celle réglée (sous-domaine d'essai avant la
     * bascule) : les liens envoyés par e-mail (invitations, mot de passe oublié) mèneraient à
     * l'adresse réglée. Renvoie [adresse réglée, adresse utilisée], sinon null.
     * @return array{0:string,1:string}|null
     */
    public static function addressMismatch(\App\Core\Request $req): ?array
    {
        $here = strtolower((string) ($req->server['HTTP_HOST'] ?? ''));
        $set = strtolower((string) parse_url(base_url(), PHP_URL_HOST));
        if ($set === '' || !preg_match('/^[a-z0-9.\-]+(:\d+)?$/', $here)) {
            return null;
        }
        $here = (string) preg_replace('/:\d+$/', '', $here);
        $norm = fn (string $h) => (string) preg_replace('/^www\./', '', $h);
        return $norm($set) === $norm($here) ? null : [$set, $here];
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
        // Alertes graves de l'écran Qualité (hors orthographe, relue en tâche de fond) : calculées
        // et vérifications du site (redirections, référentiels, textes de l'interface).
        $high = 0;
        foreach (array_merge(Derived::part('quality'), \App\Services\Controle::siteChecks()) as $q) {
            if (($q['sev'] ?? '') === 'haute' && ($q['code'] ?? '') !== 'nonrelie' && !(isset($q['id']) && \App\Services\QualityAck::acked((int) $q['id'], (string) $q['code'], (string) $q['msg']))) {
                $high++;
            }
        }
        $b['qualite'] = $high;
        // Nouvelle version du code sur GitHub (d'après la dernière vérification, sans appel réseau).
        $b['majs'] = Auth::isAdmin() && \App\Services\Updater::available() ? 1 : 0;
        // Site de l'association (administrateurs) : chèques attendus, propositions de bénévolat nouvelles.
        if (Auth::isAdmin()) {
            $b['asso-adhesions'] = count(array_filter(\App\Vitrine\Membership::all(), fn ($a) => $a['status'] === 'offline'));
            $b['asso-benevoles'] = count(array_filter(\App\Core\JsonStore::read(\App\Vitrine\Forms::VOLUNTEERS, []) ?: [], fn ($v) => ($v['status'] ?? 'nouveau') === 'nouveau'));
        }
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
