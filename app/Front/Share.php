<?php
declare(strict_types=1);

namespace App\Front;

use App\Core\Response;
use App\Core\Settings;
use App\Data\Derived;
use App\Data\Fiches;
use App\Data\Index;
use App\Data\Media;
use App\Services\Images;

/**
 * Images de partage 1200 × 630 (Facebook, WhatsApp, X…) générées avec GD,
 * d'après la maquette « Partage » : match (photo + score), personne (portrait
 * encadré sur fond jaune), face-à-face (barre V/N/D), article (photo + titre).
 * Mises en cache dans storage/cache/share et régénérées quand la fiche change.
 */
final class Share
{
    private const W = 1200;
    private const H = 630;
    private const VERSION = 3;
    private const FONTS = APP_DIR . '/Resources/fonts';
    private const CACHE = STORAGE_PATH . '/cache/share';

    public static function image(int $id): Response
    {
        $s = Index::get($id);
        if (!$s || !Index::visible($s)) {
            return self::fallback();
        }
        $key = $id . '-' . substr(md5(($s['modified'] ?? '') . '|' . self::VERSION . '|' . ($s['image'] ?? '')), 0, 10);
        return self::cached($key, function () use ($id, $s) {
            $doc = Fiches::get($id);
            if (!$doc) {
                return null;
            }
            return match ($s['type']) {
                'match' => self::drawMatch($doc),
                'personne' => self::drawPerson($doc),
                default => self::drawArticle($doc),
            };
        });
    }

    public static function faceToFace(string $club): Response
    {
        $c = Derived::get()['clubs'][$club] ?? null;
        if (!$c) {
            return self::fallback();
        }
        $key = 'h2h-' . preg_replace('/[^a-z0-9-]/', '', $club) . '-' . substr(md5(json_encode([$c['v'], $c['n'], $c['d'], self::VERSION])), 0, 10);
        return self::cached($key, fn () => self::drawH2H($club, $c));
    }

    private static function cached(string $key, callable $draw): Response
    {
        $file = self::CACHE . '/' . $key . '.png';
        if (!is_file($file)) {
            $im = $draw();
            if (!$im) {
                return self::fallback();
            }
            if (!is_dir(self::CACHE)) {
                mkdir(self::CACHE, 0775, true);
            }
            $tmp = $file . '.' . bin2hex(random_bytes(3)) . '.tmp';
            imagepng($im, $tmp, 7);
            imagedestroy($im);
            rename($tmp, $file);
        }
        return new Response((string) file_get_contents($file), 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=86400',
            'ETag' => '"' . $key . '"',
        ]);
    }

    private static function fallback(): Response
    {
        return new Response((string) file_get_contents(PUBLIC_PATH . '/assets/img/partage-defaut.png'), 200, ['Content-Type' => 'image/png', 'Cache-Control' => 'public, max-age=3600']);
    }

    // ------------------------------------------------------------------ gabarits

    private static function drawMatch(array $doc): \GdImage
    {
        $m = $doc['match'];
        $im = self::canvas([14, 31, 77]);
        // Photo à droite (55 %), fondu vers le marine à gauche
        self::photo($im, $doc['featured_image'] ?? null, 540, 0, 660, self::H);
        self::gradient($im, 528, 900, [14, 31, 77], 0, 100);
        imagefilledrectangle($im, 0, 0, 528, self::H, self::color($im, [14, 31, 77]));

        $yellow = [246, 196, 0];
        $cream = [243, 237, 223];
        $tag = trim(($m['competition_label'] ?: $m['competition']) . ($m['date'] ? ' · ' . date_num($m['date']) : ''), ' ·');
        self::tag($im, mb_strtoupper($tag), 60, 60, $yellow, [14, 31, 77]);

        $home = mb_strtoupper((string) ($m['home']['name'] ?? ''));
        $away = mb_strtoupper((string) ($m['away']['name'] ?? ''));
        if ($home !== '' && $away !== '') {
            $score = isset($m['score']['home']) ? $m['score']['home'] . ' – ' . $m['score']['away'] : '–';
            $f = 'BigShouldersDisplay-900';
            // Hauteurs de capitales en pixels : équipes 64, score 150 (réduites si trop larges).
            $teamPt = min(self::ptForCap($f, 64), self::fit($home, $f, 640, 200), self::fit($away, $f, 640, 200));
            $scorePt = min(self::ptForCap($f, 150), self::fit($score, $f, 640, 400));
            $capT = self::cap($f, $teamPt);
            $capS = self::cap($f, $scorePt);
            $y = 150 + $capT;
            self::text($im, $home, $f, $teamPt, 60, $y, $cream);
            $y += 24 + $capS;
            self::text($im, $score, $f, $scorePt, 54, $y, $yellow);
            $y += 24 + $capT;
            self::text($im, $away, $f, $teamPt, 60, $y, $cream);
            if (!empty($m['score']['pens'])) {
                $pp = $m['score']['pens'];
                $tab = 'TAB ' . (is_array($pp) ? $pp['home'] . '-' . $pp['away'] : $pp);
                self::text($im, $tab, 'BigShouldersDisplay-700', 24, 70 + self::width($score, $f, $scorePt), $y - $capT - 24 - (int) ($capS * .1), $yellow, 2);
            }
        } else {
            self::paragraph($im, mb_strtoupper((string) ($m['event'] ?: $doc['title'])), 'BigShouldersDisplay-900', 60, 60, 160, 620, 4, $cream, 1.15);
        }
        self::brand($im, 60, 560, (string) Settings::get('share.domain', 'fcsochauxretro.com'), $cream);
        return $im;
    }

    private static function drawPerson(array $doc): \GdImage
    {
        $p = $doc['personne'];
        $navy = [14, 31, 77];
        $im = self::canvas([246, 196, 0]);
        // Portrait encadré avec ombre portée
        $x = 1200 - 60 - 408;
        imagefilledrectangle($im, $x + 20, 80, $x + 408 + 20, 570 + 20, self::color($im, $navy));
        imagefilledrectangle($im, $x, 60, $x + 408, 570, self::color($im, $navy));
        imagefilledrectangle($im, $x + 6, 66, $x + 402, 564, self::color($im, [232, 223, 201]));
        self::photo($im, $doc['featured_image'] ?? null, $x + 6, 66, 396, 498, 0.22);

        $roles = $p['roles'] ?: ['joueur'];
        $lines = ['G' => 'GARDIEN', 'D' => 'DÉFENSEUR', 'M' => 'MILIEU', 'A' => 'ATTAQUANT'];
        $role = ($roles[0] ?? 'joueur') === 'joueur' ? ($lines[$p['line'] ?? ''] ?? 'JOUEUR') : mb_strtoupper(Fiche::roleLabel($p));
        self::text($im, mb_strimwidth('NOS LIONS · ' . $role, 0, 40, '…'), 'BigShouldersDisplay-700', 22, 60, 100, [31, 63, 168], 4);
        $first = mb_strtoupper(trim((string) ($p['first_name'] ?? '')));
        $last = mb_strtoupper(trim((string) ($p['last_name'] ?? '')) ?: (string) $doc['title']);
        $f = 'BigShouldersDisplay-900';
        $pt = min(self::ptForCap($f, 118), self::fit($first, $f, 600, 300), self::fit($last, $f, 600, 300));
        $cap = self::cap($f, $pt);
        $y = 150 + $cap;
        if ($first !== '') {
            self::text($im, $first, $f, $pt, 56, $y, $navy);
            $y += 18 + $cap;
        }
        self::text($im, $last, $f, $pt, 56, $y, $navy);
        $years = Fiche::personYears($p);
        if ($years !== '') {
            self::text($im, str_replace('-', ' – ', $years), 'BigShouldersDisplay-700', 30, 60, $y + 64, $navy, 2);
        }
        self::brand($im, 60, 560, 'LE MUSÉE EN LIGNE DU FCSM', $navy);
        return $im;
    }

    private static function drawArticle(array $doc): \GdImage
    {
        $im = self::canvas([14, 31, 77]);
        self::photo($im, $doc['featured_image'] ?? null, 0, 0, self::W, self::H, 0.3);
        // Voile marine pour la lisibilité
        $veil = imagecolorallocatealpha($im, 14, 31, 77, 30);
        imagefilledrectangle($im, 0, 0, self::W, self::H, $veil);
        $cat = \App\Data\Categories::primaryOf($doc['categories'] ?? []);
        if ($cat) {
            self::tag($im, mb_strtoupper(\App\Data\Categories::label($cat)), 60, 60, [246, 196, 0], [14, 31, 77]);
        }
        self::paragraph($im, mb_strtoupper((string) $doc['title']), 'BigShouldersDisplay-900', 92, 60, 200, 1080, 3, [243, 237, 223], .9);
        self::brand($im, 60, 560, (string) Settings::get('share.domain', 'fcsochauxretro.com'), [243, 237, 223]);
        return $im;
    }

    private static function drawH2H(string $club, array $c): \GdImage
    {
        $im = self::canvas([31, 63, 168]);
        $cream = [243, 237, 223];
        $yellow = [246, 196, 0];
        $navy = [14, 31, 77];
        self::text($im, 'HISTORIQUE COMPLET · ' . $c['count'] . ' MATCHS', 'BigShouldersDisplay-700', 30, 60, 92, $yellow, 5);
        $name = mb_strtoupper(Fiche::clubName($club));
        $line = 'SOCHAUX × ' . $name;
        $size = min(130, self::fit($line, 'BigShouldersDisplay-900', 1080, 130));
        self::text($im, $line, 'BigShouldersDisplay-900', $size, 56, 300, $cream);
        // Barre V / N / D
        $total = max(1, $c['v'] + $c['n'] + $c['d']);
        $x = 60;
        $w = 1080;
        imagefilledrectangle($im, $x - 5, 375, $x + $w + 5, 435, self::color($im, $cream));
        $cur = $x;
        foreach ([[$c['v'], $yellow], [$c['n'], $cream], [$c['d'], $navy]] as [$n, $col]) {
            $seg = (int) round($w * $n / $total);
            if ($seg > 0) {
                imagefilledrectangle($im, $cur, 380, min($x + $w, $cur + $seg), 430, self::color($im, $col));
            }
            $cur += $seg;
        }
        self::text($im, $c['v'] . ' V · ' . $c['n'] . ' N · ' . $c['d'] . ' D', 'BigShouldersDisplay-900', 56, 60, 545, $cream);
        self::logo($im, 1140 - 84, 470, 84);
        return $im;
    }

    // ------------------------------------------------------------------ outils de dessin

    private static function canvas(array $rgb): \GdImage
    {
        $im = imagecreatetruecolor(self::W, self::H);
        imagealphablending($im, true);
        imagesavealpha($im, false);
        imagefilledrectangle($im, 0, 0, self::W, self::H, self::color($im, $rgb));
        return $im;
    }

    private static function color(\GdImage $im, array $rgb): int
    {
        return imagecolorallocate($im, $rgb[0], $rgb[1], $rgb[2]);
    }

    private static function font(string $name): string
    {
        return self::FONTS . '/' . $name . '.woff';
    }

    /** Photo recadrée pour remplir la zone (point d'intérêt vertical $focusY). */
    private static function photo(\GdImage $im, ?string $rel, int $x, int $y, int $w, int $h, float $focusY = 0.35): void
    {
        if (!$rel) {
            return;
        }
        $file = Media::file($rel);
        if (!$file || !is_file($file)) {
            return;
        }
        $src = Images::open($file);
        if (!$src) {
            return;
        }
        [$sw, $sh] = [imagesx($src), imagesy($src)];
        $scale = max($w / $sw, $h / $sh);
        $cw = (int) round($w / $scale);
        $ch = (int) round($h / $scale);
        $cx = (int) round(($sw - $cw) / 2);
        $cy = (int) round(max(0, min($sh - $ch, $sh * $focusY - $ch / 2)));
        imagecopyresampled($im, $src, $x, $y, $cx, $cy, $w, $h, $cw, $ch);
        imagedestroy($src);
    }

    /** Dégradé horizontal d'une couleur opaque ($a0) vers transparente ($a1), alpha GD 0–127. */
    private static function gradient(\GdImage $im, int $x0, int $x1, array $rgb, int $a0, int $a1): void
    {
        for ($x = $x0; $x <= $x1; $x++) {
            $t = ($x - $x0) / max(1, $x1 - $x0);
            $a = (int) round($a0 + ($a1 - $a0) * $t);
            imageline($im, $x, 0, $x, self::H, imagecolorallocatealpha($im, $rgb[0], $rgb[1], $rgb[2], min(127, $a)));
        }
    }

    private static function text(\GdImage $im, string $s, string $font, int $size, int $x, int $baseline, array $rgb, int $tracking = 0): void
    {
        $col = self::color($im, $rgb);
        if ($tracking === 0) {
            imagettftext($im, $size, 0, $x, $baseline, $col, self::font($font), $s);
            return;
        }
        foreach (mb_str_split($s) as $ch) {
            $box = imagettftext($im, $size, 0, $x, $baseline, $col, self::font($font), $ch);
            $x = $box[2] + $tracking;
        }
    }

    /** Taille (points GD) donnant une hauteur de capitale de $px pixels. */
    private static function ptForCap(string $font, int $px): int
    {
        return (int) floor(100 * $px / max(1, self::cap($font, 100)));
    }

    private static function cap(string $font, int $size): int
    {
        $b = imagettfbbox($size, 0, self::font($font), 'H');
        return abs($b[7] - $b[1]);
    }

    private static function width(string $s, string $font, int $size): int
    {
        $b = imagettfbbox($size, 0, self::font($font), $s);
        return abs($b[2] - $b[0]);
    }

    /** Plus grande taille (≤ $max) pour laquelle le texte tient dans $maxW. */
    private static function fit(string $s, string $font, int $maxW, int $max, int $min = 28): int
    {
        if ($s === '') {
            return $max;
        }
        $w = self::width($s, $font, $max);
        return $w <= $maxW ? $max : max($min, (int) floor($max * $maxW / $w));
    }

    /** Texte sur plusieurs lignes (réduit si nécessaire pour tenir en $maxLines). */
    private static function paragraph(\GdImage $im, string $s, string $font, int $size, int $x, int $y, int $maxW, int $maxLines, array $rgb, float $lh): void
    {
        for ($sz = $size; $sz >= 36; $sz -= 4) {
            $lines = [];
            $cur = '';
            foreach (preg_split('/\s+/u', $s) as $word) {
                $try = $cur === '' ? $word : "$cur $word";
                if (self::width($try, $font, $sz) > $maxW && $cur !== '') {
                    $lines[] = $cur;
                    $cur = $word;
                } else {
                    $cur = $try;
                }
            }
            $lines[] = $cur;
            if (count($lines) <= $maxLines) {
                break;
            }
        }
        if (count($lines) > $maxLines) {
            $lines = array_slice($lines, 0, $maxLines);
            $lines[$maxLines - 1] .= '…';
        }
        foreach ($lines as $i => $l) {
            self::text($im, $l, $font, $sz, $x, $y + (int) round($sz * $lh * ($i + 1)), $rgb);
        }
    }

    private static function tag(\GdImage $im, string $s, int $x, int $y, array $bg, array $fg): void
    {
        $s = mb_strimwidth($s, 0, 48, '…');
        $size = 26;
        $w = self::width($s, 'BigShouldersDisplay-700', $size) + mb_strlen($s) * 3;
        imagefilledrectangle($im, $x, $y, $x + $w + 32, $y + 48, self::color($im, $bg));
        self::text($im, $s, 'BigShouldersDisplay-700', $size, $x + 16, $y + 36, $fg, 3);
    }

    private static function brand(\GdImage $im, int $x, int $y, string $label, array $rgb): void
    {
        self::logo($im, $x, $y - 34, 70);
        self::text($im, mb_strtoupper($label), 'BigShouldersDisplay-700', 26, $x + 86, $y + 12, $rgb, 3);
    }

    private static function logo(\GdImage $im, int $x, int $y, int $h): void
    {
        $logo = @imagecreatefrompng(PUBLIC_PATH . '/assets/img/logo-sochaux-retro.png');
        if (!$logo) {
            return;
        }
        $lw = imagesx($logo);
        $lh = imagesy($logo);
        $w = (int) round($lw * $h / $lh);
        imagecopyresampled($im, $logo, $x, $y, 0, 0, $w, $h, $lw, $lh);
        imagedestroy($logo);
    }
}
