<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Response;
use App\Data\Media;

/**
 * Images redimensionnées à la volée, au format WebP, puis mises en cache dans
 * public/media/{largeur}/{chemin}.webp : les visites suivantes sont servies
 * directement par Apache, sans PHP.
 */
final class Images
{
    public const WIDTHS = [160, 320, 480, 640, 800, 1200, 1600];

    public static function serve(string $w, string $rel): Response
    {
        // Le chemin arrive déjà décodé (Request) : pas de second décodage.
        $rel = Media::safeRel($rel);
        $isWebp = str_ends_with($rel, '.webp') && !Media::file($rel);
        $srcRel = $isWebp ? substr($rel, 0, -5) : $rel;
        $src = Media::file($srcRel);

        if ($w === 'full') {
            if (!$src) {
                return self::placeholder();
            }
            // Original servi tel quel : seulement des types sûrs, et jamais de script (SVG piégé…).
            $mime = mime_content_type($src) ?: 'application/octet-stream';
            $types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml', 'image/tiff', 'image/avif', 'application/pdf'];
            $headers = ['Content-Type' => in_array($mime, $types, true) ? $mime : 'application/octet-stream', 'Cache-Control' => 'public, max-age=2592000', 'X-Content-Type-Options' => 'nosniff'];
            if (str_starts_with($mime, 'image/')) {
                $headers['Content-Security-Policy'] = "default-src 'none'; img-src 'self' data:; style-src 'unsafe-inline'; sandbox";
            } elseif (!in_array($mime, $types, true)) {
                $headers['Content-Disposition'] = 'attachment';
            }
            return new Response((string) file_get_contents($src), 200, $headers);
        }
        $width = (int) $w;
        if (!in_array($width, self::WIDTHS, true)) {
            // Largeur hors liste (page ancienne en cache, lien extérieur) : la plus proche par défaut,
            // plutôt qu'une image cassée ; aucune variante nouvelle n'est fabriquée.
            if ($width <= 0 || $width > 10000) {
                return new Response('Largeur non autorisée', 400);
            }
            $near = self::WIDTHS[0];
            foreach (self::WIDTHS as $x) {
                if ($x <= $width) {
                    $near = $x;
                }
            }
            return Response::redirect('/media/' . $near . '/' . implode('/', array_map('rawurlencode', explode('/', $rel))), 301);
        }
        if (!$src) {
            return self::placeholder();
        }
        $dest = MEDIA_PATH . "/$width/$srcRel.webp";
        if (!is_file($dest) || filemtime($dest) < filemtime($src)) {
            $made = self::make($src, $dest, $width, Media::edit($srcRel), true);
            if ($made === null) {
                // Déjà deux images en préparation : la taille existante la plus proche, pour cette fois.
                return self::nearest($srcRel, $src, $width);
            }
            if (!$made) {
                // AVIF que GD ne sait pas lire : l'original, que les navigateurs affichent.
                return self::isAvif($src)
                    ? new Response((string) file_get_contents($src), 200, ['Content-Type' => 'image/avif', 'Cache-Control' => 'public, max-age=86400', 'X-Content-Type-Options' => 'nosniff'])
                    : self::placeholder();
            }
        }
        return new Response((string) file_get_contents($dest), 200, [
            'Content-Type' => 'image/webp',
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }

    /** Chemin local d'une déclinaison WebP d'un média (créée au besoin), ou null (export PDF). */
    public static function derivative(string $rel, int $width): ?string
    {
        $rel = Media::safeRel($rel);
        if ($rel === '' || str_ends_with(strtolower($rel), '.svg')) {
            return null;
        }
        $width = in_array($width, self::WIDTHS, true) ? $width : 800;
        $src = Media::file($rel);
        if (!$src) {
            return null;
        }
        $dest = MEDIA_PATH . "/$width/$rel.webp";
        if (!is_file($dest) || filemtime($dest) < filemtime($src)) {
            if (!self::make($src, $dest, $width, Media::edit($rel), false)) {
                return null;
            }
        }
        return $dest;
    }

    /** Préparations simultanées au plus, pour tout le site (chacune décode un original en mémoire). */
    private const SLOTS = 2;

    /**
     * Prépare une déclinaison une seule fois même si plusieurs visiteurs la demandent ensemble
     * (verrou par image : les suivants attendent puis trouvent le fichier), et au plus SLOTS à
     * la fois pour une page web (sans place libre : null, l'appelant sert une autre taille).
     * Au lancement, des milliers de vignettes manquantes ne peuvent ainsi saturer le serveur.
     */
    private static function make(string $src, string $dest, int $width, ?array $edit, bool $web): ?bool
    {
        $dir = STORAGE_PATH . '/cache/img-locks';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $lock = @fopen($dir . '/' . (crc32($dest) % 64) . '.lock', 'c');
        if ($lock) {
            flock($lock, LOCK_EX);
        }
        $slot = null;
        try {
            clearstatcache(true, $dest);
            if (is_file($dest) && filemtime($dest) >= filemtime($src)) {
                return true; // préparée entre-temps par un autre visiteur
            }
            if ($web) {
                for ($i = 0; $i < self::SLOTS && !$slot; $i++) {
                    $f = @fopen($dir . "/slot-$i.lock", 'c');
                    if ($f && flock($f, LOCK_EX | LOCK_NB)) {
                        $slot = $f;
                    } elseif ($f) {
                        fclose($f);
                    }
                }
                if (!$slot) {
                    return null;
                }
            }
            return self::generate($src, $dest, $width, $edit);
        } finally {
            if ($slot) {
                flock($slot, LOCK_UN);
                fclose($slot);
            }
            if ($lock) {
                flock($lock, LOCK_UN);
                fclose($lock);
            }
        }
    }

    /** Redirection (jamais gardée) vers la taille déjà prête la plus proche, sinon l'image d'attente. */
    private static function nearest(string $srcRel, string $src, int $width): Response
    {
        $widths = self::WIDTHS;
        usort($widths, fn ($a, $b) => abs($a - $width) <=> abs($b - $width) ?: $b <=> $a);
        foreach ($widths as $w) {
            $f = MEDIA_PATH . "/$w/$srcRel.webp";
            if ($w !== $width && is_file($f) && filemtime($f) >= filemtime($src)) {
                return new Response('', 302, ['Location' => MEDIA_URL . "/$w/" . str_replace('%2F', '/', rawurlencode($srcRel)) . '.webp', 'Cache-Control' => 'no-store']);
            }
        }
        return self::placeholder();
    }

    /**
     * @param array{rotate?:int,crop?:array{0:float,1:float,2:float,3:float}}|null $edit
     *        retouches non destructives saisies dans la médiathèque (l'original reste intact)
     */
    public static function generate(string $src, string $dest, int $width, ?array $edit = null): bool
    {
        $info = @getimagesize($src);
        if (!$info && !self::isAvif($src)) {
            return self::copySvg($src, $dest);
        }
        if ($info && $info[0] * $info[1] > 120_000_000) {
            return false;
        }
        $im = self::open($src, $edit);
        if (!$im) {
            return false;
        }
        [$w, $h] = [imagesx($im), imagesy($im)];
        $tw = min($width, $w);
        $th = (int) max(1, round($h * $tw / $w));
        $out = imagecreatetruecolor($tw, $th);
        imagealphablending($out, false);
        imagesavealpha($out, true);
        imagecopyresampled($out, $im, 0, 0, 0, 0, $tw, $th, $w, $h);
        imagedestroy($im);
        $dir = dirname($dest);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return false;
        }
        $tmp = $dest . '.' . bin2hex(random_bytes(3)) . '.tmp';
        $ok = imagewebp($out, $tmp, $tw <= 320 ? 76 : 80);
        imagedestroy($out);
        if ($ok) {
            rename($tmp, $dest);
        }
        return $ok;
    }

    /**
     * Ouvre une image (JPEG, PNG, GIF, WebP, BMP, et AVIF si GD le lit) en tenant compte de
     * l'orientation EXIF, puis applique les retouches de la médiathèque : rotation (degrés,
     * sens horaire) et recadrage (fractions x, y, largeur, hauteur de l'image pivotée).
     */
    public static function open(string $src, ?array $edit = null): ?\GdImage
    {
        $info = @getimagesize($src);
        // getimagesize() ne reconnaît pas tous les AVIF : leur signature suffit.
        $type = $info[2] ?? (self::isAvif($src) ? IMAGETYPE_AVIF : null);
        if ($type === null || ($info && $info[0] * $info[1] > 120_000_000)) {
            return null;
        }
        ini_set('memory_limit', '1536M');
        set_time_limit(120);
        $im = match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($src),
            IMAGETYPE_PNG => @imagecreatefrompng($src),
            IMAGETYPE_GIF => @imagecreatefromgif($src),
            IMAGETYPE_WEBP => @imagecreatefromwebp($src),
            IMAGETYPE_BMP => @imagecreatefrombmp($src),
            IMAGETYPE_AVIF => function_exists('imagecreatefromavif') ? @imagecreatefromavif($src) : false,
            default => false,
        };
        if (!$im) {
            return null;
        }
        if ($type === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
            $exif = @exif_read_data($src);
            $o = $exif['Orientation'] ?? 1;
            if (in_array($o, [3, 6, 8], true)) {
                $im = imagerotate($im, [3 => 180, 6 => -90, 8 => 90][$o], 0);
            }
        }
        if (!imageistruecolor($im)) {
            imagepalettetotruecolor($im);
        }
        // Bandes unies incrustées dans le fichier (photo « encadrée » de blanc ou de noir) : rognées
        // avant toute autre retouche, pour que la photo remplisse vraiment son cadre (bandeau en cover).
        $t = $edit['trim'] ?? null;
        if (is_array($t) && count($t) === 4) {
            [$l, $tp, $r, $b] = array_map(fn ($v) => max(0.0, min(0.45, (float) $v)), array_values($t));
            $W = imagesx($im);
            $H = imagesy($im);
            $rect = ['x' => (int) round($l * $W), 'y' => (int) round($tp * $H), 'width' => (int) round((1 - $l - $r) * $W), 'height' => (int) round((1 - $tp - $b) * $H)];
            if ($rect['width'] > 10 && $rect['height'] > 10 && ($rect['width'] < $W || $rect['height'] < $H) && ($c = imagecrop($im, $rect))) {
                imagedestroy($im);
                $im = $c;
            }
        }
        $rot = (int) ($edit['rotate'] ?? 0);
        if (in_array($rot, [90, 180, 270], true)) {
            $r = imagerotate($im, -$rot, 0);
            if ($r) {
                imagedestroy($im);
                $im = $r;
            }
        }
        $c = $edit['crop'] ?? null;
        if (is_array($c) && count($c) === 4) {
            [$cx, $cy, $cw, $ch] = array_map(fn ($v) => max(0.0, min(1.0, (float) $v)), array_values($c));
            if ($cw > 0.02 && $ch > 0.02 && ($cw < 0.999 || $ch < 0.999)) {
                $W = imagesx($im);
                $H = imagesy($im);
                $rect = ['x' => (int) round($cx * $W), 'y' => (int) round($cy * $H), 'width' => (int) max(1, round(min($cw, 1 - $cx) * $W)), 'height' => (int) max(1, round(min($ch, 1 - $cy) * $H))];
                $cropped = imagecrop($im, $rect);
                if ($cropped) {
                    imagedestroy($im);
                    $im = $cropped;
                }
            }
        }
        return $im;
    }

    /**
     * Bandes unies incrustées sur les bords d'une image (fractions [gauche, haut, droite, bas]), ou
     * null s'il n'y en a pas : colonnes (ou lignes) du bord vers le centre dont presque tous les points
     * ont la couleur du bord (blanc, noir ou autre teinte unie). Au plus 35 % par côté ; une bande de
     * moins de 1 % (liseré) est ignorée ; une image presque unie n'est jamais rognée.
     * @return array{0:float,1:float,2:float,3:float}|null
     */
    public static function bands(\GdImage $im): ?array
    {
        $W = imagesx($im);
        $H = imagesy($im);
        if ($W < 40 || $H < 40) {
            return null;
        }
        $rgb = function (int $x, int $y) use ($im): array {
            $c = imagecolorat($im, $x, $y);
            return [($c >> 16) & 255, ($c >> 8) & 255, $c & 255];
        };
        $near = fn (array $a, array $b) => abs($a[0] - $b[0]) + abs($a[1] - $b[1]) + abs($a[2] - $b[2]) <= 36;
        // Une ligne (verticale ou horizontale) est « unie » si 98 % de ses points ont la couleur de référence.
        $uniform = function (bool $col, int $i, array $ref) use ($W, $H, $rgb, $near): bool {
            $len = $col ? $H : $W;
            $step = max(1, intdiv($len, 120));
            $n = $ok = 0;
            for ($k = 0; $k < $len; $k += $step) {
                $n++;
                $ok += $near($col ? $rgb($i, $k) : $rgb($k, $i), $ref) ? 1 : 0;
            }
            return $ok >= 0.98 * $n;
        };
        $side = function (bool $col, bool $fromStart) use ($W, $H, $rgb, $uniform): int {
            $len = $col ? $W : $H;
            $edge = $fromStart ? 0 : $len - 1;
            $ref = $col ? $rgb($edge, intdiv($H, 2)) : $rgb(intdiv($W, 2), $edge);
            $max = (int) floor($len * 0.35);
            $n = 0;
            while ($n < $max && $uniform($col, $fromStart ? $n : $len - 1 - $n, $ref)) {
                $n++;
            }
            return $n >= max(3, $len * 0.01) ? $n : 0;
        };
        [$l, $r, $t, $b] = [$side(true, true), $side(true, false), $side(false, true), $side(false, false)];
        // Image presque unie (ciel, fond de scan vide) : rien.
        if (!$l && !$r && !$t && !$b || ($W - $l - $r) < $W * 0.4 || ($H - $t - $b) < $H * 0.4) {
            return null;
        }
        return [round($l / $W, 4), round($t / $H, 4), round($r / $W, 4), round($b / $H, 4)];
    }

    /**
     * Repère, une fois par photo, les bandes unies incrustées (Images::bands) : photos principales des
     * fiches d'abord (bandeaux), puis le reste de la médiathèque, dans le temps donné (secondes). Bandes
     * trouvées : notées dans le média (« trim »), anciennes déclinaisons supprimées pour être refaites.
     * L'original n'est jamais modifié ; un recadrage choisi par l'historien l'emporte (Media::editOf).
     */
    public static function scanBands(float $budget, bool $featuredOnly = false): ?string
    {
        $end = microtime(true) + $budget;
        $all = Media::all();
        $first = [];
        foreach (\App\Data\Index::all() as $s) {
            if (!empty($s['image']) && isset($all[$s['image']]) && !isset($all[$s['image']]['trim'])) {
                $first[$s['image']] = true;
            }
        }
        $todo = array_keys($first);
        foreach ($featuredOnly ? [] : $all as $rel => $m) {
            if (!isset($m['trim']) && !isset($first[$rel])) {
                $todo[] = (string) $rel;
            }
        }
        $changes = [];
        $found = 0;
        foreach ($todo as $rel) {
            if (microtime(true) > $end || count($changes) >= 5000) {
                break;
            }
            $lower = strtolower($rel);
            $file = Media::file($rel);
            if (!$file || !preg_match('/\.(jpe?g|png|webp|gif|bmp|avif)$/', $lower)) {
                $changes[$rel] = ['trim' => []];
                continue;
            }
            // Analyse sur une déclinaison déjà faite (5 ms au lieu d'ouvrir l'original en grand) : les
            // bandes ont la même proportion à toutes les tailles. Original seulement s'il n'y en a pas,
            // ou si la photo est retouchée (rotation : les bandes se mesurent sur l'image d'origine).
            $small = null;
            if (empty($all[$rel]['edit'])) {
                foreach ([480, 640, 800, 320, 1200, 1600] as $w) {
                    if (is_file($f = MEDIA_PATH . "/$w/$rel.webp") && filemtime($f) >= filemtime($file)) {
                        $small = $f;
                        break;
                    }
                }
            }
            $im = $small ? @imagecreatefromwebp($small) : self::open($file);
            $b = $im ? self::bands($im) : null;
            if ($im) {
                imagedestroy($im);
            }
            $changes[$rel] = ['trim' => $b ?? []];
            if ($b) {
                $found++;
                foreach (glob(MEDIA_PATH . '/*/' . $rel . '.webp') ?: [] as $f) {
                    @unlink($f);
                }
            }
        }
        if (!$changes) {
            return null;
        }
        Media::putMany($changes);
        return count($changes) . ' photo(s) examinée(s), ' . $found . ' avec des bandes unies rognées';
    }

    /** Fichier AVIF, reconnu à sa signature (boîte « ftyp » d'un fichier ISO-BMFF). */
    public static function isAvif(string $file): bool
    {
        $head = (string) @file_get_contents($file, false, null, 0, 12);
        return substr($head, 4, 4) === 'ftyp' && in_array(substr($head, 8, 4), ['avif', 'avis', 'mif1', 'msf1'], true);
    }

    private static function copySvg(string $src, string $dest): bool
    {
        // SVG et formats non matriciels : pas de redimensionnement.
        return false;
    }

    public static function placeholder(): Response
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 300"><rect width="400" height="300" fill="' . \App\Core\Club::color('sand') . '"/>'
            . '<g fill="none" stroke="' . \App\Core\Club::color('navy') . '" stroke-opacity=".35" stroke-width="6"><rect x="150" y="105" width="100" height="80" rx="6"/>'
            . '<circle cx="178" cy="132" r="9"/><path d="M155 180l35-30 25 20 15-12 20 22"/></g></svg>';
        // Jamais gardée par le navigateur : l'image s'affiche dès que l'original arrive (copie des médias).
        return new Response($svg, 200, ['Content-Type' => 'image/svg+xml', 'Cache-Control' => 'no-store']);
    }

    /** Pré-génère les vignettes (ligne de commande). */
    public static function warmup(int $width, ?callable $log = null): int
    {
        if (!in_array($width, self::WIDTHS, true)) {
            return 0; // largeur jamais demandée par les pages
        }
        $n = 0;
        foreach (Media::all() as $rel => $m) {
            if (!str_starts_with((string) ($m['mime'] ?? ''), 'image/') || str_contains((string) $m['mime'], 'svg')) {
                continue;
            }
            $src = Media::file($rel);
            $dest = MEDIA_PATH . "/$width/$rel.webp";
            if ($src && !is_file($dest) && self::make($src, $dest, $width, Media::editOf($m), false)) {
                $n++;
                if ($log && $n % 200 === 0) {
                    $log("{$n}…");
                }
            }
        }
        return $n;
    }
}
