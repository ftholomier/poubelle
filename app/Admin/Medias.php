<?php
declare(strict_types=1);

namespace App\Admin;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Data\Activity;
use App\Data\Collections;
use App\Data\Index;
use App\Data\Media;
use App\Data\Paths;
use App\Services\Images;
use App\Services\Search;

/**
 * Médiathèque : recherche et filtres (sans crédit, sans légende, inutilisés…),
 * envoi par glisser-déposer, légende / crédit / droits / texte alternatif
 * (FR et EN), crédit appliqué à une sélection, « utilisée dans », remplacement
 * du fichier (mêmes adresses) et suppression (administrateurs).
 */
final class Medias extends Base
{
    public const FILTERS = [
        '' => 'Tous les médias',
        'sans-credit' => 'Sans crédit',
        'sans-legende' => 'Sans légende',
        'inutilise' => 'Inutilisés',
        'pdf' => 'Documents PDF',
        'contributions' => 'Issus des contributions',
        'recents' => 'Ajoutés ces 30 derniers jours',
    ];
    private const PER_PAGE = 60;

    public static function index(Request $req): Response
    {
        $all = Media::all();
        $usage = Media::usage();
        $q = Search::norm($req->str('q'));
        $filter = isset(self::FILTERS[$req->str('filtre')]) ? $req->str('filtre') : '';
        $folder = $req->str('dossier');
        $sort = in_array($req->str('tri'), ['recent', 'ancien', 'nom'], true) ? $req->str('tri') : 'recent';
        $folders = [];
        $counts = array_fill_keys(array_keys(self::FILTERS), 0);
        $list = [];
        $recent = date('c', strtotime('-30 days'));
        foreach ($all as $rel => $m) {
            $rel = (string) $rel;
            $top = explode('/', $rel)[0];
            $folders[$top] = ($folders[$top] ?? 0) + 1;
            $flags = self::flags($rel, $m, $usage, $recent);
            $counts['']++;
            foreach ($flags as $f) {
                $counts[$f]++;
            }
            if ($filter !== '' && !in_array($filter, $flags, true)) {
                continue;
            }
            if ($folder !== '' && $top !== $folder) {
                continue;
            }
            if ($q !== '' && !str_contains(Search::norm($rel . ' ' . ($m['title'] ?? '') . ' ' . ($m['caption'] ?? '') . ' ' . ($m['credit'] ?? '') . ' ' . ($m['alt'] ?? '') . ' ' . ($m['caption_raw'] ?? '')), $q)) {
                continue;
            }
            $list[$rel] = $m;
        }
        krsort($folders, SORT_NATURAL);
        $key = fn (string $rel, array $m) => (string) ($m['added'] ?? $m['date'] ?? $rel);
        match ($sort) {
            'nom' => uksort($list, fn ($a, $b) => strnatcasecmp(basename((string) $a), basename((string) $b))),
            'ancien' => uksort($list, fn ($a, $b) => strcmp($key((string) $a, $list[$a]), $key((string) $b, $list[$b]))),
            default => uksort($list, fn ($a, $b) => strcmp($key((string) $b, $list[$b]), $key((string) $a, $list[$a]))),
        };
        $total = count($list);
        $pages = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min($pages, max(1, (int) $req->str('page', '1')));
        $items = [];
        foreach (array_slice($list, ($page - 1) * self::PER_PAGE, self::PER_PAGE, true) as $rel => $m) {
            $items[] = self::item((string) $rel, $m, $usage);
        }
        return self::html('admin/medias/index', [
            'items' => $items, 'total' => $total, 'page' => $page, 'pages' => $pages, 'counts' => $counts,
            'folders' => $folders, 'filter' => $filter, 'folder' => $folder, 'sort' => $sort, 'q' => $req->str('q'),
            'query' => array_filter(['q' => $req->str('q'), 'filtre' => $filter, 'dossier' => $folder, 'tri' => $sort === 'recent' ? '' : $sort]),
            'canDelete' => Auth::can('destroy'),
        ], ['title' => 'Médiathèque', 'crumb' => 'Contenus', 'nav' => 'medias', 'scripts' => ['admin/medias.js']]);
    }

    /** @return list<string> filtres auxquels appartient le média */
    private static function flags(string $rel, array $m, array $usage, string $recent): array
    {
        $f = [];
        $isPdf = str_ends_with(strtolower($rel), '.pdf');
        if (!$isPdf && trim((string) ($m['credit'] ?? '')) === '') {
            $f[] = 'sans-credit';
        }
        if (trim((string) ($m['caption'] ?? '')) === '') {
            $f[] = 'sans-legende';
        }
        if (empty($usage[$rel])) {
            $f[] = 'inutilise';
        }
        if ($isPdf) {
            $f[] = 'pdf';
        }
        if (str_starts_with($rel, 'contributions/')) {
            $f[] = 'contributions';
        }
        if ((string) ($m['added'] ?? '') >= $recent) {
            $f[] = 'recents';
        }
        return $f;
    }

    /** Données d'une carte de la médiathèque (et de sa fenêtre d'édition). */
    private static function item(string $rel, array $m, array $usage): array
    {
        $used = [];
        foreach (array_slice(array_unique($usage[$rel] ?? []), 0, 30) as $u) {
            if (is_int($u)) {
                $s = Index::get($u);
                $used[] = ['label' => $s ? $s['title'] : "Fiche n° $u", 'url' => '/admin/fiche/' . $u, 'status' => $s['status'] ?? ''];
            } else {
                $name = substr((string) $u, 2);
                $used[] = ['label' => ucfirst(Collections::label($name)), 'url' => self::collectionUrl($name), 'status' => ''];
            }
        }
        $isPdf = str_ends_with(strtolower($rel), '.pdf');
        $file = Media::file($rel);
        return [
            'file' => $rel,
            'name' => basename($rel),
            'thumb' => $isPdf ? '/assets/admin/pdf.svg' : img($rel, 320),
            'large' => $isPdf ? '/media/full/' . $rel : img($rel, 800),
            'full' => '/media/full/' . $rel,
            'pdf' => $isPdf,
            'missing' => $file === null,
            'caption' => (string) ($m['caption'] ?? ''),
            'caption_en' => (string) ($m['caption_en'] ?? ''),
            'credit' => (string) ($m['credit'] ?? ''),
            'alt' => (string) ($m['alt'] ?? ''),
            'alt_en' => (string) ($m['alt_en'] ?? ''),
            'rights' => (string) ($m['rights'] ?? ''),
            'date_text' => (string) ($m['date_text'] ?? ''),
            'source' => (string) ($m['source'] ?? ($m['wp_parent'] ? 'WordPress' : '')),
            'raw' => (string) ($m['caption_raw'] ?? ''),
            'width' => (int) ($m['width'] ?? 0),
            'height' => (int) ($m['height'] ?? 0),
            'size' => $file ? Base::size((int) filesize($file)) : '',
            'added' => (string) ($m['added'] ?? $m['date'] ?? ''),
            'used' => $used,
            'used_count' => count(array_unique($usage[$rel] ?? [])),
        ];
    }

    private static function collectionUrl(string $name): string
    {
        return match ($name) {
            'slider', 'ticker', 'palmares', 'epoques', 'teasers', 'reserves' => '/admin/accueil',
            'rubriques' => '/admin/rubriques',
            'clubs', 'stades' => '/admin/referentiels',
            default => '/admin/collection/' . $name,
        };
    }

    /** Envoi d'un fichier (sélecteur d'images, médiathèque) ou remplacement d'un fichier existant. */
    public static function upload(Request $req): Response
    {
        $f = $req->files['file'] ?? null;
        if (!$f || ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $code = (int) ($f['error'] ?? UPLOAD_ERR_NO_FILE);
            return self::json(['error' => in_array($code, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) ? 'Fichier trop lourd pour le serveur (limite PHP upload_max_filesize).' : 'Aucun fichier reçu.'], 422);
        }
        $name = (string) $f['name'];
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file((string) $f['tmp_name']) ?: '';
        if (!in_array($ext, Media::UPLOAD_EXT, true) || !preg_match('#^(image/(jpeg|png|gif|webp)|application/pdf)$#', $mime)) {
            return self::json(['error' => "« $name » : format refusé (JPG, PNG, GIF, WebP ou PDF)."], 422);
        }
        if ((int) $f['size'] > Media::UPLOAD_MAX) {
            return self::json(['error' => "« $name » dépasse 25 Mo."], 422);
        }
        if (str_starts_with($mime, 'image/') && !@getimagesize((string) $f['tmp_name'])) {
            return self::json(['error' => "« $name » : image illisible."], 422);
        }
        $user = self::actor();
        $replace = (string) ($req->post['replace'] ?? '');
        if ($replace !== '') {
            $old = Media::get($replace);
            $oldExt = strtolower(pathinfo($replace, PATHINFO_EXTENSION));
            $same = fn ($e) => $e === 'jpeg' ? 'jpg' : $e;
            if (!$old) {
                return self::json(['error' => 'Média à remplacer introuvable.'], 404);
            }
            if ($same($oldExt) !== $same($ext)) {
                return self::json(['error' => 'Le nouveau fichier doit avoir le même format (.' . $oldExt . ') pour garder les mêmes adresses.'], 422);
            }
            $rel = $replace;
        } else {
            $base = Paths::slug(pathinfo($name, PATHINFO_FILENAME), 60) ?: 'image';
            $dir = date('Y/m');
            $rel = "$dir/$base.$ext";
            for ($i = 2; is_file(Media::ORIGINALS . '/' . $rel) || Media::get($rel); $i++) {
                $rel = "$dir/$base-$i.$ext";
            }
        }
        $dest = Media::ORIGINALS . '/' . $rel;
        if (!is_dir(dirname($dest))) {
            mkdir(dirname($dest), 0775, true);
        }
        if (!move_uploaded_file((string) $f['tmp_name'], $dest) && !(PHP_SAPI === 'cli' && rename((string) $f['tmp_name'], $dest))) {
            return self::json(['error' => 'Écriture impossible sur le serveur.'], 500);
        }
        @chmod($dest, 0664);
        self::purgeDerivatives($rel);
        [$w, $h] = @getimagesize($dest) ?: [null, null];
        $meta = ['file' => $rel, 'mime' => $mime, 'width' => $w, 'height' => $h, 'size' => filesize($dest)];
        if ($replace === '') {
            $meta += ['title' => pathinfo($name, PATHINFO_FILENAME), 'caption' => '', 'credit' => '', 'alt' => '', 'rights' => '', 'added' => date('c'), 'source' => 'back-office (' . $user['name'] . ')'];
        } else {
            $meta['replaced'] = date('c');
        }
        Media::put($rel, $meta, $user);
        return self::json(['ok' => true, 'file' => $rel, 'thumb' => img($rel, 320), 'replaced' => $replace !== '']);
    }

    /** Légende, crédit, droits… d'un média, ou crédit / droits appliqués à une sélection. */
    public static function save(Request $req): Response
    {
        $d = $req->json() ?: $req->post;
        $user = self::actor();
        if (!empty($d['files']) && is_array($d['files'])) {
            $changes = [];
            foreach (['credit', 'rights', 'caption', 'date_text'] as $k) {
                if (isset($d[$k]) && trim((string) $d[$k]) !== '') {
                    $changes[$k] = Html::line($d[$k], $k === 'caption' ? 500 : 250);
                }
            }
            if (!$changes) {
                return self::json(['error' => 'Indiquez au moins un crédit, des droits ou une légende à appliquer.', 'field' => 'credit'], 422);
            }
            $overwrite = !empty($d['overwrite']);
            $batch = [];
            foreach (array_slice($d['files'], 0, 2000) as $rel) {
                $m = Media::get((string) $rel);
                if (!$m) {
                    continue;
                }
                // Par défaut, seuls les champs vides sont complétés.
                $apply = $overwrite ? $changes : array_filter($changes, fn ($v, $k) => trim((string) ($m[$k] ?? '')) === '', ARRAY_FILTER_USE_BOTH);
                if ($apply) {
                    $batch[(string) $rel] = $apply;
                }
            }
            $n = $batch ? Media::putMany($batch) : 0;
            Activity::log($user, 'a complété ' . $n . ' média(s)', ['title' => implode(', ', array_keys($changes))]);
            \App\Data\Derived::markDirty();
            return self::json(['ok' => true, 'message' => $n . ' média(s) mis à jour.', 'reload' => true]);
        }
        $rel = (string) ($d['file'] ?? '');
        if (!Media::get($rel)) {
            return self::json(['error' => 'Média introuvable.'], 404);
        }
        $meta = [
            'caption' => Html::line($d['caption'] ?? '', 500),
            'caption_en' => Html::line($d['caption_en'] ?? '', 500),
            'credit' => Html::line($d['credit'] ?? '', 250),
            'alt' => Html::line($d['alt'] ?? '', 300),
            'alt_en' => Html::line($d['alt_en'] ?? '', 300),
            'rights' => Html::line($d['rights'] ?? '', 250),
            'date_text' => Html::line($d['date_text'] ?? '', 120),
        ];
        Media::put($rel, $meta, $user);
        \App\Data\Derived::markDirty();
        return self::json(['ok' => true, 'message' => 'Média enregistré.', 'item' => self::item($rel, Media::get($rel) ?? [], Media::usage())]);
    }

    public static function delete(Request $req): Response
    {
        if (!Auth::can('destroy')) {
            return self::json(['error' => 'Suppression réservée aux administrateurs.'], 403);
        }
        $d = $req->json() ?: $req->post;
        $rel = (string) ($d['file'] ?? '');
        if (!Media::get($rel)) {
            return self::json(['error' => 'Média introuvable.'], 404);
        }
        $used = array_unique(Media::usage()[$rel] ?? []);
        if ($used && empty($d['force'])) {
            return self::json(['error' => 'Ce média est utilisé dans ' . count($used) . ' contenu(s) : retirez-le d’abord, ou confirmez la suppression.', 'used' => count($used)], 409);
        }
        $file = Media::file($rel);
        if ($file) {
            @unlink($file);
        }
        self::purgeDerivatives($rel);
        Media::remove($rel);
        Activity::log(self::actor(), 'a supprimé le média', ['title' => $rel]);
        return self::json(['ok' => true, 'message' => 'Média supprimé.', 'reload' => true]);
    }

    /** Efface les versions redimensionnées (public/media/{largeur}/…). */
    private static function purgeDerivatives(string $rel): void
    {
        foreach (Images::WIDTHS as $w) {
            @unlink(PUBLIC_PATH . "/media/$w/$rel.webp");
        }
    }
}
