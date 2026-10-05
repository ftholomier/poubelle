<?php
declare(strict_types=1);

namespace App\Admin;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Data\Activity;
use App\Shop\Catalog;
use App\Shop\Mockup;
use App\Shop\Orders;
use App\Shop\Texts;
use App\Shop\Vector;

/**
 * BOUTIQUE (administrateurs) : supports de l'imprimeur, modèles dessinés dans l'éditeur (logo de
 * l'association, textes, formes ; jamais de photo), aperçu sur le produit, fichier d'impression
 * PDF vectoriel. Commandes, paiements et imprimeur viendront ensuite (lots B et C).
 */
final class Shop extends Base
{
    public static function index(Request $req): Response
    {
        return self::html('admin/boutique/index', [
            'd' => \App\Shop\Accounts::dashboard(), 'models' => Catalog::models(), 'supports' => Catalog::supports(),
            'rec' => \App\Shop\Accounts::lastReconcile(), 'payable' => Orders::payable(),
        ], ['title' => 'Boutique', 'crumb' => 'Boutique', 'nav' => 'boutique']);
    }

    public static function promos(Request $req): Response
    {
        $code = $req->str('code');
        return self::html('admin/boutique/promos', ['promos' => \App\Shop\Promos::all(), 'models' => Catalog::models(), 'edit' => $code !== '' ? \App\Shop\Promos::find($code) : null],
            ['title' => 'Codes promo', 'crumb' => 'Boutique', 'nav' => 'boutique-promos']);
    }

    public static function savePromo(Request $req): Response
    {
        $old = \App\Shop\Promos::code($req->str('old'));
        if ($req->str('action') === 'delete') {
            \App\Shop\Promos::delete($old);
            Activity::log(self::actor(), 'a supprimé le code promo ' . $old, ['path' => '/admin/boutique/promos']);
            return self::back('/admin/boutique/promos', 'Code ' . $old . ' supprimé.');
        }
        $type = $req->str('type');
        $v = (float) str_replace(',', '.', $req->str('value'));
        try {
            $p = \App\Shop\Promos::save([
                'code' => $req->str('code'), 'type' => $type, 'value' => $type === 'percent' ? (int) $v : (int) round($v * 100),
                'min' => (int) round((float) str_replace(',', '.', $req->str('min')) * 100), 'from' => $req->str('from'), 'to' => $req->str('to'),
                'max' => (int) $req->str('max'), 'once' => $req->str('once') === '1', 'active' => $req->str('active') === '1',
                'models' => (array) ($req->post['models'] ?? []), 'note' => $req->str('note'),
            ], $old);
        } catch (\InvalidArgumentException $e) {
            return self::back('/admin/boutique/promos', null, $e->getMessage());
        }
        Activity::log(self::actor(), 'a enregistré le code promo ' . $p['code'], ['path' => '/admin/boutique/promos']);
        return self::back('/admin/boutique/promos', 'Code ' . $p['code'] . ' enregistré.');
    }

    /** POST /admin/boutique/rapprochement */
    public static function reconcile(Request $req): Response
    {
        $r = \App\Shop\Accounts::reconcile();
        if (isset($r['error'])) {
            return self::back('/admin/boutique', null, 'Rapprochement impossible : ' . $r['error']);
        }
        $bad = count(array_filter($r['rows'], fn ($x) => $x['level'] !== 'ok'));
        return self::back('/admin/boutique', 'Rapprochement fait : ' . count($r['rows']) . ' paiement(s) vérifié(s)' . ($r['fixed'] ? ', ' . $r['fixed'] . ' commande(s) rattrapée(s)' : '') . ($bad ? ', ' . $bad . ' point(s) à regarder.' : ', tout concorde.'));
    }

    /** Mois proposés pour les relevés : depuis la première commande payée (12 au moins). @return list<string> */
    public static function statementMonths(): array
    {
        $first = date('Y-m');
        foreach (\App\Shop\Accounts::sold() as $o) {
            $first = min($first, substr((string) $o['paid_at'], 0, 7));
        }
        $out = [];
        for ($t = strtotime(date('Y-m-01')); count($out) < 12 || date('Y-m', $t) >= $first; $t = strtotime('-1 month', $t)) {
            $out[] = date('Y-m', $t);
            if (count($out) > 120) {
                break;
            }
        }
        return $out;
    }

    public static function statements(Request $req): Response
    {
        return self::html('admin/boutique/releves', ['s' => \App\Shop\Accounts::statement($req->str('mois')), 'months' => self::statementMonths()],
            ['title' => 'Relevés de l’imprimeur', 'crumb' => 'Boutique', 'nav' => 'boutique-releves']);
    }

    public static function statementFile(Request $req, string $kind): Response
    {
        $s = \App\Shop\Accounts::statement($req->str('mois'));
        $name = 'releve-imprimeur-' . $s['ym'];
        return $kind === 'csv'
            ? new Response(\App\Shop\Accounts::statementCsv($s), 200, ['Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="' . $name . '.csv"', 'Cache-Control' => 'private, no-store'])
            : new Response(\App\Shop\Accounts::statementPdf($s), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="' . $name . '.pdf"', 'Cache-Control' => 'private, no-store']);
    }

    // ------------------------------------------------------------------ supports

    public static function supports(Request $req): Response
    {
        return self::html('admin/boutique/supports', ['supports' => Catalog::supports(), 'mockups' => Catalog::MOCKUPS], ['title' => 'Supports', 'crumb' => 'Boutique', 'nav' => 'boutique-supports']);
    }

    /** POST /admin/boutique/supports : un support (nom, faces, couleurs, tailles, référence, actif), ou nouveau. */
    public static function saveSupport(Request $req): Response
    {
        $key = (string) preg_replace('/[^a-z0-9-]/', '', $req->str('key'));
        $faces = [];
        foreach ((array) ($req->post['faces'] ?? []) as $f) {
            $label = trim((string) ($f['label'] ?? ''));
            if ($label === '') {
                continue;
            }
            $fk = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower(\App\Data\Names::ascii($label))), '-') ?: 'face';
            $faces[$fk] = [$label, (float) ($f['w'] ?? 0), (float) ($f['h'] ?? 0), (float) ($f['bleed'] ?? 0)];
        }
        $colors = [];
        foreach (preg_split('/\R/', $req->str('colors')) ?: [] as $line) {
            if (preg_match('/^\s*(.+?)\s*[:=]\s*(#[0-9a-f]{6})\s*$/i', $line, $m)) {
                $colors[$m[1]] = strtoupper($m[2]);
            }
        }
        $name = trim($req->str('name')) ?: 'Support';
        if (!$faces) {
            return self::back('/admin/boutique/supports', null, 'Il faut au moins une face (libellé, largeur, hauteur).');
        }
        $data = [
            'name' => $name, 'mockup' => $req->str('mockup'), 'faces' => $faces, 'colors' => $colors,
            'sizes' => array_values(array_filter(array_map('trim', explode(',', $req->str('sizes'))))),
            'ref' => trim($req->str('ref')), 'note' => trim($req->str('note')), 'active' => $req->str('active') === '1',
            'cost' => (int) round((float) str_replace(',', '.', $req->str('cost')) * 100),
        ];
        $key = Catalog::saveSupport($key, $data, Auth::user());
        Activity::log(self::actor(), 'a modifié le support « ' . $name . ' » de la boutique', ['path' => '/admin/boutique/supports']);
        return self::back('/admin/boutique/supports#s-' . $key, 'Support « ' . $name . ' » enregistré.');
    }

    // ------------------------------------------------------------------ réglages de la boutique

    public static function settings(Request $req): Response
    {
        return self::html('admin/boutique/reglages', ['c' => Orders::config(), 'payable' => \App\Services\Payments::stripeReady(), 'models' => Catalog::models()],
            ['title' => 'Réglages de la boutique', 'crumb' => 'Boutique', 'nav' => 'boutique-reglages']);
    }

    public static function saveSettings(Request $req): Response
    {
        $eur = fn (string $k) => (int) round((float) str_replace(',', '.', $req->str($k)) * 100);
        $email = trim($req->str('printer_email'));
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return self::back('/admin/boutique/reglages', null, 'L’adresse e-mail de l’imprimeur n’est pas valide.');
        }
        $before = Orders::config();
        $c = Orders::saveConfig([
            'open' => $req->str('open') === '1', 'printer_name' => $req->str('printer_name'), 'printer_email' => $email,
            'shipping' => $eur('shipping'), 'free_from' => $eur('free_from'), 'delay' => $req->str('delay'), 'ship_cost' => $eur('ship_cost'),
            'alert_email' => $req->str('alert_email'), 'cgv' => $req->str('cgv'),
        ]);
        Activity::log(self::actor(), 'a modifié les réglages de la boutique' . ($before['printer_email'] !== $c['printer_email'] ? ' (adresse de l’imprimeur changée)' : ''), ['path' => '/admin/boutique/reglages']);
        return self::back('/admin/boutique/reglages', 'Réglages de la boutique enregistrés.');
    }

    // ------------------------------------------------------------------ commandes

    public static function orders(Request $req): Response
    {
        $st = $req->str('statut');
        $all = Orders::all();
        $list = $st !== '' ? array_values(array_filter($all, fn ($o) => $o['status'] === $st)) : array_values(array_filter($all, fn ($o) => $o['status'] !== 'pending' || strtotime($o['created']) > time() - 86400));
        return self::html('admin/boutique/commandes', ['orders' => $list, 'all' => $all, 'st' => $st],
            ['title' => 'Commandes', 'crumb' => 'Boutique', 'nav' => 'boutique-commandes']);
    }

    public static function order(Request $req, string $id): Response
    {
        $o = Orders::get($id);
        if (!$o) {
            return self::back('/admin/boutique/commandes', null, 'Commande introuvable.');
        }
        return self::html('admin/boutique/commande', ['o' => $o, 'previews' => self::orderPreviews($o), 'base' => '/admin/boutique/commandes/' . $o['id'], 'who' => 'association'],
            ['title' => 'Commande ' . $o['id'], 'crumb' => 'Boutique › Commandes', 'nav' => 'boutique-commandes']);
    }

    /** Aperçus (SVG) des articles d'une commande. @return list<string> */
    public static function orderPreviews(array $o): array
    {
        $out = [];
        foreach ($o['items'] as $it) {
            $m = Catalog::find($it['model']);
            $sup = $m ? Catalog::support($m['support']) : null;
            if (!$m || !$sup) {
                $out[] = '';
                continue;
            }
            [$mm] = Catalog::applyOptions($m, $it['opts']);
            $fk = array_key_first(array_filter($mm['faces'], fn ($f) => $f['layers'])) ?? array_key_first($mm['faces']);
            $out[] = Mockup::render($sup['mockup'], (string) $fk, $mm['faces'][$fk], $mm['color'], $it['values'])['svg'];
        }
        return $out;
    }

    /** POST /admin/boutique/commandes/{id} : étape, suivi, message, remboursement, paiement hors ligne. */
    public static function orderAction(Request $req, string $id): Response
    {
        $o = Orders::get($id);
        $back = '/admin/boutique/commandes/' . $id;
        if (!$o) {
            return self::back('/admin/boutique/commandes', null, 'Commande introuvable.');
        }
        $who = (string) (Auth::user()['name'] ?? 'association');
        switch ($req->str('action')) {
            case 'status':
                $st = $req->str('status');
                Orders::setStatus($id, $st, $who, $req->str('note'), $req->str('notify') === '1', ['carrier' => $req->str('carrier'), 'number' => $req->str('number'), 'url' => $req->str('url')]);
                Activity::log(self::actor(), 'a passé la commande ' . $id . ' à « ' . (Orders::STATUSES[$st] ?? $st) . ' »', ['path' => $back]);
                return self::back($back, 'Étape enregistrée.');
            case 'message':
                Orders::message($id, 'association', $req->str('text'));
                return self::back($back . '#messages', 'Message envoyé au client.');
            case 'refund':
                $r = Orders::refund($id, (int) round((float) str_replace(',', '.', $req->str('amount')) * 100), $who);
                if (isset($r['error'])) {
                    return self::back($back, null, 'Remboursement impossible : ' . $r['error']);
                }
                Activity::log(self::actor(), 'a remboursé ' . Orders::money($r['cents']) . ' sur la commande ' . $id, ['path' => $back]);
                return self::back($back, 'Remboursement de ' . Orders::money($r['cents']) . ' effectué.');
            case 'paid':
                Orders::markPaid($id, '', $o['total'], $who . ' (hors ligne)');
                return self::back($back, 'Paiement enregistré : la commande part chez l’imprimeur.');
        }
        return self::back($back, null, 'Action inconnue.');
    }

    /** GET /admin/boutique/commandes/{id}/pdf/{n} : fichier d'impression d'un article. */
    public static function orderPdf(Request $req, string $id, string $n): Response
    {
        $o = Orders::get($id);
        $pdf = $o ? Orders::pdf($o, (int) $n - 1) : null;
        if ($pdf === null) {
            return self::back('/admin/boutique/commandes/' . $id, null, 'Fichier indisponible (commande non payée ?).');
        }
        return new Response($pdf, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="' . $o['id'] . '-' . (int) $n . '.pdf"', 'Cache-Control' => 'private, no-store']);
    }

    // ------------------------------------------------------------------ banque de textes

    public static function texts(Request $req): Response
    {
        return self::html('admin/boutique/textes', ['lists' => Texts::lists(), 'ai' => \App\Services\Gemini::ready()], ['title' => 'Banque de textes', 'crumb' => 'Boutique', 'nav' => 'boutique-textes']);
    }

    /** POST /admin/boutique/textes : enregistrer une liste, en créer une, la supprimer, ou demander des phrases à l'IA. */
    public static function textsAction(Request $req): Response
    {
        $action = $req->str('action');
        $id = $req->str('id');
        $back = '/admin/boutique/textes';
        if ($action === 'new') {
            $l = Texts::save(['name' => trim($req->str('name')) ?: 'Nouvelle liste', 'note' => $req->str('note'), 'items' => []], Auth::user());
            return self::back($back . '#l-' . $l['id'], 'Liste « ' . $l['name'] . ' » créée : ajoutez des phrases ou demandez-en à l’IA.');
        }
        $l = Texts::find($id);
        if (!$l) {
            return self::back($back, null, 'Liste introuvable.');
        }
        if ($action === 'delete') {
            Texts::delete($id, Auth::user());
            Activity::log(self::actor(), 'a supprimé la liste « ' . $l['name'] . ' » de la banque de textes', ['path' => $back]);
            return self::back($back, 'Liste « ' . $l['name'] . ' » supprimée.');
        }
        if ($action === 'ai') {
            try {
                $n = Texts::suggest($id, max(1, min(30, (int) $req->str('count', '10') ?: 10)), $req->str('hint'), Auth::user());
            } catch (\Throwable $e) {
                return self::back($back . '#l-' . $id, null, 'L’IA n’a pas répondu : ' . $e->getMessage());
            }
            return self::back($back . '#l-' . $id, $n ? $n . ' phrase(s) proposée(s) par l’IA, à relire et valider.' : 'L’IA n’a rien proposé de nouveau.');
        }
        $items = [];
        foreach ((array) ($req->post['items'] ?? []) as $i) {
            if (!is_array($i) || !empty($i['del'])) {
                continue;
            }
            $items[] = ['id' => (string) ($i['id'] ?? ''), 'text' => (string) ($i['text'] ?? ''), 'note' => (string) ($i['note'] ?? ''), 'ok' => !empty($i['ok'])];
        }
        foreach (preg_split('/\R/', $req->str('add')) ?: [] as $line) {
            if (trim($line) !== '') {
                $items[] = ['text' => str_replace("'", '’', trim($line)), 'note' => '', 'ok' => true];
            }
        }
        $l = Texts::save(['id' => $id, 'name' => $req->str('name'), 'note' => $req->str('note'), 'items' => $items], Auth::user());
        Activity::log(self::actor(), 'a modifié la liste « ' . $l['name'] . ' » de la banque de textes', ['path' => $back]);
        return self::back($back . '#l-' . $id, 'Liste « ' . $l['name'] . ' » enregistrée.');
    }

    // ------------------------------------------------------------------ modèles

    public static function models(Request $req): Response
    {
        $previews = [];
        foreach (Catalog::models() as $id => $m) {
            $sup = Catalog::support($m['support']);
            $fk = array_key_first($m['faces']);
            $previews[$id] = Mockup::render($sup['mockup'], (string) $fk, $m['faces'][$fk], $m['color'], self::sample($m))['svg'];
        }
        return self::html('admin/boutique/modeles', ['models' => Catalog::models(), 'supports' => Catalog::supports(), 'previews' => $previews], ['title' => 'Modèles', 'crumb' => 'Boutique', 'nav' => 'boutique-modeles']);
    }

    /** POST /admin/boutique/modeles : nouveau (nom, support), dupliquer, supprimer, activer. */
    public static function modelAction(Request $req): Response
    {
        $action = $req->str('action');
        $id = $req->str('id');
        if ($action === 'new') {
            $sup = Catalog::support($req->str('support'));
            if (!$sup) {
                return self::back('/admin/boutique/modeles', null, 'Support inconnu.');
            }
            $m = Catalog::saveModel(['name' => trim($req->str('name')) ?: 'Nouveau modèle', 'support' => $sup['key'], 'faces' => self::starter($sup)], Auth::user());
            return self::back('/admin/boutique/modeles/' . $m['id'], 'Modèle créé : placez le logo et les textes, puis enregistrez.');
        }
        $m = Catalog::find($id);
        if (!$m) {
            return self::back('/admin/boutique/modeles', null, 'Modèle introuvable.');
        }
        if ($action === 'duplicate') {
            $copy = Catalog::saveModel(['name' => $m['name'] . ' (copie)', 'id' => ''] + $m, Auth::user());
            return self::back('/admin/boutique/modeles/' . $copy['id'], 'Copie créée.');
        }
        if ($action === 'delete') {
            Catalog::deleteModel($id, Auth::user());
            Activity::log(self::actor(), 'a supprimé le modèle « ' . $m['name'] . ' » de la boutique', ['path' => '/admin/boutique/modeles']);
            return self::back('/admin/boutique/modeles', 'Modèle « ' . $m['name'] . ' » supprimé.');
        }
        if ($action === 'toggle') {
            Catalog::saveModel(['active' => !$m['active']] + $m, Auth::user());
            return self::back('/admin/boutique/modeles', $m['active'] ? 'Modèle mis de côté.' : 'Modèle prêt à la vente.');
        }
        return self::back('/admin/boutique/modeles', null, 'Action inconnue.');
    }

    /** Premier dessin d'un nouveau modèle : le logo centré en haut de la première face. */
    private static function starter(array $sup): array
    {
        $fk = array_key_first($sup['faces']);
        $f = $sup['faces'][$fk];
        $lw = min($f['w'] * 0.4, $f['h'] * 0.5 / Vector::LOGO_RATIO);
        return [$fk => ['bg' => $sup['colors'] ? '' : '#0E1F4D', 'layers' => [['id' => 'logo', 'type' => 'logo', 'x' => round(($f['w'] - $lw) / 2, 1), 'y' => round($f['h'] * 0.08, 1), 'w' => round($lw, 1)]]]];
    }

    public static function editor(Request $req, string $id): Response
    {
        $m = Catalog::find($id);
        if (!$m) {
            return self::back('/admin/boutique/modeles', null, 'Modèle introuvable.');
        }
        return self::html('admin/boutique/editeur', [
            'model' => $m, 'support' => Catalog::support($m['support']), 'fonts' => Vector::FONTS, 'palette' => Vector::PALETTE, 'lists' => Texts::forEditor(),
        ], ['title' => $m['name'], 'crumb' => 'Boutique › Modèles', 'nav' => 'boutique-modeles', 'scripts' => ['admin/boutique.js']]);
    }

    /** POST /admin/boutique/modeles/{id} (JSON) : enregistre le dessin. */
    public static function saveModel(Request $req, string $id): Response
    {
        $m = Catalog::find($id);
        $in = $req->json();
        if (!$m || !is_array($in['faces'] ?? null)) {
            return self::json(['ok' => false, 'error' => 'Modèle introuvable.'], 404);
        }
        $saved = Catalog::saveModel([
            'id' => $id, 'name' => trim((string) ($in['name'] ?? '')) ?: $m['name'], 'support' => $m['support'],
            'color' => (string) ($in['color'] ?? $m['color']), 'active' => (bool) ($in['active'] ?? $m['active']), 'faces' => $in['faces'],
            'sale' => is_array($in['sale'] ?? null) ? $in['sale'] : $m['sale'],
        ], Auth::user());
        Activity::log(self::actor(), 'a modifié le modèle « ' . $saved['name'] . ' » de la boutique', ['path' => '/admin/boutique/modeles/' . $id]);
        return self::json(['ok' => true, 'model' => $saved]);
    }

    /**
     * POST /admin/boutique/apercu (JSON : support, face, color, design, values) : maquette du
     * produit, fichier d'impression (avec fonds perdus) et boîtes des calques pour l'éditeur.
     */
    public static function preview(Request $req): Response
    {
        $in = $req->json();
        $sup = Catalog::support((string) ($in['support'] ?? ''));
        $fk = (string) ($in['face'] ?? '');
        if (!$sup || !isset($sup['faces'][$fk])) {
            return self::json(['ok' => false], 404);
        }
        $face = $sup['faces'][$fk] + ['bg' => ($in['bg'] ?? '') !== '' ? Vector::hex($in['bg']) : '', 'layers' => array_map([Catalog::class, 'cleanLayer'], array_values(array_filter((array) ($in['layers'] ?? []), 'is_array')))];
        $values = array_map(fn ($v) => mb_substr((string) $v, 0, 200), (array) ($in['values'] ?? []));
        $mock = Mockup::render($sup['mockup'], $fk, $face, (string) ($in['color'] ?? ''), $values);
        $boxes = [];
        $warn = [];
        foreach (Vector::shapes($face['layers'], $values) as $sh) {
            $b = Vector::bbox($sh['d']);
            if ($b) {
                $id = $sh['layer'];
                $boxes[$id] = isset($boxes[$id]) ? [min($boxes[$id][0], $b[0]), min($boxes[$id][1], $b[1]), max($boxes[$id][2], $b[2]), max($boxes[$id][3], $b[3])] : $b;
            }
        }
        foreach ($face['layers'] as $l) {
            $b = $boxes[$l['id']] ?? null;
            if ($b && ($b[0] < -0.5 || $b[1] < -0.5 || $b[2] > $face['w'] + 0.5 || $b[3] > $face['h'] + 0.5) && empty($face['bg'])) {
                $warn[] = 'Un calque (' . ($l['type'] === 'text' ? '« ' . mb_strimwidth((string) $l['text'], 0, 24, '…') . ' »' : $l['type']) . ') dépasse de la zone imprimable.';
            }
            if ($l['type'] === 'text' && !Vector::fits($l, $values)) {
                $warn[] = 'Le texte « ' . mb_strimwidth(Vector::textOf($l, $values), 0, 30, '…') . ' » ne tient pas dans son cadre, même au corps minimum : agrandissez le cadre, baissez le minimum ou raccourcissez le texte.';
            }
            if ($l['type'] === 'text' && $sup['mockup'] === 'cap' && (float) $l['size'] * 25.4 / 72 * 0.7 < 5) {
                $warn[] = 'Casquette brodée : texte trop petit (moins de 5 mm de haut).';
            }
        }
        return self::json(['ok' => true, 'mockup' => $mock['svg'], 'area' => $mock['area'], 'vb' => $mock['vb'],
            'print' => Vector::svg($face, $values, true), 'boxes' => $boxes, 'warn' => array_values(array_unique($warn)),
            'fields' => array_map(fn ($f) => ['choices' => $f['choices'], 'rejected' => $f['rejected']], Catalog::fields(['faces' => [$fk => $face]]))]);
    }

    /** GET /admin/boutique/modeles/{id}/pdf : fichier d'impression (toutes les faces dessinées). */
    public static function pdf(Request $req, string $id): Response
    {
        $m = Catalog::find($id);
        if (!$m) {
            return self::back('/admin/boutique/modeles', null, 'Modèle introuvable.');
        }
        $sup = Catalog::support($m['support']);
        $values = self::sample($m);
        foreach (Catalog::fields($m) as $f => $_) {
            if (($v = trim($req->str('v_' . $f))) !== '') {
                $values[$f] = $v;
            }
        }
        $faces = [];
        $colorName = (string) (array_search($m['color'], $sup['colors'], true) ?: '');
        foreach ($m['faces'] as $fk => $f) {
            if ($f['layers'] || $f['bg']) {
                $faces[] = ['name' => $m['name'] . ' · ' . $sup['name'] . ' · ' . $f['label'] . ' · ' . $f['w'] . ' × ' . $f['h'] . ' mm' . ($f['bleed'] ? ' + ' . $f['bleed'] . ' mm de fonds perdus' : '') . ($colorName !== '' ? ' · support ' . $colorName : ''), 'side' => $f, 'values' => $values];
            }
        }
        if (!$faces) {
            return self::back('/admin/boutique/modeles/' . $id, null, 'Rien à imprimer : le modèle est vide.');
        }
        $bytes = Vector::pdf($faces, ['title' => $m['name'], 'cmyk' => $req->str('rvb') !== '1']);
        $name = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower(\App\Data\Names::ascii($m['name']))), '-') ?: 'modele';
        return new Response($bytes, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="impression-' . $name . '.pdf"', 'Cache-Control' => 'private, no-store']);
    }

    /** Valeurs d'exemple des champs du client (le texte par défaut de chaque champ). */
    private static function sample(array $m): array
    {
        return array_map(fn ($f) => $f['default'], Catalog::fields($m));
    }
}
