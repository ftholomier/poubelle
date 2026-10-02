<?php
declare(strict_types=1);

namespace App\Services\Import;

use App\Core\Cache;
use App\Core\Fs;
use App\Core\Request;
use App\Core\Response;

/**
 * Données livrées avec le site (ancienne base déjà convertie en fichiers + photos des pros), regroupées dans
 * une ou plusieurs archives (donnees.zip, ou donnees-1-sur-3.zip, donnees-2-sur-3.zip…) pour que l'envoi par
 * FTP reste rapide. Elles sont décompressées automatiquement dès la première visite, par lots de quelques
 * secondes, puis supprimées. Aucune base de données, aucune manipulation.
 */
final class Bundle
{
    private const DIR = STORAGE_PATH . '/install';
    private const STATE = self::DIR . '/progression.json';
    private const DONE = self::DIR . '/termine.json';
    private const LOCK = self::DIR . '/installation.lock';
    /** Seuls ces dossiers peuvent être écrits par les archives. */
    private const PREFIXES = ['storage/data/', 'public/media/pros/'];

    public static function pending(): bool
    {
        return !is_file(self::DONE) && self::present() !== [];
    }

    /** Décompresse pendant $budget secondes au plus, puis renvoie la page de progression (rechargée automatiquement). */
    public static function handle(float $budget = 8.0): Response
    {
        @set_time_limit(120);
        ignore_user_abort(true);
        if (!class_exists(\ZipArchive::class)) {
            return self::page('L\'extension PHP « zip » est nécessaire pour installer les données. Activez-la dans l\'espace client de votre hébergement (réglages PHP), puis rechargez cette page.');
        }
        $fh = @fopen(self::LOCK, 'c');
        if (!$fh) {
            return self::page('Le dossier storage/ n\'est pas modifiable par le site : donnez-lui les droits d\'écriture (755 ou 775) dans votre logiciel FTP, puis rechargez cette page.');
        }
        if (!flock($fh, LOCK_EX | LOCK_NB)) {
            fclose($fh);
            return self::page(null, self::percent(self::state()));
        }
        try {
            $state = self::state();
            $missing = self::missing($state);
            if ($missing) {
                $many = count($missing) > 1;
                return self::page('Il manque ' . ($many ? 'les fichiers ' : 'le fichier ') . implode(', ', $missing) . ' : envoyez-' . ($many ? 'les' : 'le')
                    . ' par FTP dans le dossier storage/install/ du site, sans ' . ($many ? 'les' : 'le') . ' décompresser. Cette page reprendra toute seule.', 0, 15);
            }
            $t0 = microtime(true);
            foreach (self::present() as $file) {
                $name = basename($file);
                if (preg_match('/-\d+-sur-(\d+)\.zip$/', $name, $m)) {
                    $state['parts'] = max((int) $state['parts'], (int) $m[1]);
                }
                if (in_array($name, $state['done'], true)) {
                    @unlink($file); // déjà installée
                    continue;
                }
                $zip = new \ZipArchive();
                if ($zip->open($file) !== true) {
                    return self::page('L\'archive ' . $name . ' est illisible : renvoyez-la par FTP (transfert binaire) dans storage/install/, puis rechargez cette page.');
                }
                $total = $zip->numFiles;
                $offset = $state['current'] === $name ? (int) $state['offset'] : 0;
                while ($offset < $total && microtime(true) - $t0 < $budget) {
                    $names = [];
                    $end = min($total, $offset + 400);
                    for ($i = $offset; $i < $end; $i++) {
                        $entry = (string) $zip->getNameIndex($i);
                        if (self::safe($entry)) {
                            $names[] = $entry;
                        }
                    }
                    if ($names && !$zip->extractTo(BASE_PATH, $names)) {
                        $zip->close();
                        return self::page('Décompression impossible : vérifiez que les dossiers storage/ et public/media/ sont modifiables (droits d\'écriture), puis rechargez cette page.');
                    }
                    $offset = $end;
                    $state = ['current' => $name, 'offset' => $offset, 'total' => $total] + $state;
                    Fs::writeJson(self::STATE, $state);
                }
                $zip->close();
                if ($offset < $total) {
                    return self::page(null, self::percent($state));
                }
                $state['done'][] = $name;
                $state = ['current' => null, 'offset' => 0, 'total' => 0] + $state;
                Fs::writeJson(self::STATE, $state);
                @unlink($file);
                if (microtime(true) - $t0 >= $budget) {
                    return self::page(null, self::percent($state));
                }
            }
            Fs::writeJson(self::DONE, ['at' => date('c'), 'archives' => $state['done']]);
            @touch(STORAGE_PATH . '/cache/sitemap.dirty'); // sitemaps à refaire avec les données installées
            @unlink(self::STATE);
            Cache::flush();
            Cache::flush('pages');
            Cache::bump();
            return Response::redirect(Request::uri(), 302);
        } finally {
            flock($fh, LOCK_UN);
            fclose($fh);
        }
    }

    /** @return string[] archives de données présentes, dans l'ordre */
    private static function present(): array
    {
        $files = array_values(array_filter(glob(self::DIR . '/donnees*.zip') ?: [], static fn (string $f): bool => preg_match('/^donnees(-\d+-sur-\d+)?\.zip$/', basename($f)) === 1));
        natsort($files);
        return array_values($files);
    }

    /** @return string[] parties attendues ni présentes ni déjà installées */
    private static function missing(array $state): array
    {
        $n = (int) $state['parts'];
        foreach (self::present() as $f) {
            if (preg_match('/-\d+-sur-(\d+)\.zip$/', $f, $m)) {
                $n = max($n, (int) $m[1]);
            }
        }
        $missing = [];
        for ($k = 1; $k <= $n; $k++) {
            $name = 'donnees-' . $k . '-sur-' . $n . '.zip';
            if (!is_file(self::DIR . '/' . $name) && !in_array($name, $state['done'], true)) {
                $missing[] = $name;
            }
        }
        return $missing;
    }

    /** @return array{done:string[], current:?string, offset:int, total:int, parts:int} */
    private static function state(): array
    {
        $s = Fs::readJson(self::STATE, []);
        return (is_array($s) ? $s : []) + ['done' => [], 'current' => null, 'offset' => 0, 'total' => 0, 'parts' => 0];
    }

    private static function percent(array $state): int
    {
        $parts = max(1, (int) $state['parts'], count($state['done']) + count(self::present()));
        $current = (int) $state['total'] > 0 ? (int) $state['offset'] / (int) $state['total'] : 0;
        return (int) min(99, floor((count($state['done']) + $current) * 100 / $parts));
    }

    private static function safe(string $name): bool
    {
        if ($name === '' || str_contains($name, '..') || str_contains($name, '\\') || str_contains($name, "\0")) {
            return false;
        }
        foreach (self::PREFIXES as $p) {
            if (str_starts_with($name, $p)) {
                return true;
            }
        }
        return false;
    }

    /** Page autonome (aucune donnée n'est encore en place) : progression, attente d'un fichier ou erreur. */
    private static function page(?string $error = null, int $percent = 0, int $refresh = 1): Response
    {
        $waiting = $error !== null && $percent === 0 && $refresh > 1;
        $title = $error === null ? 'Installation en cours…' : ($waiting ? 'Installation en attente' : 'Installation interrompue');
        $body = $error === null
            ? '<p>Mise en place des fiches, demandes, messages et photos de l\'ancien site. Cette page se recharge toute seule : laissez-la ouverte (quelques minutes au plus).</p><div class="bar"><i style="width:' . $percent . '%"></i></div><p class="pct">' . $percent . ' %</p>'
            : '<p>' . htmlspecialchars($error, ENT_QUOTES, 'UTF-8') . '</p>';
        $html = '<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">'
            . ($error === null || $waiting ? '<meta http-equiv="refresh" content="' . $refresh . '">' : '')
            . '<title>' . $title . '</title><style>body{margin:0;min-height:100vh;display:grid;place-items:center;background:#fff6e8;color:#1c1233;font:16px/1.55 system-ui,-apple-system,"Segoe UI",sans-serif}'
            . 'main{max-width:520px;margin:24px;padding:32px;background:#fff;border:2px solid #1c1233;border-radius:18px;box-shadow:6px 6px 0 #1c1233}h1{margin:0 0 8px;font-size:24px}'
            . '.bar{height:14px;border:2px solid #1c1233;border-radius:99px;overflow:hidden;margin-top:18px}.bar i{display:block;height:100%;background:#ff4f3a}.pct{margin:8px 0 0;font-weight:700;text-align:right}</style></head>'
            . '<body><main><h1>' . $title . '</h1>' . $body . '</main></body></html>';
        $res = Response::html($html, 503);
        $res->header('Retry-After', '5');
        $res->header('Cache-Control', 'no-store');
        return $res;
    }
}
