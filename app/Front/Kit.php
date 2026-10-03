<?php
declare(strict_types=1);

namespace App\Front;

use App\Core\Auth;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Response;
use App\Data\Collections;
use App\Data\Index;
use App\Pdf\Layout;
use App\Services\I18n;
use App\Services\Images;
use App\Services\Qr;
use App\Services\Souvenirs;

/**
 * Les Après-midi Bonal (INTERACTIF › Participer) : page du kit souvenirs du mois, PDF de 4 pages
 * en gros caractères, adresse courte « /souvenir/{match}/ » du QR code (vers le formulaire
 * de témoignage).
 */
final class Kit
{
    /** À augmenter quand la mise en page change (les PDF en cache sont alors refaits). */
    public const VERSION = '1';
    private const DIR = STORAGE_PATH . '/cache/pdf';

    public static function base(): string
    {
        return url('/interactif/souvenirs/');
    }

    public static function pdfUrl(string $ym): string
    {
        return url('/interactif/souvenirs/' . $ym . '.pdf');
    }

    public static function landing(Request $req): Response
    {
        $ym = date('Y-m');
        $next = date('Y-m', strtotime('first day of next month'));
        $past = [];
        for ($i = 1; $i <= 6; $i++) {
            $p = date('Y-m', strtotime("first day of -$i month"));
            if ($k = Souvenirs::match($p)) {
                $past[] = ['ym' => $p, 'month' => Souvenirs::monthLabel($p), 'c' => $k];
            }
        }
        // Derniers témoignages publiés (« Ils y étaient »).
        $temoins = [];
        foreach (Souvenirs::allTestimonies() as $mid => $list) {
            $s = Index::get((int) $mid);
            if ($s && Index::visible($s)) {
                foreach ($list as $t) {
                    $temoins[] = $t + ['s' => $s];
                }
            }
        }
        usort($temoins, fn ($a, $b) => strcmp($b['at'], $a['at']));
        return Pages::render('interactif/souvenirs', [
            'ym' => $ym, 'month' => Souvenirs::monthLabel($ym), 'kit' => Souvenirs::kit($ym),
            'next' => ['ym' => $next, 'month' => Souvenirs::monthLabel($next), 'c' => Souvenirs::match($next)],
            'past' => $past, 'temoins' => array_slice($temoins, 0, 4),
        ], [
            'title' => t('Les Après-midi Bonal : le kit souvenirs du mois'),
            'description' => t('Chaque mois, un kit à imprimer en gros caractères pour partager les grandes heures du FC Sochaux-Montbéliard avec les anciens supporters : le grand match d’il y a 30 ou 40 ans, des visages à reconnaître, un quiz et des questions pour raconter.'),
            'active' => 'interactif',
            'body_class' => 'page-souvenirs',
            'styles' => ['css/mosaic.css', 'css/interactif.css', 'css/souvenirs.css'],
        ]);
    }

    /** /interactif/souvenirs/{aaaa-mm}.pdf */
    public static function pdf(Request $req, string $ym): ?Response
    {
        if (!Souvenirs::validMonth($ym) || $ym > date('Y-m', strtotime('first day of +2 month'))) {
            return null;
        }
        $choice = Collections::get('souvenirs', [])[$ym] ?? [];
        $stamp = substr(sha1(self::VERSION . '|' . (@filemtime(STORAGE_PATH . '/cache/derived.php') ?: 0) . '|' . json_encode($choice) . '|' . base_url() . '|' . (I18n::isEn() ? 'en' : 'fr')
            . '|' . \App\Core\Settings::get('legal.address', '') . \App\Core\Settings::get('legal.email', '')), 0, 12);
        $file = self::DIR . "/souvenirs-$ym-$stamp.pdf";
        if (is_file($file)) {
            $bytes = (string) file_get_contents($file);
        } else {
            if (!Auth::user() && !RateLimiter::hit('pdf', $req->ip(), 40, 600)) {
                return new Response(t('Trop de demandes de PDF en peu de temps : réessayez dans quelques minutes.'), 429, ['Content-Type' => 'text/plain; charset=utf-8', 'Retry-After' => '600']);
            }
            $k = Souvenirs::kit($ym);
            if (!$k) {
                return null;
            }
            @set_time_limit(120);
            $bytes = self::build($k);
            if (!is_dir(self::DIR)) {
                @mkdir(self::DIR, 0775, true);
            }
            foreach (glob(self::DIR . "/souvenirs-$ym-*.pdf") ?: [] as $old) {
                @unlink($old);
            }
            $tmp = $file . '.' . bin2hex(random_bytes(3)) . '.tmp';
            if (@file_put_contents($tmp, $bytes) !== false) {
                @rename($tmp, $file);
            }
        }
        return new Response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . (I18n::isEn() ? 'memory-kit' : 'kit-souvenirs') . '-' . $ym . '.pdf"',
            'Content-Length' => (string) strlen($bytes),
            'Cache-Control' => 'public, max-age=3600',
            'X-Robots-Tag' => 'noindex',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** /souvenir/ et /souvenir/{id}/ (adresse du QR code) : formulaire de témoignage, match prérempli. */
    public static function souvenir(Request $req, ?string $id = null): Response
    {
        $q = ['type' => 'temoignage'];
        $s = $id !== null && ctype_digit($id) ? Index::get((int) $id) : null;
        if ($s && Index::visible($s)) {
            $dm = $s['type'] === 'match' ? \App\Data\Derived::match((int) $s['id']) : null;
            $q['fiche'] = $dm ? Site::matchLabel($dm) . ', ' . date_fr($dm['date']) : $s['title'];
        }
        return Response::redirect(url('/contribuer/') . '?' . http_build_query($q) . '#formulaire', 302);
    }

    // ================================================================== le PDF

    /** Kit de 4 pages A4 en gros caractères. */
    public static function build(array $k): string
    {
        $l = new Layout();
        $m = $k['m'];
        $c = $k['match'];
        $s = $c['s'];
        $l->running = t('Les Après-midi Bonal') . ' · ' . $k['month'];
        $l->url = base_url() . self::base();
        $l->pageWord = t('Page');
        $l->exported = t('Kit souvenirs de {m}', ['m' => $k['month']]);
        $cw = $l->cw();
        $x0 = $l->ml;
        $R = fn (string $t, string $f = 'serif', float $sz = 14, string $col = 'ink') => Layout::run($t, $f, $sz, $col);
        $para = function (array $runs, float $w, float $lh = 1.34, string $align = 'left', ?float $x = null) use ($l, $x0): float {
            $lines = $l->wrap($runs, $w, $lh);
            $h = $l->drawLines($lines, $x ?? $x0, $l->y, $w, $align);
            $l->y += $h;
            return $h;
        };
        $title = function (string $eyebrow, string $t, string $lead) use ($l, $para, $R, $cw): void {
            $l->text($l->ml, $l->y + 12, mb_strtoupper($eyebrow), 'display-b', 12, 'blue', 1.4);
            $l->y += 22;
            $para([$R(mb_strtoupper($t), 'display', 34, 'navy')], $cw, 1.0);
            $l->rect($l->ml, $l->y + 4, 46, 4, 'yellow');
            $l->y += 18;
            if ($lead !== '') {
                $para([$R($lead, 'serif-i', 14.5, 'muted')], $cw);
                $l->y += 10;
            }
        };

        // ---------------------------------------------------------- 1. le grand match du mois
        $l->newPage();
        $l->masthead(t('Kit souvenirs') . ' · ' . $k['month']);
        $date = date_fr($m['date'] ?? null, true);
        $title(t('Les Après-midi Bonal'), $c['ago'] > 1 ? t('Il y a {n} ans ce mois-ci', ['n' => $c['ago']]) : t('Ce mois-ci'), trim($date . ' · ' . ($m['stadium'] ?? ''), ' ·'));
        $photo = !empty($s['image']) && !\App\Data\Index::isPlaceholderImage($s['image']) ? Images::derivative($s['image'], 1200) : null;
        $img = $photo ? $l->loadImage($photo) : null;
        if ($img) {
            $l->drawImage($img, $x0, $l->y, $cw, 200, true, 0.35);
            $l->rect($x0, $l->y + 200, $cw, 4, 'yellow');
            $l->y += 214;
        }
        // Tableau d'affichage
        $sh = !empty($m['sochaux_home']);
        $home = mb_strtoupper((string) ($m['home']['name'] ?? ''));
        $away = mb_strtoupper((string) ($m['away']['name'] ?? ''));
        $score = isset($m['score']['home']) ? $m['score']['home'] . ' – ' . $m['score']['away'] : '–';
        $bh = 62;
        $l->rect($x0, $l->y, $cw, $bh, 'navy');
        $sw = $l->width($score, 'display', 38);
        $l->text($x0 + ($cw - $sw) / 2, $l->y + 45, $score, 'display', 38, 'yellow');
        $side = ($cw - $sw) / 2 - 26;
        $hs = $l->fit($home, 'display', 22, $side);
        $l->text($x0 + ($cw - $sw) / 2 - 14 - $l->width($hs, 'display', 22), $l->y + 40, $hs, 'display', 22, $sh ? 'yellow' : 'cream');
        $as = $l->fit($away, 'display', 22, $side);
        $l->text($x0 + ($cw + $sw) / 2 + 14, $l->y + 40, $as, 'display', 22, $sh ? 'cream' : 'yellow');
        $l->y += $bh + 10;
        $comp = trim((string) ($m['competition_label'] ?: ($m['competition'] ?? '')) . ((string) ($m['round_text'] ?? '') !== '' ? ' · ' . $m['round_text'] : ''), ' ·');
        $facts = array_filter([$comp, !empty($m['spectators']) ? t('{n} spectateurs', ['n' => number_format((int) $m['spectators'], 0, ',', ' ')]) : '']);
        $para([$R(implode(' · ', $facts), 'serif-b', 13, 'navy')], $cw, 1.3, 'center');
        if (!empty($m['goals_text'])) {
            $para([$R(t('Buts') . ' : ', 'serif-b', 13.5, 'navy'), $R(trim((string) $m['goals_text']), 'serif', 13.5)], $cw, 1.3, 'center');
        }
        $l->y += 8;
        if ($k['intro'] !== '') {
            $para([$R($k['intro'], 'serif-i', 14.5, 'ink')], $cw);
            $l->y += 6;
        }
        $bottom = $l->bottom() - 6;
        foreach ($k['story'] as $h) {
            $runs = [$R($h['min'] . "'  ", 'display-b', 15, $h['goal'] ? 'blue' : 'navy'), $R($h['text'], $h['goal'] ? 'serif-b' : 'serif', 14)];
            if ($l->y + $l->measure($runs, $cw, 1.32) > $bottom) {
                break;
            }
            $para($runs, $cw, 1.32);
            $l->y += 4;
        }
        if ($k['breve'] !== '') {
            $runs = [$R(t('Le saviez-vous ?') . '  ', 'display-b', 14, 'blue'), $R($k['breve'], 'serif', 13.5)];
            $hh = $l->measure($runs, $cw - 28, 1.3) + 22;
            if ($l->y + $hh + 6 < $bottom) {
                $l->y += 6;
                $l->rect($x0, $l->y, $cw, $hh, 'butter');
                $l->rect($x0, $l->y, 5, $hh, 'yellow');
                $l->y += 11;
                $para($runs, $cw - 28, 1.3, 'left', $x0 + 16);
            }
        }

        // ---------------------------------------------------------- 2. vous les reconnaissez ?
        $faces = $k['faces'];
        if ($faces) {
            $l->newPage();
            $title(t('Jeu de mémoire'), t('Vous les reconnaissez ?'), t('Des joueurs de l’époque, sur les photos du musée. Écrivez leur nom sous leur photo : les réponses sont en bas de la page, à l’envers.'));
            $gap = 16;
            $cols = 3;
            $w = ($cw - $gap * ($cols - 1)) / $cols;
            $ph = 196;
            foreach (array_values($faces) as $i => $f) {
                $col = $i % $cols;
                $row = intdiv($i, $cols);
                $x = $x0 + $col * ($w + $gap);
                $y = $l->y + $row * ($ph + 62);
                $file = Images::derivative($f['image'], 480);
                $im = $file ? $l->loadImage($file) : null;
                $l->rect($x, $y, $w, $ph, 'sand');
                if ($im) {
                    $l->drawImage($im, $x, $y, $w, $ph, true, 0.15);
                }
                $l->rect($x, $y, $w, $ph, null, 'navy', 2);
                $l->rect($x, $y, 30, 30, 'yellow');
                $n = (string) ($i + 1);
                $l->text($x + 15 - $l->width($n, 'display', 20) / 2, $y + 23, $n, 'display', 20, 'navy');
                $l->line($x, $y + $ph + 40, $x + $w, $y + $ph + 40, 'muted', 0.8);
                $l->text($x, $y + $ph + 22, t('Son nom :'), 'serif-i', 12.5, 'muted');
            }
            $l->y += ceil(count($faces) / $cols) * ($ph + 62);
            self::answers($l, array_map(fn ($i, $f) => ($i + 1) . '. ' . $f['name'], array_keys($faces), $faces), t('Réponses'));
        }

        // ---------------------------------------------------------- 3. le quiz des anciens
        if ($k['quiz']) {
            $l->newPage();
            $title(t('Le quiz des anciens'), t('Le quiz des anciens'), t('Entourez la bonne réponse. Les deux premières questions portent sur le match du mois. Les réponses sont en bas de la page, à l’envers.'));
            $letters = ['A', 'B', 'C', 'D'];
            $done = [];
            foreach ($k['quiz'] as $i => $q) {
                if ($l->y + 96 > $l->bottom() - 50) {
                    break; // la page est pleine : les solutions doivent rester visibles
                }
                $done[] = $q;
                $l->rect($x0, $l->y + 1, 26, 26, 'navy');
                $n = (string) ($i + 1);
                $l->text($x0 + 13 - $l->width($n, 'display', 17) / 2, $l->y + 21, $n, 'display', 17, 'yellow');
                $yq = $l->y;
                $para([$R($q['q'], 'serif-b', 15, 'navy')], $cw - 38, 1.25, 'left', $x0 + 38);
                $l->y = max($l->y, $yq + 28) + 6;
                $cx = $x0 + 38;
                foreach ($q['a'] as $j => $a) {
                    $label = $letters[$j] . '. ' . $a;
                    $lw = $l->width($label, 'serif', 14.5) + 34;
                    if ($cx + $lw > $x0 + $cw) {
                        $cx = $x0 + 38;
                        $l->y += 26;
                    }
                    $l->rect($cx, $l->y + 2, 14, 14, null, 'navy', 1.2);
                    $l->text($cx + 22, $l->y + 14, $label, 'serif', 14.5, 'ink');
                    $cx += $lw + 14;
                }
                $l->y += 40;
            }
            self::answers($l, array_map(fn ($i, $q) => ($i + 1) . '. ' . ($letters[$q['c']] ?? '') . ' (' . $q['a'][$q['c']] . ')', array_keys($done), $done), t('Réponses'));
        }

        // ---------------------------------------------------------- 4. racontez-nous
        $l->newPage();
        $title(t('Vos souvenirs'), t('Racontez-nous'), t('Vos souvenirs font vivre le musée. Racontez-les à vos proches… et au musée : ils pourront rejoindre la fiche du match, dans « Ils y étaient ».'));
        foreach ($k['prompts'] as $p) {
            $l->rect($x0, $l->y + 6, 9, 9, 'yellow');
            $para([$R($p, 'serif-b', 15, 'navy')], $cw - 18, 1.25, 'left', $x0 + 18);
            $l->y += 8;
            for ($i = 0; $i < 2; $i++) {
                $l->y += 26;
                $l->line($x0, $l->y, $x0 + $cw, $l->y, 'line', 0.9);
            }
            $l->y += 16;
        }
        // Encadré de retour : QR code, adresse courte, courrier, e-mail.
        $boxH = 168;
        $by = max($l->y + 6, $l->bottom() - $boxH - 4);
        $l->rect($x0, $by, $cw, $boxH, 'navy');
        $qs = 132;
        $l->rect($x0 + 18, $by + 18, $qs, $qs, 'white');
        self::qr($l, $k['contribute'], $x0 + 18, $by + 18, $qs);
        $tx = $x0 + 18 + $qs + 22;
        $tw = $cw - ($qs + 58);
        $l->y = $by + 20;
        $para([$R(mb_strtoupper(t('Envoyez vos souvenirs au musée')), 'display', 17, 'yellow')], $tw, 1.1, 'left', $tx);
        $l->y += 6;
        $short = preg_replace('#^https?://(www\.)?#', '', $k['contribute']);
        $para([$R(t('En ligne : scannez ce code avec un téléphone, ou tapez l’adresse '), 'serif', 12.5, 'cream'), $R(rtrim($short, '/'), 'serif-b', 12.5, 'yellow')], $tw, 1.3, 'left', $tx);
        if ($k['address'] !== '') {
            $l->y += 4;
            $para([$R(t('Par courrier :') . ' ', 'serif-b', 12.5, 'cream'), $R(preg_replace('/\s*\n\s*/u', ', ', $k['address']), 'serif', 12.5, 'cream')], $tw, 1.3, 'left', $tx);
        }
        if ($k['email'] !== '') {
            $l->y += 4;
            $para([$R(t('Par e-mail :') . ' ', 'serif-b', 12.5, 'cream'), $R($k['email'], 'serif', 12.5, 'cream')], $tw, 1.3, 'left', $tx);
        }
        $l->y += 4;
        $para([$R(t('Avec votre accord, votre témoignage pourra être publié, signé de votre prénom.'), 'serif-i', 11, 'mist')], $tw, 1.3, 'left', $tx);
        return $l->finish();
    }

    /** Solutions d'un jeu en bas de page, à l'envers (première ligne au plus bas). */
    private static function answers(Layout $l, array $items, string $label): void
    {
        $lines = [];
        $cur = $label . ' : ';
        foreach ($items as $it) {
            if ($l->width($cur . $it, 'serif', 11.5) > $l->cw() - 20 && trim($cur) !== $label . ' :') {
                $lines[] = rtrim($cur, ' ·');
                $cur = '';
            }
            $cur .= $it . ' · ';
        }
        $lines[] = rtrim($cur, ' ·');
        $y = $l->bottom() - 8;
        $l->line($l->ml, $y - 14 - 15 * count($lines), $l->ml + $l->cw(), $y - 14 - 15 * count($lines), 'line', 0.8);
        foreach ($lines as $i => $t) {
            // Page retournée : la première ligne se lit en haut, donc on l'écrit au plus bas.
            $l->textUpsideDown($l->ml + $l->cw() / 2, $y - 11 - 15 * $i, $t, 'serif', 11.5, 'muted');
        }
    }

    /** QR code dessiné en carrés (marge comprise dans le carré blanc de $size points). */
    private static function qr(Layout $l, string $text, float $x, float $y, float $size): void
    {
        $m = Qr::matrix($text);
        $n = count($m);
        $mod = $size / ($n + 8);
        foreach ($m as $r => $row) {
            for ($cx = 0; $cx < $n; $cx++) {
                if ($row[$cx]) {
                    $start = $cx;
                    while ($cx + 1 < $n && $row[$cx + 1]) {
                        $cx++;
                    }
                    $l->rect($x + ($start + 4) * $mod, $y + ($r + 4) * $mod, ($cx - $start + 1) * $mod + 0.05, $mod + 0.05, 'navy');
                }
            }
        }
    }
}
