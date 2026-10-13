<?php
declare(strict_types=1);

namespace App\Widgets\Screen;

use App\Core\JsonStore;
use App\Core\Response;
use App\Widgets\Context;
use App\Widgets\Store;

/**
 * Export d'une playlist pour les régies qui ne lisent pas de page web (back-office, Écrans) :
 * - ZIP des images PNG de chaque slide (rendu GD : Image), avec l'ordre et les durées
 *   (ordre.txt), et la commande ffmpeg pour en faire soi-même une vidéo (video-ffmpeg.txt) ;
 * - vidéo MP4 (H.264, 1920 × 1080 ou 1080 × 1920, 25 images/s, sans son), fabriquée en tâche de
 *   fond après la réponse au navigateur, seulement si le serveur a ffmpeg (avec libx264) et
 *   le droit de le lancer (exec) : détecté par Export::ffmpeg() ; sinon un message clair et le ZIP.
 * Fichiers dans Store::dir()/exports/ (hors du dossier public), servis au seul back-office.
 */
final class Export
{
    /** Une vidéo en cours depuis plus longtemps : considérée comme interrompue (relançable). */
    public const STALE = 900;

    /** Chemin de ffmpeg imposé (tests) ; '' : aucun ; null : détection. */
    public static ?string $ffmpegPath = null;
    private static ?string $found = null;

    public static function dir(): string
    {
        return Store::dir() . '/exports';
    }

    /**
     * ffmpeg utilisable (avec l'encodeur H.264 libx264) : son chemin, sinon null. o2switch
     * (mutualisé) n'en a pas d'ordinaire : le ZIP des images reste alors la solution.
     */
    public static function ffmpeg(): ?string
    {
        if (self::$ffmpegPath !== null) {
            return self::$ffmpegPath === '' ? null : self::$ffmpegPath;
        }
        if (self::$found === null) {
            self::$found = '';
            $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));
            if (function_exists('exec') && !in_array('exec', $disabled, true)) {
                $cands = ['/usr/bin/ffmpeg', '/usr/local/bin/ffmpeg', '/bin/ffmpeg', '/opt/bin/ffmpeg'];
                $o = [];
                @exec('command -v ffmpeg 2>/dev/null', $o);
                if (!empty($o[0])) {
                    array_unshift($cands, trim($o[0]));
                }
                foreach (array_unique($cands) as $p) {
                    if (@is_executable($p)) {
                        $enc = [];
                        @exec(escapeshellarg($p) . ' -hide_banner -encoders 2>/dev/null', $enc);
                        if (str_contains(implode("\n", $enc), 'libx264')) {
                            self::$found = $p;
                            break;
                        }
                    }
                }
            }
        }
        return self::$found === '' ? null : self::$found;
    }

    /** Pourquoi la vidéo n'est pas possible ici (message pour le back-office), ou null. */
    public static function videoUnavailable(): ?string
    {
        if (self::ffmpeg() !== null) {
            return null;
        }
        $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));
        $exec = function_exists('exec') && !in_array('exec', $disabled, true);
        return ($exec ? 'Ce serveur n’a pas ffmpeg (avec l’encodeur H.264)' : 'Ce serveur interdit de lancer un programme (fonction exec désactivée)')
            . ' : la vidéo MP4 ne peut pas être fabriquée ici (cas habituel d’un hébergement mutualisé comme o2switch). Téléchargez le ZIP des images : il contient l’ordre, les durées et la commande ffmpeg pour faire la vidéo sur un ordinateur, et la plupart des logiciels d’affichage dynamique savent enchaîner des images.';
    }

    /** Clé d'un export (playlist, format, langue, adversaire). */
    public static function key(string $id, string $format, string $lang, string $adv): string
    {
        return $id . '-' . ($format === 'vertical' ? 'vertical' : 'paysage') . '-' . ($lang === 'en' ? 'en' : 'fr') . ($adv !== '' ? '-' . substr(trim(slugify($adv), '-') ?: md5($adv), 0, 40) : '');
    }

    /** Context des exports : ni partenaire ni aperçu (les QR codes mènent au musée, comptés « libre »). */
    private static function context(string $lang): Context
    {
        \App\Services\I18n::set($lang === 'en' ? 'en' : 'fr');
        return new Context('playlist', $lang === 'en' ? 'en' : 'fr', 'sombre', 'ecran');
    }

    /** Images de la playlist : [[nom, png, durée], …]. */
    public static function frames(array $p, string $format, string $lang, string $adv): array
    {
        $c = self::context($lang);
        $out = [];
        foreach (Slides::build($p, $c, $adv) as $s) {
            foreach (Image::frames($s, $c, $format === 'vertical') as $name => [$png, $d]) {
                $out[] = [$name, $png, $d];
            }
        }
        return $out;
    }

    /** ZIP des images (fabriqué à la demande) : chemin du fichier. */
    public static function zip(array $p, string $format, string $lang, string $adv): string
    {
        @set_time_limit(120);
        $dir = self::dir();
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException('Dossier des exports impossible à créer.');
        }
        $frames = self::frames($p, $format, $lang, $adv);
        if (!$frames) {
            throw new \RuntimeException('Rien à exporter : aucune slide n’a de contenu aujourd’hui.');
        }
        $file = $dir . '/' . self::key($p['id'], $format, $lang, $adv) . '.zip';
        $tmp = $file . '.' . bin2hex(random_bytes(3)) . '.tmp';
        $z = new \ZipArchive();
        if ($z->open($tmp, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('ZIP impossible à créer.');
        }
        $order = '';
        $concat = '';
        $total = 0;
        foreach ($frames as [$name, $png, $d]) {
            $z->addFromString($name, $png);
            $order .= $name . "\t" . $d . " s\n";
            $concat .= "file '" . $name . "'\nduration " . $d . "\n";
            $total += $d;
        }
        $concat .= "file '" . $frames[count($frames) - 1][0] . "'\n";
        [$w, $h] = $format === 'vertical' ? [1080, 1920] : [1920, 1080];
        $z->addFromString('ordre.txt', 'Playlist « ' . $p['name'] . ' » (' . \App\Core\Club::s('site') . ") : les images dans l'ordre, avec la durée de chacune ; une boucle dure " . $total . " s.\n"
            . "Images : " . $w . ' × ' . $h . " px. Le logo du musée, la mention « non officiel » et l'adresse du musée sont dans chaque image : ne les recadrez pas.\n\n" . $order);
        $z->addFromString('concat.txt', $concat);
        $z->addFromString('video-ffmpeg.txt', "Pour faire une vidéo MP4 (H.264, sans son) de ces images, sur un ordinateur qui a ffmpeg (https://ffmpeg.org), dans ce dossier :\n\n" . self::command('concat.txt', 'playlist.mp4', $w, $h) . "\n");
        $z->close();
        rename($tmp, $file);
        return $file;
    }

    /** Commande ffmpeg : images (liste concat) → MP4 H.264, 25 images/s, sans son, lecture rapide sur le web. */
    public static function command(string $list, string $out, int $w, int $h, string $bin = 'ffmpeg'): string
    {
        return $bin . ' -y -loglevel error -f concat -safe 0 -i ' . escapeshellarg($list)
            . ' -vf ' . escapeshellarg('fps=25,format=yuv420p')
            . ' -c:v libx264 -preset veryfast -crf 20 -tune stillimage -an -movflags +faststart ' . escapeshellarg($out);
    }

    // ------------------------------------------------------------------ vidéo en tâche de fond

    /** État d'une vidéo : null (jamais demandée) ou [status (attente|en-cours|pret|erreur), at, file, size, error, duration]. */
    public static function status(string $key): ?array
    {
        $s = JsonStore::read(self::dir() . '/' . $key . '.json', null);
        if (!is_array($s)) {
            return null;
        }
        if (in_array($s['status'] ?? '', ['attente', 'en-cours'], true) && time() - (int) ($s['at'] ?? 0) > self::STALE) {
            $s['status'] = 'erreur';
            $s['error'] = 'Fabrication interrompue (délai dépassé) : relancez-la.';
        }
        return $s;
    }

    private static function setStatus(string $key, array $s): void
    {
        if (!is_dir(self::dir())) {
            @mkdir(self::dir(), 0775, true);
        }
        JsonStore::write(self::dir() . '/' . $key . '.json', $s + ['at' => time()]);
    }

    /**
     * Demande la vidéo : l'état passe à « attente » et la fabrication démarre après l'envoi de la
     * page (Response::detach : PHP-FPM ou LiteSpeed) ; $now : tout de suite (tests).
     */
    public static function requestVideo(array $p, string $format, string $lang, string $adv, bool $now = false): string
    {
        if (self::ffmpeg() === null) {
            throw new \RuntimeException((string) self::videoUnavailable());
        }
        $key = self::key($p['id'], $format, $lang, $adv);
        $cur = self::status($key);
        if ($cur && in_array($cur['status'], ['attente', 'en-cours'], true)) {
            return $key;
        }
        self::setStatus($key, ['status' => 'attente']);
        $job = function () use ($p, $format, $lang, $adv, $key) {
            self::makeVideo($p, $format, $lang, $adv, $key);
        };
        if ($now) {
            $job();
        } else {
            register_shutdown_function(function () use ($job) {
                Response::detach();
                $job();
            });
        }
        return $key;
    }

    /** Fabrique la vidéo (images dans un dossier temporaire, ffmpeg, puis ménage). */
    public static function makeVideo(array $p, string $format, string $lang, string $adv, string $key): void
    {
        @set_time_limit(600);
        $tmp = self::dir() . '/tmp-' . $key . '-' . bin2hex(random_bytes(3));
        try {
            self::setStatus($key, ['status' => 'en-cours']);
            if (!mkdir($tmp, 0775, true) && !is_dir($tmp)) {
                throw new \RuntimeException('Dossier temporaire impossible à créer.');
            }
            $frames = self::frames($p, $format, $lang, $adv);
            if (!$frames) {
                throw new \RuntimeException('Rien à exporter : aucune slide n’a de contenu aujourd’hui.');
            }
            $concat = '';
            $total = 0;
            foreach ($frames as $i => [$name, $png, $d]) {
                file_put_contents($tmp . '/' . $name, $png);
                $concat .= "file '" . $name . "'\nduration " . $d . "\n";
                $total += $d;
            }
            $concat .= "file '" . $frames[count($frames) - 1][0] . "'\n";
            file_put_contents($tmp . '/concat.txt', $concat);
            [$w, $h] = $format === 'vertical' ? [1080, 1920] : [1920, 1080];
            $out = self::dir() . '/' . $key . '.mp4';
            $part = $tmp . '/video.mp4';
            $cmd = 'cd ' . escapeshellarg($tmp) . ' && ' . self::command('concat.txt', $part, $w, $h, escapeshellarg((string) self::ffmpeg())) . ' 2>&1';
            $o = [];
            exec($cmd, $o, $code);
            if ($code !== 0 || !is_file($part) || filesize($part) < 1000) {
                throw new \RuntimeException('ffmpeg a échoué : ' . mb_substr(trim(implode(' ', $o)), 0, 300));
            }
            rename($part, $out);
            self::setStatus($key, ['status' => 'pret', 'file' => basename($out), 'size' => filesize($out), 'duration' => $total, 'images' => count($frames)]);
        } catch (\Throwable $e) {
            error_log('Vidéo de l’écran ' . $key . ' : ' . $e->getMessage());
            self::setStatus($key, ['status' => 'erreur', 'error' => $e->getMessage()]);
        } finally {
            foreach (glob($tmp . '/*') ?: [] as $f) {
                @unlink($f);
            }
            @rmdir($tmp);
        }
    }

    /** Fichier d'export à télécharger (nom contrôlé), sinon null. */
    public static function file(string $name): ?string
    {
        if (!preg_match('/^[a-z0-9-]{1,120}\.(zip|mp4)$/', $name)) {
            return null;
        }
        $f = self::dir() . '/' . $name;
        return is_file($f) ? $f : null;
    }
}
