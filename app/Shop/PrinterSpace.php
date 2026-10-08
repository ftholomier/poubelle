<?php
declare(strict_types=1);

namespace App\Shop;

use App\Core\JsonStore;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Services\Mailer;

/**
 * Espace imprimeur (/imprimeur/, sur l'adresse du musée), séparé du back-office : l'imprimeur n'a
 * accès qu'aux commandes payées. Connexion sans mot de passe : un lien à usage unique (valable
 * 30 minutes) envoyé à l'adresse réglée par l'administrateur (Boutique › Réglages) ; changer
 * cette adresse coupe l'accès de l'ancienne. Session de 12 heures.
 */
final class PrinterSpace
{
    private const TTL = 1800;
    private const SESSION = 43200;

    public static function handle(Request $req): Response
    {
        Session::start();
        $path = rtrim($req->path, '/') ?: '/imprimeur';
        $res = self::dispatch($req, $path);
        $res->headers['X-Robots-Tag'] = 'noindex, nofollow';
        $res->headers['X-Frame-Options'] = 'DENY';
        $res->headers['Cache-Control'] = 'no-store, private';
        $res->headers['Referrer-Policy'] = 'same-origin';
        return $res;
    }

    private static function dispatch(Request $req, string $path): Response
    {
        $post = $req->method === 'POST';
        if ($post && !Session::checkCsrf((string) ($req->post['_csrf'] ?? ''))) {
            return self::page('Session expirée', '<p>Votre session a expiré : <a href="/imprimeur/">rechargez la page</a>.</p>', 419);
        }
        if ($path === '/imprimeur/deconnexion') {
            Session::forget('printer');
            return Response::redirect('/imprimeur/');
        }
        if (preg_match('#^/imprimeur/lien/([a-f0-9]{48})$#', $path, $m)) {
            return self::useLink($m[1]);
        }
        if (!self::logged()) {
            return $post ? self::sendLink($req) : self::login();
        }
        if ($path === '/imprimeur') {
            return self::list($req);
        }
        if (preg_match('#^/imprimeur/releves(?:/(pdf|csv))?$#', $path, $m)) {
            return self::statement($req, $m[1] ?? '');
        }
        if (preg_match('#^/imprimeur/commande/([A-Z0-9-]{6,20})/pdf/(\d{1,2})$#', $path, $m)) {
            $o = self::visible($m[1]);
            $pdf = $o ? Orders::pdf($o, (int) $m[2] - 1) : null;
            return $pdf === null ? self::page('Introuvable', '<p>Fichier indisponible.</p>', 404)
                : new Response($pdf, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="' . $o['id'] . '-' . (int) $m[2] . '.pdf"']);
        }
        if (preg_match('#^/imprimeur/commande/([A-Z0-9-]{6,20})$#', $path, $m)) {
            $o = self::visible($m[1]);
            if (!$o) {
                return self::page('Introuvable', '<p>Commande introuvable. <a href="/imprimeur/">Retour</a></p>', 404);
            }
            return $post ? self::action($req, $o) : self::order($o);
        }
        return self::page('Introuvable', '<p><a href="/imprimeur/">Retour aux commandes</a></p>', 404);
    }

    /** Connecté, et toujours avec l'adresse actuellement réglée. */
    public static function logged(): bool
    {
        $s = Session::get('printer');
        $email = Orders::config()['printer_email'];
        return is_array($s) && $email !== '' && hash_equals($email, (string) ($s['email'] ?? '')) && (int) ($s['until'] ?? 0) > time();
    }

    /** L'imprimeur ne voit que les commandes payées (jamais celles en attente de paiement). */
    private static function visible(string $id): ?array
    {
        $o = Orders::get($id);
        return $o && $o['status'] !== 'pending' && !empty($o['paid_at']) ? $o : null;
    }

    // ------------------------------------------------------------------ connexion

    private static function login(string $msg = ''): Response
    {
        $c = Orders::config();
        $h = '<p>Espace de l’imprimeur de la boutique Sochaux Rétro' . ($c['printer_name'] !== '' ? ' (' . e($c['printer_name']) . ')' : '') . '. Saisissez votre adresse e-mail : vous recevrez un lien de connexion valable 30 minutes.</p>'
            . ($msg !== '' ? '<div class="alert alert--info">' . e($msg) . '</div>' : '')
            . '<form method="post" action="/imprimeur/" class="card card--pad" style="max-width:420px">' . csrf_field()
            . '<label class="f"><span class="f__k">Adresse e-mail</span><input class="in" type="email" name="email" required autocomplete="email"></label>'
            . '<button class="btn btn--navy" style="margin-top:10px">Recevoir le lien de connexion</button></form>';
        return self::page('Espace imprimeur', $h);
    }

    private static function sendLink(Request $req): Response
    {
        $email = strtolower(trim((string) ($req->post['email'] ?? '')));
        if (!RateLimiter::hit('imprimeur-lien', $req->ip(), 5, 3600)) {
            return self::login('Trop de demandes : réessayez dans une heure.');
        }
        $c = Orders::config();
        if ($c['printer_email'] !== '' && hash_equals($c['printer_email'], $email)) {
            $token = bin2hex(random_bytes(24));
            JsonStore::update(STORAGE_PATH . '/shop/printer-links.json', function ($all) use ($token, $email) {
                $all = array_filter(is_array($all) ? $all : [], fn ($l) => (int) $l['until'] > time());
                $all[hash('sha256', $token)] = ['email' => $email, 'until' => time() + self::TTL];
                return $all;
            }, []);
            $url = base_url() . '/imprimeur/lien/' . $token;
            Mailer::send($email, 'Votre lien de connexion · espace imprimeur Sochaux Rétro', '<p>Bonjour,</p><p>Pour ouvrir l’espace imprimeur de la boutique Sochaux Rétro, cliquez sur ce lien (valable 30 minutes, une seule fois) :</p><p><a href="' . e($url) . '">' . e($url) . '</a></p><p>Si vous n’êtes pas à l’origine de cette demande, ignorez ce message.</p>');
        }
        // Même réponse dans tous les cas : on ne révèle pas l'adresse réglée.
        return self::login('Si cette adresse est celle de l’imprimeur, un lien de connexion vient de lui être envoyé.');
    }

    private static function useLink(string $token): Response
    {
        $found = null;
        JsonStore::update(STORAGE_PATH . '/shop/printer-links.json', function ($all) use ($token, &$found) {
            $all = is_array($all) ? $all : [];
            $k = hash('sha256', $token);
            if (isset($all[$k]) && (int) $all[$k]['until'] > time()) {
                $found = $all[$k];
            }
            unset($all[$k]);
            return $all;
        }, []);
        if (!$found || !hash_equals(Orders::config()['printer_email'], (string) $found['email'])) {
            return self::login('Ce lien n’est plus valable : demandez-en un nouveau.');
        }
        session_regenerate_id(true);
        Session::set('printer', ['email' => $found['email'], 'until' => time() + self::SESSION]);
        return Response::redirect('/imprimeur/');
    }

    // ------------------------------------------------------------------ commandes

    private static function list(Request $req): Response
    {
        $tab = (string) ($req->query['vue'] ?? 'afaire');
        $tabs = ['afaire' => ['À fabriquer', ['paid']], 'encours' => ['En fabrication', ['production']], 'expediees' => ['Expédiées', ['shipped']], 'toutes' => ['Toutes', ['paid', 'production', 'shipped', 'delivered', 'canceled', 'refunded']]];
        $tab = isset($tabs[$tab]) ? $tab : 'afaire';
        $all = array_values(array_filter(Orders::all(), fn ($o) => !empty($o['paid_at']) && Orders::physical($o['items'])));
        $h = '<div class="row" style="gap:6px;flex-wrap:wrap;margin:0 0 12px">';
        foreach ($tabs as $k => [$label, $st]) {
            $n = count(array_filter($all, fn ($o) => in_array($o['status'], $st, true)));
            $h .= '<a class="btn btn--sm ' . ($k === $tab ? 'btn--navy' : 'btn--ghost') . '" href="/imprimeur/?vue=' . $k . '">' . e($label) . ' (' . $n . ')</a>';
        }
        $h .= '<a class="btn btn--sm btn--yellow" href="/imprimeur/releves" style="margin-left:auto">Relevé mensuel</a></div>';
        $rows = array_filter($all, fn ($o) => in_array($o['status'], $tabs[$tab][1], true));
        if (!$rows) {
            return self::page('Commandes', $h . '<p class="card card--pad muted">Aucune commande ici.</p>');
        }
        $h .= '<table class="shoporders card"><thead><tr><th>Commande</th><th>Payée le</th><th>Client</th><th>Articles</th><th>Étape</th></tr></thead><tbody>';
        foreach ($rows as $o) {
            $items = implode('<br>', array_map(fn ($it) => (int) $it['qty'] . ' × ' . e($it['support']) . ' · ' . e($it['name']), array_filter($o['items'], fn ($it) => !BookShop::digital($it))));
            $h .= '<tr><td><a href="/imprimeur/commande/' . e($o['id']) . '"><b>' . e($o['id']) . '</b></a>' . ($o['messages'] && end($o['messages'])['from'] === 'client' ? ' <span class="shopst shopst--paid">question du client</span>' : '') . '</td><td>' . e(date('d/m/Y H:i', strtotime($o['paid_at']))) . '</td><td>' . e($o['customer']['name']) . '<br><span class="xs muted">' . e($o['customer']['zip'] . ' ' . $o['customer']['city']) . '</span></td><td class="small">' . $items . '</td><td><span class="shopst shopst--' . e($o['status']) . '">' . e(Orders::STATUSES[$o['status']]) . '</span></td></tr>';
        }
        return self::page('Commandes', $h . '</tbody></table>');
    }

    /** Relevé mensuel (sans les ventes ni la marge de l'association) : écran, PDF, tableur. */
    private static function statement(Request $req, string $kind): Response
    {
        $s = Accounts::statement((string) ($req->query['mois'] ?? ''));
        if ($kind === 'pdf') {
            return new Response(Accounts::statementPdf($s, false), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="releve-' . $s['ym'] . '.pdf"']);
        }
        if ($kind === 'csv') {
            return new Response(Accounts::statementCsv($s, false), 200, ['Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="releve-' . $s['ym'] . '.csv"']);
        }
        $opts = '';
        foreach (\App\Admin\Shop::statementMonths() as $ym) {
            $opts .= '<option value="' . $ym . '"' . ($ym === $s['ym'] ? ' selected' : '') . '>' . $ym . '</option>';
        }
        $m = $s['sums'];
        $h = '<p style="margin:0 0 10px"><a href="/imprimeur/">← Commandes</a></p>'
            . '<form method="get" action="/imprimeur/releves" class="row" style="gap:10px;align-items:end;flex-wrap:wrap;margin:0 0 14px"><label class="f" style="margin:0"><span class="f__k">Mois</span><select class="in" name="mois">' . $opts . '</select></label><button class="btn btn--ghost btn--sm">Afficher</button>'
            . '<a class="btn btn--yellow btn--sm" href="/imprimeur/releves/pdf?mois=' . $s['ym'] . '">PDF</a><a class="btn btn--ghost btn--sm" href="/imprimeur/releves/csv?mois=' . $s['ym'] . '">Tableur</a></form>'
            . '<p class="card card--pad"><b>Total à facturer à l’association : ' . e(Orders::money($m['cost'] + $m['ship_cost'])) . '</b> (' . $m['items'] . ' articles, ' . $m['orders'] . ' expéditions)</p>';
        if ($s['rows']) {
            $h .= '<table class="shoporders card"><thead><tr><th>Date</th><th>Commande</th><th>Article</th><th>Qté</th><th>Unitaire</th><th>Coût</th></tr></thead><tbody>';
            foreach ($s['rows'] as $r) {
                $h .= '<tr><td>' . e(date('d/m', strtotime($r['date']))) . '</td><td>' . e($r['order']) . '</td><td>' . e($r['name']) . ($r['support'] !== '' ? ' <span class="xs muted">(' . e($r['support']) . ($r['size'] !== '' ? ', ' . e($r['size']) : '') . ')</span>' : '') . '</td><td>' . (int) $r['qty'] . '</td><td>' . e(Orders::money($r['unit_cost'])) . '</td><td>' . e(Orders::money($r['cost'])) . '</td></tr>';
            }
            $h .= '</tbody></table>';
        }
        return self::page('Relevé mensuel', $h);
    }

    private static function order(array $o): Response
    {
        $h = '<p style="margin:0 0 10px"><a href="/imprimeur/">← Toutes les commandes</a></p>' . View::partial('shop/commande', ['o' => $o, 'previews' => \App\Admin\Shop::orderPreviews($o), 'base' => '/imprimeur/commande/' . $o['id'], 'who' => 'imprimeur']);
        return self::page('Commande ' . $o['id'], $h);
    }

    private static function action(Request $req, array $o): Response
    {
        $p = $req->post;
        $back = '/imprimeur/commande/' . $o['id'];
        if (($p['action'] ?? '') === 'message') {
            Orders::message($o['id'], 'imprimeur', (string) ($p['text'] ?? ''));
            Session::flash('ok', 'Message envoyé au client.');
            return Response::redirect($back . '#messages');
        }
        if (($p['action'] ?? '') === 'status' && in_array($p['status'] ?? '', ['paid', 'production', 'shipped', 'delivered'], true) && !in_array($o['status'], ['canceled', 'refunded'], true)) {
            $c = Orders::config();
            Orders::setStatus($o['id'], (string) $p['status'], 'imprimeur' . ($c['printer_name'] !== '' ? ' (' . $c['printer_name'] . ')' : ''), (string) ($p['note'] ?? ''), ($p['notify'] ?? '') === '1',
                ['carrier' => (string) ($p['carrier'] ?? ''), 'number' => (string) ($p['number'] ?? ''), 'url' => (string) ($p['url'] ?? '')]);
            Session::flash('ok', 'Étape enregistrée.');
        }
        return Response::redirect($back);
    }

    private static function page(string $title, string $html, int $status = 200): Response
    {
        $flash = Session::takeFlash();
        return Response::html(View::partial('shop/imprimeur-layout', ['title' => $title, 'content' => $html, 'flash' => $flash, 'logged' => self::logged()]), $status);
    }
}
