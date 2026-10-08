<?php
declare(strict_types=1);

namespace App\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Data\Activity;
use App\Pdf\Livre;

/**
 * Contenus › Livre des récits (administrateurs) : compose le livre « 100 récits du Lion » en PDF
 * prêt à imprimer (App\Pdf\Livre), personnalisé, et affiche le bilan des photos de la dernière composition.
 */
final class Book extends Base
{
    private const LAST = STORAGE_PATH . '/livres/dernier.json';

    public static function index(Request $req): Response
    {
        if ($r = self::denyUnlessAdmin()) {
            return $r;
        }
        $last = json_decode((string) @file_get_contents(self::LAST), true) ?: null;
        $groups = (new Livre(['relire' => true]))->recits();
        $count = ['publie' => 0, 'relire' => 0];
        foreach ($groups as $items) {
            foreach ($items as $it) {
                $count[($it['doc']['status'] ?? '') === 'publie' ? 'publie' : 'relire']++;
            }
        }
        $book = new Livre(['relire' => true]);
        $covers = [];
        foreach (Livre::covers() as $rel) {
            $covers[] = ['rel' => $rel, 'dpi' => $book->coverDpi($rel), 'caption' => \App\Data\Media::caption($rel)];
        }
        return self::html('admin/fiches/livre', ['last' => $last, 'decades' => array_keys($groups), 'count' => $count, 'covers' => $covers, 'suggest' => $book->coverSuggestions(), 'sale' => \App\Shop\BookShop::config()],
            ['title' => 'Livre des récits', 'crumb' => 'Contenus', 'nav' => 'livre', 'scripts' => ['admin/livre.js']]);
    }

    /** POST : photos de couverture (proposer, retirer), sinon compose le livre et le renvoie en téléchargement. */
    public static function build(Request $req): Response
    {
        if ($r = self::denyUnlessAdmin()) {
            return $r;
        }
        $action = $req->str('action');
        if ($action === 'vente') {
            $c = \App\Shop\BookShop::saveConfig($req->post);
            Activity::log(self::actor(), 'a réglé la vente du livre (' . ($c['active'] ? 'en vente' : 'hors vente') . ')', null);
            return self::back('/admin/livre', $c['active'] ? 'Le livre est en vente dans la boutique.' : 'Réglages enregistrés : le livre n’est pas en vente.');
        }
        if ($action === 'couv-ajouter' || $action === 'couv-retirer') {
            $rel = \App\Data\Media::safeRel(preg_replace('#^.*?/media/(?:full|\d+)/|\.webp$#', '', trim((string) ($req->post['rel'] ?? ''))) ?? '');
            $list = Livre::covers();
            if ($action === 'couv-retirer') {
                Livre::saveCovers(array_diff($list, [$rel]));
                return self::back('/admin/livre', 'Photo retirée des couvertures proposées.');
            }
            $dpi = (new Livre())->coverDpi($rel);
            if ($dpi === null) {
                return self::back('/admin/livre', null, 'Photo introuvable dans la médiathèque, ou photo de presse.');
            }
            if ($dpi < Livre::DPI['page']) {
                return self::back('/admin/livre', null, 'Définition insuffisante pour la couverture : ' . $dpi . ' dpi (il en faut ' . Livre::DPI['page'] . '). Il faut un scan ou un original plus grand.');
            }
            Livre::saveCovers(array_merge($list, [$rel]));
            return self::back('/admin/livre', 'Photo proposée en couverture (' . $dpi . ' dpi).');
        }
        @set_time_limit(600);
        @ini_set('memory_limit', '1024M');
        $dec = array_values(array_filter(array_map('intval', (array) ($req->post['decennies'] ?? []))));
        $o = [
            'nom' => mb_substr(trim((string) ($req->post['nom'] ?? '')), 0, 60),
            'dedicace' => mb_substr(trim((string) ($req->post['dedicace'] ?? '')), 0, 600),
            'signature' => mb_substr(trim((string) ($req->post['signature'] ?? '')), 0, 80),
            'numero' => mb_substr(trim((string) ($req->post['numero'] ?? '')), 0, 12),
            'depuis' => (int) ($req->post['depuis'] ?? 0),
            'couverture' => (string) ($req->post['couverture'] ?? ''),
            'match' => (int) preg_replace('/\D/', '', (string) ($req->post['match'] ?? '')),
            'joueurs' => array_slice(array_values(array_filter(array_map('intval', preg_split('/\D+/', (string) ($req->post['joueurs'] ?? '')) ?: []))), 0, 3),
            'relire' => !empty($req->post['relire']),
            'decennies' => $dec,
        ];
        // Carnet : identifiant ou adresse de la page publique (/carnet/pseudo)
        $cid = trim((string) ($req->post['carnet'] ?? ''));
        if ($cid !== '' && !preg_match('/^[a-f0-9]{16}$/', $cid)) {
            $c = \App\Services\Carnet::bySlugOrAlias(basename(rtrim($cid, '/')));
            $cid = $c['id'] ?? '';
        }
        $o['carnet'] = $cid;
        $o['naissance'] = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($req->post['naissance'] ?? '')) ? (string) $req->post['naissance'] : '';
        $o['naissance_titre'] = mb_substr(trim((string) ($req->post['naissance_titre'] ?? '')), 0, 50);
        $o['maillot_nom'] = mb_substr(trim((string) ($req->post['maillot_nom'] ?? '')), 0, 14);
        $o['maillot_numero'] = mb_substr(preg_replace('/\D/', '', (string) ($req->post['maillot_numero'] ?? '')), 0, 2);
        $o['maillot_style'] = (string) ($req->post['maillot_style'] ?? '2026');
        $o['qr'] = !empty($req->post['qr']);
        $o['photo_legende'] = mb_substr(trim((string) ($req->post['photo_legende'] ?? '')), 0, 120);
        $f = $req->files['photo'] ?? null;
        if ($f && ($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $info = @getimagesize((string) $f['tmp_name']);
            if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG], true)) {
                return self::back('/admin/livre', null, 'Photo du lecteur : JPEG ou PNG seulement.');
            }
            if (!Livre::photoFrame((string) $f['tmp_name'])) {
                return self::back('/admin/livre', null, 'Photo du lecteur trop petite pour être imprimée nettement (' . $info[0] . ' × ' . $info[1] . ' px ; il faut au moins 670 px de large).');
            }
            $dir = STORAGE_PATH . '/livres/photos';
            @mkdir($dir, 0775, true);
            $dest = $dir . '/' . bin2hex(random_bytes(8)) . ($info[2] === IMAGETYPE_PNG ? '.png' : '.jpg');
            move_uploaded_file((string) $f['tmp_name'], $dest);
            $o['photo'] = $dest;
        }
        // Maillot photographié en 3D par le navigateur (PNG transparent), sinon le maillot dessiné
        foreach (['maillot_image', 'maillot_devant'] as $field) {
            $mi = $req->files[$field] ?? null;
            if ($mi && ($mi['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK && (int) $mi['size'] < 20 * 1024 * 1024) {
                $info = @getimagesize((string) $mi['tmp_name']);
                if ($info && $info[2] === IMAGETYPE_PNG && $info[0] >= 800) {
                    $dir = STORAGE_PATH . '/livres/photos';
                    @mkdir($dir, 0775, true);
                    $dest = $dir . '/maillot-' . bin2hex(random_bytes(8)) . '.png';
                    move_uploaded_file((string) $mi['tmp_name'], $dest);
                    $o[$field] = $dest;
                }
            }
        }
        $book = new Livre($o);
        $t = microtime(true);
        $pdf = $book->build();
        $rep = $book->report() + ['at' => date('c'), 'seconds' => round(microtime(true) - $t, 1), 'size' => strlen($pdf), 'pages' => preg_match_all('#/Type /Page\b#', $pdf), 'options' => $o];
        @mkdir(dirname(self::LAST), 0775, true);
        file_put_contents(self::LAST, json_encode($rep, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        Activity::log(self::actor(), 'a composé le livre des récits (' . $rep['pages'] . ' pages)', null);
        $name = 'livre-100-recits' . ($o['nom'] !== '' ? '-' . slugify($o['nom']) : '') . '-' . date('Ymd') . '.pdf';
        return new Response($pdf, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="' . $name . '"', 'Cache-Control' => 'no-store']);
    }
}
