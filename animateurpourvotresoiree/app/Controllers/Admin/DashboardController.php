<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Env;
use App\Core\Fs;
use App\Core\Request;
use App\Core\Response;
use App\Core\Sanitizer;
use App\Core\Str;
use App\Core\Url;
use App\Services\Ai;
use App\Services\Backup;
use App\Services\Categories;
use App\Services\Cron;
use App\Services\Geo;
use App\Services\Leads;
use App\Services\Notify;
use App\Services\Pros;
use App\Services\Push;
use App\Services\Settings;
use App\Services\Stats;
use App\Services\Store;

/** Tableau de bord, statistiques, recherche globale, notifications, mémo. */
final class DashboardController extends AdminController
{
    public function index(): Response
    {
        $pros = ['active' => 0, 'pending' => 0, 'inactive' => 0, 'suspended' => 0, 'new30' => 0];
        $byCat = [];
        $since30 = date('c', strtotime('-30 days'));
        $latestPros = [];
        foreach (Store::pros()->iterate() as $p) {
            $pros[$p['status']] = ($pros[$p['status']] ?? 0) + 1;
            if ($p['created'] >= $since30 && ($p['status'] !== 'deleted')) {
                $pros['new30']++;
            }
            if ($p['status'] === 'active') {
                $c = $p['cats'][0] ?? '';
                $byCat[$c] = ($byCat[$c] ?? 0) + 1;
            }
            if (in_array($p['status'], ['pending', 'active'], true) && count($latestPros) < 6 && ($p['created'] >= date('c', strtotime('-365 days')))) {
                $latestPros[] = $p;
            }
        }
        arsort($byCat);
        $donut = [];
        foreach (array_slice($byCat, 0, 7, true) as $slug => $n) {
            $donut[Categories::name((string) $slug) ?: 'Autre'] = $n;
        }
        if (count($byCat) > 7) {
            $donut['Autres'] = array_sum(array_slice($byCat, 7));
        }
        // demandes et messages : 30 jours glissants vs 30 jours précédents
        $req = ['now' => 0, 'prev' => 0, 'pending' => []];
        $since60 = date('c', strtotime('-60 days'));
        $reqDays = [];
        foreach (Store::requests()->iterate() as $r) {
            if ($r['created'] < $since60 && count($req['pending']) >= 8) {
                break;
            }
            if ($r['created'] >= $since30) {
                $req['now']++;
                $d = substr($r['created'], 0, 10);
                $reqDays[$d] = ($reqDays[$d] ?? 0) + 1;
            } elseif ($r['created'] >= $since60) {
                $req['prev']++;
            }
            if ($r['status'] === 'pending' && count($req['pending']) < 8) {
                $req['pending'][] = $r;
            }
        }
        $msg = ['now' => 0, 'prev' => 0];
        $msgDays = [];
        foreach (Store::messages()->iterate() as $m) {
            if ($m['created'] < $since60) {
                break;
            }
            if ($m['created'] >= $since30) {
                $msg['now']++;
                $d = substr($m['created'], 0, 10);
                $msgDays[$d] = ($msgDays[$d] ?? 0) + 1;
            } else {
                $msg['prev']++;
            }
        }
        $leads = ['Demandes de devis' => [], 'Messages aux pros' => []];
        foreach (Stats::series('pv', 30) as $d => $_) {
            $leads['Demandes de devis'][$d] = $reqDays[$d] ?? 0;
            $leads['Messages aux pros'][$d] = $msgDays[$d] ?? 0;
        }
        $traffic = ['Pages vues' => Stats::series('pv', 30), 'Visiteurs uniques' => Stats::series('uniq', 30)];
        $uniq30 = array_sum($traffic['Visiteurs uniques']);
        $uniqPrev = Stats::total('uniq', 60) - $uniq30;
        $cron = Cron::status();
        $backups = Backup::available() ? Backup::all() : [];
        $health = [
            ['Tâches planifiées (cron)', $cron['ok'] ? 'ok' : 'bad', $cron['last'] ? 'Dernier passage ' . ago($cron['last']) . ($cron['system'] ? '' : ' (cron automatique du site)') : 'Jamais exécutées : configurez le cron'],
            ['Envoi des emails', Env::get('MAIL_DRIVER', 'log') === 'log' ? 'warn' : 'ok', Env::get('MAIL_DRIVER', 'log') === 'log' ? 'Mode test : les emails sont seulement enregistrés' : 'Pilote ' . Env::get('MAIL_DRIVER')],
            ['Intelligence artificielle', Settings::get('ai.enabled') && Ai::configured() ? 'ok' : 'warn', Settings::get('ai.enabled') ? (Ai::configured() ? 'Active' : 'Clé Gemini absente') : 'Désactivée'],
            ['Notifications push', Push::available() && Push::publicKey() !== '' ? 'ok' : 'warn', Push::available() ? 'Disponibles' : 'Extension OpenSSL incomplète'],
            ['Sauvegardes', $backups && strtotime($backups[0]['date']) > time() - 86400 * 2 ? 'ok' : 'warn', $backups ? 'Dernière ' . ago($backups[0]['date']) : 'Aucune sauvegarde'],
            ['Publicité AdSense', \App\Services\Ads::enabled() ? 'ok' : 'warn', \App\Services\Ads::enabled() ? 'Active (' . \App\Services\Ads::client() . ')' : 'Désactivée'],
            ['Mode debug', Env::bool('APP_DEBUG') && Env::get('APP_ENV') === 'production' ? 'bad' : 'ok', Env::bool('APP_DEBUG') ? 'Activé' : 'Désactivé'],
        ];
        $free = @disk_free_space(STORAGE_PATH);
        if ($free !== false) {
            $health[] = ['Espace disque', $free < 500 * 1024 * 1024 ? 'bad' : 'ok', Fs::humanSize($free) . ' libres'];
        }
        return $this->page('dashboard', [
            'pros' => $pros,
            'donut' => $donut,
            'req' => $req,
            'msg' => $msg,
            'traffic' => $traffic,
            'leads' => $leads,
            'uniq30' => $uniq30,
            'uniqPrev' => $uniqPrev,
            'pv30' => array_sum($traffic['Pages vues']),
            'searches' => Stats::topSearches(12),
            'latestPros' => $latestPros,
            'notifications' => Notify::latest(8),
            'health' => $health,
            'monthly' => Stats::monthly(),
            'reviewsPending' => Store::reviews()->count(static fn ($r) => $r['status'] === 'pending'),
        ], 'Tableau de bord', 'dashboard');
    }

    // ------------------------------------------------------------ statistiques

    public function stats(): Response
    {
        $days = in_array(Request::int('jours', 30), [7, 30, 90, 365], true) ? Request::int('jours', 30) : 30;
        $metrics = [];
        foreach (['pv', 'uniq', 'pro_view', 'phone', 'site', 'devis', 'message', 'search', 'register', 'review', 'chat', 'fav', 'bots'] as $m) {
            $metrics[$m] = Stats::series($m, $days);
        }
        $topPros = Pros::publicIndex();
        uasort($topPros, static fn ($a, $b) => $b['views'] <=> $a['views']);
        $since = date('c', strtotime('-' . $days . ' days'));
        $byDep = [];
        $byCat = [];
        $byType = [];
        $bySource = [];
        foreach (Store::requests()->iterate() as $r) {
            if ($r['created'] < $since) {
                break;
            }
            if ($r['dep'] !== '') {
                $byDep[$r['dep']] = ($byDep[$r['dep']] ?? 0) + 1;
            }
            foreach ((array) $r['cats'] as $c) {
                $byCat[Categories::name($c) ?: $c] = ($byCat[Categories::name($c) ?: $c] ?? 0) + 1;
            }
            $byType[Leads::EVENT_TYPES[$r['type']] ?? 'Autre'] = ($byType[Leads::EVENT_TYPES[$r['type']] ?? 'Autre'] ?? 0) + 1;
            $bySource[$r['src']] = ($bySource[$r['src']] ?? 0) + 1;
        }
        arsort($byDep);
        arsort($byCat);
        arsort($byType);
        $searches = Stats::topSearches(30);
        $ai = [];
        for ($i = $days - 1; $i >= 0 && $i < 31; $i--) {
            $d = date('Y-m-d', strtotime("-$i days"));
            $ai[$d] = (int) (Ai::usage($d)['calls'] ?? 0);
        }
        return $this->page('stats', [
            'days' => $days,
            'metrics' => $metrics,
            'topPros' => array_slice($topPros, 0, 15, true),
            'byDep' => array_slice($byDep, 0, 15, true),
            'byCat' => $byCat,
            'byType' => $byType,
            'bySource' => $bySource,
            'searches' => $searches,
            'monthly' => Stats::monthly(),
            'ai' => $ai,
        ], 'Statistiques', 'stats');
    }

    // ------------------------------------------------------------ recherche

    public function search(): Response
    {
        $q = self::q('q', 100);
        $groups = $q !== '' && mb_strlen($q) >= 2 ? self::searchAll($q) : [];
        if (Request::query('format') === 'json') {
            return Response::json(['groups' => $groups]);
        }
        return $this->page('search', ['q' => $q, 'groups' => $groups], 'Recherche', '');
    }

    /** @return array<string,array<int,array{title:string,sub:string,url:string,kind:string,icon:string}>> */
    public static function searchAll(string $q): array
    {
        $n = Str::norm($q);
        $digits = preg_replace('/\D/', '', $q) ?? '';
        $isId = ctype_digit($q);
        $email = str_contains($q, '@') ? mb_strtolower($q) : '';
        $out = ['Pages du back-office' => [], 'Pros' => [], 'Demandes de devis' => [], 'Messages' => [], 'Contacts' => [], 'Contenus' => []];
        $menu = [
            'tableau de bord' => '', 'statistiques' => 'statistiques', 'pros a valider' => 'pros?statut=pending', 'demandes a moderer' => 'demandes?statut=pending',
            'messages a moderer' => 'messages?statut=pending', 'avis a moderer' => 'avis?statut=pending', 'emailing campagne' => 'emailing', 'modeles emails' => 'emailing/modeles',
            'file d envoi emails' => 'emailing/file', 'prospects' => 'prospects', 'blog articles' => 'blog', 'pages' => 'pages', 'page d accueil' => 'accueil',
            'metiers categories' => 'categories', 'occasions' => 'occasions', 'seo titres descriptions' => 'seo', 'pages locales' => 'seo/pages-locales',
            'redirections 404' => 'seo/redirections', 'robots txt' => 'seo/robots', 'publicite adsense' => 'publicite', 'fonctionnement moderation' => 'reglages/fonctionnement',
            'alertes notifications' => 'reglages/notifications', 'anti spam' => 'antispam', 'intelligence artificielle gemini' => 'ia', 'configuration env smtp' => 'reglages',
            'maintenance sauvegardes import' => 'maintenance', 'journal logs' => 'journal', 'archives' => 'archives', 'utilisateurs' => 'utilisateurs', 'memo' => 'memo', 'mon compte 2fa' => 'mon-compte',
        ];
        foreach ($menu as $label => $path) {
            if (str_contains($label, $n)) {
                $out['Pages du back-office'][] = ['title' => Str::ucfirst($label), 'sub' => Url::admin($path), 'url' => Url::admin($path), 'kind' => 'menu', 'icon' => '→'];
            }
        }
        foreach (Store::pros()->iterate() as $id => $p) {
            if (count($out['Pros']) >= 8) {
                break;
            }
            $hit = ($isId && (int) $id === (int) $q)
                || ($email !== '' && str_contains(mb_strtolower($p['email']), $email))
                || (strlen($digits) >= 6 && str_contains(preg_replace('/\D/', '', $p['phone']) ?? '', $digits))
                || str_contains(Str::norm($p['name'] . ' ' . $p['login'] . ' ' . $p['email'] . ' ' . $p['city'] . ' ' . $p['first'] . ' ' . $p['last']), $n);
            if ($hit) {
                $out['Pros'][] = ['title' => $p['name'], 'sub' => trim($p['city'] . ' · ' . (Pros::STATUSES[$p['status']] ?? $p['status']) . ' · ' . $p['email'], ' ·'), 'url' => Url::admin('pros/' . $id), 'kind' => '#' . $id, 'icon' => '👤'];
            }
        }
        foreach (Store::requests()->iterate() as $id => $r) {
            if (count($out['Demandes de devis']) >= 6) {
                break;
            }
            $hit = ($isId && (int) $id === (int) $q) || ($email !== '' && str_contains(mb_strtolower($r['email']), $email))
                || (strlen($digits) >= 6 && str_contains(preg_replace('/\D/', '', $r['phone']) ?? '', $digits))
                || (!$isId && mb_strlen($n) >= 3 && str_contains(Str::norm($r['name'] . ' ' . $r['email'] . ' ' . $r['city']), $n));
            if ($hit) {
                $out['Demandes de devis'][] = ['title' => ($r['name'] ?: $r['email']) . ' — ' . ($r['city'] ?: 'sans ville'), 'sub' => date_fr($r['created'], 'short') . ' · ' . (Leads::REQUEST_STATUSES[$r['status']] ?? $r['status']) . ' · ' . $r['excerpt'], 'url' => Url::admin('demandes/' . $id), 'kind' => '#' . $id, 'icon' => '📨'];
            }
        }
        if ($email !== '' || mb_strlen($n) >= 4 || $isId) {
            foreach (Store::messages()->iterate() as $id => $m) {
                if (count($out['Messages']) >= 6) {
                    break;
                }
                $hit = ($isId && (int) $id === (int) $q) || ($email !== '' && str_contains(mb_strtolower($m['email']), $email)) || (!$isId && $email === '' && str_contains(Str::norm($m['name'] . ' ' . $m['email']), $n));
                if ($hit) {
                    $out['Messages'][] = ['title' => $m['name'] . ' → pro #' . $m['pro'], 'sub' => date_fr($m['created'], 'short') . ' · ' . $m['excerpt'], 'url' => Url::admin('messages/' . $id), 'kind' => '#' . $id, 'icon' => '✉️'];
                }
            }
        }
        foreach (Store::contacts()->iterate() as $id => $c) {
            if (count($out['Contacts']) >= 5) {
                break;
            }
            if (str_contains(Str::norm($c['name'] . ' ' . $c['email'] . ' ' . $c['subject']), $n)) {
                $out['Contacts'][] = ['title' => $c['name'] . ' — ' . $c['subject'], 'sub' => $c['email'] . ' · ' . date_fr($c['created'], 'short'), 'url' => Url::admin('contacts?id=' . $id), 'kind' => 'contact', 'icon' => '💬'];
            }
        }
        foreach (Store::articles()->iterate() as $id => $a) {
            if (count($out['Contenus']) >= 5) {
                break;
            }
            if (str_contains(Str::norm($a['title'] . ' ' . $a['slug']), $n)) {
                $out['Contenus'][] = ['title' => $a['title'], 'sub' => 'Article · ' . $a['status'], 'url' => Url::admin('blog/' . $id), 'kind' => 'blog', 'icon' => '📝'];
            }
        }
        foreach (Store::pages()->iterate() as $id => $pg) {
            if (str_contains(Str::norm($pg['title'] . ' ' . $pg['slug']), $n)) {
                $out['Contenus'][] = ['title' => $pg['title'], 'sub' => 'Page /' . $pg['slug'] . '/', 'url' => Url::admin('pages/' . $id), 'kind' => 'page', 'icon' => '📄'];
            }
        }
        return array_filter($out);
    }

    // ------------------------------------------------------------ notifications

    public function notifications(): Response
    {
        if (Request::query('format') === 'json') {
            if (Request::query('count')) {
                return Response::json(['unread' => Notify::unreadCount()]);
            }
            $items = array_map(static fn ($n) => $n + ['ago' => ago($n['created'])], Notify::latest(15));
            return Response::json(['items' => $items, 'unread' => Notify::unreadCount()]);
        }
        $type = self::q('type', 40);
        $filter = $type !== '' ? static fn ($n) => $n['type'] === $type : null;
        $per = 50;
        $page = max(1, Request::int('page', 1));
        $res = Store::notifications()->find($filter, null, $per, ($page - 1) * $per);
        return $this->page('notifications', [
            'items' => $res['items'],
            'page' => $page,
            'pages' => max(1, (int) ceil($res['total'] / $per)),
            'total' => $res['total'],
            'type' => $type,
            'link' => self::pageLink(),
        ], 'Notifications', 'notifications');
    }

    public function readAll(): Response
    {
        Notify::markAllRead();
        return $this->done('Toutes les notifications sont marquées comme lues.', Request::referer() !== '' && str_contains(Request::referer(), Url::admin()) ? (string) parse_url(Request::referer(), PHP_URL_PATH) : Url::admin('notifications'));
    }

    // ------------------------------------------------------------------- mémo

    public function memo(): Response
    {
        if (Request::isPost()) {
            Store::doc('memo')->save(['html' => Sanitizer::html((string) Request::raw('html', ''), ['headings' => true, 'tables' => true, 'images' => true]), 'updated_at' => date('c'), 'updated_by' => $this->by()]);
            return $this->done('Mémo enregistré.', 'memo');
        }
        return $this->page('memo', ['memo' => Store::doc('memo')->all()], 'Mémo', 'memo');
    }

    public function pushSubscribe(): Response
    {
        $me = $this->current();
        $sub = (array) Request::input('subscription', []);
        if (!Push::subscribe('admin', (int) $me['id'], $sub, Request::userAgent())) {
            return Response::json(['error' => 'Abonnement invalide'], 422);
        }
        $this->audit('Notifications push activées sur un appareil');
        return Response::json(['ok' => true, 'message' => 'Notifications activées']);
    }
}
