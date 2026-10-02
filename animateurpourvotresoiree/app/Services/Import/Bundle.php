<?php
declare(strict_types=1);

namespace App\Services\Import;

use App\Core\Cache;
use App\Core\Fs;
use App\Core\Request;
use App\Core\Response;

/**
 * Données livrées avec le site (ancienne base déjà convertie en fichiers + photos des pros), regroupées dans
 * une seule archive pour que l'envoi par FTP reste rapide. Elle est décompressée automatiquement, par lots de
 * quelques secondes, dès la première visite, puis supprimée. Aucune base de données, aucune manipulation.
 */
final class Bundle
{
    public const FILE = STORAGE_PATH . '/install/donnees.zip';
    private const STATE = STORAGE_PATH . '/install/progression.json';
    private const DONE = STORAGE_PATH . '/install/termine.json';
    private const LOCK = STORAGE_PATH . '/install/installation.lock';
    /** Seuls ces dossiers peuvent être écrits par l'archive. */
    private const PREFIXES = ['storage/data/', 'public/media/pros/'];

    public static function pending(): bool
    {
        return is_file(self::FILE) && !is_file(self::DONE);
    }

    /** Décompresse un lot (pendant $budget secondes au plus) puis renvoie la page de progression, rechargée automatiquement. */
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
            return self::page(null, self::percent());
        }
        try {
            $zip = new \ZipArchive();
            if ($zip->open(self::FILE) !== true) {
                return self::page('L\'archive des données est illisible : renvoyez le fichier storage/install/donnees.zip par FTP (transfert binaire), puis rechargez cette page.');
            }
            $total = $zip->numFiles;
            $offset = (int) (Fs::readJson(self::STATE, ['offset' => 0])['offset'] ?? 0);
            $t0 = microtime(true);
            while ($offset < $total && microtime(true) - $t0 < $budget) {
                $names = [];
                $end = min($total, $offset + 400);
                for ($i = $offset; $i < $end; $i++) {
                    $name = (string) $zip->getNameIndex($i);
                    if (self::safe($name)) {
                        $names[] = $name;
                    }
                }
                if ($names && !$zip->extractTo(BASE_PATH, $names)) {
                    $zip->close();
                    return self::page('Décompression impossible : vérifiez que les dossiers storage/ et public/media/ sont modifiables (droits d\'écriture), puis rechargez cette page.');
                }
                $offset = $end;
                Fs::writeJson(self::STATE, ['offset' => $offset, 'total' => $total]);
            }
            $zip->close();
            if ($offset < $total) {
                return self::page(null, (int) floor($offset * 100 / max(1, $total)));
            }
            Fs::writeJson(self::DONE, ['at' => date('c'), 'files' => $total]);
            @unlink(self::FILE);
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

    private static function percent(): int
    {
        $s = Fs::readJson(self::STATE, ['offset' => 0, 'total' => 0]);
        return (int) floor((int) ($s['offset'] ?? 0) * 100 / max(1, (int) ($s['total'] ?? 0)));
    }

    /** Page autonome (aucune donnée n'est encore en place) : progression ou message d'erreur. */
    private static function page(?string $error = null, int $percent = 0): Response
    {
        $title = $error === null ? 'Installation en cours…' : 'Installation interrompue';
        $body = $error === null
            ? '<p>Mise en place des fiches, demandes, messages et photos de l\'ancien site. Cette page se recharge toute seule : laissez-la ouverte (une à deux minutes).</p><div class="bar"><i style="width:' . $percent . '%"></i></div><p class="pct">' . $percent . ' %</p>'
            : '<p>' . htmlspecialchars($error, ENT_QUOTES, 'UTF-8') . '</p>';
        $html = '<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">'
            . ($error === null ? '<meta http-equiv="refresh" content="1">' : '')
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
