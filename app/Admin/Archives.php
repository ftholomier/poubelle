<?php
declare(strict_types=1);

namespace App\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Data\Activity;
use App\Services\Catalogue;

/**
 * Contenus › Archives à ranger : catalogue des images d'archives (App\Services\Catalogue).
 * Dépôt des zip ou des images (administrateurs), puis validation image par image (toute l'équipe).
 */
final class Archives extends Base
{
    private const PER = 24;

    public static function index(Request $req): Response
    {
        $status = in_array($req->str('etat'), ['recu', 'range', 'ecarte', 'attente', 'tout'], true) ? $req->str('etat') : 'recu';
        $type = isset(Catalogue::TYPES[$req->str('type')]) ? $req->str('type') : '';
        $list = Catalogue::listing($status, $type);
        $page = max(1, (int) $req->str("page", "1"));
        $pages = max(1, (int) ceil(count($list) / self::PER));
        $slice = array_slice($list, ($page - 1) * self::PER, self::PER);
        foreach ($slice as &$it) {
            $it['suggest'] = $it['status'] === 'recu' ? Catalogue::suggest($it) : [];
        }
        unset($it);
        return self::html('admin/fiches/archives', [
            'list' => $slice, 'count' => count($list), 'page' => $page, 'pages' => $pages, 'status' => $status, 'type' => $type,
            'summary' => Catalogue::summary(), 'recits' => \App\Core\Auth::isAdmin() ? \App\Services\GrandsRecits::status() : [], 'admin' => \App\Core\Auth::isAdmin(), 'known' => \App\Core\Auth::isAdmin() ? array_keys(Catalogue::items()) : [],
        ], ['title' => 'Archives à ranger', 'crumb' => 'Contenus', 'nav' => 'archives', 'scripts' => \App\Core\Auth::isAdmin() ? ['vendor/jszip.min.js', 'admin/archives.js'] : []]);
    }

    /** POST : deposer (une image, JSON, admin), ranger, ecarter, retablir. */
    public static function action(Request $req): Response
    {
        $back = '/admin/archives?' . http_build_query(array_filter(['etat' => $req->str('etat'), 'type' => $req->str('type'), 'page' => $req->str('page')]));
        $user = self::actor();
        try {
            switch ($req->str('action')) {
                case 'deposer':
                    if ($r = self::denyUnlessAdmin()) {
                        return $r;
                    }
                    $f = $req->files['file'] ?? null;
                    if (!$f || ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                        return self::json(['error' => 'Aucun fichier reçu (limite du serveur ?).'], 422);
                    }
                    if ((int) $f['size'] > 25 * 1024 * 1024) {
                        return self::json(['error' => 'Fichier trop lourd.'], 422);
                    }
                    $r = Catalogue::receive((string) $f['tmp_name'], $user);
                    return self::json($r + ['name' => (string) $f['name']]);
                case 'ranger':
                    $ids = array_map('intval', (array) ($req->post['fiches'] ?? []));
                    $extra = (int) preg_replace('/\D/', '', (string) ($req->post['autre'] ?? ''));
                    if ($extra) {
                        $ids[] = $extra;
                    }
                    if (!$ids) {
                        return self::back($back, null, 'Cochez au moins une fiche (ou indiquez son numéro).');
                    }
                    $n = Catalogue::accept($req->str('md5'), $ids, (string) ($req->post['caption'] ?? ''), $user);
                    Activity::log($user, 'a rangé une image d’archives dans ' . $n . ' fiche(s)', null);
                    return self::back($back, 'Image ajoutée à la galerie de ' . $n . ' fiche(s).');
                case 'recits':
                    if ($r = self::denyUnlessAdmin()) {
                        return $r;
                    }
                    $n = \App\Services\GrandsRecits::create($user);
                    Activity::log($user, 'a créé ' . $n . ' grand(s) récit(s)', null);
                    return self::back($back, $n ? $n . ' grand(s) récit(s) créé(s) dans la rubrique « Grands récits ». Rangez-y maintenant les images proposées.' : 'Tous les grands récits existent déjà.');
                case 'illustrer':
                    if ($r = self::denyUnlessAdmin()) {
                        return $r;
                    }
                    $r = \App\Services\GrandsRecits::illustrate($user);
                    return self::back($back, $r['photos'] ? $r['photos'] . ' photo(s) ajoutée(s) à ' . $r['recits'] . ' récit(s). Rien n’est retiré : retouchez les galeries en relisant les récits.' : 'Aucune nouvelle photo à proposer pour l’instant (déposez d’abord les archives).');
                case 'ecarter':
                    Catalogue::reject($req->str('md5'));
                    return self::back($back, 'Image écartée.');
                case 'retablir':
                    Catalogue::restore($req->str('md5'));
                    return self::back($back, 'Image remise à ranger.');
            }
        } catch (\Throwable $e) {
            return $req->str('action') === 'deposer' ? self::json(['error' => $e->getMessage()], 500) : self::back($back, null, $e->getMessage());
        }
        return self::back($back);
    }
}
