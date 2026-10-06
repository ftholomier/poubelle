<?php
declare(strict_types=1);

namespace App\Admin;

use App\Core\Auth;
use App\Core\JsonStore;
use App\Core\Request;
use App\Core\Response;
use App\Core\Settings;
use App\Data\Activity;
use App\Data\Fiches;
use App\Data\Index;
use App\Services\Backup;
use App\Services\Cron;
use App\Services\Gemini;
use App\Services\Rag;
use App\Services\Translator;

/**
 * Système : traductions anglaises (interface et fiches), journal de l'assistant IA
 * (RGPD), utilisateurs et invitations, réglages (équivalent du .env, secrets
 * chiffrés), page d'attente, sauvegardes et tâches planifiées.
 */
final class System extends Base
{
    private const DICT = DATA_PATH . '/i18n/en.json';
    private const MISSING = STORAGE_PATH . '/i18n-missing-en.json';

    // ------------------------------------------------------------------ traductions

    public static function translations(Request $req): Response
    {
        $tab = $req->str('onglet') === 'fiches' ? 'fiches' : 'interface';
        $vars = ['tab' => $tab, 'gemini' => Translator::enabled(), 'auto' => (bool) Settings::get('translation.auto_translate', true)];
        if ($tab === 'interface') {
            $dict = JsonStore::read(self::DICT, []) ?: [];
            $missing = JsonStore::read(self::MISSING, []) ?: [];
            foreach ($missing as $fr => $at) {
                if (!isset($dict[$fr]) || $dict[$fr] === '') {
                    $dict[$fr] = '';
                }
            }
            $q = mb_strtolower($req->str('q'));
            $filter = $req->str('filtre');
            $rows = [];
            foreach ($dict as $fr => $en) {
                if ($filter === 'manquants' && $en !== '') {
                    continue;
                }
                if ($q !== '' && !str_contains(mb_strtolower($fr . ' ' . $en), $q)) {
                    continue;
                }
                $rows[] = ['fr' => (string) $fr, 'en' => (string) $en];
            }
            usort($rows, fn ($a, $b) => ($a['en'] === '' ? 0 : 1) <=> ($b['en'] === '' ? 0 : 1) ?: strcoll($a['fr'], $b['fr']));
            $total = count($rows);
            $pages = max(1, (int) ceil($total / 60));
            $page = min($pages, max(1, (int) $req->str('page', '1')));
            $vars += ['rows' => array_slice($rows, ($page - 1) * 60, 60), 'total' => $total, 'page' => $page, 'pages' => $pages, 'q' => $req->str('q'), 'filter' => $filter,
                'count' => count($dict), 'empty' => count(array_filter($dict, fn ($v) => $v === ''))];
        } else {
            $vars += self::ficheProgress($req->str('etat'));
        }
        return self::html('admin/system/translations', $vars, ['title' => 'Traductions anglaises', 'crumb' => 'Système', 'nav' => 'traductions', 'scripts' => ['admin/traductions.js'],
            'tabs' => [['Interface du site', '/admin/traductions', $tab === 'interface'], ['Fiches', '/admin/traductions?onglet=fiches', $tab === 'fiches']]]);
    }

    /** Avancement de la traduction des fiches publiées (mis en cache 10 minutes). */
    private static function ficheProgress(string $state): array
    {
        $cache = STORAGE_PATH . '/cache/i18n-progress.json';
        $data = is_file($cache) && filemtime($cache) > time() - 600 ? JsonStore::read($cache, null) : null;
        if (!is_array($data)) {
            $data = ['counts' => ['none' => 0, 'auto' => 0, 'manual' => 0, 'stale' => 0], 'list' => []];
            foreach (Index::all() as $id => $s) {
                if (!Index::visible($s)) {
                    continue;
                }
                $doc = Fiches::get((int) $id);
                if (!$doc) {
                    continue;
                }
                $st = Translator::status($doc);
                $data['counts'][$st]++;
                $data['list'][] = ['id' => (int) $id, 'title' => $s['title'], 'type' => $s['type'], 'st' => $st, 'modified' => $s['modified']];
            }
            JsonStore::write($cache, $data);
        }
        $list = $state !== '' ? array_values(array_filter($data['list'], fn ($r) => $r['st'] === $state)) : $data['list'];
        usort($list, fn ($a, $b) => strcmp((string) $b['modified'], (string) $a['modified']));
        $fails = JsonStore::read(STORAGE_PATH . '/i18n-fails.json', []) ?: [];
        return ['counts' => $data['counts'], 'list' => array_slice($list, 0, 150), 'state' => $state, 'fails' => count($fails), 'lastError' => Translator::lastError()];
    }

    public static function translationsSave(Request $req): Response
    {
        $in = $req->json() ?: $req->post;
        $action = (string) ($in['action'] ?? 'enregistrer');
        $user = self::actor();
        if ($action === 'enregistrer') {
            $rows = (array) ($in['rows'] ?? []);
            $n = 0;
            JsonStore::update(self::DICT, function ($dict) use ($rows, &$n) {
                $dict = $dict ?: [];
                foreach ($rows as $r) {
                    $fr = (string) ($r['fr'] ?? '');
                    $en = trim((string) ($r['en'] ?? ''));
                    // Texte simple si l'original l'est ; sinon seules les balises autorisées restent.
                    $en = preg_match('#<[a-z/]#i', $fr) ? Html::clean($en) : trim(strip_tags($en));
                    if ($fr === '' || ($dict[$fr] ?? '') === $en) {
                        continue;
                    }
                    $dict[$fr] = mb_substr($en, 0, 2000);
                    $n++;
                }
                return $dict;
            }, []);
            self::forgetMissing(array_column($rows, 'fr'));
            Activity::log($user, "a corrigé $n traduction(s) de l’interface", null);
            return self::json(['ok' => true, 'message' => $n ? "$n traduction(s) enregistrée(s)." : 'Aucune modification.', 'savedLabel' => 'Enregistré à ' . date('H:i')]);
        }
        if ($action === 'gemini-interface') {
            if (!Translator::enabled()) {
                return self::back('/admin/traductions', null, 'Clé Gemini non réglée.');
            }
            @set_time_limit(240);
            $dict = JsonStore::read(self::DICT, []) ?: [];
            $todo = array_keys(array_filter($dict, fn ($v) => $v === ''));
            foreach (array_keys(JsonStore::read(self::MISSING, []) ?: []) as $fr) {
                if (($dict[$fr] ?? '') === '') {
                    $todo[] = (string) $fr;
                }
            }
            $todo = array_slice(array_values(array_unique($todo)), 0, 150);
            if (!$todo) {
                return self::back('/admin/traductions', 'Toute l’interface est déjà traduite.');
            }
            try {
                $tr = [];
                foreach (array_chunk($todo, 50) as $chunk) {
                    $tr += Translator::strings($chunk);
                }
            } catch (\Throwable $e) {
                return self::back('/admin/traductions', null, 'Traduction impossible : ' . $e->getMessage());
            }
            JsonStore::update(self::DICT, function ($d) use ($tr) {
                $d = $d ?: [];
                foreach ($tr as $fr => $en) {
                    if (($d[$fr] ?? '') === '' && trim((string) $en) !== '') {
                        $d[$fr] = (string) $en;
                    }
                }
                return $d;
            }, []);
            self::forgetMissing(array_keys($tr));
            Activity::log($user, 'a traduit ' . count($tr) . ' libellé(s) avec Gemini', null);
            return self::back('/admin/traductions', count($tr) . ' libellé(s) traduit(s) avec Gemini : relisez-les (filtre « tous »).' . self::aiCost());
        }
        if ($action === 'gemini-une') {
            // Bouton « Traduire 10 fiches maintenant » : une fiche par appel, la page affiche où il en est.
            if (!Translator::enabled()) {
                return self::json(['ok' => false, 'error' => 'Clé Gemini non réglée.']);
            }
            \App\Core\Session::release(); // les autres pages du back-office restent utilisables
            @set_time_limit(120);
            $r = Translator::run(1, true);
            @unlink(STORAGE_PATH . '/cache/i18n-progress.json');
            $t = $r['tried'] ?? [];
            $last = end($t) ?: null;
            $err = !$r['done'] ? Translator::lastError() : null;
            return self::json(['ok' => true, 'done' => (int) $r['done'], 'todo' => (int) $r['todo'], 'title' => $last['title'] ?? '', 'result' => $last['result'] ?? '',
                'stop' => $last === null || ($err !== null && $err['at'] > time() - 300), 'error' => $err['msg'] ?? '']);
        }
        if ($action === 'gemini-fiches') {
            if (!Translator::enabled()) {
                return self::back('/admin/traductions?onglet=fiches', null, 'Clé Gemini non réglée.');
            }
            @set_time_limit(280);
            \App\Core\Session::release(); // sinon tout le back-office de la personne attend la fin
            $r = Translator::run(max(1, min(30, (int) ($in['n'] ?? 10))), true);
            @unlink(STORAGE_PATH . '/cache/i18n-progress.json');
            $err = ($r['done'] ?? 0) === 0 ? Translator::lastError() : null;
            if ($err) {
                return self::back('/admin/traductions?onglet=fiches', null, 'Aucune fiche traduite : ' . $err['msg']);
            }
            return self::back('/admin/traductions?onglet=fiches', ($r['done'] ?? 0) . ' fiche(s) traduite(s), ' . max(0, (int) ($r['todo'] ?? 0)) . ' restante(s).' . self::aiCost());
        }
        return self::back('/admin/traductions', null, 'Action inconnue.');
    }

    private static function forgetMissing(array $frs): void
    {
        JsonStore::update(self::MISSING, function ($m) use ($frs) {
            $m = $m ?: [];
            $dict = JsonStore::read(self::DICT, []) ?: [];
            foreach ($frs as $fr) {
                if (($dict[$fr] ?? '') !== '') {
                    unset($m[$fr]);
                }
            }
            return $m;
        }, []);
    }

    // ------------------------------------------------------------------ assistant IA

    public static function assistant(Request $req): Response
    {
        $months = Rag::logMonths();
        $month = in_array($req->str('mois'), $months, true) ? $req->str('mois') : ($months[0] ?? null);
        $rows = $month ? Rag::logs($month, 1000) : [];
        $q = mb_strtolower($req->str('q'));
        $filter = $req->str('filtre');
        $all = $rows;
        $rows = array_values(array_filter($rows, fn ($r) => ($q === '' || str_contains(mb_strtolower((string) ($r['q'] ?? '') . ' ' . ($r['a'] ?? '')), $q))
            && match ($filter) {
                'negatifs' => ($r['fb'] ?? 0) < 0,
                'positifs' => ($r['fb'] ?? 0) > 0,
                'erreurs' => empty($r['ok']),
                default => true,
            }));
        $stats = ['questions' => count($all), 'errors' => count(array_filter($all, fn ($r) => empty($r['ok']))), 'up' => count(array_filter($all, fn ($r) => ($r['fb'] ?? 0) > 0)), 'down' => count(array_filter($all, fn ($r) => ($r['fb'] ?? 0) < 0)),
            'tokens' => array_sum(array_map(fn ($r) => (int) ($r['tin'] ?? 0) + (int) ($r['tout'] ?? 0), $all)), 'ms' => $all ? (int) (array_sum(array_column($all, 'ms')) / count($all)) : 0, 'en' => count(array_filter($all, fn ($r) => ($r['lang'] ?? '') === 'en'))];
        $titles = [];
        foreach (array_slice($rows, 0, 200) as $r) {
            foreach ($r['src'] ?? [] as $id) {
                if (is_int($id) || ctype_digit((string) $id)) {
                    $titles[(int) $id] ??= Index::get((int) $id)['title'] ?? null;
                }
            }
        }
        return self::html('admin/system/assistant', [
            'rows' => array_slice($rows, 0, 200), 'total' => count($rows), 'months' => $months, 'month' => $month, 'q' => $req->str('q'), 'filter' => $filter, 'stats' => $stats, 'titles' => $titles,
            'enabled' => Rag::enabled(), 'ready' => Gemini::ready(), 'model' => Gemini::ready() ? Gemini::model() : null, 'embed' => Gemini::embedModel(),
            'logging' => (bool) Settings::get('ai.log_questions', true), 'retention' => (int) Settings::get('ai.log_retention_days', 365), 'index' => Cron::state()['assistant'] ?? null,
        ], ['title' => 'Assistant IA', 'crumb' => 'Système', 'nav' => 'assistant']);
    }

    public static function assistantAction(Request $req): Response
    {
        $action = (string) ($req->post['action'] ?? '');
        if ($action === 'reindexer') {
            @set_time_limit(290);
            try {
                $r = Rag::reindex(null, 400);
                return self::back('/admin/assistant', 'Index de l’assistant mis à jour : ' . ($r['mode'] ?? 'sémantique') . ', ' . (int) ($r['embedded'] ?? 0) . ' fiche(s) indexée(s)' . (!empty($r['remaining']) ? ', ' . (int) $r['remaining'] . ' restante(s) (la tâche planifiée continue)' : '') . '.' . self::aiCost());
            } catch (\Throwable $e) {
                return self::back('/admin/assistant', null, 'Indexation impossible : ' . $e->getMessage());
            }
        }
        if ($action === 'purger') {
            if (!Auth::can('destroy')) {
                return self::back('/admin/assistant', null, 'Réservé aux administrateurs.');
            }
            $month = (string) ($req->post['mois'] ?? '');
            if (preg_match('/^\d{4}-\d{2}$/', $month)) {
                @unlink(STORAGE_PATH . "/ai/log/$month.jsonl");
                Activity::log(self::actor(), 'a effacé le journal de l’assistant', ['title' => $month]);
                return self::back('/admin/assistant', "Journal de $month effacé.");
            }
            $n = Rag::purgeLogs();
            return self::back('/admin/assistant', "$n question(s) au-delà de la durée de conservation effacée(s).");
        }
        return self::back('/admin/assistant', null, 'Action inconnue.');
    }

    public static function assistantExport(Request $req): Response
    {
        $month = $req->str('mois');
        $rows = Rag::logs(preg_match('/^\d{4}-\d{2}$/', $month) ? $month : null, 100000);
        $out = fopen('php://temp', 'w+');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['Date', 'Langue', 'Question', 'Réponse', 'Fiches citées', 'Avis', 'Erreur', 'Modèle', 'Durée (ms)', 'Jetons'], ';');
        foreach ($rows as $r) {
            fputcsv($out, csv_safe([date('d/m/Y H:i', strtotime((string) $r['at'])), $r['lang'] ?? '', $r['q'] ?? '', $r['a'] ?? '', implode(' ', (array) ($r['src'] ?? [])), ($r['fb'] ?? 0) > 0 ? 'utile' : (($r['fb'] ?? 0) < 0 ? 'pas utile' : ''), $r['err'] ?? '', $r['model'] ?? '', $r['ms'] ?? '', (int) ($r['tin'] ?? 0) + (int) ($r['tout'] ?? 0)]), ';');
        }
        rewind($out);
        Activity::log(self::actor(), 'a exporté le journal de l’assistant', ['title' => $month ?: 'complet']);
        return new Response((string) stream_get_contents($out), 200, ['Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="assistant-questions' . ($month ? "-$month" : '') . '.csv"']);
    }

    // ------------------------------------------------------------------ utilisateurs

    public static function users(Request $req): Response
    {
        if ($r = self::denyUnlessAdmin()) {
            return $r;
        }
        $users = Auth::users();
        uasort($users, fn ($a, $b) => [$a['status'] === 'disabled', $a['role'] !== 'admin', $a['name']] <=> [$b['status'] === 'disabled', $b['role'] !== 'admin', $b['name']]);
        $counts = [];
        foreach (Activity::recent(3000) as $a) {
            if (!empty($a['uid'])) {
                $counts[$a['uid']] = ($counts[$a['uid']] ?? 0) + 1;
            }
        }
        return self::html('admin/system/users', ['users' => $users, 'me' => Auth::user(), 'counts' => $counts, 'link' => \App\Core\Session::pull('invite_link'), 'mismatch' => self::addressMismatch($req)], ['title' => 'Utilisateurs', 'crumb' => 'Système', 'nav' => 'utilisateurs']);
    }

    public static function usersAction(Request $req): Response
    {
        if ($r = self::denyUnlessAdmin()) {
            return $r;
        }
        $action = (string) ($req->post['action'] ?? '');
        $id = (string) ($req->post['id'] ?? '');
        $me = Auth::user();
        $actor = self::actor();
        try {
            switch ($action) {
                case 'inviter':
                    $u = Auth::createUser((string) ($req->post['email'] ?? ''), Html::line($req->post['name'] ?? '', 80), (string) ($req->post['role'] ?? 'user'));
                    $token = Auth::invite($u['id']);
                    $sent = Account::mailLink($u, $token);
                    Activity::log($actor, 'a invité', ['title' => $u['name'] . ' (' . Auth::ROLES[$u['role']] . ')']);
                    if (!$sent) {
                        \App\Core\Session::set('invite_link', ['name' => $u['name'], 'url' => base_url() . '/admin/invitation/' . $token]);
                    }
                    return self::back('/admin/utilisateurs', $sent ? 'Invitation envoyée à ' . $u['email'] . '.' : null, $sent ? null : 'L’e-mail n’a pas pu partir (réglages e-mail). Transmettez le lien d’invitation affiché ci-dessous.');
                case 'renvoyer':
                case 'reinitialiser':
                    $u = Auth::find($id);
                    if (!$u) {
                        break;
                    }
                    if ($u['status'] === 'disabled') {
                        return self::back('/admin/utilisateurs', null, 'Ce compte est désactivé : réactivez-le d’abord.');
                    }
                    $token = Auth::invite($id);
                    $sent = Account::mailLink($u, $token, $action === 'renvoyer' && $u['status'] !== 'active' ? 'invite' : 'reset');
                    if (!$sent) {
                        \App\Core\Session::set('invite_link', ['name' => $u['name'], 'url' => base_url() . '/admin/invitation/' . $token]);
                    }
                    return self::back('/admin/utilisateurs', $sent ? 'Lien envoyé à ' . $u['email'] . '.' : null, $sent ? null : 'L’e-mail n’a pas pu partir : transmettez le lien affiché ci-dessous.');
                case 'role':
                    $u = Auth::find($id);
                    $role = (string) ($req->post['role'] ?? 'user');
                    if (!$u || !isset(Auth::ROLES[$role])) {
                        break;
                    }
                    if ($u['role'] === 'admin' && $role !== 'admin' && Auth::countAdmins() <= 1) {
                        return self::back('/admin/utilisateurs', null, 'Il faut garder au moins un administrateur.');
                    }
                    Auth::update($id, ['role' => $role]);
                    Activity::log($actor, 'a changé le niveau de', ['title' => $u['name'] . ' → ' . Auth::ROLES[$role]]);
                    return self::back('/admin/utilisateurs', 'Niveau d’accès modifié.');
                case 'desactiver':
                case 'reactiver':
                    $u = Auth::find($id);
                    if (!$u || $u['id'] === $me['id']) {
                        return self::back('/admin/utilisateurs', null, 'Vous ne pouvez pas désactiver votre propre compte.');
                    }
                    if ($action === 'desactiver' && $u['role'] === 'admin' && Auth::countAdmins() <= 1) {
                        return self::back('/admin/utilisateurs', null, 'Il faut garder au moins un administrateur actif.');
                    }
                    Auth::update($id, ['status' => $action === 'desactiver' ? 'disabled' : ($u['password'] ? 'active' : 'invited'), 'invite' => null]);
                    Activity::log($actor, $action === 'desactiver' ? 'a désactivé le compte de' : 'a réactivé le compte de', ['title' => $u['name']]);
                    return self::back('/admin/utilisateurs', $action === 'desactiver' ? 'Compte désactivé : la personne est déconnectée.' : 'Compte réactivé.');
                case 'supprimer':
                    $u = Auth::find($id);
                    if (!$u || $u['id'] === $me['id']) {
                        return self::back('/admin/utilisateurs', null, 'Vous ne pouvez pas supprimer votre propre compte.');
                    }
                    if ($u['role'] === 'admin' && Auth::countAdmins() <= 1) {
                        return self::back('/admin/utilisateurs', null, 'Il faut garder au moins un administrateur.');
                    }
                    Auth::delete($id);
                    Activity::log($actor, 'a supprimé le compte de', ['title' => $u['name']]);
                    return self::back('/admin/utilisateurs', 'Compte supprimé (son nom reste dans l’historique des fiches).');
            }
        } catch (\RuntimeException $e) {
            return self::back('/admin/utilisateurs', null, $e->getMessage());
        }
        return self::back('/admin/utilisateurs', null, 'Action inconnue.');
    }

    // ------------------------------------------------------------------ réglages

    /** Groupes de réglages accessibles à tous (contenus éditoriaux) ; les autres sont réservés aux administrateurs. */
    private const EDITORIAL_GROUPS = ['waiting'];

    /** Page d'attente : écran dédié (accessible à tous les membres de l'équipe). */
    public static function waiting(Request $req): Response
    {
        return self::settings($req, 'waiting');
    }

    public static function settings(Request $req, ?string $force = null): Response
    {
        $schema = Settings::schema();
        $group = $force ?? (isset($schema[$req->str('groupe')]) ? $req->str('groupe') : 'general');
        // Groupe réglé dans un autre écran (site de l'association : pavé dédié).
        if ($force === null && !empty($schema[$group]['hidden'])) {
            return Response::redirect($group === 'vitrine' ? '/admin/association/reglages' : '/admin/reglages');
        }
        if ($group === 'waiting' && $force === null) {
            return Response::redirect('/admin/page-attente');
        }
        if (!in_array($group, self::EDITORIAL_GROUPS, true) && ($r = self::denyUnlessAdmin())) {
            return $r;
        }
        $values = [];
        foreach ($schema[$group]['fields'] as $k => $f) {
            $key = "$group.$k";
            $values[$k] = ($f['type'] ?? '') === 'secret' ? Settings::hasValue($key) : Settings::get($key);
        }
        $options = [];
        if ($group === 'audio' || $group === 'recherche') {
            $m = Gemini::ready() ? Gemini::models() : ['tts' => [], 'generate' => []];
            $options['gemini_tts_models'] = (array) ($m['tts'] ?? []);
            $options['gemini_generate_models'] = (array) ($m['generate'] ?? []);
        }
        if ($group === 'ai') {
            $m = Gemini::ready() ? Gemini::models() : ['generate' => [], 'embed' => [], 'error' => null];
            $options['gemini_generate_models'] = (array) ($m['generate'] ?? []);
            $options['gemini_embedding_models'] = (array) ($m['embed'] ?? []);
            $options['_error'] = $m['error'] ?? null;
            $options['_at'] = $m['at'] ?? null;
        }
        $isWaiting = $group === 'waiting';
        $tabs = [];
        if (Auth::isAdmin()) {
            foreach ($schema as $g => $t) {
                if (empty($t['hidden'])) {
                    $tabs[] = [$t['label'], '/admin/reglages?groupe=' . $g, $g === $group];
                }
            }
        }
        return self::html('admin/system/settings', [
            'group' => $group, 'schema' => $schema[$group], 'values' => $values, 'options' => $options, 'status' => self::groupStatus($group),
        ], ['title' => $isWaiting ? 'Page d’attente' : 'Réglages · ' . $schema[$group]['label'], 'crumb' => $isWaiting ? 'Éditorial' : 'Système', 'nav' => $isWaiting ? 'attente' : 'reglages', 'tabs' => $isWaiting ? [] : $tabs]);
    }

    /** Petits contrôles affichés en tête de groupe (clé valide, e-mail configuré…). */
    private static function groupStatus(string $group): array
    {
        return match ($group) {
            'ai' => ['ready' => Gemini::ready(), 'check' => null],
            'mail' => ['from' => (string) Settings::get('mail.from_email', '') ?: (string) Settings::get('general.contact_email', '')],
            'donations' => ['methods' => \App\Front\Donations::methods(), 'test' => \App\Front\Donations::testMode()],
            'waiting' => ['enabled' => (bool) Settings::get('waiting.enabled', false), 'teaser' => is_file(APP_DIR . '/Resources/video/teaser.mp4')],
            default => [],
        };
    }

    /** Bouton « Rafraîchir toutes les pages maintenant » (Réglages › Général) : vide le cache des pages. */
    public static function purgePages(Request $req): Response
    {
        if (!Auth::can('settings')) {
            return self::back('/admin/reglages?groupe=general', null, 'Réservé aux administrateurs.');
        }
        $n = \App\Core\PageCache::purge();
        Activity::log(self::actor(), 'a vidé le cache des pages', null);
        return self::back('/admin/reglages?groupe=general', 'Cache des pages vidé (' . $n . ' fichier(s)) : chaque page est refaite à la prochaine visite.');
    }

    public static function settingsSave(Request $req): Response
    {
        $in = $req->json() ?: $req->post;
        $schema = Settings::schema();
        $group = (string) ($in['_group'] ?? '');
        if (!isset($schema[$group])) {
            return self::json(['error' => 'Groupe de réglages inconnu.'], 400);
        }
        if (!in_array($group, self::EDITORIAL_GROUPS, true) && !Auth::can('settings')) {
            return self::json(['error' => 'Réservé aux administrateurs.'], 403);
        }
        $changes = [];
        foreach ($schema[$group]['fields'] as $k => $f) {
            $type = $f['type'] ?? 'text';
            $key = "$group.$k";
            if ($type === 'secret') {
                if (!empty($in['_delete'][$k])) {
                    $changes[$key] = '__delete__';
                } elseif (trim((string) ($in[$k] ?? '')) !== '') {
                    $changes[$key] = trim((string) $in[$k]);
                }
                continue;
            }
            if (!array_key_exists($k, $in)) {
                if ($type === 'bool') {
                    $changes[$key] = false;
                }
                continue;
            }
            $v = $in[$k];
            switch ($type) {
                case 'bool':
                    $changes[$key] = (bool) $v;
                    break;
                case 'number':
                    $n = str_replace(',', '.', trim((string) $v));
                    if ($n === '') {
                        $changes[$key] = null;
                        break;
                    }
                    if (!is_numeric($n)) {
                        return self::json(['error' => '« ' . $f['label'] . ' » doit être un nombre.', 'field' => $k], 422);
                    }
                    $n = str_contains($n, '.') ? (float) $n : (int) $n;
                    if ((isset($f['min']) && $n < $f['min']) || (isset($f['max']) && $n > $f['max'])) {
                        return self::json(['error' => '« ' . $f['label'] . ' » doit être compris entre ' . ($f['min'] ?? '…') . ' et ' . ($f['max'] ?? '…') . '.', 'field' => $k], 422);
                    }
                    $changes[$key] = $n;
                    break;
                case 'email':
                    $s = trim((string) $v);
                    if ($s !== '' && !filter_var($s, FILTER_VALIDATE_EMAIL)) {
                        return self::json(['error' => '« ' . $f['label'] . ' » : adresse e-mail invalide.', 'field' => $k], 422);
                    }
                    $changes[$key] = $s;
                    break;
                case 'url':
                    $s = rtrim(trim((string) $v), '/');
                    if ($s !== '' && !preg_match('#^https?://[^\s]+$#i', $s)) {
                        return self::json(['error' => '« ' . $f['label'] . ' » : adresse invalide (https://…).', 'field' => $k], 422);
                    }
                    $changes[$key] = $s;
                    break;
                case 'wysiwyg':
                    $changes[$key] = Html::clean((string) $v);
                    break;
                case 'date':
                    $changes[$key] = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $v) ? (string) $v : '';
                    break;
                case 'datetime':
                    $changes[$key] = preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', (string) $v) ? (string) $v : '';
                    break;
                case 'image':
                    $r = Html::line($v, 300);
                    $changes[$key] = $r !== '' && \App\Data\Media::get($r) ? $r : '';
                    break;
                case 'select':
                    $s = (string) $v;
                    if (isset($f['options']) && !isset($f['options'][$s])) {
                        return self::json(['error' => '« ' . $f['label'] . ' » : valeur non permise.', 'field' => $k], 422);
                    }
                    $changes[$key] = $s;
                    break;
                default:
                    $changes[$key] = Html::line($v, 1000);
            }
        }
        if ($group === 'translation' && isset($changes['translation.languages'])) {
            $codes = array_values(array_intersect(['fr', 'en'], array_map('trim', explode(',', (string) $changes['translation.languages']))));
            $changes['translation.languages'] = implode(',', in_array('fr', $codes, true) ? $codes : array_merge(['fr'], $codes));
        }
        Settings::save($changes);
        Settings::reset();
        if ($group === 'ai') {
            @unlink(STORAGE_PATH . '/cache/gemini-models.json');
        }
        if (in_array($group, ['waiting', 'general', 'privacy'], true)) {
            @unlink(STORAGE_PATH . '/cache/sitemap.xml');
        }
        if ($group === 'donations') {
            \App\Front\Donations::rebuildStats();
        }
        Activity::log(self::actor(), 'a modifié les réglages', ['title' => $schema[$group]['label']]);
        $msg = 'Réglages « ' . $schema[$group]['label'] . ' » enregistrés.';
        if ($group === 'waiting') {
            $msg = Settings::get('waiting.enabled', false) ? 'Page d’attente ACTIVÉE : les visiteurs ne voient plus qu’elle et rien n’est indexé (vous voyez le site car vous êtes connecté).' : 'Page d’attente désactivée : le site est ouvert à tous' . (Settings::get('general.noindex', false) ? ' (toujours masqué aux moteurs de recherche : Réglages › Général).' : ' et aux moteurs de recherche.');
        }
        return self::json(['ok' => true, 'message' => $msg, 'savedLabel' => 'Enregistré à ' . date('H:i'), 'reload' => $group === 'ai' || $group === 'waiting']);
    }

    // ------------------------------------------------------------------ sauvegardes

    public static function backups(Request $req): Response
    {
        return self::html('admin/system/backups', ['list' => Backup::list(), 'enabled' => (bool) Settings::get('backups.enabled', true), 'hour' => (int) Settings::get('backups.hour', 3), 'keep' => (int) Settings::get('backups.keep', 14), 'disk' => @disk_free_space(STORAGE_PATH) ?: null],
            ['title' => 'Sauvegardes', 'crumb' => 'Système', 'nav' => 'sauvegardes']);
    }

    public static function backupsAction(Request $req): Response
    {
        // Une sauvegarde contient la clé de chiffrement, les réglages et les comptes : administrateurs seulement.
        if ($deny = self::denyUnlessAdmin()) {
            return $deny;
        }
        $action = (string) ($req->post['action'] ?? '');
        if ($action === 'lancer') {
            @set_time_limit(600);
            $r = Backup::run(true, !empty($req->post['photos']));
            Activity::log(self::actor(), 'a lancé une sauvegarde', ['title' => $r['file'] ?? '']);
            return isset($r['error']) ? self::back('/admin/sauvegardes', null, $r['error']) : self::back('/admin/sauvegardes', 'Sauvegarde créée : ' . $r['file'] . ' (' . Base::size($r['size']) . ', ' . $r['files'] . ' fichiers).');
        }
        if ($action === 'supprimer') {
            if (!Auth::can('destroy')) {
                return self::back('/admin/sauvegardes', null, 'Réservé aux administrateurs.');
            }
            $p = Backup::path((string) ($req->post['file'] ?? ''));
            if ($p) {
                @unlink($p);
                Activity::log(self::actor(), 'a supprimé une sauvegarde', ['title' => basename($p)]);
            }
            return self::back('/admin/sauvegardes', 'Sauvegarde supprimée.');
        }
        return self::back('/admin/sauvegardes', null, 'Action inconnue.');
    }

    public static function backupDownload(Request $req, string $file): Response
    {
        if ($deny = self::denyUnlessAdmin()) {
            return $deny;
        }
        $p = Backup::path($file);
        if (!$p) {
            return Response::notFound();
        }
        Activity::log(self::actor(), 'a téléchargé une sauvegarde', ['title' => $file]);
        $res = new Response('', 200, ['Content-Type' => 'application/zip', 'Content-Disposition' => 'attachment; filename="' . $file . '"', 'Content-Length' => (string) filesize($p)]);
        $res->file = $p;
        return $res;
    }

    // ------------------------------------------------------------------ tâches planifiées

    public static function tasks(Request $req): Response
    {
        $state = Cron::state();
        return self::html('admin/system/tasks', ['state' => $state, 'tasks' => Cron::TASKS, 'last' => $state['_last'] ?? null], ['title' => 'Tâches planifiées', 'crumb' => 'Système', 'nav' => 'taches']);
    }

    public static function tasksRun(Request $req): Response
    {
        // Certaines tâches envoient des e-mails ou purgent des données : administrateurs seulement.
        if ($deny = self::denyUnlessAdmin()) {
            return $deny;
        }
        $task = (string) ($req->post['task'] ?? '');
        if (!isset(Cron::TASKS[$task])) {
            return self::back('/admin/taches', null, 'Tâche inconnue.');
        }
        @set_time_limit(290);
        ob_start();
        $r = Cron::run($task);
        ob_end_clean();
        if (!empty($r['_busy'])) {
            return self::back('/admin/taches', null, 'Les tâches planifiées sont déjà en train de tourner : réessayez dans une minute.');
        }
        $res = $r[$task] ?? null;
        Activity::log(self::actor(), 'a lancé la tâche', ['title' => Cron::TASKS[$task][1]]);
        if (!$res) {
            return self::back('/admin/taches', '« ' . Cron::TASKS[$task][1] . ' » : rien à faire pour le moment.');
        }
        return $res['ok']
            ? self::back('/admin/taches', '« ' . Cron::TASKS[$task][1] . ' » terminée en ' . number_format($res['ms'] / 1000, 1, ',', ' ') . ' s.')
            : self::back('/admin/taches', null, '« ' . Cron::TASKS[$task][1] . ' » a échoué : ' . (is_string($res['result']) ? $res['result'] : ''));
    }
}
