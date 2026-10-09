<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\JsonStore;
use App\Core\Request;
use App\Core\Response;
use App\Data\Index;

/**
 * Suivi de la newsletter « Ce jour-là », lettre par lettre (une par semaine) : envois, ouvertures
 * (image invisible, approximatif : certaines messageries chargent les images d'office, d'autres
 * jamais), clics sur les liens (redirection par le site) et désinscriptions. Les abonnés n'y sont
 * comptés que par une empreinte de leur jeton, jamais par leur adresse.
 */
final class NewsletterStats
{
    private const DIR = STORAGE_PATH . '/newsletter/stats';

    private static function file(string $week): string
    {
        return self::DIR . '/' . $week . '.json';
    }

    private static function valid(string $week, string $token): bool
    {
        return (bool) preg_match('/^\d{4}-\d{2}$/', $week) && (bool) preg_match('/^[a-f0-9]{32}$/', $token);
    }

    private static function who(string $token): string
    {
        return substr(hash('sha256', 'nl|' . $token), 0, 16);
    }

    /**
     * Début d'une lettre : relève les liens du site présents dans le corps (toutes langues) et leur
     * donne un numéro. Seuls ces liens-là pourront être suivis (pas de redirection vers ailleurs).
     * @param array<string,string> $htmlByLang
     */
    public static function start(string $week, string $subject, array $htmlByLang, int $total): void
    {
        $base = base_url();
        $links = [];
        foreach ($htmlByLang as $html) {
            preg_match_all('/href="([^"]+)"/', $html, $m);
            foreach ($m[1] as $u) {
                $u = html_entity_decode($u, ENT_QUOTES, 'UTF-8');
                if (str_starts_with($u, $base . '/') && !str_contains($u, '/newsletter/desinscription/') && !in_array($u, $links, true)) {
                    $links[] = $u;
                }
            }
        }
        JsonStore::update(self::file($week), function ($s) use ($week, $subject, $links, $total) {
            $s = is_array($s) ? $s : [];
            return $s + ['week' => $week, 'subject' => $subject, 'started' => date('c'), 'total' => $total, 'links' => $links, 'sent' => 0, 'failed' => 0, 'opens' => [], 'clicks' => [], 'clickers' => [], 'unsub' => []];
        }, []);
    }

    /** Envois relevés à la fin de chaque passage. */
    public static function progress(string $week, int $sent, int $failed, bool $finished): void
    {
        JsonStore::update(self::file($week), function ($s) use ($sent, $failed, $finished) {
            if (!is_array($s) || !$s) {
                return $s;
            }
            $s['sent'] = $sent;
            $s['failed'] = $failed;
            if ($finished && empty($s['finished'])) {
                $s['finished'] = date('c');
            }
            return $s;
        }, []);
    }

    /** Liens du corps remplacés par des liens suivis, image d'ouverture ajoutée (pour un abonné). */
    public static function personalize(string $html, string $week, string $token): string
    {
        $s = JsonStore::read(self::file($week), []) ?: [];
        $links = (array) ($s['links'] ?? []);
        $base = base_url();
        $html = (string) preg_replace_callback('/href="([^"]+)"/', function ($m) use ($links, $week, $token, $base) {
            $i = array_search(html_entity_decode($m[1], ENT_QUOTES, 'UTF-8'), $links, true);
            return $i === false ? $m[0] : 'href="' . e($base . '/newsletter/c/' . $week . '/' . $token . '/' . $i . '/') . '"';
        }, $html);
        return $html . '<img src="' . e($base . '/newsletter/o/' . $week . '/' . $token . '/') . '" width="1" height="1" alt="" style="display:block;width:1px;height:1px;border:0">';
    }

    public static function click(Request $req, string $week, string $token, string $i): Response
    {
        $s = self::valid($week, $token) ? (JsonStore::read(self::file($week), []) ?: []) : [];
        $url = $s['links'][(int) $i] ?? null;
        if (!$url || !ctype_digit($i)) {
            return Response::redirect(url('/'));
        }
        $who = self::who($token);
        JsonStore::update(self::file($week), function ($s) use ($i, $who) {
            if (!is_array($s) || !$s) {
                return $s;
            }
            $s['clicks'][$i][$who] = ($s['clicks'][$i][$who] ?? 0) + 1;
            $s['clickers'][$who] ??= date('c');
            $s['opens'][$who] ??= date('c'); // un clic vaut ouverture (images bloquées)
            return $s;
        }, []);
        return Response::redirect($url);
    }

    public static function open(Request $req, string $week, string $token): Response
    {
        if (self::valid($week, $token) && is_file(self::file($week))) {
            $who = self::who($token);
            JsonStore::update(self::file($week), function ($s) use ($who) {
                if (is_array($s) && $s) {
                    $s['opens'][$who] ??= date('c');
                }
                return $s;
            }, []);
        }
        return new Response(base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7'), 200, ['Content-Type' => 'image/gif', 'Cache-Control' => 'no-store, private']);
    }

    /** Désinscription comptée sur la dernière lettre partie (dans les 14 jours), par moyen. */
    public static function unsub(string $via): void
    {
        $last = self::history(1)[0] ?? null;
        if (!$last || strtotime((string) $last['started']) < time() - 14 * 86400) {
            return;
        }
        JsonStore::update(self::file($last['week']), function ($s) use ($via) {
            if (is_array($s) && $s) {
                $s['unsub'][$via] = ($s['unsub'][$via] ?? 0) + 1;
            }
            return $s;
        }, []);
    }

    /**
     * Lettres, la plus récente d'abord, avec leurs taux.
     * @return list<array<string,mixed>>
     */
    public static function history(int $limit = 26): array
    {
        $files = glob(self::DIR . '/*.json') ?: [];
        rsort($files);
        $out = [];
        foreach (array_slice($files, 0, $limit) as $f) {
            $s = JsonStore::read($f, []) ?: [];
            if (!$s) {
                continue;
            }
            $sent = (int) ($s['sent'] ?? 0);
            $opens = count((array) ($s['opens'] ?? []));
            $clickers = count((array) ($s['clickers'] ?? []));
            $top = [];
            foreach ((array) ($s['clicks'] ?? []) as $i => $by) {
                $top[] = ['url' => $s['links'][$i] ?? '', 'label' => self::label((string) ($s['links'][$i] ?? '')), 'clicks' => array_sum($by), 'people' => count($by)];
            }
            usort($top, fn ($a, $b) => $b['people'] <=> $a['people'] ?: $b['clicks'] <=> $a['clicks']);
            $out[] = [
                'week' => $s['week'], 'subject' => $s['subject'] ?? '', 'started' => $s['started'] ?? null, 'finished' => $s['finished'] ?? null,
                'total' => (int) ($s['total'] ?? 0), 'sent' => $sent, 'failed' => (int) ($s['failed'] ?? 0),
                'opens' => $opens, 'clickers' => $clickers, 'clicks' => array_sum(array_map('array_sum', (array) ($s['clicks'] ?? []))),
                'openRate' => $sent ? $opens / $sent : null, 'clickRate' => $sent ? $clickers / $sent : null,
                'unsub' => array_sum((array) ($s['unsub'] ?? [])), 'unsubVia' => (array) ($s['unsub'] ?? []),
                'top' => $top,
            ];
        }
        return $out;
    }

    /** Nom lisible d'un lien suivi : le titre de la fiche, sinon le chemin. */
    private static function label(string $url): string
    {
        $path = (string) parse_url($url, PHP_URL_PATH);
        if ($path === '' || $path === '/') {
            return 'Accueil du musée';
        }
        $doc = Index::byPath($path);
        return $doc ? (string) $doc['title'] : match (true) {
            str_starts_with($path, '/faire-un-don') => 'Faire un don',
            str_starts_with($path, '/saisons') => 'Toutes les saisons',
            default => $path,
        };
    }
}
