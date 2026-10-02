<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Fs;
use App\Core\Request;
use App\Core\Str;
use App\Core\Url;

/**
 * Redirections 301 : anciennes URL du site (fiche.php, depresultat.php…), redirections
 * créées dans le back-office, et journal des pages introuvables.
 */
final class Redirects
{
    /** Redirection pour une URL inconnue, ou null. */
    public static function resolve(string $path, array $query): ?string
    {
        $manual = self::manual();
        $key = rtrim(strtolower($path), '/') ?: '/';
        foreach ([$path, $key, $key . '/'] as $candidate) {
            if (isset($manual[$candidate])) {
                self::countHit($candidate);
                return (string) $manual[$candidate]['to'];
            }
        }
        $legacy = self::legacy($path, $query);
        if ($legacy !== null) {
            return $legacy;
        }
        return null;
    }

    /** @return array<string,array{to:string,code:int,hits:int,created:string}> */
    public static function manual(): array
    {
        return Store::doc('redirects')->get('items', []);
    }

    public static function add(string $from, string $to, string $note = ''): void
    {
        $from = '/' . ltrim(trim($from), '/');
        Store::doc('redirects')->update(static function (array $d) use ($from, $to, $note): array {
            $d['items'][$from] = ['to' => trim($to), 'code' => 301, 'hits' => 0, 'note' => $note, 'created' => date('c')];
            return $d;
        });
    }

    public static function remove(string $from): void
    {
        Store::doc('redirects')->update(static function (array $d) use ($from): array {
            unset($d['items'][$from]);
            return $d;
        });
    }

    private static function countHit(string $from): void
    {
        $f = STORAGE_PATH . '/data/redirect_hits.json';
        try {
            Fs::withLock($f . '.lock', static function () use ($f, $from): void {
                $h = Fs::readJson($f, []);
                $h[$from] = ($h[$from] ?? 0) + 1;
                Fs::writeJson($f, $h);
            }, 2);
        } catch (\Throwable) {
        }
    }

    public static function hits(): array
    {
        return Fs::readJson(STORAGE_PATH . '/data/redirect_hits.json', []);
    }

    /** Correspondance des URL de l'ancien site. */
    public static function legacy(string $path, array $query): ?string
    {
        $p = strtolower($path);
        $q = static fn (string $k): string => trim((string) ($query[$k] ?? ''));
        switch ($p) {
            case '/index.php':
            case '/index.html':
            case '/accueil.php':
                return '/';
            case '/fiche.php':
            case '/fiche2.php':
                $id = (int) $q('id');
                $pro = $id > 0 ? Store::pros()->get($id) : null;
                if ($pro && Pros::isPublished($pro)) {
                    return Url::pro($pro);
                }
                if ($pro) {
                    $cat = $pro['categories'][0] ?? null;
                    return !empty($pro['dep']) ? Url::dep($cat, (string) $pro['dep']) : Url::category($cat);
                }
                return Url::category(null);
            case '/depresultat.php':
                return Url::dep(null, $q('dep'));
            case '/depresultatregion.php':
                $r = Geo::fromOldRegion($q('num_region'));
                return $r ? Url::region(null, $r['code']) : Url::category(null);
            case '/villeresultat.php':
                $ville = $q('ville');
                $cp = '';
                if (preg_match('/^(.*)-(\d{5})$/', $ville, $m)) {
                    [$ville, $cp] = [$m[1], $m[2]];
                }
                $c = Geo::match($ville, $cp, $q('dep'));
                return $c ? Url::city(null, $c['insee']) : Url::dep(null, $q('dep'));
            case '/moteurresultat.php':
                $mot = $q('mot');
                $cats = Categories::detect($mot, 1);
                $norm = Str::norm($mot);
                if ($cats && (str_word_count($norm) <= 2)) {
                    return Url::category($cats[0]);
                }
                foreach (Categories::occasions() as $slug => $o) {
                    if (in_array($norm, array_map([Str::class, 'norm'], $o['keywords']), true)) {
                        return Url::occasion($slug);
                    }
                }
                return Url::search(['q' => $mot]);
            case '/appeloffre.php':
            case '/appel_offre.php':
                return '/devis/';
            case '/prestations.php':
                return '/professionnels/';
            case '/form_inscription.php':
            case '/inscription.php':
            case '/form_inscription2.php':
                return '/inscription-pro/';
            case '/form_connexion.php':
            case '/connexion.php':
            case '/admin_animateur.php':
                return '/connexion/';
            case '/recherche.php':
                return '/recherche/';
            case '/listeville.php':
            case '/tags.php':
                return '/plan-du-site/';
            case '/ml.php':
            case '/mentions.php':
                return '/mentions-legales/';
            case '/contact.php':
                return '/contact/';
        }
        // Photos de l'ancien site : /upload/1345.JPG, /upload/1345_2.JPG
        if (preg_match('#^/upload/(\d+)(?:_(\d))?\.(jpe?g|png|gif)$#i', $path, $m)) {
            $pro = Store::pros()->get((int) $m[1]);
            $n = isset($m[2]) && $m[2] !== '' ? (int) $m[2] - 1 : 0;
            $photo = $pro['photos'][$n] ?? ($pro['photos'][0] ?? null);
            if ($pro && Pros::isPublished($pro) && $photo) {
                return Pros::photo($photo, 'lg');
            }
            return null;
        }
        // Anciens articles .php
        if (str_ends_with($p, '.php')) {
            $a = Blog::byLegacyUrl($path);
            if ($a) {
                return Url::blog($a['slug']);
            }
            return '/';
        }
        return null;
    }

    /** Journal des 404 (agrégé) pour créer des redirections depuis le back-office. */
    public static function log404(string $path): void
    {
        if (strlen($path) > 300 || Request::isBot() && preg_match('#\.(env|git|php\d?|asp|cgi|sql|bak|zip)$|wp-|xmlrpc|phpmyadmin#i', $path)) {
            return;
        }
        $f = STORAGE_PATH . '/data/404.json';
        try {
            Fs::withLock($f . '.lock', static function () use ($f, $path): void {
                $d = Fs::readJson($f, []);
                $d[$path] = ['n' => ($d[$path]['n'] ?? 0) + 1, 'last' => date('c'), 'ref' => mb_substr(Request::referer(), 0, 200)];
                if (count($d) > 2000) {
                    uasort($d, static fn ($a, $b) => strcmp($b['last'], $a['last']));
                    $d = array_slice($d, 0, 1500, true);
                }
                Fs::writeJson($f, $d);
            }, 2);
        } catch (\Throwable) {
        }
    }

    public static function notFoundLog(): array
    {
        $d = Fs::readJson(STORAGE_PATH . '/data/404.json', []);
        uasort($d, static fn ($a, $b) => $b['n'] <=> $a['n']);
        return $d;
    }
}
